<?php

if (!function_exists('renderMainMenuItems')) {
    function renderMainMenuItems(string $homeHref, string $pagesPrefix = ''): void
    {
        $items = [
            ['Main', $homeHref],
            ['View', $pagesPrefix . 'view.php'],
            ['Edit', $pagesPrefix . 'edit.php'],
            ['Import dat', $pagesPrefix . 'insert1.php'],
            ['Manuální editace (A.1)', $pagesPrefix . 'result-counting.php'],
            ['Zkoušení (A.2)', $pagesPrefix . 'zkouseni.php'],
            ['Rozdělit cvičení', $pagesPrefix . 'rozdat_cviceni.php'],
            ['Přehled učitelé', $pagesPrefix . 'overview-ucitele.php'],
            ['Nepokrytá výuka', $pagesPrefix . 'nepokryte-predmety.php'],
            ['Nastavení', $pagesPrefix . 'settings.php'],
        ];

        foreach ($items as [$label, $href]) {
            echo '<li><a href="'
                . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</a></li>' . PHP_EOL;
        }
    }
}
