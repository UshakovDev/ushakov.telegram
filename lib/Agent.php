<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

class Agent
{
	private static function logSafe(string $message): void
	{
		try {
			$moduleId = 'ushakov.telegram';
			if (Option::get($moduleId, 'ENABLE_LOGS', 'Y') !== 'Y') { return; }
			$root = Application::getDocumentRoot() ?: (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
			$uploadPath = $root !== '' ? rtrim($root, '/').'/upload/ushakov_tg_agents.log' : '';
			if ($uploadPath === '') { return; }
			$line = date('c') . ' ' . $message . "\n";
			@file_put_contents($uploadPath, $line, FILE_APPEND);
		} catch (\Throwable $e) {
			// ignore
		}
	}

	/**
	 * Обрабатывает очередь. Вставьте сюда интеграцию с вашей ORM-очередью,
	 * например Queue::dequeue() -> Sender::send(...)
	 */
	public static function process(): string
	{
		try {
			self::logSafe('Agent::process tick');
			$moduleId = 'ushakov.telegram';
			$useQueue = Option::get($moduleId, 'USE_QUEUE', 'Y') === 'Y';
			if (!$useQueue) {
				self::logSafe('Agent::process queue disabled');
				return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::process() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::process();')"; // Очередь не используется — просто перезапланируемся
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

			self::logSafe('Agent::process done');
			return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::process() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::process();')"; // запускать и дальше
		} catch (\Throwable $e) {
			self::logSafe('Agent::process error: ' . $e->getMessage());
			return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::process() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::process();')";
		}
	}

	/**
	 * Периодическая сверка ролей staff/customer по текущему членству в группах.
	 */
	public static function reconcileRoles(): string
	{
		try {
			self::logSafe('Agent::reconcileRoles tick');
			$moduleId = 'ushakov.telegram';
			$groupsOpt = (string) Option::get($moduleId, 'STAFF_GROUP_IDS', '1');
			$staffGroupIds = array_filter(array_map('intval', array_map('trim', explode(',', $groupsOpt))));
			if (empty($staffGroupIds)) {
				self::logSafe('Agent::reconcileRoles no staff groups');
				return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::reconcileRoles() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::reconcileRoles();')";
			}

			$conn = Application::getConnection();
			$sqlHelper = $conn->getSqlHelper();
			$table = 'b_ushakov_tg_bindings';
			if (!$conn->isTableExists($table)) {
				self::logSafe('Agent::reconcileRoles no table');
				return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::reconcileRoles() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::reconcileRoles();')";
			}

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

			self::logSafe('Agent::reconcileRoles done');
			return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::reconcileRoles() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::reconcileRoles();')";
		} catch (\Throwable $e) {
			self::logSafe('Agent::reconcileRoles error: ' . $e->getMessage());
			return "(\\Bitrix\\Main\\Loader::includeModule('ushakov.telegram') ? \\\Ushakov\\Telegram\\Agent::reconcileRoles() : '\\\\\\\\Ushakov\\\\Telegram\\\\Agent::reconcileRoles();')";
		}
	}
}
