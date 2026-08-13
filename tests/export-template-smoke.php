<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$template = __DIR__ . '/../excel/template.xlsx';
$output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atpu-template-smoke.xlsx';
$spreadsheet = IOFactory::load($template);
$main = $spreadsheet->getSheetByName('Hlavni');
$helper = $spreadsheet->getSheetByName('Pomocný');

if (!$main || !$helper) {
    throw new RuntimeException('V šabloně chybí očekávané listy.');
}

$helper->setCellValue('C7', 3.0);
$helper->setCellValue('C12', 0.3);
$main->setCellValue('B11', 'c');
$main->setCellValue('C11', 14);
$main->setCellValue('E11', 2);
$main->setCellValue('I11', 0.5);
$main->setCellValue('M11', "=IF(B11=\"c\",E11*C11*I11*'Pomocný'!\$C\$7,0)");
$main->setCellValue('B27', 10);
$main->setCellValue('F27', "=B27*'Pomocný'!\$C\$12");

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->setPreCalculateFormulas(true);
$writer->save($output);
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

$check = IOFactory::load($output);
$main = $check->getSheetByName('Hlavni');
$direct = (float)$main->getCell('M11')->getCalculatedValue();
$exam = (float)$main->getCell('F27')->getCalculatedValue();

if (abs($direct - 42.0) > 0.0001 || abs($exam - 3.0) > 0.0001) {
    throw new RuntimeException("Výpočet šablony nesouhlasí: A.1=$direct, A.2=$exam");
}

$check->disconnectWorksheets();
@unlink($output);
echo "export-template: OK\n";

