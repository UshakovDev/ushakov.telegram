<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Config\Option;

class Sender
{
    public static function parseChatIds(string $raw): array
    {
        $ids = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($ids));
    }

    public static function send(string $token, array $chatIds, string $text): void
    {
        if (!$token || !$chatIds || !$text) {
            return;
        }
        foreach ($chatIds as $chatId) {
            $params = [
                'chat_id' => $chatId,
                'text'    => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
