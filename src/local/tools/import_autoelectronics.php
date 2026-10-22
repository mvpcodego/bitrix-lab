<?php
// Демо-каталог автоэлектроники вместо одежды: php local/tools/import_autoelectronics.php
// Данные — синтетические из собственного демо (data/demo-data.json), картинки — свои (data/img).
// Повторный запуск обновляет товары по XML_ID, одежда только деактивируется (можно вернуть).
require __DIR__ . '/cli.php';

use Bitrix\Main\Loader;
use Bitrix\Catalog\Model\Price;
use Bitrix\Catalog\Model\Product;

Loader::includeModule('iblock'); Loader::includeModule('catalog');

const IBLOCK_ID = 2;
const PREFIX = 'mvpcode:';

$sections = [
    'head_unit' => ['Магнитолы', 14],
    'camera' => ['Камеры', 8],
    'audio' => ['Акустика', 6],
    'alarm' => ['Сигнализации', 6],
    'frame' => ['Переходные рамки', 6],
    'wiring' => ['Проводка и адаптеры', 4],
    'adapter' => ['Проводка и адаптеры', 3],
    'soundproofing' => ['Шумоизоляция', 4],
    'tuning' => ['Тюнинг салона', 4],
    'accessory' => ['Аксессуары', 5],
];

$data = json_decode(file_get_contents(__DIR__ . '/data/demo-data.json'), true);
$imgDir = __DIR__ . '/data/img';
$baseGroup = \Bitrix\Catalog\GroupTable::getList(['filter' => ['=BASE' => 'Y']])->fetch()['ID'];

function enumId(string $code): ?int {
    $p = CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code])->Fetch();
    if (!$p) return null;
    $e = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => $p['ID']])->Fetch();
    return $e ? (int)$e['ID'] : null;
}
$trend = enumId('TREND');
$newProduct = enumId('NEWPRODUCT');
$saleLeader = enumId('SALELEADER');

function describe(array $p): string {
    $s = $p['specs'] ?? [];
    $parts = [];
    switch ($p['type']) {
        case 'head_unit':
            $parts[] = "Магнитола на Android с экраном {$s['screen']}\"";
            $parts[] = "{$s['cores']} ядер, {$s['ram']}/{$s['rom']} ГБ";
            if (!empty($s['dsp'])) $parts[] = 'встроенный звуковой процессор';
            if (!empty($s['gps'])) $parts[] = 'GPS';
            if (!empty($s['lte'])) $parts[] = '4G-модем';
            if (!empty($s['wifi'])) $parts[] = 'Wi‑Fi';
            $parts[] = 'линейка «' . ($s['tier'] ?? '') . '»';
            break;
        case 'frame': $parts[] = "Переходная рамка под экран {$s['screen']}\", цвет — {$s['color']}"; break;
        case 'wiring': $parts[] = "Комплект проводки, {$s['pins']} пинов" . (!empty($s['canbus']) ? ', с CAN-шиной' : ''); break;
        case 'camera': $parts[] = isset($s['channels']) ? "Система на {$s['channels']} камеры" : 'Камера ' . ($s['resolution'] ?? ''); break;
        case 'audio': $parts[] = isset($s['size']) ? "Акустика {$s['size']}" . (isset($s['rms']) ? ", {$s['rms']} Вт RMS" : '') : 'Автозвук'; break;
        default: $parts[] = $p['title'];
    }
    return implode(', ', array_filter($parts)) . '. Подбираем совместимость с вашей машиной и устанавливаем в нашем центре.';
}

// 1. одежда — в архив (деактивация, не удаление)
$off = 0;
$r = CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'ACTIVE' => 'Y', '!XML_ID' => PREFIX . '%'], false, false, ['ID']);
$el = new CIBlockElement();
while ($x = $r->Fetch()) { $el->Update($x['ID'], ['ACTIVE' => 'N']); $off++; }
$rs = CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'ACTIVE' => 'Y', '!XML_ID' => PREFIX . '%'], false, ['ID']);
$sec = new CIBlockSection();
while ($x = $rs->Fetch()) { $sec->Update($x['ID'], ['ACTIVE' => 'N']); }

