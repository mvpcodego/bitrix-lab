<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/** @var array $arResult */
if (empty($arResult)) return;

// плоский список bitrix:menu → дерево первого и второго уровня
$tree = [];
foreach ($arResult as $item) {
    if ($item['DEPTH_LEVEL'] == 1) {
        $tree[] = $item + ['CHILDREN' => []];
    } elseif ($item['DEPTH_LEVEL'] == 2 && $tree) {
        $tree[array_key_last($tree)]['CHILDREN'][] = $item;
    }
}
?>
<nav class="mvp-nav" aria-label="Каталог">
    <ul class="mvp-nav-list">
        <?php foreach ($tree as $item): ?>
            <li class="mvp-nav-item<?= $item['CHILDREN'] ? ' has-children' : '' ?><?= $item['SELECTED'] ? ' is-active' : '' ?>">
                <a class="mvp-nav-link" href="<?= htmlspecialcharsbx($item['LINK']) ?>"><?= htmlspecialcharsbx($item['TEXT']) ?></a>
                <?php if ($item['CHILDREN']): ?>
                    <ul class="mvp-nav-sub">
                        <?php foreach ($item['CHILDREN'] as $child): ?>
                            <li><a href="<?= htmlspecialcharsbx($child['LINK']) ?>"<?= $child['SELECTED'] ? ' class="is-active"' : '' ?>><?= htmlspecialcharsbx($child['TEXT']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
</nav>
