<?php
namespace Mvpcode\Leads;

use Bitrix\Main\Loader;

class Agent
{
    private const MAX_ATTEMPTS = 5;
    private const BATCH = 20;

    /**
     * Отправляет новые заявки в Битрикс24 сделками. Запускается агентом раз в 5 минут.
     * Сбой портала не теряет заявку: она остаётся new с увеличенным ATTEMPTS, после 5 попыток — error.
     */
    public static function sendToBitrix24(): string
    {
        $self = '\\' . __METHOD__ . '();';
        if (!Loader::includeModule('mvpcode.leads')) {
            return $self;
        }
        $client = Bitrix24Client::fromOptions();
        if ($client === null) {
            return $self; // вебхук не настроен — заявки копятся и уйдут, когда его укажут
        }

        $leads = LeadTable::getList([
            'filter' => ['=STATUS' => LeadTable::STATUS_NEW, '<ATTEMPTS' => self::MAX_ATTEMPTS],
            'order' => ['ID' => 'ASC'],
            'limit' => self::BATCH,
        ])->fetchAll();

        foreach ($leads as $lead) {
            try {
                $dealId = $client->addDeal($lead);
                LeadTable::update($lead['ID'], [
                    'STATUS' => LeadTable::STATUS_SENT,
                    'B24_DEAL_ID' => $dealId,
                    'ATTEMPTS' => $lead['ATTEMPTS'] + 1,
                    'ERROR' => null,
                ]);
            } catch (\Throwable $e) {
                $attempts = $lead['ATTEMPTS'] + 1;
                LeadTable::update($lead['ID'], [
                    'ATTEMPTS' => $attempts,
                    'STATUS' => $attempts >= self::MAX_ATTEMPTS ? LeadTable::STATUS_ERROR : LeadTable::STATUS_NEW,
                    'ERROR' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            }
        }

        return $self;
    }
}
