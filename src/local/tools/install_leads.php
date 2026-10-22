<?php
require __DIR__ . '/cli.php';
use Bitrix\Main\ModuleManager;
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/mvpcode.leads/install/index.php';
$m = new mvpcode_leads();
if (in_array('--reinstall', $argv, true) && ModuleManager::isModuleInstalled('mvpcode.leads')) {
    $m->DoUninstall();
    echo "uninstalled\n";
}
if (!ModuleManager::isModuleInstalled('mvpcode.leads')) {
    $m->DoInstall();
    echo "installed\n";
}
$c = \Bitrix\Main\Application::getConnection();
echo 'table: ', $c->isTableExists('b_mvpcode_lead') ? 'yes' : 'no', "\n";
foreach ([['sale', 'OnSaleOrderSaved'], ['main', 'OnPageStart']] as [$mod, $ev]) {
    $h = array_filter(\Bitrix\Main\EventManager::getInstance()->findEventHandlers($mod, $ev), fn($x) => ($x['TO_MODULE_ID'] ?? '') === 'mvpcode.leads');
    echo "handler $mod:$ev: ", count($h), "\n";
}
$a = \CAgent::GetList([], ['MODULE_ID' => 'mvpcode.leads'])->Fetch();
echo 'agent: ', $a ? $a['NAME'] . ' every ' . $a['AGENT_INTERVAL'] . 's' : 'no', "\n";
