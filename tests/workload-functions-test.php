<?php

require_once __DIR__ . '/../includes/workload-functions.php';

$coefficients = [];
foreach (workloadDefaultCoefficients() as [$area, $kind, $activity, $value]) {
    $coefficients[$area][$kind][$activity] = $value;
}

$expect = static function (float $expected, float $actual, string $message): void {
    if (abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ": expected $expected, got $actual");
    }
};

$weeklyLecture = [
    'typ' => 'P',
    'semestr' => 'ZS',
    'pocetJednotekPrednaska' => 2,
    'jednotkaPrednaskaTypId' => 2,
];
$semesterLecture = $weeklyLecture;
$semesterLecture['pocetJednotekPrednaska'] = 28;
$semesterLecture['jednotkaPrednaskaTypId'] = 1;

$weeks = ['ZS' => 14, 'LS' => 14];
$expect(28.0, workloadSemesterHours($weeklyLecture, $weeks), 'Týdenní hodiny se musí násobit počtem týdnů');
$expect(28.0, workloadSemesterHours($semesterLecture, $weeks), 'Semestrální hodiny se nesmí násobit znovu');
$expect(2.25, workloadA1Coefficient($coefficients, 'standard', 'P'), 'Běžná přednáška');
$expect(3.0, workloadA1Coefficient($coefficients, 'd', 'P'), 'Doktorská přednáška');
$expect(1.5, workloadA1Coefficient($coefficients, 'd', 'C'), 'Doktorské cvičení používá výchozí koeficient dle šablony');
$expect(31.5, workloadSemesterHours($weeklyLecture, $weeks) * 0.5 * workloadA1Coefficient($coefficients, 'standard', 'P'), 'Podíl 50 %');

$finalBachelorCourse = [
    'typ' => 'C',
    'zkratka' => 'AP6UI',
    'semestr' => 'LS',
    'pocetJednotekCviceni' => 2,
    'jednotkaCviceniTypId' => 2,
];
$finalMasterCourse = [
    'typ' => 'P',
    'zkratka' => 'AP0DA',
    'semestr' => 'LS',
    'pocetJednotekPrednaska' => 2,
    'jednotkaPrednaskaTypId' => 2,
];
$monthlyCourse = [
    'typ' => 'C',
    'zkratka' => 'AP5XX',
    'semestr' => 'ZS',
    'pocetJednotekCviceni' => 8,
    'jednotkaCviceniTypId' => 3,
];
$semesterFinalCourse = [
    'typ' => 'P',
    'zkratka' => 'AP6ZZ',
    'semestr' => 'LS',
    'pocetJednotekPrednaska' => 24,
    'jednotkaPrednaskaTypId' => 1,
];
$combinedBlockCourse = [
    'typ' => 'C',
    'zkratka' => 'AK0DA',
    'semestr' => 'LS',
    'pocetJednotekCviceni' => 15,
    'jednotkaCviceniTypId' => 2,
];

$expect(24.0, workloadSemesterHours($finalBachelorCourse, $weeks), 'Predmet 6. semestru bakalare ma pouzit 12 tydnu');
$expect(24.0, workloadSemesterHours($finalMasterCourse, $weeks), 'Predmet s 0 ve tretim znaku ma pouzit 12 tydnu');
$expect(28.0, workloadSemesterHours($monthlyCourse, $weeks), 'Mesicni hodiny se prepocitavaji pres ctyrtydenni mesic');
$expect(24.0, workloadSemesterHours($semesterFinalCourse, $weeks), 'Semestralni jednotka se nenasobi ani u posledniho rocniku');
$expect(2.0, workloadWeeklyHoursForExport($semesterFinalCourse, $weeks), 'Export semestralnich hodin u posledniho rocniku deli 12 tydny');
$expect(2.0, workloadWeeklyHoursForExport($monthlyCourse, $weeks), 'Export mesicnich hodin vraci tydenni ekvivalent');
$expect(1.0, workloadWeeksForRow($combinedBlockCourse, $weeks), 'Kombinovana forma podle druheho znaku K ma pouzit 1 tyden');
$expect(15.0, workloadSemesterHours($combinedBlockCourse, $weeks), 'Blokova vyuka se nesmi nasobit 12 ani 14 tydny');
$expect(15.0, workloadWeeklyHoursForExport($combinedBlockCourse, $weeks), 'Export blokove vyuky ma ukazat plny pocet hodin v jednom tydnu');

