<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';

$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);
$rocnikId = isset($_GET['rocnik_id']) ? (int)$_GET['rocnik_id'] : 0;

function hRocnikCviceni($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function prelozJazykRocniku($jazyk): string
{
    return (int)$jazyk === 1 ? 'Čeština' : ((int)$jazyk === 2 ? 'Angličtina' : 'Neznámý');
}

function prelozFormuRocniku($forma): string
{
    return (int)$forma === 1 ? 'Prezenční' : ((int)$forma === 2 ? 'Kombinovaná' : 'Neznámá');
}

function prelozTypRocniku($typ): string
{
    return match ((int)$typ) {
        1 => 'Bakalářský',
        2 => 'Navazující',
        3 => 'Doktorský',
        default => 'Neznámý',
    };
}

function resultCountingLink(array $subject): string
{
    return 'result-counting.php?' . http_build_query(array_filter([
        'zkratka' => (string)($subject['zkratka'] ?? ''),
        'semestr' => (string)($subject['semestr'] ?? ''),
        'katedra' => (string)($subject['idPracoviste'] ?? ''),
        'sort' => 'zkratka',
        'order' => 'asc',
    ], static fn($value) => $value !== ''));
}

if ($rocnikId <= 0) {
    exit('Chybí ID ročníku.');
}

$stmt = $pdo->prepare("
    SELECT
        rsp.id,
        rsp.stprIdno,
        sp.nazev AS nazev_programu,
        rsp.rocnik,
        rsp.jazyk,
        rsp.idForma,
        sp.typ,
        rsp.pocetStudentu,
        MIN(o.fakulta) AS fakulta
    FROM rocniky_studijniho_programu rsp
    JOIN studijniprogram sp
      ON sp.stprIdno = rsp.stprIdno
     AND sp.IdVerze = rsp.idVerze
    LEFT JOIN obor o
      ON o.stprIdno = sp.stprIdno
     AND o.IdVerze = rsp.idVerze
    WHERE rsp.id = ?
      AND rsp.idVerze = ?
    GROUP BY rsp.id, rsp.stprIdno, sp.nazev, rsp.rocnik, rsp.jazyk, rsp.idForma, sp.typ, rsp.pocetStudentu
    LIMIT 1
");
$stmt->execute([$rocnikId, $idVerze]);
$rocnik = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rocnik) {
    exit('Ročník nebyl nalezen v aktivní verzi.');
}

$stmt = $pdo->prepare("
    SELECT
        p.id AS predmet_id,
        p.zkratka,
        p.nazev,
        p.rok,
        p.semestr,
        p.idPracoviste,
        pr.nadrazenepracoviste AS fakulta_predmetu,
        pr.zkratka AS katedra,
        COUNT(DISTINCT upp.id) AS cviceni_radku,
        SUM(CASE WHEN upp.max_pocet_studentu IS NOT NULL AND upp.max_pocet_studentu > 1 THEN 1 ELSE 0 END) AS cviceni_s_maximem,
        GROUP_CONCAT(DISTINCT COALESCE(CAST(upp.max_pocet_studentu AS CHAR), 'bez maxima') ORDER BY upp.max_pocet_studentu SEPARATOR ', ') AS maxima,
        GROUP_CONCAT(DISTINCT spl.nazev ORDER BY spl.nazev SEPARATOR ' | ') AS studijni_plany
    FROM rocniky_studijniho_programu rsp
    JOIN studijniprogram sp
      ON sp.stprIdno = rsp.stprIdno
     AND sp.IdVerze = rsp.idVerze
    JOIN obor o
      ON o.stprIdno = sp.stprIdno
     AND o.IdVerze = rsp.idVerze
    JOIN studijni_plan spl
      ON spl.oborIdno = o.oborIdno
     AND spl.IdVerze = rsp.idVerze
    JOIN plan_predmet_obsazenost ppo
      ON ppo.stplIdno = spl.stplIdno
     AND ppo.rocnik = rsp.rocnik
     AND ppo.IdVerze = rsp.idVerze
     AND ppo.platnost = 'A'
    JOIN predmet p
      ON p.zkratka = ppo.predmet_zkratka
     AND p.rok = ppo.rok
     AND p.semestr = ppo.semestr
     AND p.IdVerze = rsp.idVerze
    JOIN ucitelpredmetprirazeni upp
      ON upp.predmetid = p.id
     AND upp.IdVerze = p.IdVerze
     AND upp.typ = 'C'
    LEFT JOIN pracoviste pr
      ON pr.idpracoviste = p.idPracoviste
     AND pr.IdVerze = p.IdVerze
    WHERE rsp.id = ?
      AND rsp.idVerze = ?
    GROUP BY p.id, p.zkratka, p.nazev, p.rok, p.semestr, p.idPracoviste, pr.nadrazenepracoviste, pr.zkratka
    ORDER BY p.semestr, p.zkratka
");
$stmt->execute([$rocnikId, $idVerze]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pocetStudentu = $rocnik['pocetStudentu'] !== null ? (int)$rocnik['pocetStudentu'] : null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Cvičení ročníku</title>
    <link rel="stylesheet" href="../stylepage.css">
    <link rel="stylesheet" href="../stylepages/stylepage-edit-rocniky.css">
    <style>
        .info-box {
            background: #f0f4f8;
            border: 1px solid #d0d7e0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 18px;
        }

        .action-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin: 14px 0;
        }

        .link-button {
            display: inline-block;
            padding: 7px 10px;
            border: 1px solid #3c9a69;
            border-radius: 4px;
            background: #e9f6ef;
            color: #1d5c3c;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .muted {
            color: #666;
            font-size: .9rem;
        }

        .warning {
            color: #7a5a00;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Cvičení studijního ročníku</h1>

    <div class="info-box">
        <strong><?= hRocnikCviceni($rocnik['fakulta'] ?? '–') ?> -
            <?= hRocnikCviceni($rocnik['nazev_programu']) ?></strong><br>
        Ročník: <strong><?= (int)$rocnik['rocnik'] ?></strong> |
        Jazyk: <strong><?= hRocnikCviceni(prelozJazykRocniku($rocnik['jazyk'])) ?></strong> |
        Forma: <strong><?= hRocnikCviceni(prelozFormuRocniku($rocnik['idForma'])) ?></strong> |
        Typ: <strong><?= hRocnikCviceni(prelozTypRocniku($rocnik['typ'])) ?></strong> |
        Počet studentů: <strong><?= $pocetStudentu === null ? 'nezadáno' : $pocetStudentu ?></strong>
    </div>

    <?php if ($pocetStudentu === null || $pocetStudentu <= 0): ?>
        <div class="warning">
            Pro tento ročník není zadán kladný počet studentů. Nastavte jej nejprve v předchozí tabulce a uložte změny.
        </div>
    <?php endif; ?>

    <p class="muted">
        U předmětů níže otevřete Manuální editaci a nastavte ve sloupci <strong>Max. počet</strong>
        hodnotu pro řádky typu <strong>Cvičení</strong>. Teprve potom se předmět zobrazí na stránce
        <strong>Rozdělit cvičení</strong>.
    </p>

    <div class="action-row">
        <a class="link-button" href="edit_rocniky.php">Zpět na ročníky</a>
        <a class="link-button" href="rozdat_cviceni.php">Otevřít rozdělení cvičení</a>
    </div>

    <?php if (empty($subjects)): ?>
        <p class="no-results">
            Pro tento ročník nebyly nalezeny žádné předměty s řádkem typu Cvičení v aktuální verzi.
        </p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Semestr</th>
                <th>Zkratka</th>
                <th>Název předmětu</th>
                <th>Katedra</th>
                <th>Řádků cvičení</th>
                <th>Aktuální max. počet</th>
                <th>Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><?= hRocnikCviceni($subject['semestr']) ?></td>
                    <td><strong><?= hRocnikCviceni($subject['zkratka']) ?></strong></td>
                    <td>
                        <?= hRocnikCviceni($subject['nazev']) ?><br>
                        <span class="muted"><?= hRocnikCviceni($subject['studijni_plany'] ?? '') ?></span>
                    </td>
                    <td>
                        <?= hRocnikCviceni(trim((string)($subject['fakulta_predmetu'] ?? '') . ' ' . (string)($subject['katedra'] ?? ''))) ?>
                    </td>
                    <td><?= (int)$subject['cviceni_radku'] ?></td>
                    <td>
                        <?= hRocnikCviceni($subject['maxima'] ?: 'bez maxima') ?>
                        <?php if ((int)$subject['cviceni_s_maximem'] === 0): ?>
                            <br><span class="muted">nebude zatím v rozdělení</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-cviceni" href="<?= hRocnikCviceni(resultCountingLink($subject)) ?>">
                            Nastavit v A.1
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
