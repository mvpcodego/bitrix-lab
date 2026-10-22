<?php
// Оформление демо-магазина под автоэлектронику (поверх шаблона eshop_bootstrap_v4): php local/tools/apply_theme.php
require __DIR__ . '/cli.php';

$root = $_SERVER['DOCUMENT_ROOT'];
$data = __DIR__ . '/data';

copy("$data/logo.png", "$root/include/logo.png");
copy("$data/logo_retina.png", "$root/include/logo_retina.png");
copy("$data/logo.png", "$root/include/logo_mobile.png");
copy("$data/logo_retina.png", "$root/include/logo_mobile_retina.png");
copy("$data/logo_footer.png", "$root/include/logo_footer.png");
copy("$data/logo_footer_retina.png", "$root/include/logo_footer_retina.png");
file_put_contents("$root/include/company_logo_footer.php", '<img src="/include/logo_footer.png" srcset="/include/logo_footer_retina.png 2x" alt="Автоэлектроника" />');
$footerFile = "$root/bitrix/templates/eshop_bootstrap_v4/footer.php";
file_put_contents($footerFile, str_replace('include/company_logo_mobile.php', 'include/company_logo_footer.php', file_get_contents($footerFile)));
@mkdir("$root/upload/mvpcode", 0775, true);
copy("$data/hero.png", "$root/upload/mvpcode/hero.png");

file_put_contents("$root/include/company_name.php", 'Автоэлектроника');
file_put_contents("$root/include/copyright.php", '© Автоэлектроника с установкой — демо-магазин MVP Code, ' . date('Y'));
file_put_contents("$root/include/about.php", <<<'HTML'
<div class="mb-5">
	<h3>Почему мы?</h3>
	<ul>
		<li>Подбираем комплект под вашу машину: магнитола, рамка, проводка</li>
		<li>Устанавливаем в своём центре, обычно за один день</li>
		<li>Гарантия на товар и на работу</li>
		<li>Сохраняем штатные функции: руль, камеру, климат</li>
		<li>Оплата после проверки установки</li>
	</ul>
</div>
HTML);

// главная: баннер с одеждой → свой блок, заголовки
$index = file_get_contents("$root/index.php");
$index = preg_replace('/\$APPLICATION->SetTitle\([^;]*\);/u', '$APPLICATION->SetTitle("Автоэлектроника с установкой");', $index, 1);
$hero = <<<'HTML'
<div class="mb-5 p-4 rounded" style="background:#f3f5f9;display:flex;gap:28px;align-items:center;flex-wrap:wrap">
	<img src="/upload/mvpcode/hero.png" alt="Магнитола на Android в салоне" style="width:260px;max-width:100%;border-radius:18px">
	<div style="flex:1;min-width:240px">
		<h2 class="mb-3">Магнитолы на Android с установкой под вашу машину</h2>
		<p class="mb-4">Подберём комплект с рамкой и проводкой, сохраним штатные кнопки на руле и камеру, установим за день.</p>
		<a href="/catalog/" class="btn btn-primary">Перейти в каталог</a>
	</div>
</div>
HTML;
$index = preg_replace('/<\?if \(IsModuleInstalled\("advertising"\)\):\?>.*?<\?endif\?>/su', $hero, $index, 1);
$index = str_replace('<h2>Тренды сезона</h2>', '<h2>Популярные магнитолы</h2>', $index);
file_put_contents("$root/index.php", $index);

// полоса «Маркетплейс» из шаблона
$headerFile = "$root/bitrix/templates/eshop_bootstrap_v4/header.php";
$header = file_get_contents($headerFile);
$header = preg_replace('/<\?\$APPLICATION->IncludeComponent\(\s*"bitrix:eshop\.banner".*?\);\?>/su', '', $header, 1);
file_put_contents($headerFile, $header);

// новости: вместо модной одежды — три последние установки из демо-данных
\Bitrix\Main\Loader::includeModule('iblock');
$news = CIBlock::GetList([], ['CODE' => 'news'])->Fetch();
if ($news) {
    $el = new CIBlockElement();
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => $news['ID'], '!XML_ID' => 'mvpcode:%'], false, false, ['ID']);
    while ($x = $r->Fetch()) { $el->Update($x['ID'], ['ACTIVE' => 'N']); }
    $demo = json_decode(file_get_contents("$data/demo-data.json"), true);
    $inst = array_values(array_filter($demo['installations'], fn($i) => !empty($i['published'])));
    usort($inst, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    $imgs = ['install-b.png', 'install-c.png', 'install-d.png'];
    foreach (array_slice($inst, 0, 3) as $k => $i) {
        $xml = 'mvpcode:install-' . $i['id'];
        $fields = [
            'IBLOCK_ID' => $news['ID'], 'XML_ID' => $xml, 'ACTIVE' => 'Y', 'CODE' => 'install-' . $i['id'],
            'NAME' => "{$i['car_make']} {$i['car_model']} {$i['year']}: установили {$i['head_unit']}",
            'ACTIVE_FROM' => \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($i['created_at'])),
            'PREVIEW_TEXT' => $i['description'], 'DETAIL_TEXT' => $i['description'] . " Город: {$i['city']}.",
            'PREVIEW_PICTURE' => CFile::MakeFileArray("$data/img/{$imgs[$k]}"),
            'DETAIL_PICTURE' => CFile::MakeFileArray("$data/img/{$imgs[$k]}"),
        ];
        $ex = CIBlockElement::GetList([], ['IBLOCK_ID' => $news['ID'], 'XML_ID' => $xml], false, false, ['ID'])->Fetch();
        $okNews = $ex ? $el->Update($ex['ID'], $fields) : $el->Add($fields);
        if (!$okNews) echo 'news error: ', $el->LAST_ERROR, "\n";
    }
    (new CIBlock())->Update($news['ID'], ['NAME' => 'Наши установки']);
}

