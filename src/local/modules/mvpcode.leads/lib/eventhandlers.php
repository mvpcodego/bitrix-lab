<?php
namespace Mvpcode\Leads;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Sale\Order;

class EventHandlers
{
    /**
     * sale:OnSaleOrderSaved — новый заказ становится заявкой.
     * Обработчик не должен ронять оформление заказа: любые ошибки пишем в лог и выходим.
     */
    public static function onSaleOrderSaved(Event $event): EventResult
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');
        $isNew = (bool)$event->getParameter('IS_NEW');
        if (!$isNew || !$order instanceof Order) {
            return new EventResult(EventResult::SUCCESS);
        }

        try {
            $props = $order->getPropertyCollection();
            $name = $props->getPayerName() ? (string)$props->getPayerName()->getValue() : '';
            $phone = $props->getPhone() ? (string)$props->getPhone()->getValue() : '';
            $email = $props->getUserEmail() ? (string)$props->getUserEmail()->getValue() : '';

            LeadTable::add([
                'SOURCE' => 'order',
                'EXTERNAL_KEY' => 'order:' . $order->getId(),
                'ORDER_ID' => $order->getId(),
                'NAME' => $name,
                'PHONE' => Phone::normalize($phone),
                'EMAIL' => $email,
                'AMOUNT' => $order->getPrice(),
                'UTM_SOURCE' => Utm::fromCookie(),
            ]);
        } catch (SqlQueryException $e) {
            // повторное событие по тому же заказу упирается в уникальный EXTERNAL_KEY — это не ошибка
            if (stripos($e->getMessage(), 'Duplicate') === false) {
                \CEventLog::Log('ERROR', 'MVPCODE_LEAD_ADD', 'mvpcode.leads', $order->getId(), $e->getMessage());
            }
        } catch (\Throwable $e) {
            \CEventLog::Log('ERROR', 'MVPCODE_LEAD_ADD', 'mvpcode.leads', $order->getId(), $e->getMessage());
        }

        return new EventResult(EventResult::SUCCESS);
    }
}
