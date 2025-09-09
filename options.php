<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

$module_id = "ushakov.telegram";
Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/main/options.php');
Loc::loadMessages(__FILE__);

// Подключение модуля для регистрации автозагрузки классов
Loader::includeModule($module_id);

$request = Context::getCurrent()->getRequest();
// Webhook actions
if ($request->isPost() && check_bitrix_sessid()) {
    $action = (string)$request->getPost('ACTION');
    if ($action === 'SET_WEBHOOK') {
        // Приоритет: из POST (и сохранить), затем из опций, затем из заголовков прокси/хост
        $hostPost = trim((string)$request->getPost('WEBHOOK_PUBLIC_HOST'));
        if ($hostPost !== '') {
            Option::set($module_id, 'WEBHOOK_PUBLIC_HOST', $hostPost);
            $host = $hostPost;
        } else {
            $hostOpt = trim((string) Option::get($module_id, 'WEBHOOK_PUBLIC_HOST', ''));
            if ($hostOpt !== '') {
                $host = $hostOpt;
            } else {
                $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_X_ORIGINAL_HOST'] ?? $_SERVER['HTTP_HOST']);
            }
        }
        $r = \Ushakov\Telegram\Service\WebhookRegistrar::setWebhook($host);
        $note = $r['ok'] ? 'Вебхук установлен: '.$r['webhook'] : 'Ошибка: '.htmlspecialcharsbx($r['error'] ?? '');
        echo BeginNote().$note.EndNote();
    } elseif ($action === 'DELETE_WEBHOOK') {
        $r = \Ushakov\Telegram\Service\WebhookRegistrar::deleteWebhook();
        $note = $r['ok'] ? 'Вебхук удалён' : 'Ошибка: '.htmlspecialcharsbx($r['error'] ?? '');
        echo BeginNote().$note.EndNote();
    } elseif ($action === 'INFO_WEBHOOK') {
        $r = \Ushakov\Telegram\Service\WebhookRegistrar::getWebhookInfo();
        echo '<pre style="max-height:300px;overflow:auto">'.htmlspecialcharsbx(print_r($r['response'], true)).'</pre>';
    }
}


// Вкладки
$aTabs = [
    [
        "DIV"   => "settings",
        "TAB"   => Loc::getMessage("USH_TG_TAB_SETTINGS"),
        "TITLE" => Loc::getMessage("USH_TG_TAB_TITLE_SETTINGS"),
        "OPTIONS" => [
            // Основное
            Loc::getMessage("USH_TG_SECTION_MAIN"),
            ["BOT_TOKEN", Loc::getMessage("USH_TG_OPT_BOT_TOKEN"), "", ["text", 50]],
            ["BOT_USERNAME", Loc::getMessage("USH_TG_OPT_BOT_USERNAME"), "", ["text", 30]],
            ["WEBHOOK_PUBLIC_HOST", Loc::getMessage("USH_TG_OPT_WEBHOOK_PUBLIC_HOST"), "", ["text", 60]],
            ["DEFAULT_CHAT_IDS", Loc::getMessage("USH_TG_OPT_CHAT_IDS"), "", ["text", 50]],
            ["STAFF_GROUP_IDS", Loc::getMessage("USH_TG_OPT_STAFF_GROUPS"), "1", ["text", 30]],
            

            // События
            Loc::getMessage("USH_TG_SECTION_EVENTS"),
            ["SEND_ORDER_NEW", Loc::getMessage("USH_TG_OPT_SEND_ORDER_NEW"), "Y", ["checkbox"]],
            ["SEND_ORDER_STATUS", Loc::getMessage("USH_TG_OPT_SEND_ORDER_STATUS"), "Y", ["checkbox"]],
            ["SEND_ORDER_PAY", Loc::getMessage("USH_TG_OPT_SEND_ORDER_PAY"), "Y", ["checkbox"]],
            ["SEND_ORDER_CANCELED", Loc::getMessage("USH_TG_OPT_SEND_ORDER_CANCELED"), "Y", ["checkbox"]],
            ["SEND_ORDER_UNCANCELED", "Снятие отмены заказа", "Y", ["checkbox"]],
            ["SEND_USER_REGISTERED", Loc::getMessage("USH_TG_OPT_SEND_USER_REGISTERED"), "N", ["checkbox"]],
            ["SEND_FORM_NEW", Loc::getMessage("USH_TG_OPT_SEND_FORM_NEW"), "N", ["checkbox"]],

            // Клиентские уведомления
            Loc::getMessage("USH_TG_SECTION_CUSTOMERS"),
            ["CUSTOMER_NOTIFY_ENABLED", Loc::getMessage("USH_TG_OPT_CUSTOMER_ENABLED"), "N", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_NEW", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_NEW"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_STATUS", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_STATUS"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_PAY", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_PAY"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_CANCEL", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_CANCEL"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_UNCANCEL", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_UNCANCEL"), "Y", ["checkbox"]],

            // Шаблоны
            Loc::getMessage("USH_TG_SECTION_TEMPLATES"),
            ["TPL_ORDER_NEW", Loc::getMessage("USH_TG_TPL_ORDER_NEW"), Loc::getMessage("USH_TG_TPL_ORDER_NEW_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_STATUS", Loc::getMessage("USH_TG_TPL_ORDER_STATUS"), Loc::getMessage("USH_TG_TPL_ORDER_STATUS_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_PAY", Loc::getMessage("USH_TG_TPL_ORDER_PAY"), Loc::getMessage("USH_TG_TPL_ORDER_PAY_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_CANCELED", "Шаблон: отмена заказа", "❌ Заказ #ORDER_ID# отменён\nПричина: #REASON#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#", ["textarea", 6, 60]],
            ["TPL_ORDER_UNCANCELED", "Шаблон: снятие отмены", "♻️ Отмена заказа #ORDER_ID# снята\nСумма: #PRICE#\nСсылка: #ADMIN_URL#", ["textarea", 6, 60]],
            ["TPL_USER_REGISTERED", Loc::getMessage("USH_TG_TPL_USER_REGISTERED"), Loc::getMessage("USH_TG_TPL_USER_REGISTERED_DEF"), ["textarea", 5, 60]],
            ["TPL_FORM_NEW", Loc::getMessage("USH_TG_TPL_FORM_NEW"), Loc::getMessage("USH_TG_TPL_FORM_NEW_DEF"), ["textarea", 6, 60]],
        ],
    ],
    [
        "DIV"   => "rights",
        "TAB"   => Loc::getMessage("USH_TG_TAB_RIGHTS"),
        "TITLE" => Loc::getMessage("USH_TG_TAB_TITLE_RIGHTS"),
        "OPTIONS" => [],
    ],
];

// Сохранение опций ДОЛЖНО идти до вывода формы
if ($request->isPost() && ( $request['save'] !== null || $request['apply'] !== null ) && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        if (!empty($aTab['OPTIONS'])) {
            __AdmSettingsSaveOptions($module_id, $aTab['OPTIONS']);
        }
    }
    LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($module_id) . '&lang=' . LANGUAGE_ID);
}