// название сайта (заголовки страниц «…от магазина Одежда+»)
(new CSite())->Update('s1', ['NAME' => 'Автоэлектроника', 'SITE_NAME' => 'Автоэлектроника']);
\Bitrix\Main\Config\Option::set('main', 'site_name', 'Автоэлектроника');
$cat = "$root/catalog/index.php";
if (is_file($cat)) file_put_contents($cat, str_replace(['Одежда+', 'Одежда'], 'Автоэлектроника', file_get_contents($cat)));

// тексты демо про одежду: мета главной, «О магазине», блок о компании
$aboutText = 'Магнитолы, камеры, акустика и сигнализации с установкой. Подбираем комплект под ваш автомобиль: головное устройство, переходную рамку и проводку, чтобы всё встало без доработок.';
file_put_contents("$root/include/company_about.php", $aboutText);
$section = file_get_contents("$root/.section.php");
$section = preg_replace("/'description' => '[^']*'/u", "'description' => 'Автоэлектроника с установкой: магнитолы, камеры, акустика, сигнализации. Подбор по автомобилю.'", $section);
$section = preg_replace("/'keywords' => '[^']*'/u", "'keywords' => 'магнитола андроид, камера заднего вида, установка магнитолы, автоакустика'", $section);
file_put_contents("$root/.section.php", $section);
$aboutPage = file_get_contents("$root/about/index.php");
$aboutPage = preg_replace('#<p>Мы рады приветствовать.*?одежды в городе и области\. </p>#su', <<<'HTML'
<p>Демо-магазин автоэлектроники на 1С-Битрикс, собранный MVP Code для показа интеграций.</p>

	<p>В каталоге магнитолы, камеры, акустика, сигнализации, переходные рамки и проводка. Магнитолы продаются комплектом: к головному устройству подбираются рамка и проводка под конкретный автомобиль.</p>

	<p>Каждый заказ с сайта попадает в журнал заявок модуля mvpcode.leads вместе с источником рекламы (utm_source) и уходит сделкой в Битрикс24. Если портал недоступен, заявка ждёт в очереди и отправляется повторно.</p>
HTML, $aboutPage);
file_put_contents("$root/about/index.php", $aboutPage);

// SEO-шаблоны каталога (наследуемые свойства) — там «от магазина Одежда+» в title, description и alt картинок
$seo = [
    'SECTION_META_TITLE' => '{=this.Name} — купить с установкой в Москве',
    'SECTION_META_DESCRIPTION' => '{=this.Name} в магазине «Автоэлектроника»: подбор по автомобилю, установка в день обращения, гарантия.',
    'ELEMENT_META_TITLE' => '{=this.Name} — купить с установкой',
    'ELEMENT_META_DESCRIPTION' => '{=this.Name}: цена, характеристики, совместимость. Установим в день обращения.',
    'ELEMENT_PREVIEW_PICTURE_FILE_ALT' => '{=this.Name}',
    'ELEMENT_PREVIEW_PICTURE_FILE_TITLE' => '{=this.Name}',
    'ELEMENT_DETAIL_PICTURE_FILE_ALT' => '{=this.Name}',
    'ELEMENT_DETAIL_PICTURE_FILE_TITLE' => '{=this.Name}',
];
(new \Bitrix\Iblock\InheritedProperty\IblockTemplates(2))->set($seo);
// у старых разделов одежды свои шаблоны перекрывали инфоблочные — убираем их
$conn = \Bitrix\Main\Application::getConnection();
$conn->queryExecute("DELETE FROM b_iblock_iproperty WHERE IBLOCK_ID=2 AND ENTITY_TYPE='S' AND TEMPLATE LIKE '%Одежда%'");
$conn->queryExecute("TRUNCATE TABLE b_iblock_element_iprop");
$conn->queryExecute("TRUNCATE TABLE b_iblock_section_iprop");

// защита статистики от частых запросов: все заходы с Mac идут с одного адреса докер-шлюза,
// и после пары быстрых проверок стенд отвечает 503 всем, включая браузер. На локальном стенде выключаем
\Bitrix\Main\Config\Option::set('statistic', 'DEFENCE_ON', 'N');

// рекламные баннеры демо (полоса «Маркетплейс» и слайдер одежды) — выключаем
\Bitrix\Main\Application::getConnection()->queryExecute("UPDATE b_adv_banner SET ACTIVE='N'");

BXClearCache(true);
\Bitrix\Main\Application::getInstance()->getManagedCache()->cleanAll();
echo "theme applied\n";