if (resolveWorkloadKind(['druh_predmetu' => 'auto', 'jazyk' => 2]) !== 'c') {
    throw new RuntimeException('Anglická výuka se musí automaticky vyhodnotit jako cizojazyčná.');
}

// --- Cizojazyčné koeficienty (A.1) ---
$expect(3.0, workloadA1Coefficient($coefficients, 'c', 'P'), 'Cizojazyčná přednáška');
$expect(2.25, workloadA1Coefficient($coefficients, 'c', 'C'), 'Cizojazyčné cvičení');
$expect(2.25, workloadA1Coefficient($coefficients, 'c', 'S'), 'Cizojazyčný seminář');
$expect(4.5, workloadA1Coefficient($coefficients, 'dc', 'P'), 'Doktorská cizojazyčná přednáška');

// --- REGRESNÍ TEST ---
// Chyba nalezená na exportu úvazku A. Viktorina: stránka uvazek-ucitele.php
// přepisovala číselné upp.jazyk textovým popisem (j.popis AS jazyk), takže
// (int)"Angličtina" === 0 a cizojazyčná výuka spadla na standardní koeficient.
// Detail pak ukazoval 63,00 PB místo správných 84,00 PB.
if (resolveWorkloadKind(['druh_predmetu' => 'auto', 'jazyk' => 'Angličtina']) === 'c') {
    throw new RuntimeException('Textový popis jazyka nesmí být považován za platný vstup.');
}

$anglickaPrednaska = [
    'typ' => 'P',
    'semestr' => 'LS',
    'pocetJednotekPrednaska' => 2,
    'jednotkaPrednaskaTypId' => 2,
    'druh_predmetu' => 'auto',
    'jazyk' => 2,
    'podil' => 100,
];
$pbSpravne = workloadSemesterHours($anglickaPrednaska, $weeks)
    * 1.0
    * workloadA1Coefficient($coefficients, resolveWorkloadKind($anglickaPrednaska), 'P');
$expect(84.0, $pbSpravne, 'Anglická přednáška 2 h/týd musí dát 84 PB, ne 63 PB');

// --- Hranice přetíženosti (konfigurovatelná) ---
$expect(110.0, WORKLOAD_OVERLOAD_DEFAULT, 'Výchozí hranice přetíženosti je 110 %');
$expect(1000.0, WORKLOAD_FULL_TIME_POINTS_DEFAULT, 'Výchozí plný úvazek je 1000 PB');

$expectClass = static function (string $expected, string $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ": expected $expected, got $actual");
    }
};

$expectClass('workload-neutral', workloadStatusClass(null), 'Bez kapacity');
$expectClass('workload-low', workloadStatusClass(50.0), 'Nízké vytížení');
$expectClass('workload-good', workloadStatusClass(95.0), 'V normě');
$expectClass('workload-high', workloadStatusClass(105.0), 'Nad 100 %, ale pod hranicí přetížení');
$expectClass('workload-over', workloadStatusClass(114.9), 'Nad výchozí hranicí 110 %');
$expectClass('workload-good', workloadStatusClass(95.0, 150.0), 'Vlastní hranice 150 % – 95 % je v normě');
$expectClass('workload-high', workloadStatusClass(130.0, 150.0), 'Vlastní hranice 150 % – 130 % ještě není přetížení');
$expectClass('workload-over', workloadStatusClass(160.0, 150.0), 'Vlastní hranice 150 % – 160 % už je přetížení');

echo "workload-functions: OK\n";
