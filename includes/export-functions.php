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
                'zkratka'            => $r['zkratka'],
                'nazev'              => $r['nazev'],
                'semestr'            => $r['semestr'] ?? null,
                'jazyk'              => $r['jazyk'] ?? null,
                'prednaska'          => 0,
                'cviceni'            => 0,
                'seminar'            => 0,
                'max_studenti_cv'    => 0, // max studentů na skupinu cvičení
                'max_studenti_sem'   => 0, // max studentů na skupinu semináře
            ];
        }

        $podil = ((float)($r['podil'] ?? 0)) / 100;
        $typ = $r['typ'] ?? '';
        $maxStudentu = (int)($r['max_pocet_studentu'] ?? 0);

        if ($typ === 'P') {
            $grouped[$k]['prednaska'] += ((float)($r['pocetJednotekPrednaska'] ?? 0)) * $podil;
        } elseif ($typ === 'C') {
            $grouped[$k]['cviceni'] += ((float)($r['pocetJednotekCviceni'] ?? 0)) * $podil;
            if ($maxStudentu > 0) {
                $grouped[$k]['max_studenti_cv'] = max($grouped[$k]['max_studenti_cv'], $maxStudentu);
            }
        } elseif ($typ === 'S') {
            $grouped[$k]['seminar'] += ((float)($r['pocetJednotekSeminar'] ?? 0)) * $podil;
            if ($maxStudentu > 0) {
                $grouped[$k]['max_studenti_sem'] = max($grouped[$k]['max_studenti_sem'], $maxStudentu);
            }
        }
    }

    // BUG FIX: dotaz celkového počtu studentů na každý předmět (pro výpočet počtu skupin)
    // Používáme data z plan_predmet_obsazenost + rocniky_studijniho_programu
    $subjectKeys = [];
    foreach ($grouped as $item) {
        $subjectKeys[$item['zkratka'] . '_' . ($item['semestr'] ?? '')] = true;
    }

    $poctyStudentu = []; // klíč: 'zkratka_semestr' => int
    if (!empty($subjectKeys)) {
        $zkratkyList = array_unique(array_column(array_values($grouped), 'zkratka'));
        $placeholders = implode(',', array_fill(0, count($zkratkyList), '?'));
        $stmtSt = $pdo->prepare("
            SELECT
                p.zkratka,
                p.semestr,
                COALESCE(SUM(DISTINCT rsp.pocetStudentu), 0) AS pocet_studentu
            FROM predmet p
            LEFT JOIN plan_predmet_obsazenost ppo
                ON ppo.predmet_zkratka = p.zkratka
               AND ppo.rok = p.rok
               AND ppo.semestr = p.semestr
               AND ppo.IdVerze = p.IdVerze
               AND ppo.platnost = 'A'
            LEFT JOIN studijni_plan spl ON spl.stplIdno = ppo.stplIdno AND spl.IdVerze = p.IdVerze
            LEFT JOIN obor o ON o.oborIdno = spl.oborIdno AND o.IdVerze = p.IdVerze
            LEFT JOIN studijniprogram sp ON sp.stprIdno = o.stprIdno AND sp.IdVerze = p.IdVerze
            LEFT JOIN rocniky_studijniho_programu rsp
                ON rsp.stprIdno = sp.stprIdno
               AND rsp.rocnik = ppo.rocnik
               AND rsp.idForma = sp.idForma
               AND rsp.jazyk = sp.jazyk
               AND rsp.idVerze = p.IdVerze
            WHERE p.zkratka IN ($placeholders)
              AND p.IdVerze = ?
            GROUP BY p.zkratka, p.semestr
        ");
        $stmtSt->execute(array_merge($zkratkyList, [$idVerze]));
        foreach ($stmtSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $poctyStudentu[$row['zkratka'] . '_' . $row['semestr']] = (int)$row['pocet_studentu'];
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

        // BUG FIX: výpočet počtu skupin z celkového počtu studentů a max. kapacity skupiny
        // Přednáška: 1 skupina (všichni studenti dohromady)
        $skupinyP = 1;
        $skupinyC = 1;
        $skupinyS = 1;

        $pocetStudentu = $poctyStudentu[$item['zkratka'] . '_' . ($item['semestr'] ?? '')] ?? 0;

        if ($pocetStudentu > 0 && $item['max_studenti_cv'] > 0) {
            $skupinyC = max(1, (int)ceil($pocetStudentu / $item['max_studenti_cv']));
        }
        if ($pocetStudentu > 0 && $item['max_studenti_sem'] > 0) {
            $skupinyS = max(1, (int)ceil($pocetStudentu / $item['max_studenti_sem']));
        }

        $sheet1->setCellValue("A{$currentRow}", $item['zkratka']); // kód
        $sheet1->setCellValue("B{$currentRow}", $druh);            // druh
        $sheet1->setCellValue("C{$currentRow}", $zs);              // ZS týdny
        $sheet1->setCellValue("D{$currentRow}", $ls);              // LS týdny

        // Rozvrhové hodiny týdně
        $sheet1->setCellValue("E{$currentRow}", $item['prednaska']);
        $sheet1->setCellValue("F{$currentRow}", $item['cviceni']);
        $sheet1->setCellValue("G{$currentRow}", $item['seminar']);
        $sheet1->setCellValue("H{$currentRow}", 0); // ateliér

        // Skupiny
        $sheet1->setCellValue("I{$currentRow}", $skupinyP);
        $sheet1->setCellValue("J{$currentRow}", $skupinyC);
        $sheet1->setCellValue("K{$currentRow}", $skupinyS);
        $sheet1->setCellValue("L{$currentRow}", 1); // ateliér skupiny

        // Pracovní body – pro ZS bereme C, pro LS D
        $tydnyCell = ($semestrPredmetu === 'LS') ? "D{$currentRow}" : "C{$currentRow}";

        $sheet1->setCellValue("M{$currentRow}", "=E{$currentRow}*{$tydnyCell}*I{$currentRow}");
        $sheet1->setCellValue("N{$currentRow}", "=F{$currentRow}*{$tydnyCell}*J{$currentRow}");
        $sheet1->setCellValue("O{$currentRow}", "=G{$currentRow}*{$tydnyCell}*K{$currentRow}");
        $sheet1->setCellValue("P{$currentRow}", "=H{$currentRow}*{$tydnyCell}*L{$currentRow}");

        $currentRow++;
    }

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputPath);

    return $outputPath;
}

function getA2PredmetyProExportForKatedra(PDO $pdo, string $katedra, ?string $semestr = null): array
{
    return getA2PredmetyProExport($pdo, $katedra, $semestr);
}
