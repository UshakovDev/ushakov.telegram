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
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
            RegisterModule($this->MODULE_ID);
        }
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('USH_TG_INSTALL_TITLE'),
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/step.php'
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
                    $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/unstep.php'
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
        // Создайте таблицы через ORM здесь (если нужны).
        // Пример:
        // \Ushakov\Telegram\ORM\QueueTable::getEntity()->createDbTable();
        return true;
    }

    public function UnInstallDB($removeData = false)
    {
        if ($removeData) {
            // Удалите таблицы/данные, если пользователь выбрал соответствующий чекбокс.
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
        // Совместимое событие оплаты
        $em->registerEventHandler('sale', 'OnSaleOrderPaid', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleOrderPaid');
        // Регистрация пользователя
        $em->registerEventHandler('main', 'OnAfterUserAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onUserRegistered');
        // Новая заявка (перехват почтовых событий)
        $em->registerEventHandler('main', 'OnBeforeEventAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onBeforeEventAdd');
        // Отмена заказа
        $em->registerEventHandler('sale', 'OnSaleCancelOrder', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onSaleCancelOrder');
        // Врезка на страницу профиля (кнопка привязки)
        $em->registerEventHandler('main', 'OnEpilog', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onEpilog');

        // Очередь не используется в этой версии
        // Агент сверки ролей каждые 15 минут
        // На всякий случай удалим возможные старые варианты строки агента
        \CAgent::RemoveAgent("\\\\Ushakov\\\\Telegram\\\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::RemoveAgent("\\Ushakov\\Telegram\\Agent::reconcileRoles();", $this->MODULE_ID);
        \CAgent::AddAgent(
            "\\Ushakov\\Telegram\\AgentRunner::reconcileRoles();",
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
        $em->unRegisterEventHandler('main', 'OnAfterUserAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onUserRegistered');
        $em->unRegisterEventHandler('main', 'OnBeforeEventAdd', $this->MODULE_ID, '\\Ushakov\\Telegram\\Events', 'onBeforeEventAdd');
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
        // Копируйте /install/themes, /install/components, /install/admin при необходимости.
        return true;
    }

    public function UnInstallFiles()
    {
        // Удаление скопированных файлов, если копировали в InstallFiles().
        return true;
    }
}
