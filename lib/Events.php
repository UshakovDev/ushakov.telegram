<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

class Events
{
    protected static function buildAbsoluteUrl(string $path): string
    {
        $host = trim((string) Option::get('ushakov.telegram', 'WEBHOOK_PUBLIC_HOST', ''));
        if ($host === '') {
            $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
        }
        if ($host === '') {
            return $path;
        }
        if (!preg_match('~^https?://~i', $host)) {
            $host = 'https://' . $host;
        }
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        return $host . $path;
    }

    protected static function resolvePaymentSystemName($order): string
    {
        $paymentName = '';
        if (is_object($order)) {
            if (method_exists($order, 'getPaymentSystemName')) {
                $paymentName = (string)$order->getPaymentSystemName();
            }
            if ($paymentName === '' && method_exists($order, 'getPaymentCollection')) {
                $pc = $order->getPaymentCollection();
                if ($pc) {
                    foreach ($pc as $payment) {
                        $psName = '';
                        if (method_exists($payment, 'getPaymentSystemId')) {
                            $psId = (int)$payment->getPaymentSystemId();
                            if ($psId > 0 && class_exists('\\Bitrix\\Sale\\PaySystem\\Manager')) {
                                $service = \Bitrix\Sale\PaySystem\Manager::getObjectById($psId);
                                if ($service) {
                                    $psName = (string)$service->getField('NAME');
                                }
                                if ($psName === '') {
                                    $row = \Bitrix\Sale\PaySystem\Manager::getList([
                                        'filter' => ['ID' => $psId],
                                        'select' => ['NAME']
                                    ])->fetch();
                                    if ($row && !empty($row['NAME'])) {
                                        $psName = (string)$row['NAME'];
                                    }
                                }
                            }
                        }
                        if ($psName === '' && method_exists($payment, 'getPaymentSystemName')) {
                            $psName = (string)$payment->getPaymentSystemName();
                        }
                        if ($psName !== '') {
                            $paymentName = $psName;
                            break;
                        }
                    }
                }
            }
        }
        return $paymentName;
    }

