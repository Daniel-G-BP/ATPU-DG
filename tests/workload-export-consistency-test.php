<?php
/**
 * workload-export-consistency-test.php
 *
 * Ověřuje, že "web" kalkulátor (calculateTeacherWorkload) a Excel export
 * (exportUvazekDoExcelu) dávají STEJNÉ výsledky pro A.1, A.2 a celkový součet.
 * README tvrdí, že web a XLSX export používají stejný kalkulátor – tenhle test
 * to ověřuje na skutečném exportu, ne jen na sdílených funkcích v PHP.
 *
 * Spouští se UVNITŘ web kontejneru (potřebuje DB připojení na host "db"):
 *
 *   docker compose exec web php tests/workload-export-consistency-test.php
 *   docker compose exec web php tests/workload-export-consistency-test.php 42
 *
 * Bez argumentu otestuje prvních N učitelů aktivní verze (viz $limitTeachers).
 * S argumentem otestuje jen konkrétní teacherId.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/dbh.inc.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/workload-functions.php';
require_once __DIR__ . '/../includes/export-functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$tolerance = 0.02;
$limitTeachers = 15;

$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

if (!$idVerze) {
    fwrite(STDERR, "Není nastavena žádná aktivní verze dat.\n");
    exit(2);
}

if (isset($argv[1]) && is_numeric($argv[1])) {
    $teacherIds = [(int)$argv[1]];
} else {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE IdVerze = ? ORDER BY id LIMIT ?");
    $stmt->bindValue(1, $idVerze, PDO::PARAM_INT);
    $stmt->bindValue(2, $limitTeachers, PDO::PARAM_INT);
    $stmt->execute();
    $teacherIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if ($teacherIds === []) {
    fwrite(STDERR, "Žádní učitelé k otestování.\n");
    exit(2);
}

$failures = 0;
$tested = 0;

foreach ($teacherIds as $teacherId) {
    try {
        $workload = calculateTeacherWorkload($pdo, $teacherId, $idVerze);
    } catch (Throwable $e) {
        echo "SKIP teacher {$teacherId}: {$e->getMessage()}\n";
        continue;
    }

    try {
        $filePath = exportUvazekDoExcelu($teacherId);
    } catch (Throwable $e) {
        echo "FAIL teacher {$teacherId}: export selhal – {$e->getMessage()}\n";
        $failures++;
        continue;
    }

    // Znovu načíst uložený soubor a nechat PhpSpreadsheet dopočítat vzorce.
    $check = IOFactory::load($filePath);
    $main = $check->getSheetByName('Hlavni');
    $helper = $check->getSheetByName('Pomocný');

    if (!$main || !$helper) {
        echo "FAIL teacher {$teacherId}: export neobsahuje listy Hlavni/Pomocný\n";
        $failures++;
        @unlink($filePath);
        continue;
    }

    $a2Row = null;
    for ($row = 1; $row <= $main->getHighestRow(); $row++) {
        if (str_starts_with((string)$main->getCell("A{$row}")->getValue(), 'A.2 PEDAGOGICKÁ')) {
            $a2Row = $row;
            break;
        }
    }

    if ($a2Row === null) {
        echo "FAIL teacher {$teacherId}: sekce A.2 nenalezena v exportu\n";
        $failures++;
        @unlink($filePath);
        continue;
    }

    $exportA1 = (float)$main->getCell('S7')->getCalculatedValue();
    $exportA2 = (float)$main->getCell("S{$a2Row}")->getCalculatedValue();
    $exportTotal = (float)$main->getCell('S5')->getCalculatedValue();

    // Zkontrolovat, že se ve výpočetní oblasti neobjevily chyby vzorců.
    $errorCells = [];
    foreach (['S7', "S{$a2Row}", 'S5', 'T7'] as $cellRef) {
        $val = $main->getCell($cellRef)->getCalculatedValue();
        if (is_string($val) && str_starts_with($val, '#')) {
            $errorCells[] = "{$cellRef}={$val}";
        }
    }

    $rowFailures = [];

    if (abs($exportA1 - $workload['a1']) > $tolerance) {
        $rowFailures[] = sprintf('A.1: web=%.2f export=%.2f', $workload['a1'], $exportA1);
    }
    if (abs($exportA2 - $workload['a2']) > $tolerance) {
        $rowFailures[] = sprintf('A.2: web=%.2f export=%.2f', $workload['a2'], $exportA2);
    }
    if (abs($exportTotal - $workload['total']) > $tolerance) {
        $rowFailures[] = sprintf('Celkem: web=%.2f export=%.2f', $workload['total'], $exportTotal);
    }
    if ($errorCells !== []) {
        $rowFailures[] = 'Chyby vzorců: ' . implode(', ', $errorCells);
    }

    $tested++;

    if ($rowFailures === []) {
        echo "OK   teacher {$teacherId}: A.1={$exportA1} A.2={$exportA2} total={$exportTotal}\n";
    } else {
        echo "FAIL teacher {$teacherId}: " . implode(' | ', $rowFailures) . "\n";
        $failures++;
    }

    $check->disconnectWorksheets();
    unset($check);
    @unlink($filePath);
}

echo "\n{$tested} učitelů otestováno, {$failures} selhání.\n";
exit($failures > 0 ? 1 : 0);
