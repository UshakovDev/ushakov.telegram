<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Config\Option;

class Agent
{
    /**
     * Обрабатывает очередь. Вставьте сюда интеграцию с вашей ORM-очередью,
     * например Queue::dequeue() -> Sender::send(...)
     */
    public static function process()
    {
        $moduleId = 'ushakov.telegram';
        $useQueue = Option::get($moduleId, 'USE_QUEUE', 'Y') === 'Y';
        if (!$useQueue) {
            return __METHOD__ . '();'; // Очередь не используется — просто перезапланируемся
        }

        // Пример псевдологики (замените на свою QueueTable/Queue класс)
        // while ($item = Queue::dequeueReady()) {
        //     try {
        //         Sender::send($item['token'], $item['chatIds'], $item['text']);
        //         Queue::markDone($item['id']);
        //     } catch (\Throwable $e) {
        //         Queue::markRetry($item['id'], $e->getMessage());
        //     }
        // }

        return __METHOD__ . '();'; // запускать и дальше
    }
}
