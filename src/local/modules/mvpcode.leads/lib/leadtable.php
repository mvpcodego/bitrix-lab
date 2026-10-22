<?php
namespace Mvpcode\Leads;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

/**
 * Заявки с сайта: заказ из интернет-магазина или форма. Каждая заявка потом уходит в Битрикс24 агентом.
 * STATUS: new — ещё не отправлена, sent — сделка создана, error — последняя попытка упала (см. ERROR).
 */
class LeadTable extends DataManager
{
    public const STATUS_NEW = 'new';
    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    public static function getTableName(): string
    {
        return 'b_mvpcode_lead';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\StringField('SOURCE'))->configureRequired()->configureSize(32),
            // ключ идемпотентности: один заказ — одна заявка, даже если событие пришло дважды
            (new Fields\StringField('EXTERNAL_KEY'))->configureRequired()->configureUnique()->configureSize(64),
            (new Fields\IntegerField('ORDER_ID'))->configureNullable(),
            (new Fields\StringField('NAME'))->configureSize(255),
            (new Fields\StringField('PHONE'))->configureSize(32),
            (new Fields\StringField('EMAIL'))->configureSize(255),
            (new Fields\FloatField('AMOUNT'))->configureDefaultValue(0),
            (new Fields\StringField('UTM_SOURCE'))->configureSize(128),
            (new Fields\StringField('STATUS'))->configureRequired()->configureDefaultValue(self::STATUS_NEW)->configureSize(16),
            (new Fields\IntegerField('ATTEMPTS'))->configureDefaultValue(0),
            (new Fields\IntegerField('B24_DEAL_ID'))->configureNullable(),
            (new Fields\TextField('ERROR'))->configureNullable(),
        ];
    }
}
