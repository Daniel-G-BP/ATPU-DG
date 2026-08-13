<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/workload-functions.php';

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

    $profile = getTeacherWorkloadProfile($pdo, $teacherId, $idVerze);
    $workload = calculateTeacherWorkload($pdo, $teacherId, $idVerze);
    $coefficients = getWorkloadCoefficients($pdo, $idVerze);
    $fullTimePoints = getWorkloadFullTimePoints($pdo, $idVerze);

    $name = $teacher['name'] ?? '';
    $surname = $teacher['surname'] ?? '';
    $fileBase = preg_replace('/[^A-Za-z0-9_-]/u', '_', $surname . $name);
    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . "{$fileBase}-uvazek-" . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.xlsx';

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

    // Koeficienty se při každém exportu zapisují z DB do kopie šablony.
    $a1 = $coefficients['A1'] ?? [];
    $a2 = $coefficients['A2']['standard'] ?? [];
    $sheet2->fromArray([
        [(float)($a1['standard']['P'] ?? 0), (float)($a1['standard']['C'] ?? 0), (float)($a1['standard']['S'] ?? 0), (float)($a1['standard']['R'] ?? 0)],
        [(float)($a1['c']['P'] ?? 0), (float)($a1['c']['C'] ?? 0), (float)($a1['c']['S'] ?? 0), null],
        [(float)($a1['d']['P'] ?? 0), null, null, null],
        [(float)($a1['dc']['P'] ?? 0), null, null, null],
    ], null, 'C6');
    $sheet2->fromArray([
        [(float)($a1['standard']['P'] ?? 0), (float)($a1['standard']['C'] ?? 0), (float)($a1['standard']['S'] ?? 0), (float)($a1['standard']['R'] ?? 0)],
        [(float)($a1['c']['P'] ?? 0), (float)($a1['c']['C'] ?? 0), (float)($a1['c']['S'] ?? 0), null],
        [(float)($a1['d']['P'] ?? 0), null, null, null],
        [(float)($a1['dc']['P'] ?? 0), null, null, null],
    ], null, 'M6');
    $sheet2->fromArray([[
        (float)($a2['KL'] ?? 0),
        (float)($a2['ZAP'] ?? 0),
        (float)($a2['ZK'] ?? 0),
        (float)($a2['DZ'] ?? 0),
    ]], null, 'C12');

    $sheet1->setCellValue('B4', $profile['pozice'] ?? null);
    $sheet1->setCellValue('L4', $profile['nastup'] ?? null);
    $sheet1->setCellValue('F5', (float)$profile['uvazek']);
    $sheet1->setCellValue('B5', '=F5*' . $fullTimePoints);
    $sheet1->setCellValue('L5', !empty($profile['vyjimka']) ? 'ano' : 'ne');
    $sheet1->setCellValue('B2', "=CONCATENATE('Pomocný'!B22,\" \")");
    $sheet1->setCellValue('L2', "='Pomocný'!B21");
    $sheet1->setCellValue('B3', "=CONCATENATE('Pomocný'!B19,\" \",'Pomocný'!B20)");
    $sheet1->setCellValue('S3', "='Pomocný'!B23");

    $grouped = getTeacherA1ExportRows($pdo, $teacherId, $idVerze);

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
        $pocetTydnu = (float)($item['tydny'] ?? ($semestrPredmetu === 'LS' ? $tydnyLS : $tydnyZS));
        $zs = '';
        $ls = '';
        if ($semestrPredmetu === 'ZS') {
            $zs = $pocetTydnu;
        } elseif ($semestrPredmetu === 'LS') {
            $ls = $pocetTydnu;
        }

        $druh = (string)($item['druh'] ?? '');

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

        $sheet1->setCellValue("M{$currentRow}",
            "=IF(B{$currentRow}=\"c\",E{$currentRow}*{$tydnyCell}*I{$currentRow}*'Pomocný'!\$C\$7,"
            . "IF(B{$currentRow}=\"d\",E{$currentRow}*{$tydnyCell}*I{$currentRow}*'Pomocný'!\$C\$8,"
            . "IF(B{$currentRow}=\"dc\",E{$currentRow}*{$tydnyCell}*I{$currentRow}*'Pomocný'!\$C\$9,"
            . "E{$currentRow}*{$tydnyCell}*I{$currentRow}*'Pomocný'!\$C\$6)))"
        );
        $sheet1->setCellValue("N{$currentRow}",
            "=IF(B{$currentRow}=\"c\",F{$currentRow}*{$tydnyCell}*J{$currentRow}*'Pomocný'!\$D\$7,"
            . "F{$currentRow}*{$tydnyCell}*J{$currentRow}*'Pomocný'!\$D\$6)"
        );
        $sheet1->setCellValue("O{$currentRow}",
            "=IF(B{$currentRow}=\"c\",G{$currentRow}*{$tydnyCell}*K{$currentRow}*'Pomocný'!\$E\$7,"
            . "G{$currentRow}*{$tydnyCell}*K{$currentRow}*'Pomocný'!\$E\$6)"
        );
        $sheet1->setCellValue("P{$currentRow}",
            "=H{$currentRow}*{$tydnyCell}*L{$currentRow}*'Pomocný'!\$F\$6"
        );

        $currentRow++;
    }
    $sheet1->setCellValue('S7', "=SUM(M{$startRow}:P{$endWriteRow})");
    $sheet1->setCellValue('T7', '=S7/$B$5');

    // =========================================================
    // ČÁST A.2 – ZKOUŠENÍ
    // =========================================================

    // Načti data A.2 pro tohoto učitele z zkouseni_prirazeni
    $a2Rows = getTeacherA2ExportRows($pdo, $teacherId, $idVerze);

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
        $sheet1->setCellValue("F{$row}", "=B{$row}*'Pomocný'!\$C\$12");
        $sheet1->setCellValue("G{$row}", "=C{$row}*'Pomocný'!\$D\$12");
        $sheet1->setCellValue("H{$row}", "=D{$row}*'Pomocný'!\$E\$12");
        $sheet1->setCellValue("I{$row}", "=E{$row}*'Pomocný'!\$F\$12");
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

    $a2TotalRow = 23 + $a1RowsInserted;
    $sheet1->setCellValue("S{$a2TotalRow}", "=SUM(F{$a2StartRow}:I{$a2EndWriteRow})");
    $sheet1->setCellValue("T{$a2TotalRow}", "=S{$a2TotalRow}/\$B\$5");

    // Souhrnné hodnoty oblastí, které aplikace eviduje agregovaně.
    $a2RowsInserted = max(0, $a2NeededRows - $a2TemplateRows);
    $totalRowsInserted = $a1RowsInserted + $a2RowsInserted;
    $sheet1->setCellValue('S' . (39 + $totalRowsInserted), (float)$workload['a3']);
    $sheet1->setCellValue('S' . (53 + $totalRowsInserted), (float)$workload['b']);
    $sheet1->setCellValue('S' . (93 + $totalRowsInserted), (float)$workload['c']);
    $sheet1->setCellValue('S' . (114 + $totalRowsInserted), (float)$workload['d']);

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    // Celý formulář obsahuje mnoho vzorců. Přepočet provede Excel při otevření;
    // server tak negeneruje export desítky sekund až minut.
    $writer->setPreCalculateFormulas(false);
    $writer->save($outputPath);

    return $outputPath;
}

