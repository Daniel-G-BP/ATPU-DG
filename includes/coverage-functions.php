<?php

/**
 * Evidence nepokryté výuky.
 *
 * Předmět je považován za NEPOKRYTÝ, pokud jakákoliv jeho část výuky není plně
 * pokrytá. Části se posuzují po kombinaci (typ výuky, jazyk):
 *
 *  - část nemá přiřazeného žádného vyučujícího (teacherid IS NULL), nebo
 *  - součet podílů přiřazených vyučujících je menší než 100 %.
 *
 * Stačí jediná nepokrytá část a celý předmět spadá do přehledu.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/workload-functions.php';

/**
 * Tolerance pro porovnání součtu podílů se 100 % (kvůli desetinným podílům).
 */
const COVERAGE_TOLERANCE = 0.01;

/**
 * Rozhodne, zda je jedna část výuky nepokrytá.
 * Čistá funkce bez DB – testovatelná samostatně.
 *
 * @param float $soucetPodilu Součet podílů vyučujících přiřazených k této části.
 */
function jeCastNepokryta(float $soucetPodilu): bool
{
    return $soucetPodilu < 100.0 - COVERAGE_TOLERANCE;
}

/**
 * Kolik procent části výuky zbývá pokrýt (nikdy záporné).
 */
function chybejiciPodil(float $soucetPodilu): float
{
    return max(0.0, round(100.0 - $soucetPodilu, 2));
}

/**
 * Pokrytí předmětu jako celku v procentech (0–100).
 *
 * Procenta jednotlivých částí NELZE sčítat – každá část je vlastní 100 %.
 * Pokrytí předmětu je proto průměr pokrytí všech jeho částí:
 * předmět se čtyřmi částmi, z nichž žádná nemá vyučujícího, je pokryt na 0 %,
 * nikoliv „chybí 400 %“.
 *
 * @param array $podilyCasti Součet podílů za každou část výuky předmětu.
 */
function pokrytiPredmetu(array $podilyCasti): float
{
    if ($podilyCasti === []) {
        return 100.0;
    }

    $soucet = 0.0;
    foreach ($podilyCasti as $podil) {
        // Přepokrytí (např. 120 %) se nezapočítává jako bonus.
        $soucet += min(100.0, max(0.0, (float)$podil));
    }

    return round($soucet / count($podilyCasti), 1);
}

/**
 * Kolik procent výuky předmětu zbývá pokrýt (0–100).
 */
function chybejiciPokrytiPredmetu(array $podilyCasti): float
{
    return round(100.0 - pokrytiPredmetu($podilyCasti), 1);
}

/**
 * Vrátí seznam předmětů s nepokrytou výukou.
 *
 * @param array $filters katedra (idpracoviste), fakulta (nadrazenepracoviste),
 *                       semestr, zkratka, nazev
 * @return array Pole předmětů; každý má klíč 'casti' se seznamem nepokrytých částí.
 */
