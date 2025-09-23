<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class ushakov_telegram extends CModule
{
    var $MODULE_ID = 'ushakov.telegram';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;
    var $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        $path = str_replace('\\', '/', __FILE__);
        $path = substr($path, 0, strlen($path) - strlen('/index.php'));
        include $path . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->PARTNER_NAME = 'Ushakov';
        $this->PARTNER_URI  = 'https://example.com';

        $this->MODULE_NAME        = Loc::getMessage('USH_TG_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('USH_TG_MODULE_DESC');
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!IsModuleInstalled($this->MODULE_ID)) {
            // Сначала регистрируем модуль, чтобы autoload и Loader::includeModule работали в InstallDB
            RegisterModule($this->MODULE_ID);
            \Bitrix\Main\Loader::includeModule($this->MODULE_ID);
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
        }
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('USH_TG_INSTALL_TITLE'),
            dirname(__FILE__) . '/step.php'
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION, $step;
        $step = (int)($step ?: 1);
        switch ($step) {
            case 1:
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage('USH_TG_UNINSTALL_TITLE'),
                    dirname(__FILE__) . '/unstep.php'
                );
                break;
            case 2:
                $removeData = ($_REQUEST['USH_TG_REMOVE_DATA'] ?? 'N') === 'Y';
                $this->UnInstallEvents();
                $this->UnInstallFiles();
                $this->UnInstallDB($removeData);
                UnRegisterModule($this->MODULE_ID);
                break;
        }
    }

    public function InstallDB()
    {
        // Создание/миграции таблиц через ORM
        try {
            \Bitrix\Main\Loader::includeModule($this->MODULE_ID);
            $conn = \Bitrix\Main\Application::getConnection();
            $helper = $conn->getSqlHelper();

            // Текущая версия схемы
            $ver = (int) (\Bitrix\Main\Config\Option::get($this->MODULE_ID, 'SCHEMA_VERSION', '0'));

            // v1: создать таблицу
            if ($ver < 1) {
                if (!$conn->isTableExists('b_ushakov_tg_bindings')) {
                    \Ushakov\Telegram\ORM\BindingTable::getEntity()->createDbTable();
                    // Индексы/уникальные ключи
                    $conn->queryExecute("CREATE UNIQUE INDEX ux_ush_tg_site_user ON b_ushakov_tg_bindings (SITE_ID, USER_ID)");
                    $conn->queryExecute("CREATE INDEX ix_ush_tg_chat ON b_ushakov_tg_bindings (CHAT_ID)");
                }
                $ver = 1; \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'SCHEMA_VERSION', (string)$ver);
            }

            // v2: добавить ROLE, IS_STAFF, LAST_USED_AT (если нет)
            if ($ver < 2) {
                $col = $conn->query("SHOW COLUMNS FROM b_ushakov_tg_bindings LIKE 'ROLE'")->fetch();
                if (!$col) { $conn->queryExecute("ALTER TABLE b_ushakov_tg_bindings ADD COLUMN ROLE VARCHAR(16) NULL AFTER CONSENT"); }
                $col = $conn->query("SHOW COLUMNS FROM b_ushakov_tg_bindings LIKE 'IS_STAFF'")->fetch();
                if (!$col) { $conn->queryExecute("ALTER TABLE b_ushakov_tg_bindings ADD COLUMN IS_STAFF TINYINT(1) NOT NULL DEFAULT 0 AFTER ROLE"); }
                $col = $conn->query("SHOW COLUMNS FROM b_ushakov_tg_bindings LIKE 'LAST_USED_AT'")->fetch();
                if (!$col) { $conn->queryExecute("ALTER TABLE b_ushakov_tg_bindings ADD COLUMN LAST_USED_AT DATETIME NULL AFTER IS_STAFF"); }
                $ver = 2; \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'SCHEMA_VERSION', (string)$ver);
            }
        } catch (\Throwable $e) {
            // Логировать при необходимости
        }
        return true;
    }

    public function UnInstallDB($removeData = false)
    {
        if ($removeData) {
            try {
                // Удаляем таблицу привязок, если существует
                $conn = \Bitrix\Main\Application::getConnection();
                if ($conn && $conn->isTableExists('b_ushakov_tg_bindings')) {
                    $conn->queryExecute('DROP TABLE b_ushakov_tg_bindings');
                }
                // Удаляем все опции модуля (включая возможные варианты регистра имён)
                \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return true;
    }

    public function InstallEvents()
    {
        $em = EventManager::getInstance();

        // Новый заказ
        $em->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderSaved');
        // Смена статуса
        $em->registerEventHandler('sale', 'OnSaleStatusOrderChange', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderStatusChange');
        // Оплата заказа
        $em->registerEventHandler('sale', 'OnSalePayOrder', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderPay');
        // Статус отгрузки
        $em->registerEventHandler('sale', 'OnSaleShipmentEntitySaved', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onShipmentSaved');
        // Совместимое событие оплаты
        $em->registerEventHandler('sale', 'OnSaleOrderPaid', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleOrderPaid');
        // Регистрация пользователя
        $em->registerEventHandler('main', 'OnAfterUserAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onUserRegistered');

        // Отмена заказа
        $em->registerEventHandler('sale', 'OnSaleCancelOrder', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleCancelOrder');
        // Врезка на страницу профиля (кнопка привязки)
        $em->registerEventHandler('main', 'OnEpilog', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onEpilog');

        // Очередь не используется в этой версии
        // Агент сверки ролей каждые 15 минут (простая строка вызова)
        // На всякий случай удалим возможные старые варианты строки агента
        \CAgent::RemoveAgent("\\\\Ushakov\\Telegram\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::RemoveAgent("\\Ushakov\\Telegram\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::RemoveAgent("\\Ushakov\\Telegram\\AgentRunner::reconcileRoles();", $this->MODULE_ID);
        \CAgent::AddAgent(
            "\\Ushakov\\Telegram\\Agent::reconcileRoles();",
            $this->MODULE_ID,
            'N',
            900,
            '',
            'Y'
        );
        return true;
    }

    public function UnInstallEvents()
    {
        $em = EventManager::getInstance();
        $em->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderSaved');
        $em->unRegisterEventHandler('sale', 'OnSaleStatusOrderChange', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderStatusChange');
        $em->unRegisterEventHandler('sale', 'OnSalePayOrder', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onOrderPay');
        $em->unRegisterEventHandler('sale', 'OnSaleOrderPaid', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleOrderPaid');
        $em->unRegisterEventHandler('sale', 'OnSaleShipmentEntitySaved', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onShipmentSaved');
        $em->unRegisterEventHandler('main', 'OnAfterUserAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onUserRegistered');

        $em->unRegisterEventHandler('sale', 'OnSaleCancelOrder', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleCancelOrder');
        $em->unRegisterEventHandler('main', 'OnEpilog', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onEpilog');

        // Удаляем как новые, так и старые варианты строк агентов
        \CAgent::RemoveAgent("\\Ushakov\\Telegram\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::RemoveAgent("\\\\Ushakov\\Telegram\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::RemoveAgent("\\Ushakov\\Telegram\\AgentRunner::reconcileRoles();", $this->MODULE_ID);
        return true;
    }

    public function InstallFiles()
    {
        // Копируем публичные скрипты в /bitrix/tools/ushakov.telegram
        try {
            // Путь модуля: поддерживаем размещение как в /local/modules, так и в /bitrix/modules
            $modulePath = dirname(__FILE__); // .../install
            $moduleRoot = dirname($modulePath); // корень модуля
            $from = $moduleRoot . '/tools';
            $to   = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/ushakov.telegram';
            if (is_dir($from)) {
                if (!is_dir($to)) {
                    \Bitrix\Main\IO\Directory::createDirectory($to);
                }
                CopyDirFiles($from, $to, true, true);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return true;
    }

    public function UnInstallFiles()
    {
        try {
            // Удаляем каталог инструментов модуля из /bitrix/tools
            DeleteDirFilesEx('/bitrix/tools/ushakov.telegram');
        } catch (\Throwable $e) {
            // ignore
        }
        return true;
    }
}
