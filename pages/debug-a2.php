<?php
/**
 * debug-a2.php  –  diagnostika importu typZkousky a tabulky zkouseni_prirazeni
 * Smaž po použití!
 */
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';

$pdo     = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

echo "<h2>Debug A.2 – idVerze = $idVerze</h2>";
echo "<style>table{border-collapse:collapse;font-family:monospace;font-size:13px}
td,th{border:1px solid #ccc;padding:4px 8px}th{background:#eee}</style>";

// 1) Hodnoty typZkousky v tabulce predmet
echo "<h3>1) Hodnoty typZkousky v tabulce predmet (top 30)</h3>";
$stmt = $pdo->prepare("
    SELECT typZkousky, COUNT(*) AS cnt
    FROM predmet
    WHERE IdVerze = ?
    GROUP BY typZkousky
    ORDER BY cnt DESC
    LIMIT 30
");
$stmt->execute([$idVerze]);
$rows = $stmt->fetchAll();

echo "<table><tr><th>typZkousky</th><th>Počet předmětů</th></tr>";
foreach ($rows as $r) {
    $val = $r['typZkousky'] === null ? '<null>' : htmlspecialchars($r['typZkousky']);
    echo "<tr><td>$val</td><td>{$r['cnt']}</td></tr>";
}
echo "</table>";

// 2) Vzorky aSkut / bSkut / cSkut
echo "<h3>2) Vzorky aSkut/bSkut/cSkut (předměty se zakončením)</h3>";
$stmt = $pdo->prepare("
    SELECT zkratka, typZkousky, aSkut, bSkut, cSkut,
           (COALESCE(aSkut,0)+COALESCE(bSkut,0)+COALESCE(cSkut,0)) AS celkem_skut
    FROM predmet
    WHERE IdVerze = ?
      AND typZkousky IS NOT NULL
    LIMIT 30
");
$stmt->execute([$idVerze]);
$rows = $stmt->fetchAll();

echo "<table><tr><th>Zkratka</th><th>typZkousky</th><th>aSkut</th><th>bSkut</th><th>cSkut</th><th>celkem</th></tr>";
foreach ($rows as $r) {
    $bg = ($r['celkem_skut'] == 0) ? 'background:#ffe0e0' : '';
    echo "<tr style='$bg'>
        <td>{$r['zkratka']}</td>
        <td>".htmlspecialchars($r['typZkousky'] ?? '')."</td>
        <td>{$r['aSkut']}</td>
        <td>{$r['bSkut']}</td>
        <td>{$r['cSkut']}</td>
        <td><b>{$r['celkem_skut']}</b></td>
    </tr>";
}
echo "</table>";

// 3) Obsah zkouseni_prirazeni
echo "<h3>3) Záznamy v zkouseni_prirazeni (top 30)</h3>";
$stmt = $pdo->prepare("
    SELECT zp.id, p.zkratka, zp.teacherid, zp.pocet_kl, zp.pocet_zap, zp.pocet_zk, zp.pocet_dz,
           (zp.pocet_kl+zp.pocet_zap+zp.pocet_zk+zp.pocet_dz) AS celkem
    FROM zkouseni_prirazeni zp
    JOIN predmet p ON p.id = zp.predmetid AND p.IdVerze = zp.IdVerze
    WHERE zp.IdVerze = ?
    LIMIT 30
");
$stmt->execute([$idVerze]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "<p style='color:red'><b>⚠ Tabulka zkouseni_prirazeni je prázdná pro tuto verzi!</b></p>";
} else {
    echo "<table><tr><th>ID</th><th>Zkratka</th><th>TeacherID</th>
                     <th>pocet_kl</th><th>pocet_zap</th><th>pocet_zk</th><th>pocet_dz</th><th>Celkem</th></tr>";
    foreach ($rows as $r) {
        $bg = ($r['celkem'] == 0) ? 'background:#ffe0e0' : '';
        echo "<tr style='$bg'>
            <td>{$r['id']}</td>
            <td>{$r['zkratka']}</td>
            <td>{$r['teacherid']}</td>
            <td>{$r['pocet_kl']}</td>
            <td>{$r['pocet_zap']}</td>
            <td>{$r['pocet_zk']}</td>
            <td>{$r['pocet_dz']}</td>
            <td><b>{$r['celkem']}</b></td>
        </tr>";
    }
    echo "</table>";
}

// 4) ucitelpredmetprirazeni – vzorek
echo "<h3>4) ucitelpredmetprirazeni – vzorek (předměty se zakončením, top 20)</h3>";
$stmt = $pdo->prepare("
    SELECT upp.teacherid, p.zkratka, upp.typ, upp.podil,
           p.typZkousky,
           (COALESCE(p.aSkut,0)+COALESCE(p.bSkut,0)+COALESCE(p.cSkut,0)) AS celkem_skut
    FROM ucitelpredmetprirazeni upp
    JOIN predmet p ON p.id = upp.predmetid AND p.IdVerze = upp.IdVerze
    WHERE upp.IdVerze = ?
      AND p.typZkousky IS NOT NULL
    LIMIT 20
");
$stmt->execute([$idVerze]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "<p style='color:red'><b>⚠ ucitelpredmetprirazeni nemá záznamy s nastaveným typZkousky!</b></p>";
} else {
    echo "<table><tr><th>TeacherID</th><th>Zkratka</th><th>Typ</th><th>Podíl</th>
                     <th>typZkousky</th><th>celkem_skut</th></tr>";
    foreach ($rows as $r) {
        $bg = ($r['celkem_skut'] == 0) ? 'background:#ffe0e0' : '';
        echo "<tr style='$bg'>
            <td>{$r['teacherid']}</td>
            <td>{$r['zkratka']}</td>
            <td>{$r['typ']}</td>
            <td>{$r['podil']}</td>
            <td>".htmlspecialchars($r['typZkousky'] ?? '')."</td>
            <td><b>{$r['celkem_skut']}</b></td>
        </tr>";
    }
    echo "</table>";
}

// 5) plan_predmet_obsazenost – alternativní zdroj počtu studentů
echo "<h3>5) plan_predmet_obsazenost – vzorek (top 20)</h3>";
$stmt = $pdo->prepare("
    SELECT ppo.stplIdno, ppo.rocnik, ppo.rok, ppo.semestr,
           ppo.plan_obsazeni, ppo.obsazeni,
           ppo.typ_akce, ppo.typ_akce_zkr,
           ppo.predmet_zkratka, p.typZkousky
    FROM plan_predmet_obsazenost ppo
    JOIN predmet p ON p.zkratka = ppo.predmet_zkratka AND p.IdVerze = ppo.IdVerze
    WHERE ppo.IdVerze = ?
      AND p.typZkousky IS NOT NULL
    LIMIT 20
");
$stmt->execute([$idVerze]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "<p style='color:orange'>plan_predmet_obsazenost je prázdná (nebo nemá předměty se zakončením).</p>";
} else {
    echo "<table><tr><th>stplIdno</th><th>Ročník</th><th>Rok</th><th>Semestr</th>
                     <th>Plan obsazení</th><th>Obsazení</th><th>Typ akce</th><th>Typ zkr.</th>
                     <th>Zkratka předmětu</th><th>typZkousky</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>
            <td>{$r['stplIdno']}</td>
            <td>{$r['rocnik']}</td>
            <td>{$r['rok']}</td>
            <td>{$r['semestr']}</td>
            <td>{$r['plan_obsazeni']}</td>
            <td>{$r['obsazeni']}</td>
            <td>".htmlspecialchars($r['typ_akce'] ?? '')."</td>
            <td>".htmlspecialchars($r['typ_akce_zkr'] ?? '')."</td>
            <td>{$r['predmet_zkratka']}</td>
            <td>".htmlspecialchars($r['typZkousky'] ?? '')."</td>
        </tr>";
    }
    echo "</table>";
}

// --- Sekce 6: Detailní rozbor konkrétní kantorky ---
echo "<h3>6) Detail: kantorka Balaban Cakirpaloglu – předmět SK0AR</h3>";
$pdo2 = connectToDatabase();
$idV2 = getAktivniVerze($pdo2);

$q = $pdo2->prepare("
    SELECT t.id AS teacherid, CONCAT(t.surname,' ',t.name) AS jmeno,
           p.zkratka, p.typZkousky,
           p.aSkut, p.bSkut, p.cSkut,
           (COALESCE(p.aSkut,0)+COALESCE(p.bSkut,0)+COALESCE(p.cSkut,0)) AS celkem_skut,
           upp.typ, upp.podil,
           zp.pocet_kl, zp.pocet_zap, zp.pocet_zk, zp.pocet_dz
    FROM teachers t
    JOIN ucitelpredmetprirazeni upp ON upp.teacherid = t.id AND upp.IdVerze = t.IdVerze
    JOIN predmet p ON p.id = upp.predmetid AND p.IdVerze = upp.IdVerze
    LEFT JOIN zkouseni_prirazeni zp ON zp.teacherid = t.id AND zp.predmetid = p.id AND zp.IdVerze = p.IdVerze
    WHERE t.IdVerze = ?
      AND t.surname = 'Balaban Cakirpaloglu'
      AND p.zkratka = 'SK0AR'
    ORDER BY upp.typ
");
$q->execute([$idV2]);
$rows2 = $q->fetchAll();
if (empty($rows2)) {
    echo "<p>Kantorka nebo předmět SK0AR nenalezeny.</p>";
} else {
    echo "<table><tr><th>Jméno</th><th>Zkratka</th><th>typZkousky</th>
              <th>aSkut</th><th>bSkut</th><th>cSkut</th><th>celkem_skut</th>
              <th>typ výuky</th><th>podíl</th>
              <th>pocet_kl</th><th>pocet_zap</th><th>pocet_zk</th><th>pocet_dz</th></tr>";
    foreach ($rows2 as $r) {
        echo "<tr>
            <td>{$r['jmeno']}</td><td>{$r['zkratka']}</td>
            <td>".htmlspecialchars($r['typZkousky']??'')."</td>
            <td>{$r['aSkut']}</td><td>{$r['bSkut']}</td><td>{$r['cSkut']}</td>
            <td><b>{$r['celkem_skut']}</b></td>
            <td>{$r['typ']}</td><td>{$r['podil']}</td>
            <td>{$r['pocet_kl']}</td><td>{$r['pocet_zap']}</td>
            <td>{$r['pocet_zk']}</td><td>{$r['pocet_dz']}</td>
        </tr>";
    }
    echo "</table>";
}

echo "<hr><p><small>debug-a2.php – smaž po diagnóze</small></p>";