function getA2PredmetyProExportForKatedra(PDO $pdo, string $katedra, ?string $semestr = null): array
{
    return getA2PredmetyProExport($pdo, $katedra, $semestr);
}

/**
 * Společné formátování hlavičky tabulky v exportu.
 */
function styleExportHeader(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->getFont()->setBold(true);
    $sheet->getStyle($range)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('DCE6F1');
    $sheet->getStyle($range)->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
}

/**
 * Export přehledové tabulky vytížení jednotlivých vyučujících.
 *
 * @param array $filters search, katedra, fakulta
 * @param array|null $teacherIds Konkrétní vyučující vybraní checkboxy; null = všichni dle filtru.
 * @return string Cesta k vygenerovanému souboru.
 */
function exportPrehledVytizeni(array $filters = [], ?array $teacherIds = null): string
{
    require_once __DIR__ . '/functions-overview-ucitele.php';

    $pdo = connectToDatabase();
    $idVerze = getAktivniVerze($pdo);
    $threshold = getWorkloadOverloadThreshold($pdo, $idVerze);
    $fullTimePoints = getWorkloadFullTimePoints($pdo, $idVerze);

    $normalizedTeacherIds = $teacherIds === null ? null : normalizeTeacherIds($teacherIds);
    if ($teacherIds !== null && $normalizedTeacherIds === []) {
        throw new InvalidArgumentException('Pro export vyberte alespoň jednoho učitele.');
    }

    $teachers = getAllTeachersForExport($pdo, $filters, $normalizedTeacherIds);
    if ($teachers === []) {
        throw new InvalidArgumentException('Výběru neodpovídá žádný učitel.');
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Vytíženost');

    $sheet->setCellValue('A1', 'Přehled vytíženosti vyučujících');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $popisFiltru = [];
    if (!empty($filters['fakulta'])) {
        $popisFiltru[] = 'fakulta: ' . $filters['fakulta'];
    }
    if (!empty($filters['katedra'])) {
        $stmt = $pdo->prepare("SELECT zkratka FROM pracoviste WHERE idpracoviste = ? AND IdVerze = ?");
        $stmt->execute([$filters['katedra'], $idVerze]);
        $popisFiltru[] = 'katedra: ' . ($stmt->fetchColumn() ?: $filters['katedra']);
    }
    if (!empty($filters['search'])) {
        $popisFiltru[] = 'hledání: ' . $filters['search'];
    }
    if ($normalizedTeacherIds !== null) {
        $popisFiltru[] = 'ruční výběr: ' . count($normalizedTeacherIds) . ' uč.';
    }

    $sheet->setCellValue('A2', $popisFiltru
        ? 'Filtr – ' . implode(', ', $popisFiltru)
        : 'Filtr – všichni vyučující');
    $sheet->setCellValue('A3', 'Hranice přetíženosti: ' . $threshold . ' % úvazku');
    $sheet->setCellValue('A4', 'Plný úvazek: ' . $fullTimePoints . ' PB');
    $sheet->setCellValue('A5', 'Vygenerováno: ' . date('d.m.Y H:i'));
    $sheet->getStyle('A2:A5')->getFont()->setItalic(true)->setSize(9);

    $headers = [
        'Příjmení', 'Jméno', 'Katedra', 'Fakulta', 'Úvazek',
        'A.1 přímá výuka', 'A.2 zkoušení', 'A.3 ostatní', 'B tvůrčí',
        'C administrativa', 'D další', 'Celkem PB', 'Kapacita PB',
        'Vytíženost %', 'Stav',
    ];

    $headerRow = 7;
    $sheet->fromArray([$headers], null, 'A' . $headerRow);
    styleExportHeader($sheet, 'A' . $headerRow . ':O' . $headerRow);

    $row = $headerRow + 1;
    $pocetPretizenych = 0;

    foreach ($teachers as $teacher) {
        $w = $teacher['workload'] ?? null;

        if ($w === null) {
            $sheet->fromArray([[
                $teacher['surname'], $teacher['name'],
                $teacher['katedra'] ?? '', $teacher['fakulta'] ?? '',
                '', '', '', '', '', '', '', '', '', '', 'Bez dat',
            ]], null, 'A' . $row);
            $row++;
            continue;
        }

        $jePretizen = !empty($w['is_overloaded']);
        if ($jePretizen) {
            $pocetPretizenych++;
        }

        $stav = $w['percent'] === null
            ? 'Bez kapacity'
            : ($jePretizen ? 'PŘETÍŽEN' : ($w['percent'] >= 80 ? 'V normě' : 'Nízké vytížení'));

        $sheet->fromArray([[
            $teacher['surname'],
            $teacher['name'],
            $teacher['katedra'] ?? '',
            $teacher['fakulta'] ?? '',
            (float)$w['uvazek'],
            (float)$w['a1'],
            (float)$w['a2'],
            (float)$w['a3'],
            (float)$w['b'],
            (float)$w['c'],
            (float)$w['d'],
            (float)$w['total'],
            (float)$w['capacity'],
            $w['percent'],
            $stav,
        ]], null, 'A' . $row);

        if ($jePretizen) {
            $sheet->getStyle("A{$row}:O{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8D7DA');
        }

        $row++;
    }

    $lastRow = $row - 1;

    if ($lastRow >= $headerRow + 1) {
        $sumRow = $row + 1;
        $sheet->setCellValue('A' . $sumRow, 'Součet / průměr');
        $sheet->setCellValue('F' . $sumRow, "=SUM(F" . ($headerRow + 1) . ":F{$lastRow})");
        $sheet->setCellValue('G' . $sumRow, "=SUM(G" . ($headerRow + 1) . ":G{$lastRow})");
        $sheet->setCellValue('L' . $sumRow, "=SUM(L" . ($headerRow + 1) . ":L{$lastRow})");
        $sheet->setCellValue('M' . $sumRow, "=SUM(M" . ($headerRow + 1) . ":M{$lastRow})");
        $sheet->setCellValue('N' . $sumRow, "=AVERAGE(N" . ($headerRow + 1) . ":N{$lastRow})");
        $sheet->getStyle("A{$sumRow}:O{$sumRow}")->getFont()->setBold(true);

        $sheet->setCellValue('A' . ($sumRow + 1), 'Počet přetížených vyučujících: ' . $pocetPretizenych);
        $sheet->getStyle('A' . ($sumRow + 1))->getFont()->setBold(true);

        $sheet->getStyle('N' . ($headerRow + 1) . ':N' . $lastRow)
            ->getNumberFormat()->setFormatCode('0.0');
        $sheet->getStyle('F' . ($headerRow + 1) . ':M' . $lastRow)
            ->getNumberFormat()->setFormatCode('0.00');

        $sheet->setAutoFilter('A' . $headerRow . ':O' . $lastRow);
    }

    foreach (range('A', 'O') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));

    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'prehled-vytizenosti-' . date('Y-m-d') . '.xlsx';

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputPath);
    $spreadsheet->disconnectWorksheets();

    return $outputPath;
}

