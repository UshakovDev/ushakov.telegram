<?php
namespace Ushakov\Telegram\Service;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Config\Option;

final class WebhookRegistrar
{
    private const MODULE_ID = 'ushakov.telegram';

    public static function ensureSecrets(): array
    {
        $secret = (string) Option::get(self::MODULE_ID, 'WEBHOOK_SECRET', '');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(16));
            Option::set(self::MODULE_ID, 'WEBHOOK_SECRET', $secret);
        }
        $signKey = (string) Option::get(self::MODULE_ID, 'SIGN_KEY', '');
        if ($signKey === '') {
            $signKey = bin2hex(random_bytes(32));
            Option::set(self::MODULE_ID, 'SIGN_KEY', $signKey);
        }
        return [$secret, $signKey];
    }

    public static function setWebhook(string $host): array
    {
        [$secret] = self::ensureSecrets();
        $token = trim((string) Option::get(self::MODULE_ID, 'BOT_TOKEN', ''));

        if ($token === '') {
            return ['ok' => false, 'error' => 'BOT_TOKEN is empty'];
        }

        $url = "https://{$host}/bitrix/tools/ushakov.telegram/webhook.php?secret={$secret}";

        $http = new HttpClient();
        $http->setTimeout(10);
        $res = $http->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message']),
        ]);

        $json = @json_decode($res, true) ?: [];
        return ['ok' => (bool)($json['ok'] ?? false), 'response' => $json, 'webhook' => $url];
    }

    public static function deleteWebhook(): array
    {
        $token = trim((string) Option::get(self::MODULE_ID, 'BOT_TOKEN', ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'BOT_TOKEN is empty'];
        }
        $http = new HttpClient();
        $http->setTimeout(10);
        $res = $http->get("https://api.telegram.org/bot{$token}/deleteWebhook");
        $json = @json_decode($res, true) ?: [];
        return ['ok' => (bool)($json['ok'] ?? false), 'response' => $json];
    }

    public static function getWebhookInfo(): array
    {
        $token = trim((string) Option::get(self::MODULE_ID, 'BOT_TOKEN', ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'BOT_TOKEN is empty'];
        }
        $http = new HttpClient();
        $http->setTimeout(10);
        $res = $http->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
        $json = @json_decode($res, true) ?: [];
        return ['ok' => (bool)($json['ok'] ?? false), 'response' => $json];
    }

    public static function buildDeepLink(string $botUsername, string $siteId, int $userId): string
    {
        $signKey = (string) Option::get(self::MODULE_ID, 'SIGN_KEY', '');
        $sign = hash_hmac('sha256', $siteId.'|'.$userId, $signKey);
        return "https://t.me/{$botUsername}?start={$siteId}-{$userId}-{$sign}";
    }
}