$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($module_id)?>&lang=<?=LANGUAGE_ID?>">
    <?php
    foreach ($aTabs as $index => $aTab) {
        $tabControl->BeginNextTab();
        if (!empty($aTab['OPTIONS'])) {
            __AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
            if ($index === 0) {
                // Подсказки
                \Bitrix\Main\UI\Extension::load("ui.hint");
                ?>
                <script>
                    BX.ready(function(){ BX.UI.Hint.init(document.body); });
                </script>
                <div class="adm-info-message" data-hint="<?=Loc::getMessage('USH_TG_HINT_CHAT_IDS')?>">
                    <?=Loc::getMessage('USH_TG_HINT_BLOCK_TITLE')?>
                </div>
                <div class="adm-info-message-wrap" style="margin-top: 12px;">
                    <div class="adm-info-message">
                        <b><?=Loc::getMessage('USH_TG_WEBHOOK_STATUS')?></b><br/>
                        <?php
                        $info = \Ushakov\Telegram\Service\WebhookRegistrar::getWebhookInfo();
                        if (!(bool)($info['ok'] ?? false)) {
                            $err = (string)($info['error'] ?? '');
                            if ($err === '') { $err = Loc::getMessage('USH_TG_WEBHOOK_STATUS_NOT_CONFIGURED'); }
                            echo '<div>'.htmlspecialcharsbx($err).'</div>';
                        } else {
                            $resp = (array)($info['response'] ?? []);
                            $url  = (string)($resp['url'] ?? '');
                            $pending = (int)($resp['pending_update_count'] ?? 0);
                            $lastErr = (string)($resp['last_error_message'] ?? '');
                            echo '<div>URL: '.htmlspecialcharsbx($url).'</div>';
                            echo '<div>Pending: '.$pending.'</div>';
                            if ($lastErr !== '') {
                                echo '<div>Last error: '.htmlspecialcharsbx($lastErr).'</div>';
                            }
                        }
                        ?>
                        <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="hidden" name="ACTION" id="tg-action" value="">
                            <button type="submit" class="adm-btn-save" onclick="document.getElementById('tg-action').value='SET_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_SET_WEBHOOK')?></button>
                            <button type="submit" class="adm-btn" onclick="document.getElementById('tg-action').value='DELETE_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_DELETE_WEBHOOK')?></button>
                            <button type="submit" class="adm-btn" onclick="document.getElementById('tg-action').value='INFO_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_INFO_WEBHOOK')?></button>
                        </div>
                        <?php
                        $botUsername = trim((string) Option::get($module_id, 'BOT_USERNAME', ''));
                        global $USER;
                        if ($botUsername !== '' && is_object($USER) && method_exists($USER, 'GetID')) {
                            $deep = \Ushakov\Telegram\Service\WebhookRegistrar::buildDeepLink($botUsername, SITE_ID, (int)$USER->GetID());
                            echo '<div style="margin-top:8px">'.Loc::getMessage('USH_TG_DEEPLINK').': ';
                            echo '<a target="_blank" href="'.htmlspecialcharsbx($deep).'">'.htmlspecialcharsbx($deep)."</a></div>";
                            $payload = '';
                            $parsed = parse_url($deep);
                            if (!empty($parsed['query'])) {
                                parse_str($parsed['query'], $q);
                                if (!empty($q['start'])) { $payload = (string)$q['start']; }
                            }
                            if ($payload !== '') {
                                echo '<div style="margin-top:4px">'.Loc::getMessage('USH_TG_DEEPLINK_MANUAL').': <code>/start '.htmlspecialcharsbx($payload)."</code></div>";
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
        } else {
            require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
        }
    }
    $tabControl->Buttons(['btnApply' => true, 'btnCancel' => false, 'btnSaveAndAdd' => false]);
    echo bitrix_sessid_post();
    ?>
</form>
<?php $tabControl->End(); ?>
