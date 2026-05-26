<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/functions.php';

function duplicateRowStyle(Worksheet $sheet, int $sourceRow, int $targetRow, string $fromCol = 'A', string $toCol = 'P'): void
{
    foreach (range($fromCol, $toCol) as $col) {
        $sheet->duplicateStyle(
            $sheet->getStyle($col . $sourceRow),
            $col . $targetRow
        );

        $sheet->setCellValue($col . $targetRow, $sheet->getCell($col . $sourceRow)->getValue());
    }

    $sheet->getRowDimension($targetRow)->setRowHeight(
        $sheet->getRowDimension($sourceRow)->getRowHeight()
    );
}

function exportUvazekDoExcelu(int $teacherId): string
{
    $templatePath = __DIR__ . '/../excel/template.xlsx';

    $pdo = connectToDatabase();
    $idVerze = getAktivniVerze($pdo);

    $spreadsheet = IOFactory::load($templatePath);
    $sheet2 = $spreadsheet->getSheetByName("Pomocný");
    $sheet1 = $spreadsheet->getSheetByName("Hlavni");

    if (!$sheet1 || !$sheet2) {
        throw new Exception('V Excel šabloně chybí list "Hlavni" nebo "Pomocný".');
    }

    $stmt = $pdo->prepare("
        SELECT 
            p.nazev,
            p.nadrazenepracoviste AS fakulta,
            t.name,
            t.surname
        FROM teachers t
        JOIN pracoviste p 
            ON t.idPracoviste = p.idpracoviste
            AND p.IdVerze = t.IdVerze
        WHERE t.id = ?
            AND t.IdVerze = ?
    ");
    $stmt->execute([$teacherId, $idVerze]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception("Učitel s ID $teacherId nebyl nalezen.");
    }

    $name = $teacher['name'] ?? '';
    $surname = $teacher['surname'] ?? '';
    $fileBase = preg_replace('/[^A-Za-z0-9_-]/u', '_', $surname . $name);
    $outputPath = __DIR__ . "/../excel/{$fileBase}-uvazek.xlsx";

    $stmt = $pdo->prepare("
        SELECT r.akademickyrok
        FROM nastaveni n
        JOIN roky r ON n.Hodnota = r.rok
        WHERE n.Nazev = 'AktivniRok'
          AND n.IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idVerze]);
    $rok = $stmt->fetchColumn();

    // BUG FIX: počet týdnů čteme z nastavení (dříve bylo natvrdo 14)
    $stmtTydny = $pdo->prepare("
        SELECT Nazev, Hodnota FROM nastaveni
        WHERE Nazev IN ('PocetTydnuZS', 'PocetTydnuLS') AND IdVerze = ?
    ");
    $stmtTydny->execute([$idVerze]);
    $tydnyNastaveni = $stmtTydny->fetchAll(PDO::FETCH_KEY_PAIR);
    $tydnyZS = (int)($tydnyNastaveni['PocetTydnuZS'] ?? 14);
    $tydnyLS = (int)($tydnyNastaveni['PocetTydnuLS'] ?? 14);

    $fakulta = $teacher['fakulta'] ?? '';

    // Hlavička
    $sheet2->setCellValue("B22", $fakulta);
    $sheet2->setCellValue("B21", $teacher['nazev']);   // ústav
    $sheet2->setCellValue("B19", $teacher['name']);    // jméno
    $sheet2->setCellValue("B20", $teacher['surname']); // příjmení
    $sheet2->setCellValue("B23", $rok);

    // Respektuje globální nastavení ZahrnoutAJ
    $zahrnoutAJ = getZahrnoutAJ($pdo);
    $ajCondition = $zahrnoutAJ ? '' : 'AND upp.jazyk != 2';

    // BUG FIX: dotaz rozšířen o max_pocet_studentu pro výpočet počtu skupin
    $sql = "
        SELECT
            p.zkratka,
            p.nazev,
            p.semestr,
            upp.typ,
            upp.podil,
            upp.jazyk,
            upp.max_pocet_studentu,
            ph.pocetJednotekPrednaska,
            ph.pocetJednotekCviceni,
            ph.pocetJednotekSeminar
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
            ON upp.predmetid = p.id
           AND p.IdVerze = upp.IdVerze
        LEFT JOIN predmet_hodiny ph
            ON p.id = ph.predmetid
        WHERE upp.teacherid = ?
          AND upp.IdVerze = ?
          $ajCondition
        ORDER BY p.zkratka, p.semestr, upp.jazyk, upp.typ
    ";

    $stmtData = $pdo->prepare($sql);
    $stmtData->execute([$teacherId, $idVerze]);
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];

    foreach ($rows as $r) {
        $k = $r['zkratka'] . '_' . ($r['semestr'] ?? '') . '_' . ($r['jazyk'] ?? '');

        if (!isset($grouped[$k])) {
            $grouped[$k] = [
                'zkratka'   => $r['zkratka'],
                'nazev'     => $r['nazev'],
                'semestr'   => $r['semestr'] ?? null,
                'jazyk'     => $r['jazyk'] ?? null,
                'prednaska' => 0,
                'cviceni'   => 0,
                'seminar'   => 0,
                'skupinyP'  => 0, // počet řádků s typ='P' = počet skupin přednášek
                'skupinyC'  => 0, // počet řádků s typ='C' = počet skupin cvičení
                'skupinyS'  => 0, // počet řádků s typ='S' = počet skupin semináře
            ];
        }

        $typ = $r['typ'] ?? '';

        // Hodiny za týden = hodnota z předmět_hodiny (nezávislá na počtu skupin)
        // Skupiny = každý řádek v DB = jedna skupina daného učitele
        if ($typ === 'P') {
            $grouped[$k]['skupinyP']++;
            $grouped[$k]['prednaska'] = (float)($r['pocetJednotekPrednaska'] ?? 0);
        } elseif ($typ === 'C') {
            $grouped[$k]['skupinyC']++;
            $grouped[$k]['cviceni'] = (float)($r['pocetJednotekCviceni'] ?? 0);
        } elseif ($typ === 'S') {
            $grouped[$k]['skupinyS']++;
            $grouped[$k]['seminar'] = (float)($r['pocetJednotekSeminar'] ?? 0);
        }
    }

    // Seřazení pro stabilní výstup
    uasort($grouped, function ($a, $b) {
        return [$a['zkratka'], $a['semestr'], $a['jazyk']] <=> [$b['zkratka'], $b['semestr'], $b['jazyk']];
    });

    // Oblast dat v šabloně
    $startRow = 11;
    $summaryRow = 23;          // první souhrnný řádek, který nesmíme přepsat
    $templateLastDataRow = 22; // poslední vzorový řádek pro data
    $templateDataRows = $summaryRow - $startRow; // 12 řádků: 11–22

    $neededRows = count($grouped);

    if ($neededRows > $templateDataRows) {
        $rowsToInsert = $neededRows - $templateDataRows;

        // vloží nové řádky před souhrnný blok
        $sheet1->insertNewRowBefore($summaryRow, $rowsToInsert);

        // zkopíruje vzhled posledního datového řádku do nových řádků
        for ($i = 0; $i < $rowsToInsert; $i++) {
            $targetRow = $templateLastDataRow + 1 + $i;
            duplicateRowStyle($sheet1, $templateLastDataRow, $targetRow, 'A', 'P');
        }
    }

    // Vyčistit oblast dat, do které budeme zapisovat
    $endWriteRow = max($templateLastDataRow, $startRow + $neededRows - 1);
    for ($row = $startRow; $row <= $endWriteRow; $row++) {
        foreach (range('A', 'P') as $col) {
            $sheet1->setCellValue($col . $row, null);
        }
    }

    // Zápis do listu Hlavni
    $currentRow = $startRow;

    foreach ($grouped as $item) {
        $semestrPredmetu = $item['semestr'] ?? 'ZS';

        // BUG FIX: počet týdnů z DB místo hardcoded 14
        $zs = '';
        $ls = '';
        if ($semestrPredmetu === 'ZS') {
            $zs = $tydnyZS;
        } elseif ($semestrPredmetu === 'LS') {
            $ls = $tydnyLS;
        }

        // Druh: anglická/cizojazyčná výuka
        $druh = '';
        if ((int)$item['jazyk'] === 2) {
            $druh = 'c';
        }

        // Skupiny = počet řádků v DB pro daného učitele a typ výuky
        $skupinyP = $item['skupinyP'];
        $skupinyC = $item['skupinyC'];
        $skupinyS = $item['skupinyS'];

        $sheet1->setCellValue("A{$currentRow}", $item['zkratka']); // kód
        $sheet1->setCellValue("B{$currentRow}", $druh);            // druh
        $sheet1->setCellValue("C{$currentRow}", $zs);              // ZS týdny
        $sheet1->setCellValue("D{$currentRow}", $ls);              // LS týdny

        // Rozvrhové hodiny týdně – nuly jako prázdné buňky
        $sheet1->setCellValue("E{$currentRow}", $item['prednaska'] ?: null);
        $sheet1->setCellValue("F{$currentRow}", $item['cviceni']   ?: null);
        $sheet1->setCellValue("G{$currentRow}", $item['seminar']   ?: null);
        $sheet1->setCellValue("H{$currentRow}", null); // ateliér – vždy prázdné

        // Skupiny – nuly jako prázdné buňky
        $sheet1->setCellValue("I{$currentRow}", $skupinyP ?: null);
        $sheet1->setCellValue("J{$currentRow}", $skupinyC ?: null);
        $sheet1->setCellValue("K{$currentRow}", $skupinyS ?: null);
        $sheet1->setCellValue("L{$currentRow}", null); // ateliér skupiny – vždy prázdné

        // Pracovní body – pro ZS bereme C, pro LS D
        $tydnyCell = ($semestrPredmetu === 'LS') ? "D{$currentRow}" : "C{$currentRow}";

        $sheet1->setCellValue("M{$currentRow}", "=E{$currentRow}*{$tydnyCell}*I{$currentRow}");
        $sheet1->setCellValue("N{$currentRow}", "=F{$currentRow}*{$tydnyCell}*J{$currentRow}");
        $sheet1->setCellValue("O{$currentRow}", "=G{$currentRow}*{$tydnyCell}*K{$currentRow}");
        $sheet1->setCellValue("P{$currentRow}", "=H{$currentRow}*{$tydnyCell}*L{$currentRow}");

        $currentRow++;
    }

    // =========================================================
    // ČÁST A.2 – ZKOUŠENÍ
    // =========================================================

    // Načti data A.2 pro tohoto učitele z zkouseni_prirazeni
    $stmtA2 = $pdo->prepare("
        SELECT
            p.zkratka,
            zp.pocet_kl,
            zp.pocet_zap,
            zp.pocet_zk,
            zp.pocet_dz
        FROM zkouseni_prirazeni zp
        JOIN predmet p ON p.id = zp.predmetid AND p.IdVerze = zp.IdVerze
        WHERE zp.teacherid = ?
          AND zp.IdVerze   = ?
          AND (zp.pocet_kl + zp.pocet_zap + zp.pocet_zk + zp.pocet_dz) > 0
        ORDER BY p.zkratka
    ");
    $stmtA2->execute([$teacherId, $idVerze]);
    $a2Rows = $stmtA2->fetchAll(PDO::FETCH_ASSOC);

    // Počet řádků vložených pro A.1 (posun A.2 sekce)
    $a1RowsInserted = max(0, $neededRows - $templateDataRows);

    // Pozice A.2 datové oblasti v šabloně (27–38 = 12 řádků), posunuté o A.1 overflow
    $a2StartRow        = 27 + $a1RowsInserted;
    $a2TemplateLastRow = 38 + $a1RowsInserted;
    $a2TemplateRows    = 12; // šablona má 12 datových řádků pro A.2
    $a2SummaryRow      = 39 + $a1RowsInserted; // A.3 header

    $a2NeededRows = count($a2Rows);

    if ($a2NeededRows > $a2TemplateRows) {
        $a2ToInsert = $a2NeededRows - $a2TemplateRows;
        $sheet1->insertNewRowBefore($a2SummaryRow, $a2ToInsert);
        for ($i = 0; $i < $a2ToInsert; $i++) {
            $targetRow = $a2TemplateLastRow + 1 + $i;
            duplicateRowStyle($sheet1, $a2TemplateLastRow, $targetRow, 'A', 'I');
        }
    }

    // Vyčistit oblast A.2
    $a2EndWriteRow = max($a2TemplateLastRow, $a2StartRow + $a2NeededRows - 1);
    for ($row = $a2StartRow; $row <= $a2EndWriteRow; $row++) {
        foreach (['A','B','C','D','E'] as $col) {
            $sheet1->setCellValue($col . $row, null);
        }
    }

    // Zápis dat A.2
    $a2CurrentRow = $a2StartRow;
    foreach ($a2Rows as $a2) {
        $sheet1->setCellValue("A{$a2CurrentRow}", $a2['zkratka']);
        $sheet1->setCellValue("B{$a2CurrentRow}", (int)$a2['pocet_kl']  ?: null);
        $sheet1->setCellValue("C{$a2CurrentRow}", (int)$a2['pocet_zap'] ?: null);
        $sheet1->setCellValue("D{$a2CurrentRow}", (int)$a2['pocet_zk']  ?: null);
        $sheet1->setCellValue("E{$a2CurrentRow}", (int)$a2['pocet_dz']  ?: null);
        // Sloupce F–I mají v šabloně vzorce =B*koef atd. – necháme je živé
        $a2CurrentRow++;
    }

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputPath);

    return $outputPath;
}

function getA2PredmetyProExportForKatedra(PDO $pdo, string $katedra, ?string $semestr = null): array
{
    return getA2PredmetyProExport($pdo, $katedra, $semestr);
}
