<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($argc !== 5) {
    fwrite(STDERR, "Usage: php verify-export-workbook.php file.xlsx expectedA1 expectedA2 expectedTotal\n");
    exit(2);
}

[$script, $file, $expectedA1, $expectedA2, $expectedTotal] = $argv;
$book = IOFactory::load($file);
$main = $book->getSheetByName('Hlavni');
$helper = $book->getSheetByName('Pomocný');
if (!$main || !$helper) {
    throw new RuntimeException('Export neobsahuje očekávané listy.');
}

$a2Row = null;
for ($row = 1; $row <= $main->getHighestRow(); $row++) {
    if (str_starts_with((string)$main->getCell("A{$row}")->getValue(), 'A.2 PEDAGOGICKÁ')) {
        $a2Row = $row;
        break;
    }
}
if ($a2Row === null) {
    throw new RuntimeException('V exportu nebyla nalezena sekce A.2.');
}

$actual = [
    'A1' => (float)$main->getCell('S7')->getCalculatedValue(),
    'A2' => (float)$main->getCell("S{$a2Row}")->getCalculatedValue(),
    'total' => (float)$main->getCell('S5')->getCalculatedValue(),
];
$expected = ['A1' => (float)$expectedA1, 'A2' => (float)$expectedA2, 'total' => (float)$expectedTotal];

foreach ($expected as $key => $value) {
    if (abs($value - $actual[$key]) > 0.02) {
        throw new RuntimeException("$key nesouhlasí: očekáváno $value, export {$actual[$key]}");
    }
}

echo json_encode(['status' => 'OK', 'a2Row' => $a2Row, 'values' => $actual], JSON_UNESCAPED_UNICODE) . PHP_EOL;

