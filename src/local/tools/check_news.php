<?php
require __DIR__ . '/cli.php';
\Bitrix\Main\Loader::includeModule('iblock');
$r = CIBlockElement::GetList([], ['IBLOCK_ID' => 1], false, false, ['ID','NAME','ACTIVE','XML_ID','ACTIVE_FROM','PREVIEW_PICTURE']);
while ($x = $r->Fetch()) echo $x['ID'],' | ',$x['ACTIVE'],' | ',$x['XML_ID'],' | ',$x['ACTIVE_FROM'],' | ',$x['PREVIEW_PICTURE'],' | ',$x['NAME'],"\n";
$d = json_decode(file_get_contents(__DIR__.'/data/demo-data.json'), true);
echo 'installations published: ', count(array_filter($d['installations'], fn($i)=>!empty($i['published']))), "\n";
