<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Mvpcode\Leads\Agent;
use Mvpcode\Leads\LeadTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);
Loader::includeModule('mvpcode.leads');
Loader::includeModule('currency');

$right = $APPLICATION->GetGroupRight('mvpcode.leads');
if ($right < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$tableId = 'tbl_mvpcode_leads';
$sort = new CAdminSorting($tableId, 'ID', 'desc');
$list = new CAdminList($tableId, $sort);

$filterStatus = (string)($_GET['find_status'] ?? '');

// групповые действия: вернуть в очередь (после исправления вебхука) и отправить сейчас
if ($right >= 'W' && check_bitrix_sessid()) {
    if (($ids = $list->GroupAction()) && ($_REQUEST['action'] ?? '') === 'retry') {
        foreach ($ids as $id) {
            LeadTable::update((int)$id, ['STATUS' => LeadTable::STATUS_NEW, 'ATTEMPTS' => 0, 'ERROR' => null]);
        }
    }
    if (($_GET['send_now'] ?? '') === 'Y') {
        Agent::sendToBitrix24();
        LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID);
    }
}

$by = in_array($sort->getField(), ['ID', 'CREATED_AT', 'STATUS', 'AMOUNT'], true) ? $sort->getField() : 'ID';
$order = strtoupper($sort->getOrder()) === 'ASC' ? 'ASC' : 'DESC';
$filter = $filterStatus !== '' ? ['=STATUS' => $filterStatus] : [];

$rows = LeadTable::getList(['filter' => $filter, 'order' => [$by => $order], 'limit' => 200]);
$rsData = new CAdminResult($rows, $tableId);
$rsData->NavStart(50);
$list->NavText($rsData->GetNavPrint(Loc::getMessage('MVPCODE_LEADS_NAV')));

$list->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'CREATED_AT', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_DATE'), 'sort' => 'CREATED_AT', 'default' => true],
    ['id' => 'ORDER_ID', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_ORDER'), 'default' => true],
    ['id' => 'NAME', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_NAME'), 'default' => true],
    ['id' => 'PHONE', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_PHONE'), 'default' => true],
    ['id' => 'AMOUNT', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_AMOUNT'), 'sort' => 'AMOUNT', 'default' => true],
    ['id' => 'UTM_SOURCE', 'content' => 'utm_source', 'default' => true],
    ['id' => 'STATUS', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_STATUS'), 'sort' => 'STATUS', 'default' => true],
    ['id' => 'B24_DEAL_ID', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_DEAL'), 'default' => true],
    ['id' => 'ERROR', 'content' => Loc::getMessage('MVPCODE_LEADS_COL_ERROR'), 'default' => true],
]);

$statusText = [
    LeadTable::STATUS_NEW => Loc::getMessage('MVPCODE_LEADS_STATUS_NEW'),
    LeadTable::STATUS_SENT => Loc::getMessage('MVPCODE_LEADS_STATUS_SENT'),
    LeadTable::STATUS_ERROR => Loc::getMessage('MVPCODE_LEADS_STATUS_ERROR'),
];

while ($lead = $rsData->Fetch()) {
    $row = $list->AddRow($lead['ID'], $lead);
    $row->AddViewField('CREATED_AT', $lead['CREATED_AT'] instanceof \Bitrix\Main\Type\DateTime ? $lead['CREATED_AT']->toString() : '');
    $row->AddViewField('ORDER_ID', $lead['ORDER_ID']
        ? '<a href="sale_order_view.php?ID=' . (int)$lead['ORDER_ID'] . '&lang=' . LANGUAGE_ID . '">' . (int)$lead['ORDER_ID'] . '</a>' : '');
    $row->AddViewField('AMOUNT', CCurrencyLang::CurrencyFormat((float)$lead['AMOUNT'], 'RUB'));
    $row->AddViewField('STATUS', htmlspecialcharsbx($statusText[$lead['STATUS']] ?? $lead['STATUS']) . ($lead['ATTEMPTS'] ? ' (' . (int)$lead['ATTEMPTS'] . ')' : ''));
    $row->AddViewField('ERROR', htmlspecialcharsbx(mb_substr((string)$lead['ERROR'], 0, 120)));
}

if ($right >= 'W') {
    $list->AddGroupActionTable(['retry' => Loc::getMessage('MVPCODE_LEADS_ACTION_RETRY')]);
    $list->AddAdminContextMenu([[
        'TEXT' => Loc::getMessage('MVPCODE_LEADS_SEND_NOW'),
        'LINK' => $APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&send_now=Y&' . bitrix_sessid_get(),
        'ICON' => 'btn_new',
    ]]);
}
$list->CheckListMode();

$APPLICATION->SetTitle(Loc::getMessage('MVPCODE_LEADS_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<form method="get" action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <?= Loc::getMessage('MVPCODE_LEADS_COL_STATUS') ?>:
    <select name="find_status">
        <option value=""><?= Loc::getMessage('MVPCODE_LEADS_ALL') ?></option>
        <?php foreach ($statusText as $code => $text): ?>
            <option value="<?= $code ?>"<?= $filterStatus === $code ? ' selected' : '' ?>><?= htmlspecialcharsbx($text) ?></option>
        <?php endforeach ?>
    </select>
    <input type="submit" value="<?= Loc::getMessage('MVPCODE_LEADS_FILTER') ?>">
</form>
<?php
$list->DisplayList();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
