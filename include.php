<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses("ushakov.telegram", [
    'Ushakov\\Telegram\\Events' => 'lib/Events.php',
    'Ushakov\\Telegram\\Sender' => 'lib/Sender.php',
    'Ushakov\\Telegram\\Agent'  => 'lib/Agent.php',
    'Ushakov\\Telegram\\AgentRunner'  => 'lib/AgentRunner.php',
    // 'Ushakov\\Telegram\\ORM\\QueueTable' => 'lib/ORM/QueueTable.php',
    'Ushakov\\Telegram\\Service\\WebhookRegistrar' => 'lib/Service/WebhookRegistrar.php',
    'Ushakov\\Telegram\\Repository\\BindingRepository' => 'lib/Repository/BindingRepository.php',
]);

// Если будете делать платную/демо-версию, можно использовать:
// $status = CModule::IncludeModuleEx("ushakov.telegram");
// if ($status === MODULE_DEMO_EXPIRED) { /* запретить работу */ }

// Важно: тег закрывается для совместимости с демо-обфускацией
?>
