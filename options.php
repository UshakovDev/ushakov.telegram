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
        $note = $r['ok']
            ? Loc::getMessage('USH_TG_NOTE_WEBHOOK_SET', ['#WEBHOOK#' => (string)($r['webhook'] ?? '')])
            : Loc::getMessage('USH_TG_NOTE_ERROR', ['#ERROR#' => (string)($r['error'] ?? '')]);
        echo BeginNote().$note.EndNote();
    } elseif ($action === 'DELETE_WEBHOOK') {
        $r = \Ushakov\Telegram\Service\WebhookRegistrar::deleteWebhook();
        $note = $r['ok']
            ? Loc::getMessage('USH_TG_NOTE_WEBHOOK_DELETED')
            : Loc::getMessage('USH_TG_NOTE_ERROR', ['#ERROR#' => (string)($r['error'] ?? '')]);
        echo BeginNote().$note.EndNote();
    } elseif ($action === 'INFO_WEBHOOK') {
        $r = \Ushakov\Telegram\Service\WebhookRegistrar::getWebhookInfo();
        echo '<pre style="max-height:300px;overflow:auto">'.htmlspecialcharsbx(print_r($r['response'], true)).'</pre>';
    } elseif ($action === 'RESET_TEMPLATES_DEFAULTS') {
        // Сброс шаблонов до значений по умолчанию (берутся из lang/*.php)
        foreach ([
            'TPL_ORDER_NEW',
            'TPL_ORDER_STATUS',
            'TPL_ORDER_PAY',
            'TPL_ORDER_CANCELED',
            'TPL_ORDER_UNCANCELED',
            'TPL_USER_REGISTERED',
            'TPL_FORM_NEW',
            'TPL_SHIPMENT_STATUS',
        ] as $optName) {
            Option::delete($module_id, ['name' => $optName]);
        }
        echo BeginNote().Loc::getMessage('USH_TG_NOTE_TEMPLATES_RESET').EndNote();
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

            ["SEND_ORDER_UNCANCELED", Loc::getMessage("USH_TG_OPT_SEND_ORDER_UNCANCELED"), "Y", ["checkbox"]],

            ["SEND_SHIPMENT_STATUS", Loc::getMessage("USH_TG_OPT_SEND_SHIPMENT_STATUS"), "Y", ["checkbox"]],

            ["SEND_USER_REGISTERED", Loc::getMessage("USH_TG_OPT_SEND_USER_REGISTERED"), "N", ["checkbox"]],
            

            // Клиентские уведомления
            Loc::getMessage("USH_TG_SECTION_CUSTOMERS"),
            ["CUSTOMER_NOTIFY_ENABLED", Loc::getMessage("USH_TG_OPT_CUSTOMER_ENABLED"), "N", ["checkbox"]],
            ["CUSTOMER_SHOW_BIND_BUTTON", Loc::getMessage("USH_TG_OPT_CUSTOMER_SHOW_BIND"), "N", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_NEW", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_NEW"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_STATUS", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_STATUS"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_PAY", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_PAY"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_CANCEL", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_CANCEL"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_ORDER_UNCANCEL", Loc::getMessage("USH_TG_OPT_CUSTOMER_ORDER_UNCANCEL"), "Y", ["checkbox"]],
            ["CUSTOMER_EVENTS_SHIPMENT_STATUS", Loc::getMessage("USH_TG_OPT_CUSTOMER_SHIPMENT_STATUS"), "Y", ["checkbox"]],


            // Шаблоны
            Loc::getMessage("USH_TG_SECTION_TEMPLATES"),
            ["TPL_ORDER_NEW", Loc::getMessage("USH_TG_TPL_ORDER_NEW"), Loc::getMessage("USH_TG_TPL_ORDER_NEW_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_STATUS", Loc::getMessage("USH_TG_TPL_ORDER_STATUS"), Loc::getMessage("USH_TG_TPL_ORDER_STATUS_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_PAY", Loc::getMessage("USH_TG_TPL_ORDER_PAY"), Loc::getMessage("USH_TG_TPL_ORDER_PAY_DEF"), ["textarea", 6, 60]],

            ["TPL_ORDER_CANCELED", Loc::getMessage("USH_TG_TPL_ORDER_CANCELED"), Loc::getMessage("USH_TG_TPL_ORDER_CANCELED_DEF"), ["textarea", 6, 60]],
            ["TPL_ORDER_UNCANCELED", Loc::getMessage("USH_TG_TPL_ORDER_UNCANCELED"), Loc::getMessage("USH_TG_TPL_ORDER_UNCANCELED_DEF"), ["textarea", 6, 60]],

            ["TPL_USER_REGISTERED", Loc::getMessage("USH_TG_TPL_USER_REGISTERED"), Loc::getMessage("USH_TG_TPL_USER_REGISTERED_DEF"), ["textarea", 5, 60]],

            ["TPL_SHIPMENT_STATUS", Loc::getMessage("USH_TG_TPL_SHIPMENT_STATUS"), Loc::getMessage("USH_TG_TPL_SHIPMENT_STATUS_DEF"), ["textarea", 6, 60]],
            
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
    // Обработаем сохранение прав доступа: подключим стандартный обработчик до редиректа
    // Ему требуется переменная $Update и массивы $GROUPS/$RIGHTS/$SITES
    $Update = 'Y';
    $GROUPS = (array)$request->getPost('GROUPS');
    $RIGHTS = (array)$request->getPost('RIGHTS');
    $SITES  = (array)$request->getPost('SITES');
    ob_start();
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
    ob_end_clean();
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
                    BX.ready(function(){
                        BX.UI.Hint.init(document.body);
                        var hints = {
                            BOT_TOKEN: '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_BOT_TOKEN'))?>',
                            BOT_USERNAME: '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_BOT_USERNAME'))?>',
                            WEBHOOK_PUBLIC_HOST: '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_PUBLIC_HOST'))?>',
                            DEFAULT_CHAT_IDS: '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_CHAT_IDS'))?>',
                            STAFF_GROUP_IDS: '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_STAFF_GROUPS'))?>'
                        };
                        // Добавим общую подсказку к заголовку блока шаблонов
                        var templatesTitle = '<?=CUtil::JSEscape(strip_tags(Loc::getMessage('USH_TG_SECTION_TEMPLATES')))?>';
                        var headers = document.querySelectorAll('tr.heading td, tr td.adm-detail-content-cell-l');
                        var templatesHeader = Array.prototype.find.call(headers, function(td){
                            var t = (td.innerText || td.textContent || '').trim();
                            return t.indexOf(templatesTitle) !== -1;
                        });
                        if (templatesHeader) {
                            var iconT = document.createElement('span');
                            iconT.className = 'ui-hint';
                            iconT.setAttribute('data-hint', '<?=CUtil::JSEscape(Loc::getMessage('USH_TG_HINT_TEMPLATES'))?>');
                            iconT.style.marginLeft = '6px';
                            templatesHeader.appendChild(iconT);
                        }

                        Object.keys(hints).forEach(function(name){
                            var input = document.querySelector('[name="'+name+'"]');
                            if (!input) return;
                            var row = input.closest('tr');
                            if (!row) return;
                            var label = row.querySelector('td.adm-detail-content-cell-l');
                            if (!label) return;
                            var icon = document.createElement('span');
                            icon.className = 'ui-hint';
                            icon.setAttribute('data-hint', hints[name]);
                            icon.style.marginLeft = '6px';
                            label.appendChild(icon);
                        });
                        BX.UI.Hint.init(document.body);
                    });
                </script>
                <div class="adm-info-message-wrap" style="margin-top: 12px;">
                    <div class="adm-info-message">
                        <b><?=Loc::getMessage('USH_TG_HELP_BOT_TITLE')?></b><br/>
                        <div style="margin-top:6px; line-height:1.5;">
                            <?=Loc::getMessage('USH_TG_HELP_BOT_TEXT')?>
                        </div>
                    </div>
                    <div class="adm-info-message" style="margin-top: 8px;">
                        <b><?=Loc::getMessage('USH_TG_HELP_WEBHOOK_TITLE')?></b><br/>
                        <div style="margin-top:6px; line-height:1.5;">
                            <?=Loc::getMessage('USH_TG_HELP_WEBHOOK_TEXT')?>
                        </div>
                    </div>
                    
                    <div class="adm-info-message" style="margin-top: 8px;">
                        <b><?=Loc::getMessage('USH_TG_HELP_STAFF_DOUBLE_TITLE')?></b><br/>
                        <div style="margin-top:6px; line-height:1.5;">
                            <?=Loc::getMessage('USH_TG_HELP_STAFF_DOUBLE_TEXT')?>
                        </div>
                    </div>

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
                            echo '<div>'.Loc::getMessage('USH_TG_WEBHOOK_INFO_URL').': '.htmlspecialcharsbx($url).'</div>';
                            echo '<div>'.Loc::getMessage('USH_TG_WEBHOOK_INFO_PENDING').': '.$pending.'</div>';
                            if ($lastErr !== '') {
                                echo '<div>'.Loc::getMessage('USH_TG_WEBHOOK_INFO_LAST_ERROR').': '.htmlspecialcharsbx($lastErr).'</div>';
                            }
                        }
                        ?>
                        <style>
                            .ush-tg-btn { cursor: pointer; transition: transform .02s ease, filter .1s ease; }
                            .ush-tg-btn:hover { filter: brightness(1.05); }
                            .ush-tg-btn:active { transform: translateY(1px); filter: brightness(0.95); }
                            .ush-tg-btn:disabled { cursor: default; opacity: .6; filter: none; transform: none; }
                        </style>
                        <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="hidden" name="ACTION" id="tg-action" value="">
                            <button type="submit" class="adm-btn-save ush-tg-btn" onclick="document.getElementById('tg-action').value='SET_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_SET_WEBHOOK')?></button>
                            <button type="submit" class="adm-btn ush-tg-btn" onclick="document.getElementById('tg-action').value='DELETE_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_DELETE_WEBHOOK')?></button>
                            <button type="submit" class="adm-btn ush-tg-btn" onclick="document.getElementById('tg-action').value='INFO_WEBHOOK'"><?=Loc::getMessage('USH_TG_BTN_INFO_WEBHOOK')?></button>

                        </div>
                        <?php
                        $botUsername = trim((string) Option::get($module_id, 'BOT_USERNAME', ''));
                        global $USER;
                        if ($botUsername !== '' && is_object($USER) && method_exists($USER, 'GetID')) {
                            $botLink = 'https://t.me/'.rawurlencode($botUsername);
                            // Ссылка на бота
                            echo '<div style="margin-top:8px">'.Loc::getMessage('USH_TG_BOT_LINK').': ';
                            echo '<a target="_blank" href="'.htmlspecialcharsbx($botLink).'">'.htmlspecialcharsbx($botLink)."</a></div>";
                            // Диплинк используем только для получения payload, но не показываем

                            // В админке SITE_ID может быть равен языку (например, 'ru'). Возьмём корректный siteId из контекста/дефолтного сайта
                            $siteIdCtx = (string) \Bitrix\Main\Context::getCurrent()->getSite();
                            if ($siteIdCtx === '' || strlen($siteIdCtx) > 2) {
                                try {
                                    $row = \Bitrix\Main\SiteTable::getList([
                                        'filter' => ['=ACTIVE' => 'Y'],
                                        'order'  => ['DEF' => 'DESC', 'SORT' => 'ASC'],
                                        'select' => ['LID'],
                                        'limit'  => 1,
                                    ])->fetch();
                                    if ($row && !empty($row['LID'])) { $siteIdCtx = (string) $row['LID']; }
                                } catch (\Throwable $e) { /* ignore */ }
                                if ($siteIdCtx === '' && defined('SITE_ID') && strlen((string)SITE_ID) <= 2) { $siteIdCtx = (string) SITE_ID; }
                            }
                            $deep = \Ushakov\Telegram\Service\WebhookRegistrar::buildDeepLink($botUsername, $siteIdCtx, (int)$USER->GetID());

                            $payload = '';
                            $parsed = parse_url($deep);
                            if (!empty($parsed['query'])) {
                                parse_str($parsed['query'], $q);
                                if (!empty($q['start'])) { $payload = (string)$q['start']; }
                            }
                            if ($payload !== '') {
                                $cmd = '/start ' . $payload;
                                $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
                                $personalUrl = ($host !== '' ? 'https://'.$host : '') . '/personal/orders/';
                                echo '<div style="margin-top:8px;max-width:760px">';

                                echo '<div style="margin-bottom:6px">'.Loc::getMessage('USH_TG_BIND_HELP_TITLE').'</div>';
                                $cmdEsc = htmlspecialcharsbx($cmd);
                                echo '<div><code id="ush-tg-copy-cmd" style="cursor:pointer" title="'.htmlspecialcharsbx(Loc::getMessage('USH_TG_COPY')).'">'.$cmdEsc.'</code> ';
                                echo '<span style="font-size:12px;color:#666">'.Loc::getMessage('USH_TG_BIND_COPY_HINT').'</span></div>';
                                $personalLink = '<a target="_blank" href="'.htmlspecialcharsbx($personalUrl).'">'.htmlspecialcharsbx($personalUrl).'</a>';
                                echo '<div style="margin-top:6px">'.str_replace('#URL#', $personalLink, Loc::getMessage('USH_TG_BIND_PERSONAL_HINT')).'</div>';

                                echo '<script>(function(){var el=document.getElementById("ush-tg-copy-cmd");if(!el)return;el.addEventListener("click",function(){var t=el.textContent||el.innerText;try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t);}else{var ta=document.createElement("textarea");ta.value=t;ta.style.position="fixed";ta.style.left="-1000px";ta.style.top="-1000px";document.body.appendChild(ta);ta.focus();ta.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);}el.style.background="#e6ffed";setTimeout(function(){el.style.background="";},600);}catch(e){}});})();</script>';
                                echo '</div>';
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
    ?>
    <button type="submit" class="adm-btn" title="<?=htmlspecialcharsbx(Loc::getMessage('USH_TG_BTN_DEFAULTS_HINT'))?>" onclick="var a=document.getElementById('tg-action'); if(a){a.value='RESET_TEMPLATES_DEFAULTS';}"><?=Loc::getMessage('USH_TG_BTN_DEFAULTS')?></button>
    <?php
    echo bitrix_sessid_post();
    ?>
</form>
<?php $tabControl->End(); ?>
