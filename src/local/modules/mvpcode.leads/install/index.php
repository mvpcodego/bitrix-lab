<?php
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Mvpcode\Leads\LeadTable;

Loc::loadMessages(__FILE__);

class mvpcode_leads extends CModule
{
    public $MODULE_ID = 'mvpcode.leads';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = 'MVP Code';
    public $PARTNER_URI = 'https://mvp-code.ru';

    private const AGENT = '\\Mvpcode\\Leads\\Agent::sendToBitrix24();';

    public function __construct()
    {
        $version = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $version['VERSION'];
        $this->MODULE_VERSION_DATE = $version['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MVPCODE_LEADS_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MVPCODE_LEADS_DESCRIPTION');
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();

        \CAgent::AddAgent(self::AGENT, $this->MODULE_ID, 'N', 300);
    }

    public function DoUninstall(): void
    {
        Loader::includeModule($this->MODULE_ID);

        \CAgent::RemoveModuleAgents($this->MODULE_ID);
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    // имена и public — как в CModule (PHP сравнивает имена методов без учёта регистра)
    public function InstallDB()
    {
        $connection = Application::getConnection();
        $entity = LeadTable::getEntity();
        if (!$connection->isTableExists(LeadTable::getTableName())) {
            $entity->createDbTable();
        }
    }

    public function UnInstallDB()
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists(LeadTable::getTableName())) {
            $connection->dropTable(LeadTable::getTableName());
        }
    }

    public function InstallEvents()
    {
        $em = EventManager::getInstance();
        $em->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, \Mvpcode\Leads\EventHandlers::class, 'onSaleOrderSaved');
        $em->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID, \Mvpcode\Leads\Utm::class, 'rememberFromRequest');
    }

    public function UnInstallEvents()
    {
        $em = EventManager::getInstance();
        $em->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, \Mvpcode\Leads\EventHandlers::class, 'onSaleOrderSaved');
        $em->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID, \Mvpcode\Leads\Utm::class, 'rememberFromRequest');
    }

    public function InstallFiles()
    {
        // в /bitrix/admin кладём тонкие обёртки, сама страница живёт в модуле
        CopyDirFiles(__DIR__ . '/admin', Application::getDocumentRoot() . '/bitrix/admin', true, true);
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(__DIR__ . '/admin', Application::getDocumentRoot() . '/bitrix/admin');
    }
}
