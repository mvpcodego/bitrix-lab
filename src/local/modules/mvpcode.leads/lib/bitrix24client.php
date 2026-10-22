<?php
namespace Mvpcode\Leads;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

/** Минимальный клиент REST Битрикс24 через входящий вебхук (https://<портал>/rest/<user>/<key>/). */
class Bitrix24Client
{
    public function __construct(private string $webhookUrl)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/') . '/';
    }

    public static function fromOptions(): ?self
    {
        $url = trim(Option::get('mvpcode.leads', 'b24_webhook', ''));
        return $url !== '' ? new self($url) : null;
    }

    /** @return array результат метода; бросает RuntimeException с текстом ошибки портала */
    public function call(string $method, array $params): array
    {
        $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 20]);
        $http->setHeader('Content-Type', 'application/json');
        $body = $http->post($this->webhookUrl . $method . '.json', Json::encode($params));
        $status = $http->getStatus();
        if ($body === false || $status === 0) {
            throw new \RuntimeException('Портал не ответил: ' . implode('; ', $http->getError()));
        }
        $data = Json::decode($body);
        if ($status >= 400 || isset($data['error'])) {
            throw new \RuntimeException(($data['error'] ?? 'HTTP ' . $status) . ': ' . ($data['error_description'] ?? ''));
        }
        return $data;
    }

    public function addDeal(array $lead): int
    {
        $title = 'Заказ с сайта №' . ($lead['ORDER_ID'] ?: $lead['ID']) . ($lead['NAME'] ? ' — ' . $lead['NAME'] : '');
        $result = $this->call('crm.deal.add', [
            'fields' => [
                'TITLE' => $title,
                'OPPORTUNITY' => (float)$lead['AMOUNT'],
                'CURRENCY_ID' => 'RUB',
                'SOURCE_ID' => 'WEB',
                'SOURCE_DESCRIPTION' => 'Интернет-магазин' . ($lead['UTM_SOURCE'] ? ', utm_source=' . $lead['UTM_SOURCE'] : ''),
                'UTM_SOURCE' => $lead['UTM_SOURCE'] ?: '',
                'COMMENTS' => trim("Телефон: {$lead['PHONE']}\nEmail: {$lead['EMAIL']}"),
            ],
        ]);
        return (int)$result['result'];
    }
}
