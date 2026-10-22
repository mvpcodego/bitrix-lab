<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('mvpcode.leads') < 'R') {
    return false;
}

return [
    'parent_menu' => 'global_menu_store',
    'section' => 'mvpcode_leads',
    'sort' => 1000,
    'text' => Loc::getMessage('MVPCODE_LEADS_MENU'),
    'title' => Loc::getMessage('MVPCODE_LEADS_MENU'),
    'url' => 'mvpcode_leads_list.php?lang=' . LANGUAGE_ID,
    'icon' => 'sale_menu_icon_orders',
    'items_id' => 'menu_mvpcode_leads',
];
