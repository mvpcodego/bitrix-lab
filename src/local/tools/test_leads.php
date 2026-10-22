<?php
// Сквозная проверка модуля mvpcode.leads: php local/tools/test_leads.php
require __DIR__ . '/cli.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Mvpcode\Leads\{Agent, LeadTable, Phone};

Loader::includeModule('sale'); Loader::includeModule('catalog'); Loader::includeModule('mvpcode.leads');
$ok = 0; $fail = 0;
function check(string $name, bool $cond, string $extra = '') { global $ok, $fail; $cond ? $ok++ : $fail++; echo ($cond ? 'OK  ' : 'FAIL'), " $name", $extra !== '' ? " — $extra" : '', "\n"; }

// 1. нормализация телефона
check('телефон 8 (900) 000-45-67', Phone::normalize('8 (900) 000-45-67') === '79000004567');
check('телефон +7 900 0004567', Phone::normalize('+7 900 0004567') === '79000004567');
check('телефон 9000004567', Phone::normalize('9000004567') === '79000004567');

// 2. заказ → заявка с UTM
$_COOKIE['MVPCODE_UTM_SOURCE'] = 'yandex_direct';
$product = \Bitrix\Catalog\ProductTable::getList(['filter' => ['>QUANTITY' => 0, '=AVAILABLE' => 'Y'], 'limit' => 1])->fetch();
$order = Sale\Order::create(SITE_ID ?: 's1', 1);
$order->setPersonTypeId(1);
$basket = Sale\Basket::create(SITE_ID ?: 's1');
$item = $basket->createItem('catalog', $product['ID']);
$item->setFields(['QUANTITY' => 1, 'CURRENCY' => 'RUB', 'LID' => SITE_ID ?: 's1', 'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\Basket::getDefaultProviderName()]);
$order->setBasket($basket);
$props = $order->getPropertyCollection();
if ($p = $props->getPayerName()) $p->setValue('Тест Покупатель');
if ($p = $props->getPhone()) $p->setValue('8 (900) 000-11-22');
if ($p = $props->getUserEmail()) $p->setValue('test@example.com');
$res = $order->save();
check('заказ сохранён', $res->isSuccess(), implode('; ', $res->getErrorMessages()));
$orderId = $order->getId();
$lead = LeadTable::getList(['filter' => ['=ORDER_ID' => $orderId]])->fetch();
check('заявка создана по событию OnSaleOrderSaved', (bool)$lead);
check('UTM записан', ($lead['UTM_SOURCE'] ?? '') === 'yandex_direct', (string)($lead['UTM_SOURCE'] ?? ''));
check('телефон нормализован', ($lead['PHONE'] ?? '') === '79000001122', (string)($lead['PHONE'] ?? ''));
check('сумма = сумме заказа', abs(($lead['AMOUNT'] ?? 0) - $order->getPrice()) < 0.01);

// 3. идемпотентность: повторное событие по тому же заказу не создаёт дубль
\Mvpcode\Leads\EventHandlers::onSaleOrderSaved(new \Bitrix\Main\Event('sale', 'OnSaleOrderSaved', ['ENTITY' => $order, 'IS_NEW' => true]));
$cnt = LeadTable::getCount(['=ORDER_ID' => $orderId]);
check('повторное событие не создало дубль', $cnt === 1, "заявок: $cnt");

// 4. сбой портала: заявка остаётся в очереди, попытка засчитана
Option::set('mvpcode.leads', 'b24_webhook', 'http://nginx/local/tools/mock_b24.php?fail=1&x=');
$before = (int)LeadTable::getByPrimary($lead['ID'])->fetch()['ATTEMPTS'];
Agent::sendToBitrix24();
$after = LeadTable::getByPrimary($lead['ID'])->fetch();
check('при сбое заявка осталась в очереди', $after['STATUS'] === LeadTable::STATUS_NEW);
check('попытка засчитана, ошибка сохранена', (int)$after['ATTEMPTS'] === $before + 1 && str_contains((string)$after['ERROR'], 'QUERY_LIMIT'), (string)$after['ERROR']);

// 5. портал работает: заявка уходит сделкой
Option::set('mvpcode.leads', 'b24_webhook', 'http://nginx/local/tools/mock_b24.php?x=');
Agent::sendToBitrix24();
$sent = LeadTable::getByPrimary($lead['ID'])->fetch();
check('заявка отправлена', $sent['STATUS'] === LeadTable::STATUS_SENT && $sent['B24_DEAL_ID'] > 0, 'сделка ' . $sent['B24_DEAL_ID']);

Option::set('mvpcode.leads', 'b24_webhook', '');
echo "\nИтог: $ok OK, $fail FAIL\n";
exit($fail ? 1 : 0);