function getNepokrytePredmety(PDO $pdo, int $idVerze, array $filters = []): array
{
    $where = ['p.IdVerze = ?', 'upp.IdVerze = ?'];
    $params = [$idVerze, $idVerze];

    if (!empty($filters['katedra'])) {
        $where[] = 'p.idPracoviste = ?';
        $params[] = $filters['katedra'];
    }
    if (!empty($filters['fakulta'])) {
        $where[] = 'pr.nadrazenepracoviste = ?';
        $params[] = $filters['fakulta'];
    }
    if (!empty($filters['semestr'])) {
        $where[] = 'p.semestr = ?';
        $params[] = $filters['semestr'];
    }
    if (!empty($filters['zkratka'])) {
        $where[] = 'p.zkratka LIKE ?';
        $params[] = '%' . $filters['zkratka'] . '%';
    }
    if (!empty($filters['nazev'])) {
        $where[] = 'p.nazev LIKE ?';
        $params[] = '%' . $filters['nazev'] . '%';
    }
    if (!getZahrnoutAJ($pdo)) {
        $where[] = 'upp.jazyk != 2';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Agregace po částech výuky: (předmět, typ, jazyk).
    $sql = "
        SELECT
            p.id AS predmetid,
            p.zkratka,
            p.nazev,
            p.semestr,
            p.rok,
            pr.zkratka AS katedra,
            pr.nadrazenepracoviste AS fakulta,
            upp.typ,
            upp.jazyk,
            j.popis AS jazyk_nazev,
            COUNT(upp.id) AS pocet_radku,
            SUM(CASE WHEN upp.teacherid IS NULL THEN 1 ELSE 0 END) AS pocet_bez_ucitele,
            COALESCE(SUM(CASE WHEN upp.teacherid IS NOT NULL THEN upp.podil ELSE 0 END), 0) AS soucet_podilu
        FROM predmet p
        JOIN ucitelpredmetprirazeni upp ON upp.predmetid = p.id
        JOIN pracoviste pr ON pr.idpracoviste = p.idPracoviste AND pr.IdVerze = p.IdVerze
        LEFT JOIN jazyk j ON j.id = upp.jazyk
        $whereSql
        GROUP BY p.id, p.zkratka, p.nazev, p.semestr, p.rok,
                 pr.zkratka, pr.nadrazenepracoviste, upp.typ, upp.jazyk, j.popis
        ORDER BY p.zkratka, upp.typ, upp.jazyk
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 1. průchod – seskupit VŠECHNY části výuky podle předmětu.
    // Pokryté části jsou potřeba pro správný výpočet pokrytí předmětu jako celku.
    $predmety = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $predmetId = (int)$row['predmetid'];
        $soucetPodilu = (float)$row['soucet_podilu'];
        $bezUcitele = (int)$row['pocet_bez_ucitele'];

        if (!isset($predmety[$predmetId])) {
            $predmety[$predmetId] = [
                'predmetid' => $predmetId,
                'zkratka' => $row['zkratka'],
                'nazev' => $row['nazev'],
                'semestr' => $row['semestr'],
                'rok' => $row['rok'],
                'katedra' => $row['katedra'],
                'fakulta' => $row['fakulta'],
                'casti' => [],
                'vsechny_podily' => [],
            ];
        }

        $predmety[$predmetId]['vsechny_podily'][] = $soucetPodilu;

        if (!jeCastNepokryta($soucetPodilu)) {
            continue;
        }

        $predmety[$predmetId]['casti'][] = [
            'typ' => $row['typ'],
            'jazyk' => (int)$row['jazyk'],
            'jazyk_nazev' => $row['jazyk_nazev'],
            'soucet_podilu' => round($soucetPodilu, 2),
            'chybi' => chybejiciPodil($soucetPodilu),
            'bez_ucitele' => $bezUcitele,
            'duvod' => $bezUcitele > 0 && $soucetPodilu <= 0.0
                ? 'Bez vyučujícího'
                : 'Neúplný podíl',
        ];
    }

    // 2. průchod – ponechat jen předměty s alespoň jednou nepokrytou částí
    // a dopočítat pokrytí předmětu jako celku.
    $vysledek = [];

    foreach ($predmety as $predmet) {
        if ($predmet['casti'] === []) {
            continue;
        }

        $predmet['pocet_casti'] = count($predmet['casti']);
        $predmet['pocet_casti_celkem'] = count($predmet['vsechny_podily']);
        $predmet['pokryti_procent'] = pokrytiPredmetu($predmet['vsechny_podily']);
        $predmet['chybi_celkem'] = chybejiciPokrytiPredmetu($predmet['vsechny_podily']);
        unset($predmet['vsechny_podily']);

        $vysledek[] = $predmet;
    }

    return $vysledek;
}

/**
 * Souhrnné statistiky pokrytí výuky pro danou verzi a filtry.
 */
function getPokrytiStatistiky(PDO $pdo, int $idVerze, array $filters = []): array
{
    $nepokryte = getNepokrytePredmety($pdo, $idVerze, $filters);

    $where = ['p.IdVerze = ?'];
    $params = [$idVerze];

    if (!empty($filters['katedra'])) {
        $where[] = 'p.idPracoviste = ?';
        $params[] = $filters['katedra'];
    }
    if (!empty($filters['fakulta'])) {
        $where[] = 'pr.nadrazenepracoviste = ?';
        $params[] = $filters['fakulta'];
    }
    if (!empty($filters['semestr'])) {
        $where[] = 'p.semestr = ?';
        $params[] = $filters['semestr'];
    }
    if (!empty($filters['zkratka'])) {
        $where[] = 'p.zkratka LIKE ?';
        $params[] = '%' . $filters['zkratka'] . '%';
    }
    if (!empty($filters['nazev'])) {
        $where[] = 'p.nazev LIKE ?';
        $params[] = '%' . $filters['nazev'] . '%';
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id)
        FROM predmet p
        JOIN pracoviste pr ON pr.idpracoviste = p.idPracoviste AND pr.IdVerze = p.IdVerze
        WHERE " . implode(' AND ', $where) . "
    ");
    $stmt->execute($params);
    $celkem = (int)$stmt->fetchColumn();

    $pocetNepokrytych = count($nepokryte);
    $pocetCasti = array_sum(array_column($nepokryte, 'pocet_casti'));

    return [
        'celkem_predmetu' => $celkem,
        'nepokrytych_predmetu' => $pocetNepokrytych,
        'pokrytych_predmetu' => max(0, $celkem - $pocetNepokrytych),
        'nepokrytych_casti' => $pocetCasti,
        'procento_pokryti' => $celkem > 0
            ? round((($celkem - $pocetNepokrytych) / $celkem) * 100, 1)
            : null,
    ];
}
