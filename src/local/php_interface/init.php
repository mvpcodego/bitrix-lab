<?php
use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;

// фирменный стиль витрины поверх шаблона eshop_bootstrap_v4: сам шаблон не правим, чтобы он обновлялся штатно
EventManager::getInstance()->addEventHandler('main', 'OnBeforeProlog', function () {
    if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
        return;
    }
    Asset::getInstance()->addCss('/local/templates/.default/mvp-theme.css');
});
