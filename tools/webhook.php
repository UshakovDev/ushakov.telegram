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
// Если секрет ещё не записан (например, после пересборки), но пришёл в query — примем и сохраним
if ($secretKeep === '' && (string)$secretQuery !== '') {
    $secretKeep = (string)$secretQuery;
    Option::set($MODULE_ID, 'WEBHOOK_SECRET', $secretKeep);
}
$token       = (string) Option::get($MODULE_ID, 'BOT_TOKEN', '');

// Вебхук должен быть доступен даже без BOT_TOKEN (только без исходящих сообщений)
// Отвечаем 503 только если секрет отсутствует и в опциях, и в query
if (!$secretKeep && !(string)$secretQuery) {
    http_response_code(503);
    echo 'module_not_configured';
    exit;
}

if (!hash_equals($secretKeep, (string)$secretQuery)) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($hdr && !hash_equals($hdr, $secretKeep)) {
    http_response_code(403);
    echo 'bad_secret_header';
    exit;
}

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

$text   = trim((string)($message['text'] ?? ''));
$chatId = (int)($message['chat']['id'] ?? 0);
$from   = $message['from'] ?? [];

if ($chatId <= 0) {
    http_response_code(200);
    echo 'ok';
    exit;
}

// Поддержать варианты: 
// 1) "/start <payload>" (с пробелом)
// 2) "/start<payload>" (без пробела)
// 3) просто "<payload>" отдельным сообщением
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

    // lazy create table
    /** @var \Bitrix\Main\DB\Connection $conn */
    $conn = Application::getConnection();
    $sqlHelper = $conn->getSqlHelper();
    $table = 'b_ushakov_tg_bindings';
    if (!$conn->isTableExists($table)) {
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                ID INT(11) NOT NULL AUTO_INCREMENT,
                SITE_ID CHAR(2) NOT NULL,
                USER_ID INT(11) NOT NULL DEFAULT 0,
                CHAT_ID BIGINT NOT NULL,
                TG_USERNAME VARCHAR(255) NULL,
                CONSENT CHAR(1) NOT NULL DEFAULT 'Y',
                ROLE VARCHAR(16) NULL,
                IS_STAFF TINYINT(1) NOT NULL DEFAULT 0,
                LAST_USED_AT DATETIME NULL,
                CREATED_AT DATETIME NOT NULL,
                PRIMARY KEY (ID),
                UNIQUE KEY u_site_user (SITE_ID, USER_ID),
                KEY i_chat (CHAT_ID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->queryExecute($sql);
    } else {
        // Ленивая миграция недостающих колонок
        $col = $conn->query("SHOW COLUMNS FROM {$table} LIKE 'ROLE'")->fetch();
        if (!$col) { $conn->queryExecute("ALTER TABLE {$table} ADD COLUMN ROLE VARCHAR(16) NULL AFTER CONSENT"); }
        $col = $conn->query("SHOW COLUMNS FROM {$table} LIKE 'IS_STAFF'")->fetch();
        if (!$col) { $conn->queryExecute("ALTER TABLE {$table} ADD COLUMN IS_STAFF TINYINT(1) NOT NULL DEFAULT 0 AFTER ROLE"); }
        $col = $conn->query("SHOW COLUMNS FROM {$table} LIKE 'LAST_USED_AT'")->fetch();
        if (!$col) { $conn->queryExecute("ALTER TABLE {$table} ADD COLUMN LAST_USED_AT DATETIME NULL AFTER IS_STAFF"); }
    }

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

        $sqlIns = "INSERT INTO {$table} (SITE_ID, USER_ID, CHAT_ID, TG_USERNAME, CONSENT, ROLE, IS_STAFF, LAST_USED_AT, CREATED_AT)
            VALUES ('{$siteIdSql}', {$userIdInt}, {$chatId}, '{$tgUsernameSql}', 'Y', '{$role}', {$isStaff}, NOW(), NOW())
            ON DUPLICATE KEY UPDATE CHAT_ID = VALUES(CHAT_ID), TG_USERNAME = VALUES(TG_USERNAME), CONSENT='Y', ROLE=VALUES(ROLE), IS_STAFF=VALUES(IS_STAFF), LAST_USED_AT=VALUES(LAST_USED_AT)";
        $conn->queryExecute($sqlIns);

        if ($token !== '') {
            $q = http_build_query([
                'chat_id' => $chatId,
                'text' => "✅ Привязка выполнена. Вы будете получать уведомления о заказах.",
            ]);
            @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage?{$q}");
        }
    } else {
        if ($token !== '') {
            $q = http_build_query([
                'chat_id' => $chatId,
                'text' => "Не удалось подтвердить привязку. Вернитесь на сайт и нажмите «Привязать Telegram» ещё раз.",
            ]);
            @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage?{$q}");
        }
    }
}

http_response_code(200);
echo 'ok';
