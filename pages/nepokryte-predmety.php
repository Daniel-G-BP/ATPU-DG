<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';
require_once '../includes/coverage-functions.php';

$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

if (!$idVerze) {
    echo "<p>Chybí aktivní verze dat. Nastavte ji na stránce Import dat.</p>";
    exit;
}

$filters = [
    'katedra' => trim((string)($_GET['katedra'] ?? '')),
    'fakulta' => trim((string)($_GET['fakulta'] ?? '')),
    'semestr' => trim((string)($_GET['semestr'] ?? '')),
    'zkratka' => trim((string)($_GET['zkratka'] ?? '')),
    'nazev'   => trim((string)($_GET['nazev'] ?? '')),
];

$nepokryte = getNepokrytePredmety($pdo, $idVerze, $filters);
$statistiky = getPokrytiStatistiky($pdo, $idVerze, $filters);
$zahrnoutAJ = getZahrnoutAJ($pdo);

$katedryStmt = $pdo->prepare("
    SELECT idpracoviste, zkratka, nazev, nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ?
    ORDER BY nadrazenepracoviste, zkratka
");
$katedryStmt->execute([$idVerze]);
$katedry = $katedryStmt->fetchAll(PDO::FETCH_ASSOC);

$fakultyStmt = $pdo->prepare("
    SELECT DISTINCT nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ? AND nadrazenepracoviste IS NOT NULL AND nadrazenepracoviste <> ''
    ORDER BY nadrazenepracoviste
");
$fakultyStmt->execute([$idVerze]);
$fakulty = $fakultyStmt->fetchAll(PDO::FETCH_COLUMN);

$typNazvy = ['P' => 'Přednáška', 'C' => 'Cvičení', 'S' => 'Seminář', 'R' => 'Ateliér'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Nepokrytá výuka</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
    <style>
        .filters { display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin:0 0 18px; }
        .filters label { display:flex; flex-direction:column; gap:4px; font-size:.9rem; }
        .filters input, .filters select, .filters button { padding:7px 9px; }
        .stat-cards { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
        .stat-card { background:#f7fafb; border:1px solid #cfd8dc; border-radius:8px;
                     padding:12px 18px; min-width:150px; }
        .stat-card .value { font-size:1.5rem; font-weight:bold; color:#1a3a5c; }
        .stat-card .label { font-size:.82rem; color:#666; }
        .stat-card.warn .value { color:#b02a37; }
        .part-badge { display:inline-block; background:#fff; border:1px solid #d0d7de;
                      border-radius:5px; padding:3px 8px; margin:2px 3px 2px 0; font-size:.85rem; }
        .part-badge.none { border-color:#dc3545; background:#fdf1f2; color:#7a1a1a; }
        .part-badge.partial { border-color:#ffc107; background:#fffbf0; color:#7a5a00; }
        .coverage-table td { vertical-align:top; }
    </style>
</head>
<body>
<div class="main-content">
    <h1>Nepokrytá výuka</h1>
    <p style="color:#555; max-width:820px;">
        Přehled předmětů, u kterých <strong>jakákoliv část výuky</strong> není plně pokrytá –
        tedy nemá přiřazeného vyučujícího, nebo součet podílů nedosahuje 100 %.
        Části se posuzují zvlášť pro každou kombinaci typu výuky a jazyka.
    </p>

    <?php if (!$zahrnoutAJ): ?>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:6px;
                    padding:7px 14px; margin-bottom:12px; font-size:.9em; color:#7a5a00;">
            ⚠ <strong>Anglické výuky nejsou zahrnuty</strong> – dle nastavení se nekontrolují.
        </div>
    <?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="value"><?= $statistiky['celkem_predmetu'] ?></div>
            <div class="label">Předmětů celkem</div>
        </div>
        <div class="stat-card warn">
            <div class="value"><?= $statistiky['nepokrytych_predmetu'] ?></div>
            <div class="label">Nepokrytých předmětů</div>
        </div>
        <div class="stat-card warn">
            <div class="value"><?= $statistiky['nepokrytych_casti'] ?></div>
            <div class="label">Nepokrytých částí výuky</div>
        </div>
        <div class="stat-card">
            <div class="value">
                <?= $statistiky['procento_pokryti'] === null
                    ? '–'
                    : number_format($statistiky['procento_pokryti'], 1, ',', ' ') . ' %' ?>
            </div>
            <div class="label">Pokrytí předmětů</div>
        </div>
    </div>

    <form method="get" class="filters">
        <label>Fakulta
            <select name="fakulta">
                <option value="">— všechny —</option>
                <?php foreach ($fakulty as $fakulta): ?>
                    <option value="<?= htmlspecialchars($fakulta) ?>"
                        <?= $filters['fakulta'] === (string)$fakulta ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fakulta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Katedra
            <select name="katedra">
                <option value="">— všechny —</option>
                <?php foreach ($katedry as $katedra): ?>
                    <option value="<?= (int)$katedra['idpracoviste'] ?>"
                        <?= $filters['katedra'] === (string)$katedra['idpracoviste'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($katedra['zkratka']) ?>
                        <?= $katedra['nadrazenepracoviste'] ? '(' . htmlspecialchars($katedra['nadrazenepracoviste']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Semestr
            <select name="semestr">
                <option value="">— oba —</option>
                <option value="ZS" <?= $filters['semestr'] === 'ZS' ? 'selected' : '' ?>>ZS</option>
                <option value="LS" <?= $filters['semestr'] === 'LS' ? 'selected' : '' ?>>LS</option>
            </select>
        </label>
        <label>Zkratka
            <input type="search" name="zkratka" value="<?= htmlspecialchars($filters['zkratka']) ?>">
        </label>
        <label>Název
            <input type="search" name="nazev" value="<?= htmlspecialchars($filters['nazev']) ?>">
        </label>
        <button type="submit">Filtrovat</button>
        <a href="nepokryte-predmety.php"
           style="padding:7px 10px; border:1px solid #ccc; border-radius:4px;
                  text-decoration:none; background:white;">Reset</a>
        <a href="export-nepokryte.php?<?= htmlspecialchars(http_build_query($filters)) ?>"
           style="padding:7px 10px; border:1px solid #3c9a69; border-radius:4px;
                  text-decoration:none; background:#e9f6ef; color:#1d5c3c; font-weight:bold;">
            Export do Excelu
        </a>
    </form>

    <?php if (empty($nepokryte)): ?>
        <div style="padding:16px; background:#e9f6ef; border:1px solid #3c9a69;
                    border-radius:8px; color:#1d5c3c;">
            ✓ <strong>Veškerá výuka je pokrytá.</strong>
            Pro zvolený filtr nebyl nalezen žádný předmět s nepokrytou částí výuky.
        </div>
    <?php else: ?>
        <table class="result-table coverage-table">
            <thead>
                <tr>
                    <th>Zkratka</th>
                    <th>Název</th>
                    <th>Semestr</th>
                    <th>Katedra</th>
                    <th>Nepokryté části</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nepokryte as $predmet): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($predmet['zkratka']) ?></strong></td>
                        <td><?= htmlspecialchars($predmet['nazev']) ?></td>
                        <td><?= htmlspecialchars($predmet['semestr'] ?? '') ?></td>
                        <td>
                            <?= htmlspecialchars($predmet['katedra'] ?? '') ?>
                            <?php if (!empty($predmet['fakulta'])): ?>
                                <span style="color:#888;">(<?= htmlspecialchars($predmet['fakulta']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach ($predmet['casti'] as $cast): ?>
                                <span class="part-badge <?= $cast['soucet_podilu'] <= 0 ? 'none' : 'partial' ?>">
                                    <?= htmlspecialchars($typNazvy[$cast['typ']] ?? $cast['typ']) ?>
                                    <?php if (!empty($cast['jazyk_nazev'])): ?>
                                        · <?= htmlspecialchars($cast['jazyk_nazev']) ?>
                                    <?php endif; ?>
                                    – <?= $cast['soucet_podilu'] <= 0
                                        ? 'bez vyučujícího'
                                        : 'pokryto ' . number_format($cast['soucet_podilu'], 0, ',', ' ') . ' %' ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <a href="result-counting.php?idVerze=<?= (int)$idVerze ?>&zkratka=<?= urlencode($predmet['zkratka']) ?>"
                               style="text-decoration:none;">Doplnit →</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'navbar.php'; ?>
</body>
</html>
