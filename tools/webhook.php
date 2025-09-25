<?php
// /local/modules/ushakov.telegram/tools/webhook.php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('STOP_STATISTICS', true);

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

$MODULE_ID = 'ushakov.telegram';

Loader::includeModule($MODULE_ID);

$secretQuery = $_GET['secret'] ?? '';
$secretKeep  = (string) Option::get($MODULE_ID, 'WEBHOOK_SECRET', '');
$token       = (string) Option::get($MODULE_ID, 'BOT_TOKEN', '');

// Вебхук должен быть доступен даже без BOT_TOKEN (только без исходящих сообщений)
// Если в опциях пусто — попробуем прочитать напрямую из b_option (на случай кеша)
if ($secretKeep === '') {
    try {
        $conn = Application::getConnection();
        $row = $conn->query("SELECT VALUE FROM b_option WHERE MODULE_ID='".$conn->getSqlHelper()->forSql($MODULE_ID)."' AND NAME='WEBHOOK_SECRET' ORDER BY SITE_ID IS NOT NULL, SITE_ID LIMIT 1")->fetch();
        if ($row && isset($row['VALUE']) && (string)$row['VALUE'] !== '') {
            $secretKeep = (string)$row['VALUE'];
            // Попробуем записать обратно в опции, чтобы последующие вызовы шли через Option::get
            Option::set($MODULE_ID, 'WEBHOOK_SECRET', $secretKeep);
        }
    } catch (\Throwable $e) {
        // ignore
    }
}
// 503 если секрет всё ещё отсутствует
if ($secretKeep === '') {
    http_response_code(503);
    echo 'module_not_configured';
    exit;
}

$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
// Проверяем секрет: либо заголовок, либо query параметр должны совпасть
$okSecret = false;
if ($hdr && hash_equals($hdr, $secretKeep)) { $okSecret = true; }
if (!$okSecret && (string)$secretQuery !== '' && hash_equals((string)$secretQuery, $secretKeep)) { $okSecret = true; }
if (!$okSecret) { http_response_code(403); echo 'forbidden'; exit; }

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(200);
    echo 'ok';
    exit;
}

try {
    $update = Json::decode($raw);
} catch (\Throwable $e) {
    http_response_code(200);
    echo 'ok';
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    http_response_code(200);
    echo 'ok';
    exit;
}

$text     = trim((string)($message['text'] ?? ''));
$chatIdRaw= trim((string)($message['chat']['id'] ?? ''));
$from   = $message['from'] ?? [];

if ($chatIdRaw === '' ) {
    http_response_code(200);
    echo 'ok';
    exit;
}

// Поддержать варианты: 
// 1) "/start <payload>" (с пробелом)
// 2) "/start<payload>" (без пробела)
// 3) просто "<payload>" отдельным сообщением
// Отписка: /stop или /unlink — поддержка вариантов /stop, /stop@Bot, 
// с любыми пробелами/аргументами после команды
if (preg_match('~^/(stop|unlink)(?:@[A-Za-z0-9_]+)?(?:\s+|=|$)~ui', $text)) {
    try {
        /** @var \Bitrix\Main\DB\Connection $conn */
        $conn = Application::getConnection();
        if ($conn && $conn->isTableExists('b_ushakov_tg_bindings')) {
            $sqlHelper = $conn->getSqlHelper();
            $chatIdSql = $sqlHelper->forSql($chatIdRaw);
            $conn->queryExecute("DELETE FROM b_ushakov_tg_bindings WHERE CHAT_ID='".$chatIdSql."'");
        }
    } catch (\Throwable $e) { /* ignore */ }
    if ($token !== '') {

        \Ushakov\Telegram\Sender::send($token, [(string)$chatIdRaw], "Вы отписались от уведомлений. Чтобы подписаться снова - свяжите Telegram в личном кабинете.");
    }
    http_response_code(200);
    echo 'ok';
    exit;
}

