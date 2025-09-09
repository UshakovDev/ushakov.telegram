<?php
namespace Ushakov\Telegram;

use Bitrix\Main\Loader;

final class AgentRunner
{
    public static function process(): string
    {
        // Очередь отключена в этой версии
        return "\\Ushakov\\Telegram\\AgentRunner::process();";
    }

	public static function reconcileRoles(): string
	{
		try {
			if (Loader::includeModule('ushakov.telegram')) {
				return Agent::reconcileRoles();
			}
		} catch (\Throwable $e) {
			// ignore
		}
		return "\\Ushakov\\Telegram\\AgentRunner::reconcileRoles();";
	}
}