// 2. разделы
$sectionIds = [];
$sort = 100;
foreach (array_unique(array_column($sections, 0)) as $name) {
    $xml = PREFIX . 'sec:' . md5($name);
    $code = CUtil::translit($name, 'ru', ['replace_space' => '-', 'replace_other' => '-']);
    $ex = CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'XML_ID' => $xml], false, ['ID'])->Fetch();
    $f = ['IBLOCK_ID' => IBLOCK_ID, 'NAME' => $name, 'CODE' => $code, 'XML_ID' => $xml, 'ACTIVE' => 'Y', 'SORT' => $sort += 10];
    $sectionIds[$name] = $ex ? ($sec->Update($ex['ID'], $f) ? $ex['ID'] : $ex['ID']) : $sec->Add($f);
    if (!$sectionIds[$name]) { echo 'section error: ', $sec->LAST_ERROR, "\n"; exit(1); }
}

// 3. товары
$byType = [];
foreach ($data['parts'] as $p) {
    if (!empty($p['archived']) || !isset($sections[$p['type']])) continue;
    $byType[$p['type']][] = $p;
}
$added = $updated = 0; $trendLeft = 12;
foreach ($sections as $type => [$secName, $limit]) {
    $items = $byType[$type] ?? [];
    // разнообразие картинок: сначала по одной позиции на картинку
    usort($items, fn($a, $b) => strcmp($a['image_url'], $b['image_url']) ?: $a['id'] <=> $b['id']);
    $seen = []; $pick = [];
    foreach ($items as $p) { if (!isset($seen[$p['image_url']])) { $seen[$p['image_url']] = 1; $pick[] = $p; } }
    foreach ($items as $p) { if (count($pick) >= $limit) break; if (!in_array($p, $pick, true)) $pick[] = $p; }
    $pick = array_slice($pick, 0, $limit);

    foreach ($pick as $i => $p) {
        $xml = PREFIX . $p['sku'];
        $img = $imgDir . '/' . basename($p['image_url'], '.svg') . '.png';
        $props = [];
        if ($trend && $type === 'head_unit' && $trendLeft-- > 0) $props['TREND'] = $trend;
        if ($newProduct && $i === 0) $props['NEWPRODUCT'] = $newProduct;
        if ($saleLeader && $i === 1) $props['SALELEADER'] = $saleLeader;
        $fields = [
            'IBLOCK_ID' => IBLOCK_ID,
            'IBLOCK_SECTION_ID' => $sectionIds[$secName],
            'NAME' => $p['title'],
            'CODE' => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $p['sku'])),
            'XML_ID' => $xml,
            'ACTIVE' => 'Y',
            'SORT' => 500 - $i,
            'PREVIEW_TEXT' => describe($p),
            'DETAIL_TEXT' => describe($p),
            'PREVIEW_PICTURE' => CFile::MakeFileArray($img),
            'DETAIL_PICTURE' => CFile::MakeFileArray($img),
            'PROPERTY_VALUES' => $props,
        ];
        $ex = CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'XML_ID' => $xml], false, false, ['ID'])->Fetch();
        if ($ex) {
            unset($fields['PROPERTY_VALUES']);
            $el->Update($ex['ID'], $fields);
            CIBlockElement::SetPropertyValuesEx($ex['ID'], IBLOCK_ID, $props + ['TREND' => false, 'NEWPRODUCT' => false, 'SALELEADER' => false]);
            $id = (int)$ex['ID']; $updated++;
        } else {
            $id = (int)$el->Add($fields);
            if (!$id) { echo 'element error: ', $p['sku'], ' ', $el->LAST_ERROR, "\n"; continue; }
            $added++;
        }
        $qty = max((int)$p['stock'], 3); // демо: в наличии всё, чтобы заказ проходил
        $prod = ['ID' => $id, 'QUANTITY' => $qty, 'TYPE' => \Bitrix\Catalog\ProductTable::TYPE_PRODUCT, 'AVAILABLE' => 'Y'];
        Product::getList(['filter' => ['=ID' => $id]])->fetch() ? Product::update($id, $prod) : Product::add($prod);
        $price = Price::getList(['filter' => ['=PRODUCT_ID' => $id, '=CATALOG_GROUP_ID' => $baseGroup]])->fetch();
        $pf = ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => $baseGroup, 'PRICE' => (float)$p['price_retail'], 'CURRENCY' => 'RUB'];
        $price ? Price::update($price['ID'], $pf) : Price::add($pf);
    }
}

(new CIBlock())->Update(IBLOCK_ID, ['NAME' => 'Автоэлектроника']);
BXClearCache(true);
\Bitrix\Main\Application::getInstance()->getManagedCache()->cleanAll();
echo "одежда в архиве: $off, товаров добавлено: $added, обновлено: $updated, разделов: ", count($sectionIds), "\n";