/**
 * Export tabulky nepokryté výuky.
 *
 * @param array $filters katedra, fakulta, semestr, zkratka, nazev
 * @return string Cesta k vygenerovanému souboru.
 */
function exportNepokrytePredmety(array $filters = []): string
{
    require_once __DIR__ . '/coverage-functions.php';

    $pdo = connectToDatabase();
    $idVerze = getAktivniVerze($pdo);

    $nepokryte = getNepokrytePredmety($pdo, $idVerze, $filters);
    $statistiky = getPokrytiStatistiky($pdo, $idVerze, $filters);

    $typNazvy = ['P' => 'Přednáška', 'C' => 'Cvičení', 'S' => 'Seminář', 'R' => 'Ateliér'];

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    // List 1 – detail po částech výuky
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Nepokrytá výuka');

    $sheet->setCellValue('A1', 'Nepokrytá výuka');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Předmět je uveden, pokud jakákoliv jeho část výuky není plně pokrytá.');
    $sheet->setCellValue('A3', 'Vygenerováno: ' . date('d.m.Y H:i'));
    $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->setSize(9);

    $headers = [
        'Zkratka', 'Název', 'Semestr', 'Rok', 'Katedra', 'Fakulta',
        'Typ výuky', 'Jazyk', 'Pokrytí části %', 'Chybí v části %', 'Důvod',
        'Pokrytí předmětu %', 'Nepokrytých částí', 'Částí celkem',
    ];
    $headerRow = 5;
    $sheet->fromArray([$headers], null, 'A' . $headerRow);
    styleExportHeader($sheet, 'A' . $headerRow . ':N' . $headerRow);

    $row = $headerRow + 1;
    foreach ($nepokryte as $predmet) {
        foreach ($predmet['casti'] as $cast) {
            $sheet->fromArray([[
                $predmet['zkratka'],
                $predmet['nazev'],
                $predmet['semestr'],
                $predmet['rok'],
                $predmet['katedra'],
                $predmet['fakulta'],
                $typNazvy[$cast['typ']] ?? $cast['typ'],
                $cast['jazyk_nazev'] ?? '',
                (float)$cast['soucet_podilu'],
                (float)$cast['chybi'],
                $cast['duvod'],
                (float)$predmet['pokryti_procent'],
                (int)$predmet['pocet_casti'],
                (int)$predmet['pocet_casti_celkem'],
            ]], null, 'A' . $row);

            if ($cast['soucet_podilu'] <= 0) {
                $sheet->getStyle("A{$row}:N{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8D7DA');
            }
            $row++;
        }
    }

    $lastRow = $row - 1;
    if ($lastRow >= $headerRow + 1) {
        $sheet->getStyle('I' . ($headerRow + 1) . ':J' . $lastRow)
            ->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('L' . ($headerRow + 1) . ':L' . $lastRow)
            ->getNumberFormat()->setFormatCode('0.0');
        $sheet->setAutoFilter('A' . $headerRow . ':N' . $lastRow);
    }

    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));

    // List 2 – souhrn
    $summary = $spreadsheet->createSheet();
    $summary->setTitle('Souhrn');
    $summary->fromArray([
        ['Souhrn pokrytí výuky'],
        [],
        ['Předmětů celkem', $statistiky['celkem_predmetu']],
        ['Pokrytých předmětů', $statistiky['pokrytych_predmetu']],
        ['Nepokrytých předmětů', $statistiky['nepokrytych_predmetu']],
        ['Nepokrytých částí výuky', $statistiky['nepokrytych_casti']],
        ['Pokrytí předmětů (%)', $statistiky['procento_pokryti']],
    ], null, 'A1');
    $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $summary->getStyle('A3:A7')->getFont()->setBold(true);
    $summary->getColumnDimension('A')->setAutoSize(true);
    $summary->getColumnDimension('B')->setAutoSize(true);

    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'nepokryta-vyuka-' . date('Y-m-d') . '.xlsx';

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputPath);
    $spreadsheet->disconnectWorksheets();

    return $outputPath;
}
