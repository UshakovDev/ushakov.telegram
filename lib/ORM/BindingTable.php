<?php
namespace Ushakov\Telegram\ORM;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

Loc::loadMessages(__FILE__);

class BindingTable extends DataManager
{
    public static function getTableName()
    {
        return 'b_ushakov_tg_bindings';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('SITE_ID', [
                'required' => true,
                'validation' => function(){ return [function($v){ return is_string($v) && strlen($v) <= 2 ? true : 'SITE_ID'; }]; }
            ]),
            new IntegerField('USER_ID', [
                'required' => true,
                'default_value' => 0,
            ]),
            // chat_id может быть 64-bit или отрицательным — храним строкой
            new StringField('CHAT_ID', [
                'required' => true,
            ]),
            new StringField('TG_USERNAME', []),
            new StringField('CONSENT', [
                'default_value' => 'Y',
            ]),
            new StringField('ROLE', []),
            new IntegerField('IS_STAFF', [
                'default_value' => 0,
            ]),
            new DatetimeField('LAST_USED_AT'),
            new DatetimeField('CREATED_AT', [
                'required' => true,
                'default_value' => function(){ return new DateTime(); },
            ]),
        ];
    }
}