if (mb_stripos($text, '/start') === 0 || mb_stripos($text, '/link') === 0 || preg_match('~^[a-zA-Z]{2}-\d+-[a-f0-9]{32,}+$~', trim($text))) {
    $payload = '';
    if (mb_stripos($text, '/start') === 0) {
        // поддержка "/start <p>", "/start=<p>", "/start<p>"
        if (preg_match('~^/start(?:\s+|=)?(.+)$~ui', $text, $m)) {
            $payload = trim($m[1]);
        }
    } elseif (mb_stripos($text, '/link') === 0) {
        if (preg_match('~^/link(?:\s+|=)?(.+)$~ui', $text, $m)) {
            $payload = trim($m[1]);
        }
    } else {
        $payload = trim($text);
    }
    // убрать случайный ведущий '=' если клиент прислал "/start=..."
    if ($payload !== '' && $payload[0] === '=') { $payload = ltrim($payload, '='); }

    $signKey = (string) Option::get($MODULE_ID, 'SIGN_KEY', '');
    $ok = false; $siteId = ''; $userId = '0';
    if ($payload && $signKey) {
        [$siteId, $userId, $sign] = array_pad(explode('-', $payload, 3), 3, '');
        $calc = hash_hmac('sha256', $siteId.'|'.$userId, $signKey);
        $ok = hash_equals($calc, (string)$sign);
    }


    // Таблица создаётся/мигрируется в InstallDB. Здесь — только доступ к данным.

    /** @var \Bitrix\Main\DB\Connection $conn */
    $conn = Application::getConnection();
    $sqlHelper = $conn->getSqlHelper();
    $table = 'b_ushakov_tg_bindings';

    $tgUsername = (string)($from['username'] ?? '');
    if ($ok) {
        $siteIdSql = $sqlHelper->forSql($siteId);
        $userIdInt = (int)$userId;
        // Определение роли (staff/customer) по группам пользователя
        $isStaff = 0; $role = 'customer';
        $groupsOpt = (string) Option::get($MODULE_ID, 'STAFF_GROUP_IDS', '');
        $staffGroupIds = array_filter(array_map('intval', array_map('trim', explode(',', $groupsOpt))));
        if (!empty($staffGroupIds) && $userIdInt > 0 && class_exists('CUser')) {
            $userGroups = \CUser::GetUserGroup($userIdInt);
            if (array_intersect($staffGroupIds, array_map('intval', (array)$userGroups))) {
                $isStaff = 1; $role = 'staff';
            }
        }
        $tgUsernameSql = $sqlHelper->forSql($tgUsername);

        $chatIdSql     = $sqlHelper->forSql($chatIdRaw);

        // В проде таблица создаётся установщиком; если её нет — пропускаем запись, чтобы не ронять вебхук
        try {
            if ($conn && $conn->isTableExists($table)) {
                $sqlIns = "INSERT INTO {$table} (SITE_ID, USER_ID, CHAT_ID, TG_USERNAME, CONSENT, ROLE, IS_STAFF, LAST_USED_AT, CREATED_AT)
                    VALUES ('{$siteIdSql}', {$userIdInt}, '{$chatIdSql}', '{$tgUsernameSql}', 'Y', '{$role}', {$isStaff}, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE CHAT_ID = VALUES(CHAT_ID), TG_USERNAME = VALUES(TG_USERNAME), CONSENT='Y', ROLE=VALUES(ROLE), IS_STAFF=VALUES(IS_STAFF), LAST_USED_AT=VALUES(LAST_USED_AT)";
                $conn->queryExecute($sqlIns);
            }
        } catch (\Throwable $e) {
            // Не роняем обработку апдейта
        }


        if ($token !== '') { \Ushakov\Telegram\Sender::send($token, [(string)$chatIdRaw], "✅ Привязка выполнена. Вы будете получать уведомления о заказах."); }
    } else {
        if ($token !== '') { \Ushakov\Telegram\Sender::send($token, [(string)$chatIdRaw], "Не удалось подтвердить привязку. Вернитесь на сайт и нажмите «Привязать Telegram» ещё раз."); }
    }
}

http_response_code(200);
echo 'ok';
