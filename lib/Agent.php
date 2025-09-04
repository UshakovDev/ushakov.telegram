<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

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

	/**
	 * Периодическая сверка ролей staff/customer по текущему членству в группах.
	 */
	public static function reconcileRoles(): string
	{
		$moduleId = 'ushakov.telegram';
		$groupsOpt = (string) Option::get($moduleId, 'STAFF_GROUP_IDS', '1');
		$staffGroupIds = array_filter(array_map('intval', array_map('trim', explode(',', $groupsOpt))));
		if (empty($staffGroupIds)) { return __METHOD__.'();'; }

		$conn = Application::getConnection();
		$sqlHelper = $conn->getSqlHelper();
		$table = 'b_ushakov_tg_bindings';
		if (!$conn->isTableExists($table)) { return __METHOD__.'();'; }

		$rows = $conn->query("SELECT ID, SITE_ID, USER_ID FROM {$table}");
		while ($row = $rows->fetch()) {
			$userId = (int)$row['USER_ID'];
			if ($userId <= 0 || !class_exists('\\CUser')) { continue; }
			$userGroups = \CUser::GetUserGroup($userId);
			$isStaff = (int) (bool) array_intersect($staffGroupIds, array_map('intval', (array)$userGroups));
			$role = $isStaff ? 'staff' : 'customer';
			$conn->queryExecute(
				"UPDATE {$table} SET ROLE='".$sqlHelper->forSql($role)."', IS_STAFF=".$isStaff." WHERE ID=".(int)$row['ID']
			);
		}

		return __METHOD__.'();';
	}
}
