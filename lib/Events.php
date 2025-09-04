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
        if (Option::get('ushakov.telegram','SEND_ORDER_CANCELED','Y') !== 'Y') { return; }

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
            if (Option::get('ushakov.telegram','SEND_ORDER_CANCELED','Y') !== 'Y') { return; }
            static $sentCancel = [];
            $dupKey = 'cancel-'.$orderId;
            if (isset($sentCancel[$dupKey])) { return; }
            $sentCancel[$dupKey] = true;

            $tpl = Option::get('ushakov.telegram','TPL_ORDER_CANCELED', '❌ Заказ #ORDER_ID# отменён\nПричина: #REASON#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#');
            $text = self::render($tpl, [
                'ORDER_ID' => $orderId,
                'REASON'   => $reason,
                'PRICE'    => $priceString,
                'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID),
            ]);
            self::pushOrSend($text);
        } else {
            if (Option::get('ushakov.telegram','SEND_ORDER_UNCANCELED','Y') !== 'Y') { return; }
            static $sentUncancel = [];
            $dupKey2 = 'uncancel-'.$orderId;
            if (isset($sentUncancel[$dupKey2])) { return; }
            $sentUncancel[$dupKey2] = true;

            $tpl2 = Option::get('ushakov.telegram','TPL_ORDER_UNCANCELED', "♻️ Отмена заказа #ORDER_ID# снята\nСумма: #PRICE#\nСсылка: #ADMIN_URL#");
            $text2 = self::render($tpl2, [
                'ORDER_ID' => $orderId,
                'PRICE'    => $priceString,
                'ADMIN_URL'=> self::buildAbsoluteUrl('/bitrix/admin/sale_order_view.php?ID='.(int)$orderId.'&lang='.LANGUAGE_ID),
            ]);
            self::pushOrSend($text2);
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
        return Sender::parseChatIds($raw);
    }

    protected static function useQueue(): bool
    {
        return Option::get('ushakov.telegram', 'USE_QUEUE', 'Y') === 'Y';
    }

    protected static function render(string $tpl, array $vars): string
    {
        $search = [];
        $replace = [];
        foreach ($vars as $k => $v) {
            $search[] = '#' . $k . '#';
            $replace[] = (string)$v;
        }
        return str_replace($search, $replace, $tpl);
    }

    protected static function pushOrSend(string $text): void
    {
        $token = self::getToken();
        $chatIds = self::getChatIds();
        if (!trim($text) || !$token || !$chatIds) { return; }

        if (self::useQueue()) {
            // Queue::push([...]); // внедрите вашу очередь
        } else {
            Sender::send($token, $chatIds, $text);
        }
    }

    // Новый заказ
    public static function onOrderSaved(\Bitrix\Main\Event $event): void
    {
        $entity = $event->getParameter('ENTITY');
        $isNew  = (bool) $event->getParameter('IS_NEW');
        if (!$isNew || Option::get('ushakov.telegram','SEND_ORDER_NEW','Y') !== 'Y') { return; }

        // Дедупликация на случай повторного вызова обработчика в одной транзакции
        static $sentNewKeys = [];
        $dupKey = 'new-'.(int)$entity->getId();
        if (isset($sentNewKeys[$dupKey])) { return; }
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
                $basketText .= htmlspecialcharsbx($item->getField('NAME')) . ' x ' . (float)$item->getQuantity() . "\n";
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
            'BASKET'   => $basketText,
            'FIO'      => $props['FIO'] ?? ($props['CONTACT_NAME'] ?? ''),
            'EMAIL'    => $props['EMAIL'] ?? '',
            'PHONE'    => $props['PHONE'] ?? '',
        ]);

        self::pushOrSend($text);
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
        if (Option::get('ushakov.telegram','SEND_ORDER_STATUS','Y') !== 'Y') { return; }

        $orderId = 0; $before = ''; $after = '';
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
            if ($after === '' && is_object($order) && method_exists($order, 'getField')) {
                $after = (string)$order->getField('STATUS_ID');
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
            'PRICE'    => $priceString,
        ]);
        self::pushOrSend($text);
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
        if (Option::get('ushakov.telegram','SEND_ORDER_PAY','Y') !== 'Y') { return; }

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
        ]);
        self::pushOrSend($text);
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
        if (Option::get('ushakov.telegram','SEND_USER_REGISTERED','N') !== 'Y') { return; }
        $tpl = Option::get('ushakov.telegram','TPL_USER_REGISTERED', Loc::getMessage('USH_TG_TPL_USER_REGISTERED_DEF'));
        $text = self::render($tpl, [
            'USER_ID' => $fields['ID'] ?? '',
            'LOGIN'   => $fields['LOGIN'] ?? '',
            'EMAIL'   => $fields['EMAIL'] ?? '',
        ]);
        self::pushOrSend($text);
    }

    // Новая заявка через почтовые события
    public static function onBeforeEventAdd(&$event, &$lid, &$arFields, &$message_id, &$files, &$languageId): void
    {
        if (Option::get('ushakov.telegram','SEND_FORM_NEW','N') !== 'Y') { return; }
        // Можно ограничить типы событий через отдельную опцию (например, список через запятую)
        $tpl = Option::get('ushakov.telegram','TPL_FORM_NEW', Loc::getMessage('USH_TG_TPL_FORM_NEW_DEF'));
        $pairs = [];
        foreach ($arFields as $k => $v) {
            $pairs[] = $k . ': ' . (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }
        $text = self::render($tpl, [
            'EVENT_NAME' => $event,
            'SUBJECT'    => $arFields['SUBJECT'] ?? '',
            'FIELDS'     => implode("\n", $pairs),
        ]);
        self::pushOrSend($text);
    }
}
