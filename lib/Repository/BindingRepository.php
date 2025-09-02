<?php
namespace Ushakov\Telegram\Repository;

use Bitrix\Main\Application;

final class BindingRepository
{
    private const TABLE = 'b_ushakov_tg_bindings';

    public static function getChatId(string $siteId, int $userId): ?int
    {
        $conn = Application::getConnection();
        $res = $conn->query("
            SELECT CHAT_ID FROM ".self::TABLE."
            WHERE SITE_ID = '".$conn->getSqlHelper()->forSql($siteId)."' AND USER_ID = ".(int)$userId."
            LIMIT 1
        ");
        if ($row = $res->fetch()) {
            return (int)$row['CHAT_ID'];
        }
        return null;
    }

    public static function unlink(string $siteId, int $userId): void
    {
        $conn = Application::getConnection();
        $conn->queryExecute("
            DELETE FROM ".self::TABLE."
            WHERE SITE_ID = '".$conn->getSqlHelper()->forSql($siteId)."' AND USER_ID = ".(int)$userId
        );
    }
}
