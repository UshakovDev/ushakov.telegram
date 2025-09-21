<?php
// if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

Loader::registerAutoLoadClasses("ushakov.telegram", [
    'Ushakov\\Telegram\\Events' => 'lib/Events.php',
    'Ushakov\\Telegram\\Sender' => 'lib/Sender.php',
    'Ushakov\\Telegram\\Agent'  => 'lib/Agent.php',
    'Ushakov\\Telegram\\AgentRunner'  => 'lib/AgentRunner.php',
    'Ushakov\\Telegram\\ORM\\BindingTable' => 'lib/ORM/BindingTable.php',
    'Ushakov\\Telegram\\Service\\WebhookRegistrar' => 'lib/Service/WebhookRegistrar.php',
    'Ushakov\\Telegram\\Repository\\BindingRepository' => 'lib/Repository/BindingRepository.php',
]);

// Рантайм-подписка на смену статуса заказа (если не зарегистрирована через InstallEvents)
if (function_exists('GetModuleEvents')) {
    $already = false;
    foreach (GetModuleEvents('sale', 'OnSaleStatusOrderChange', true) as $h) {
        if (($h['TO_CLASS'] ?? '') === '\\Ushakov\\Telegram\\Events' && ($h['TO_METHOD'] ?? '') === 'onOrderStatusChange') {
            $already = true; break;
        }
    }
    if (!$already) {
        \AddEventHandler('sale', 'OnSaleStatusOrderChange', ['\\Ushakov\\Telegram\\Events', 'onOrderStatusChange']);
    }
}

// Если будете делать платную/демо-версию, можно использовать:
// $status = CModule::IncludeModuleEx("ushakov.telegram");
// if ($status === MODULE_DEMO_EXPIRED) { /* запретить работу */ }

// Важно: тег закрывается для совместимости с демо-обфускацией
?>
