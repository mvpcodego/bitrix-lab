<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
$moduleId = 'mvpcode.leads';
if ($APPLICATION->GetGroupRight($moduleId) < 'W') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && isset($_POST['save'])) {
    $url = trim((string)$_POST['b24_webhook']);
    // принимаем только https-вебхук вида https://<портал>/rest/<id>/<ключ>/
    if ($url === '' || preg_match('~^https://[^/\s]+/rest/\d+/[A-Za-z0-9]+/?$~', $url)) {
        Option::set($moduleId, 'b24_webhook', $url);
        CAdminMessage::ShowNote(Loc::getMessage('MVPCODE_LEADS_OPT_SAVED'));
    } else {
        CAdminMessage::ShowMessage(Loc::getMessage('MVPCODE_LEADS_OPT_BAD_URL'));
    }
}

$tabControl = new CAdminTabControl('mvpcode_leads_tabs', [[
    'DIV' => 'main', 'TAB' => Loc::getMessage('MVPCODE_LEADS_OPT_TAB'), 'TITLE' => Loc::getMessage('MVPCODE_LEADS_OPT_TAB'),
]]);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('MVPCODE_LEADS_OPT_WEBHOOK') ?></td>
        <td width="60%"><input type="text" size="70" name="b24_webhook"
            value="<?= htmlspecialcharsbx(Option::get($moduleId, 'b24_webhook', '')) ?>"
            placeholder="https://portal.bitrix24.ru/rest/1/xxxxxxxx/"></td>
    </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('MVPCODE_LEADS_OPT_SAVE') ?>" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