    // Отмена заказа: поддержка сигнатур (event) и (orderId, isCanceled)
    public static function onOrderCanceled($arg1, $arg2 = null): void
    {
        $adminCancelEnabled   = Option::get('ushakov.telegram','SEND_ORDER_CANCELED','Y') === 'Y';
        $adminUncancelEnabled = Option::get('ushakov.telegram','SEND_ORDER_UNCANCELED','Y') === 'Y';

        $orderId = 0; $isCanceled = false; $reason = '';
        if ($arg1 instanceof \Bitrix\Main\Event) {
            $params = $arg1->getParameters();
            $order = $params['ENTITY'] ?? null;
            if ($order) {
                $orderId = (int)$order->getId();
                $isCanceled = (bool)$order->isCanceled();
                if (method_exists($order,'getField')) {
                    $reason = (string)$order->getField('REASON_CANCELED');
                }
            } else {
                $orderId = (int)($params['ID'] ?? 0);
                $val = $params['VALUE'] ?? $params['CANCELED'] ?? null;
                $isCanceled = ($val === true || $val === 'Y' || $val === 1 || $val === '1');
                $reason = (string)($params['REASON_CANCELED'] ?? '');
            }
        } else {
            $orderId = (int)$arg1;
            $isCanceled = ($arg2 === true || $arg2 === 'Y' || $arg2 === 1 || $arg2 === '1');
        }

        if ($orderId <= 0) { return; }

        $order = \Bitrix\Sale\Order::load($orderId);
        $priceString = '';
        if ($order) {
            $priceString = (string)\CCurrencyLang::CurrencyFormat($order->getPrice(), $order->getCurrency());
            $priceString = html_entity_decode($priceString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $priceString = str_replace("\xC2\xA0", ' ', $priceString);
            if ($reason === '' && method_exists($order,'getField')) {
                $reason = (string)$order->getField('REASON_CANCELED');
            }
        }

        if ($isCanceled) {
            static $sentCancel = [];
            $dupKey = 'cancel-'.$orderId;
            if (isset($sentCancel[$dupKey])) { return; }
            $sentCancel[$dupKey] = true;

            $tpl = Option::get('ushakov.telegram','TPL_ORDER_CANCELED', '❌ Заказ #ORDER_ID# отменён\nПричина: #REASON#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#');

            $adminUrl = self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID);
            $userUrl  = self::buildAbsoluteUrl('/personal/orders/');
            // Админам — по их шаблону (если используется #URL#, подменим на #ADMIN_URL#)
            if ($adminCancelEnabled) {
                $tplAdmin = str_replace('#URL#', '#ADMIN_URL#', $tpl);
                $textAdmin = self::render($tplAdmin, [
                    'ORDER_ID' => $orderId,
                    'REASON'   => $reason,
                    'PRICE'    => $priceString,
                    'ADMIN_URL'=> $adminUrl,
                    'URL'      => $userUrl,
                ]);
                self::sendToAdmins($textAdmin, method_exists($order,'getSiteId') ? (string)$order->getSiteId() : null);
            }


            if (Option::get('ushakov.telegram','CUSTOMER_NOTIFY_ENABLED','N') === 'Y'
                && Option::get('ushakov.telegram','CUSTOMER_EVENTS_ORDER_CANCEL','Y') === 'Y') {
                if ($order) {
                    $userId = (int)$order->getUserId();
                    if ($userId > 0) {
                        $siteId = (string)(method_exists($order,'getSiteId') ? $order->getSiteId() : SITE_ID);
                        $chatId = \Ushakov\Telegram\Repository\BindingRepository::getChatId($siteId, $userId);
                        $token = self::getToken();

                        if ($chatId && $token) {
                            // Покупателям — принудительно на #URL# (личные заказы)
                            $tplCustomer = str_replace('#ADMIN_URL#', '#URL#', $tpl);
                            $textCustomer = self::render($tplCustomer, [
                                'ORDER_ID' => $orderId,
                                'REASON'   => $reason,
                                'PRICE'    => $priceString,
                                'ADMIN_URL'=> $adminUrl,
                                'URL'      => $userUrl,
                            ]);
                            Sender::send($token, [(string)$chatId], $textCustomer);
                        }

                    }
                }
            }
        } else {
            static $sentUncancel = [];
            $dupKey2 = 'uncancel-'.$orderId;
            if (isset($sentUncancel[$dupKey2])) { return; }
            $sentUncancel[$dupKey2] = true;

            $tpl2 = Option::get('ushakov.telegram','TPL_ORDER_UNCANCELED', "♻️ Отмена заказа #ORDER_ID# снята\nСумма: #PRICE#\nСсылка: #ADMIN_URL#");

            $adminUrl2 = self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID);
            $userUrl2  = self::buildAbsoluteUrl('/personal/orders/');
            if ($adminUncancelEnabled) {
                $tplAdmin2 = str_replace('#URL#', '#ADMIN_URL#', $tpl2);
                $textAdmin2 = self::render($tplAdmin2, [
                    'ORDER_ID' => $orderId,
                    'PRICE'    => $priceString,
                    'ADMIN_URL'=> $adminUrl2,
                    'URL'      => $userUrl2,
                ]);
                self::sendToAdmins($textAdmin2, method_exists($order,'getSiteId') ? (string)$order->getSiteId() : null);
            }


            if (Option::get('ushakov.telegram','CUSTOMER_NOTIFY_ENABLED','N') === 'Y'
                && Option::get('ushakov.telegram','CUSTOMER_EVENTS_ORDER_UNCANCEL','Y') === 'Y') {
                if ($order) {
                    $userId = (int)$order->getUserId();
                    if ($userId > 0) {
                        $siteId = (string)(method_exists($order,'getSiteId') ? $order->getSiteId() : SITE_ID);
                        $chatId = \Ushakov\Telegram\Repository\BindingRepository::getChatId($siteId, $userId);
                        $token = self::getToken();

                        if ($chatId && $token) {
                            $tplCustomer2 = str_replace('#ADMIN_URL#', '#URL#', $tpl2);
                            $textCustomer2 = self::render($tplCustomer2, [
                                'ORDER_ID' => $orderId,
                                'PRICE'    => $priceString,
                                'ADMIN_URL'=> $adminUrl2,
                                'URL'      => $userUrl2,
                            ]);
                            Sender::send($token, [(string)$chatId], $textCustomer2);
                        }

                    }
                }
            }
        }
    }

    // Совместимость: старый обработчик отмены
    public static function onSaleCancelOrder($orderId, $value): void
    {
        self::onOrderCanceled($orderId, $value);
    }
    protected static function getToken(): string
    {
        return (string) Option::get('ushakov.telegram', 'BOT_TOKEN', '');
    }

    protected static function getChatIds(): array
    {
        $raw = (string) Option::get('ushakov.telegram', 'DEFAULT_CHAT_IDS', '');
        $ids = Sender::parseChatIds($raw);

        // Fallback: если список пуст, попробуем взять всех сотрудников из привязок
        if (empty($ids)) {
            try {
                $conn = \Bitrix\Main\Application::getConnection();
                if ($conn && $conn->isTableExists('b_ushakov_tg_bindings')) {
                    $sqlHelper = $conn->getSqlHelper();
                    $siteFilter = '';
                    if (defined('SITE_ID') && SITE_ID !== '') {
                        $siteFilter = "SITE_ID='".$sqlHelper->forSql((string)SITE_ID)."' AND ";
                    }
                    $rows = $conn->query("SELECT DISTINCT CHAT_ID FROM b_ushakov_tg_bindings WHERE ".$siteFilter."IS_STAFF=1 AND CONSENT='Y' AND CHAT_ID IS NOT NULL AND CHAT_ID<>''");
                    while ($r = $rows->fetch()) {
                        $ids[] = (string)$r['CHAT_ID'];
                    }
                    $ids = array_values(array_unique(array_filter(array_map('trim', $ids))));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return $ids;
    }

    protected static function sendToAdmins(string $text, ?string $siteId = null): void
    {
        $token = self::getToken();
        if (!trim($text) || !$token) { return; }

        $raw = (string) Option::get('ushakov.telegram', 'DEFAULT_CHAT_IDS', '');
        $ids = Sender::parseChatIds($raw);
        if (empty($ids)) {
            try {
                $conn = \Bitrix\Main\Application::getConnection();
                if ($conn && $conn->isTableExists('b_ushakov_tg_bindings')) {
                    $sqlHelper = $conn->getSqlHelper();
                    $where = "IS_STAFF=1 AND CONSENT='Y' AND CHAT_ID IS NOT NULL AND CHAT_ID<>''";
                    if ($siteId !== null && $siteId !== '') {
                        $where = "SITE_ID='".$sqlHelper->forSql($siteId)."' AND ".$where;
                    }
                    $rows = $conn->query("SELECT DISTINCT CHAT_ID FROM b_ushakov_tg_bindings WHERE ".$where);
                    while ($r = $rows->fetch()) { $ids[] = (string)$r['CHAT_ID']; }
                    $ids = array_values(array_unique(array_filter(array_map('trim', $ids))));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if (empty($ids)) { return; }
        Sender::send($token, $ids, $text);
    }

    protected static function render(string $tpl, array $vars): string
    {
        $search = [];
        $replace = [];
        foreach ($vars as $k => $v) {
            $search[] = '#' . $k . '#';
            // HTML parse_mode: экранируем переменные, чтобы избежать слома разметки и инъекций
            $replace[] = htmlspecialcharsbx((string)$v);
        }
        return str_replace($search, $replace, $tpl);
    }

    protected static function pushOrSend(string $text): void
    {
        $token = self::getToken();
        $chatIds = self::getChatIds();
        if (!trim($text) || !$token || !$chatIds) { return; }

            Sender::send($token, $chatIds, $text);
    }

    // Новый заказ
    public static function onOrderSaved(\Bitrix\Main\Event $event): void
    {
        $entity = $event->getParameter('ENTITY');
        $isNew  = (bool) $event->getParameter('IS_NEW');
        if (!$isNew) { return; }

        // Дедупликация на случай повторного вызова обработчика в одной транзакции
        static $sentNewKeys = [];
        $dupKey = 'new-'.(int)$entity->getId();
        if (isset($sentNewKeys[$dupKey])) { return; }

        // Кросс-запросная дедупликация на короткое время (5 минут)
        try {
            $cache = new \CPHPCache();
            $cacheTtl = 300; // 5 минут
            $cacheDir = '/ushakov_tg/new_order';
            $cacheId = 'order_'.$entity->getId();
            if ($cache->InitCache($cacheTtl, $cacheId, $cacheDir)) {
                return; // уже отправляли недавно
            } else {
                if ($cache->StartDataCache()) {
                    $cache->EndDataCache(['t' => time()]);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache errors
        }

        $sentNewKeys[$dupKey] = true;

        // Сбор данных
        $orderId = $entity->getId();
        $price   = $entity->getPrice();
        $currency = $entity->getCurrency();
        $paymentName = self::resolvePaymentSystemName($entity);
        $delivery = '';
        if ($shipmentCollection = $entity->getShipmentCollection()) {
            foreach ($shipmentCollection as $shipment) {
                if (!$shipment->isSystem()) {
                    $service = $shipment->getDelivery();
                    $delivery = $service ? $service->getName() : '';
                    break;
                }
            }
        }

        $adminUrl = self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID=' . (int)$orderId . '&lang=' . LANGUAGE_ID);

        $basketText = '';
        if ($basket = $entity->getBasket()) {
            foreach ($basket->getBasketItems() as $item) {
                $basketText .= (string)$item->getField('NAME') . ' x ' . (float)$item->getQuantity() . "\n";
            }
        }

        $props = [];
        if ($propsColl = $entity->getPropertyCollection()) {
            foreach ($propsColl as $prop) {
                $code = trim((string)$prop->getField('CODE'));
                if ($code !== '') { $props[$code] = trim((string)$prop->getValue()); }
            }
        }

        $tpl = Option::get('ushakov.telegram','TPL_ORDER_NEW', Loc::getMessage('USH_TG_TPL_ORDER_NEW_DEF'));
        $text = self::render($tpl, [
            'ORDER_ID' => $orderId,
            'PRICE'    => $price . ' ' . $currency,
            'DELIVERY' => $delivery,
            'PAYMENT'  => $paymentName,
            'ADMIN_URL'=> $adminUrl,
            'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
            'BASKET'   => $basketText,
            'FIO'      => $props['FIO'] ?? ($props['CONTACT_NAME'] ?? ''),
            'EMAIL'    => $props['EMAIL'] ?? '',
            'PHONE'    => $props['PHONE'] ?? '',
        ]);

        // Админское уведомление — только если включено SEND_ORDER_NEW
        if (Option::get('ushakov.telegram','SEND_ORDER_NEW','Y') === 'Y') {
            $siteId = (string)(method_exists($entity,'getSiteId') ? $entity->getSiteId() : SITE_ID);
            // Если в шаблоне используется #URL#, подменим его на #ADMIN_URL# для сотрудников
            $tplAdmin = str_replace('#URL#', '#ADMIN_URL#', $tpl);
            $adminText = self::render($tplAdmin, [
                'ORDER_ID' => $orderId,
                'PRICE'    => $price . ' ' . $currency,
                'DELIVERY' => $delivery,
                'PAYMENT'  => $paymentName,
                'ADMIN_URL'=> $adminUrl,
                'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
                'BASKET'   => $basketText,
                'FIO'      => $props['FIO'] ?? ($props['CONTACT_NAME'] ?? ''),
                'EMAIL'    => $props['EMAIL'] ?? '',
                'PHONE'    => $props['PHONE'] ?? '',
            ]);
            self::sendToAdmins($adminText, $siteId);
        }

        // Покупателю (если включено)
        if (Option::get('ushakov.telegram','CUSTOMER_NOTIFY_ENABLED','N') === 'Y'
            && Option::get('ushakov.telegram','CUSTOMER_EVENTS_ORDER_NEW','Y') === 'Y') {
            $userId = (int) (method_exists($entity,'getUserId') ? $entity->getUserId() : 0);
            if ($userId > 0) {
                $siteId = (string)(method_exists($entity,'getSiteId') ? $entity->getSiteId() : SITE_ID);
                $chatId = \Ushakov\Telegram\Repository\BindingRepository::getChatId($siteId, $userId);
                $token = self::getToken();
                if ($chatId && $token) {
                    // Для покупателей принудительно используем #URL# вместо #ADMIN_URL#
                    $tplCustomer = str_replace('#ADMIN_URL#', '#URL#', $tpl);
                    $customerText = self::render($tplCustomer, [
                        'ORDER_ID' => $orderId,
                        'PRICE'    => $price . ' ' . $currency,
                        'DELIVERY' => $delivery,
                        'PAYMENT'  => $paymentName,
                        'ADMIN_URL'=> $adminUrl,
                        'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
                        'BASKET'   => $basketText,
                        'FIO'      => $props['FIO'] ?? ($props['CONTACT_NAME'] ?? ''),
                        'EMAIL'    => $props['EMAIL'] ?? '',
                        'PHONE'    => $props['PHONE'] ?? '',
                    ]);
                    Sender::send($token, [(string)$chatId], $customerText);
                }
            }
        }
    }

    // Совместимость со старыми регистрациями событий: некоторые окружения могли
    // регистрировать метод onSaleOrderSaved. Делаем алиас на onOrderSaved.
    public static function onSaleOrderSaved(\Bitrix\Main\Event $event): void
    {
        self::onOrderSaved($event);
    }

    // Смена статуса: поддержка сигнатур (event) и (orderId, before, after)
    public static function onOrderStatusChange($arg1, $arg2 = null, $arg3 = null): void
    {

        $orderId = 0; $before = ''; $after = ''; $order = null;
        if ($arg1 instanceof \Bitrix\Main\Event) {
            $params = $arg1->getParameters();
            $order  = $params['ENTITY'] ?? null;
            if (is_object($order) && method_exists($order, 'getId')) {
                $orderId = (int)$order->getId();
            } else {
                $orderId = (int)($params['ID'] ?? 0);
            }
            // разные версии/обёртки событий могут присылать разные ключи
            $before = (string)($params['STATUS_OLD'] ?? $params['VALUE_BEFORE'] ?? '');
            $after  = (string)($params['STATUS_NEW'] ?? $params['VALUE'] ?? '');
            if ($after === '') {
                $ordTmp = \Bitrix\Sale\Order::load($orderId);
                if ($ordTmp && method_exists($ordTmp, 'getField')) {
                    $after = (string)$ordTmp->getField('STATUS_ID');
                }
            }
        } else {
            $orderId = (int)$arg1;
            $before  = (string)$arg2;
            $after   = (string)$arg3;
        }

        if ($orderId <= 0 || $after === '') { return; }

        // Человекочитаемое название статуса
        $statusName = (string)$after;
        $row = \Bitrix\Sale\Internals\StatusLangTable::getList([
            'filter' => [
                '=STATUS_ID' => $after,
                '=LID'       => LANGUAGE_ID,
            ],
            'select' => ['NAME']
        ])->fetch();
        if ($row && isset($row['NAME']) && $row['NAME'] !== '') {
            $statusName = (string)$row['NAME'];
        } else {
            // Резервный способ через старый API
            if (class_exists('CSaleStatus')) {
                $st = \CSaleStatus::GetByID($after, LANGUAGE_ID);
                if (is_array($st) && !empty($st['NAME'])) {
                    $statusName = (string)$st['NAME'];
                }
            }
        }

        // Сумма заказа
        $priceString = '';
        if ($order = \Bitrix\Sale\Order::load($orderId)) {
            $priceString = (string)\CCurrencyLang::CurrencyFormat($order->getPrice(), $order->getCurrency());
            // Декодирование HTML-сущностей и замена неразрывных пробелов для Telegram
            $priceString = html_entity_decode($priceString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $priceString = str_replace("\xC2\xA0", ' ', $priceString);
        }

        $tpl = Option::get('ushakov.telegram','TPL_ORDER_STATUS', Loc::getMessage('USH_TG_TPL_ORDER_STATUS_DEF'));
        $text = self::render($tpl, [
            'ORDER_ID' => $orderId,
            'STATUS'   => $statusName,
            'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID),
            'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
            'PRICE'    => $priceString,
        ]);
        // Админ-уведомление — только если включено
        if (Option::get('ushakov.telegram','SEND_ORDER_STATUS','Y') === 'Y') {
            $siteId = null;
            if ($order instanceof \Bitrix\Sale\Order && method_exists($order,'getSiteId')) { $siteId = (string)$order->getSiteId(); }
            $tplAdmin = str_replace('#URL#', '#ADMIN_URL#', $tpl);
            $adminText = self::render($tplAdmin, [
                'ORDER_ID' => $orderId,
                'STATUS'   => $statusName,
                'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID),
                'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
                'PRICE'    => $priceString,
            ]);
            self::sendToAdmins($adminText, $siteId);
        }

        // Покупателю (если включено)
        if (Option::get('ushakov.telegram','CUSTOMER_NOTIFY_ENABLED','N') === 'Y'
            && Option::get('ushakov.telegram','CUSTOMER_EVENTS_ORDER_STATUS','Y') === 'Y') {
            $order = \Bitrix\Sale\Order::load($orderId);
            if ($order) {
                $userId = (int)$order->getUserId();
                if ($userId > 0) {
                    $siteId = (string)(method_exists($order,'getSiteId') ? $order->getSiteId() : SITE_ID);
                    $chatId = \Ushakov\Telegram\Repository\BindingRepository::getChatId($siteId, $userId);
                    $token = self::getToken();
                    if ($chatId && $token) {
                        $tplCustomer = str_replace('#ADMIN_URL#', '#URL#', $tpl);
                        $customerText = self::render($tplCustomer, [
                            'ORDER_ID' => $orderId,
                            'STATUS'   => $statusName,
                            'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID),
                            'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
                            'PRICE'    => $priceString,
                        ]);
                        Sender::send($token, [(string)$chatId], $customerText);
                    }
                }
            }
        }
    }

    // Совместимость: ожидают метод onSaleStatusChange
    // Поддерживаем оба варианта сигнатуры: (event) и (orderId, before, after)
    public static function onSaleStatusChange($arg1, $arg2 = null, $arg3 = null): void
    {
        if ($arg1 instanceof \Bitrix\Main\Event) {
            $e = $arg1;
            $params = $e->getParameters();
            $order = $params['ENTITY'] ?? null;
            if (is_object($order) && method_exists($order, 'getId')) {
                $orderId = (int)$order->getId();
            } else {
                $orderId = (int)($params['ID'] ?? 0);
            }
            $before = (string)($params['STATUS_OLD'] ?? '');
            $after  = (string)($params['STATUS_NEW'] ?? '');
            self::onOrderStatusChange($orderId, $before, $after);
            return;
        }
        self::onOrderStatusChange($arg1, (string)$arg2, (string)$arg3);
    }

    // Оплата заказа: поддержка сигнатур (event) и (orderId, isPaid)
    public static function onOrderPay($arg1, $arg2 = null): void
    {
        $adminPayEnabled = Option::get('ushakov.telegram','SEND_ORDER_PAY','Y') === 'Y';

        $orderId = 0; $isPaid = false; $paymentName = '';
        if ($arg1 instanceof \Bitrix\Main\Event) {
            $parameters = $arg1->getParameters();
            $order = $parameters['ENTITY'] ?? null;
            if (!$order) { return; }
            $orderId = (int)$order->getId();
            // Пытаемся определить оплату из разных источников
            $isPaid = (bool)$order->isPaid();
            $paidParam = $parameters['IS_PAID'] ?? ($parameters['VALUE'] ?? ($parameters['PAID'] ?? null));
            if ($paidParam !== null) {
                $isPaid = ($paidParam === true || $paidParam === 'Y' || $paidParam === 1 || $paidParam === '1');
            }
            $paymentName = method_exists($order,'getPaymentSystemName') ? (string)$order->getPaymentSystemName() : '';
        } else {
            // Старый формат от совместимого обработчика: (orderId, isPaid)
            $orderId = (int)$arg1;
            $isPaid = ($arg2 === true || $arg2 === 'Y' || $arg2 === 1 || $arg2 === '1');
        }

        // Отправляем только при успешной оплате
        if ($orderId <= 0 || !$isPaid) { return; }

        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order) { return; }

        if ($paymentName === '') {
            // Попытаться получить имя платежной системы из коллекции платежей
            if (method_exists($order, 'getPaymentCollection')) {
                $pc = $order->getPaymentCollection();
                if ($pc) {
                    foreach ($pc as $payment) {
                        $psName = '';
                        if (method_exists($payment, 'getPaymentSystemId')) {
                            $psId = (int)$payment->getPaymentSystemId();
                            if ($psId > 0 && class_exists('\\Bitrix\\Sale\\PaySystem\\Manager')) {
                                // Современный способ
                                $service = \Bitrix\Sale\PaySystem\Manager::getObjectById($psId);
                                if ($service) {
                                    $psName = (string)$service->getField('NAME');
                                }
                                // Резервный способ
                                if ($psName === '') {
                                    $row = \Bitrix\Sale\PaySystem\Manager::getList([
                                        'filter' => ['ID' => $psId],
                                        'select' => ['NAME']
                                    ])->fetch();
                                    if ($row && !empty($row['NAME'])) {
                                        $psName = (string)$row['NAME'];
                                    }
                                }
                            }
                        }
                        if ($psName === '' && method_exists($payment, 'getPaymentSystemName')) {
                            $psName = (string)$payment->getPaymentSystemName();
                        }
                        if ($psName !== '') {
                            $paymentName = $psName;
                            break;
                        }
                    }
                }
            }
        }

        // Защитимся от дублей (например, если прилетают оба события: OnSaleOrderPaid и OnSalePayOrder)
        static $sentKeys = [];
        $dedupKey = 'pay-'.$orderId;
        if (isset($sentKeys[$dedupKey])) { return; }
        $sentKeys[$dedupKey] = true;

        $tpl = Option::get('ushakov.telegram','TPL_ORDER_PAY', Loc::getMessage('USH_TG_TPL_ORDER_PAY_DEF'));
        $text = self::render($tpl, [
            'ORDER_ID' => $order->getId(),
            'PRICE'    => $order->getPrice() . ' ' . $order->getCurrency(),
            'PAYMENT'  => $paymentName,
            'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$order->getId().'&lang='.LANGUAGE_ID),
            'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
        ]);
        if ($adminPayEnabled) {
            $siteId = (string)(method_exists($order,'getSiteId') ? $order->getSiteId() : SITE_ID);
            $tplAdmin = str_replace('#URL#', '#ADMIN_URL#', $tpl);
            $adminText = self::render($tplAdmin, [
                'ORDER_ID' => $order->getId(),
                'PRICE'    => $order->getPrice() . ' ' . $order->getCurrency(),
                'PAYMENT'  => $paymentName,
                'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$order->getId().'&lang='.LANGUAGE_ID),
                'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
            ]);
            self::sendToAdmins($adminText, $siteId);
        }

        // Покупателю (если включено)
        if (Option::get('ushakov.telegram','CUSTOMER_NOTIFY_ENABLED','N') === 'Y'
            && Option::get('ushakov.telegram','CUSTOMER_EVENTS_ORDER_PAY','Y') === 'Y') {
            $userId = (int)$order->getUserId();
            if ($userId > 0) {
                $siteId = (string)(method_exists($order,'getSiteId') ? $order->getSiteId() : SITE_ID);
                $chatId = \Ushakov\Telegram\Repository\BindingRepository::getChatId($siteId, $userId);
                $token = self::getToken();
                if ($chatId && $token) {
                    $tplCustomer = str_replace('#ADMIN_URL#', '#URL#', $tpl);
                    $customerText = self::render($tplCustomer, [
                        'ORDER_ID' => $order->getId(),
                        'PRICE'    => $order->getPrice() . ' ' . $order->getCurrency(),
                        'PAYMENT'  => $paymentName,
                        'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$order->getId().'&lang='.LANGUAGE_ID),
                        'URL'      => self::buildAbsoluteUrl('/personal/orders/'),
                    ]);
                    Sender::send($token, [(string)$chatId], $customerText);
                }
            }
        }
    }

    // Совместимость: некоторые окружения используют событие OnSaleOrderPaid
    // и ожидают метод onSaleOrderPaid. Делаем прокси к onOrderPay.
    public static function onSaleOrderPaid(\Bitrix\Main\Event $event): void
    {
        self::onOrderPay($event);
    }

    // Регистрация пользователя
    public static function onUserRegistered($fields): void
    {
        // Админское уведомление о регистрации — по флагу
        if (Option::get('ushakov.telegram','SEND_USER_REGISTERED','N') !== 'Y') { return; }
        $tpl = Option::get('ushakov.telegram','TPL_USER_REGISTERED', Loc::getMessage('USH_TG_TPL_USER_REGISTERED_DEF'));
        $text = self::render($tpl, [
            'USER_ID' => $fields['ID'] ?? '',
            'LOGIN'   => $fields['LOGIN'] ?? '',
            'EMAIL'   => $fields['EMAIL'] ?? '',
        ]);
        self::pushOrSend($text);
    }

    

    // Врезка на страницу профиля: кнопка «Привязать Telegram»
    public static function onEpilog(): void
    {
        global $USER, $APPLICATION;
        if (!is_object($USER) || !$USER->IsAuthorized()) { return; }
        $bot = trim((string) Option::get('ushakov.telegram','BOT_USERNAME',''));
        if ($bot === '') { return; }

        // Показ кнопки: сотрудникам показываем всегда; покупателям — по опции CUSTOMER_SHOW_BIND_BUTTON
        $isStaff = false;
        try {
            $groupsOpt = (string) Option::get('ushakov.telegram','STAFF_GROUP_IDS','');
            $staffGroupIds = array_filter(array_map('intval', array_map('trim', explode(',', $groupsOpt))));
            if (!empty($staffGroupIds) && method_exists($USER, 'GetUserGroup')) {
                $userGroups = (array) $USER->GetUserGroup($USER->GetID());
                $isStaff = (bool) array_intersect($staffGroupIds, array_map('intval', $userGroups));
            }
        } catch (\Throwable $e) { /* ignore */ }
        if (!$isStaff) {
            // Для покупателей учитываем переключатель
            if (Option::get('ushakov.telegram','CUSTOMER_SHOW_BIND_BUTTON','N') !== 'Y') { return; }
        }

        // Определяем, что мы на странице заказов (универсально для разных шаблонов)
        $uri = (string)$APPLICATION->GetCurPage(false);
        $isOrders = (stripos($uri, '/personal/order') !== false) || (stripos($uri, '/personal/orders') !== false);
        if (!$isOrders) { return; }

        $deep = \Ushakov\Telegram\Service\WebhookRegistrar::buildDeepLink($bot, SITE_ID, (int)$USER->GetID());
        $payload = '';
        $parsed = parse_url($deep);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['start'])) { $payload = (string)$q['start']; }
        }

        // Ссылки на бота (без payload): пользователь вставит код вручную
        $tgUrl  = 'tg://resolve?domain=' . rawurlencode($bot);
        $webUrl = 'https://t.me/' . rawurlencode($bot);

        // Универсальная вставка через JS (ищем подходящее место, иначе плавающая кнопка)
        $btnText  = (string)(Loc::getMessage('USH_TG_DEEPLINK') ?: 'Привязать Telegram');
        $payloadJs = json_encode((string)$payload);
        $tgUrlJs   = json_encode((string)$tgUrl);
        $webUrlJs  = json_encode((string)$webUrl);
        $btnTextJs = json_encode((string)$btnText);
        $js  = "(function(){\n";
        $js .= "var p=location.pathname; var ok=(p.indexOf('/personal/order')!==-1)||(p.indexOf('/personal/orders')!==-1); if(!ok) return;\n";
        $js .= "var payload = $payloadJs;\n";
        $js .= "var tgUrl = $tgUrlJs;\n";
        $js .= "var webUrl = $webUrlJs;\n";
        $js .= "var btnText = $btnTextJs;\n";
        $js .= "function copy(text){try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).catch(function(){});}else{var ta=document.createElement(\"textarea\");ta.value=text;ta.style.position=\"fixed\";ta.style.left=\"-1000px\";ta.style.top=\"-1000px\";document.body.appendChild(ta);ta.focus();ta.select();try{document.execCommand(\"copy\");}catch(e){}document.body.removeChild(ta);}}catch(e){}}\n";
        $js .= "function build(){var wrap=document.createElement(\"div\");wrap.className=\"ushakov-tg-profile\";var btn=document.createElement(\"button\");btn.type=\"button\";btn.textContent=btnText;btn.style.cssText=\"display:inline-block;padding:8px 12px;background:#2fc6f6;color:#fff;border:none;border-radius:4px;cursor:pointer\";btn.onclick=function(){copy(\"/start \"+payload);try{window.open(tgUrl,\"_blank\");}catch(e){} setTimeout(function(){try{window.open(webUrl,\"_blank\");}catch(e){}},300);};var hint=document.createElement(\"div\");hint.style.cssText=\"margin-top:8px;max-width:560px;font-size:12px;line-height:1.5;color:#444;background:#f6f8fa;border:1px solid #e5e7eb;padding:8px 10px;border-radius:4px\";hint.textContent=\"Зачем привязывать? Вы будете получать мгновенные уведомления о статусе и оплате прямо в Telegram. Код для привязки автоматически копируется в буфер обмена. После открытия Telegram-бота вставьте этот код и отправьте сообщение.\";wrap.appendChild(btn);wrap.appendChild(hint);return wrap;}\n";
        $js .= "function place(block){var targets=[\".sale-order-list\",\".sale-order-list-container\",\"h1\",\".page-title\",\".content-title\"];var t=null;for(var i=0;i<targets.length;i++){var cand=document.querySelector(targets[i]);if(cand){t=cand;break;}}if(t){if(t.tagName&&t.tagName.toLowerCase()===\"h1\"&&t.parentNode){t.parentNode.insertBefore(block,t.nextSibling);try{var mb=parseFloat(getComputedStyle(t).marginBottom)||0;var desired=20;block.style.marginTop=(desired-mb)+\"px\";}catch(e){}}else{t.insertAdjacentElement(\"afterbegin\",block);}return true;}var cont=document.querySelector(\"#content, .workarea, main, .container\");if(cont){cont.appendChild(block);return true;}block.style.position=\"fixed\";block.style.right=\"16px\";block.style.bottom=\"16px\";block.style.zIndex=9999;document.body.appendChild(block);return true;}\n";
        $js .= "document.addEventListener(\"DOMContentLoaded\",function(){place(build());});\n";
        $js .= "})();";
        echo '<script>'.$js.'</script>';
    }

    
}
