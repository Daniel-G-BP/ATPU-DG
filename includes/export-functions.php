<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/dbh.inc.php';

function getName(int $teacherId): string
{
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("
        SELECT name 
        FROM teachers
        WHERE id = ?
    ");
    $stmt->execute([$teacherId]);
    $name = $stmt->fetchColumn();
    return $name;
}

function getSurname(int $teacherId): string
{
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("
        SELECT surname 
        FROM teachers
        WHERE id = ?
    ");
    $stmt->execute([$teacherId]);
    $name = $stmt->fetchColumn();
    return $name;
}

function exportUvazekDoExcelu(int $teacherId): string
{
    $name = getName($teacherId);
    $surname = getSurname($teacherId);
    $templatePath = __DIR__ . '/../excel/template.xlsx';
    $outputPath = __DIR__ . "/../excel/$surname$name-uvazek.xlsx";

    $spreadsheet = IOFactory::load($templatePath);
    $sheet2 = $spreadsheet->getSheetByName("Pomocný");
    $sheet1 = $spreadsheet->getSheetByName("Hlavni");

    $pdo = connectToDatabase();

    // FAKULTA
    $stmt = $pdo->query("
        SELECT cs.zkratka 
        FROM nastaveni n  
        JOIN cisfakulta cs ON n.hodnota = cs.idcis
        WHERE n.idNastaveni = 3
    ");
    $fakulta = $stmt->fetchColumn();

    // ÚSTAV, JMÉNO, PŘÍJMENÍ
    $stmt = $pdo->prepare("
        SELECT p.nazev, t.name, t.surname 
        FROM teachers t
        JOIN pracoviste p ON t.idPracoviste = p.idpracoviste
        WHERE t.id = ?
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    // AKADEMICKÝ ROK
    $stmt = $pdo->query("SELECT r.akademickyrok FROM nastaveni n 
                        join roky r on n.hodnota=r.rok
                        WHERE n.idNastaveni=2;");
    $rok = $stmt->fetchColumn();

    if (!$teacher) {
        throw new Exception("Učitel s ID $teacherId nebyl nalezen.");
    }


    // Výpis do hlavičky 
    $sheet2->setCellValue("B22", $fakulta);
    $sheet2->setCellValue("B21", $teacher['nazev']);      // ústav
    $sheet2->setCellValue("B19", $teacher['name']);       // jméno
    $sheet2->setCellValue("B20", $teacher['surname']);    // příjmení
    $sheet2->setCellValue("B23", $rok);


    $verzeStmt = $pdo->query("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
    $verzeId   = $verzeStmt->fetchColumn();

    //  Dotaz na uciteluv uvazek, podobne jako v uvazek-ucitele.php ---
    $sql = "
        SELECT 
            p.zkratka, 
            p.nazev, 
            upp.podil,
            ph.pocetJednotekPrednaska, 
            ph.pocetJednotekCviceni, 
            ph.pocetJednotekSeminar
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p ON upp.predmetid = p.id
        LEFT JOIN predmet_hodiny ph ON p.id = ph.predmetid
        WHERE upp.teacherid = ? 
        AND upp.IdVerze   = ?
        ORDER BY p.zkratka
    ";
    $stmtData = $pdo->prepare($sql);
    $stmtData->execute([$teacherId, $verzeId]);
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $r) {
        $k = $r['zkratka'];
        if (!isset($grouped[$k])) {
            $grouped[$k] = [
                'zkratka'   => $r['zkratka'],
                'nazev'     => $r['nazev'],
                'prednaska' => 0,
                'cviceni'   => 0,
                'seminar'   => 0,
            ];
        }
        // akumulace hodin
        $grouped[$k]['prednaska'] += $r['pocetJednotekPrednaska'] * ($r['podil']/100);
        $grouped[$k]['cviceni']   += $r['pocetJednotekCviceni']   * ($r['podil']/100);
        $grouped[$k]['seminar']   += $r['pocetJednotekSeminar']   * ($r['podil']/100);
    }

    //  Zapis do listu "Hlavní" (řádky 11–...) 
    $startRow = 11;
    foreach ($grouped as $item) {
        // --- Zjištění čísla ze zkratky předmětu 
        preg_match('/\d/', $item['zkratka'], $cisla);
        $cislo = isset($cisla[0]) ? (int)$cisla[0] : null;

        $isLetni = $cislo !== null && $cislo % 2 === 0;
        $je12Tydnu = in_array($cislo, [0, 6]);

        $tydnu = $isLetni
            ? ($je12Tydnu ? 12 : 14)
            : 14;

        $zs = $isLetni ? "" : $tydnu;
        $ls = $isLetni ? $tydnu : "";

        //  Typ výuky (sl. B): "c" pro cizí jazyk 
        $druh = '';
        $nazev = mb_strtolower($item['nazev']);
        if (str_contains($nazev, 'angličtina') || str_contains($nazev, 'english')) {
            $druh = 'c';
        }

        // Zápis do hlavního listu (sheet1) ---
        $sheet1->setCellValue("A{$startRow}", $item['zkratka']); // kód
        $sheet1->setCellValue("B{$startRow}", $druh);            // druh
        $sheet1->setCellValue("C{$startRow}", $zs);              // ZS týdny
        $sheet1->setCellValue("D{$startRow}", $ls);              // LS týdny

        // Hodiny týdně
        $sheet1->setCellValue("E{$startRow}", $item['prednaska']);
        $sheet1->setCellValue("F{$startRow}", $item['cviceni']);
        $sheet1->setCellValue("G{$startRow}", $item['seminar']);
        $sheet1->setCellValue("H{$startRow}", 0); // ateliér

        // Skupiny
        $sheet1->setCellValue("I{$startRow}", 1);
        $sheet1->setCellValue("J{$startRow}", 1);
        $sheet1->setCellValue("K{$startRow}", 1);
        $sheet1->setCellValue("L{$startRow}", 1);

        // Pracovní body – pomocí vzorců
        $sheet1->setCellValue("M{$startRow}", "=E{$startRow}*C{$startRow}*I{$startRow}");
        $sheet1->setCellValue("N{$startRow}", "=F{$startRow}*C{$startRow}*J{$startRow}");
        $sheet1->setCellValue("O{$startRow}", "=G{$startRow}*C{$startRow}*K{$startRow}");
        $sheet1->setCellValue("P{$startRow}", "=H{$startRow}*C{$startRow}*L{$startRow}");

        $startRow++;
    }

    // Ukládání
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputPath);

    return $outputPath;
}
