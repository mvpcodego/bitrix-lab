<?php
require __DIR__ . '/cli.php';
\Bitrix\Main\Loader::includeModule('iblock'); \Bitrix\Main\Loader::includeModule('catalog');
$r = CIBlock::GetList([], []);
while ($ib = $r->Fetch()) {
    $cnt = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib['ID']], []);
    $sc = CIBlockSection::GetCount(['IBLOCK_ID' => $ib['ID']]);
    $cat = CCatalog::GetByID($ib['ID']);
    echo $ib['ID'], ' | ', $ib['IBLOCK_TYPE_ID'], ' | ', $ib['CODE'], ' | ', $ib['NAME'], " | el=$cnt sec=$sc", $cat ? ' | catalog' . ($cat['PRODUCT_IBLOCK_ID'] ? ' offers of ' . $cat['PRODUCT_IBLOCK_ID'] : '') : '', "\n";
}
