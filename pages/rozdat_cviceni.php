<?php
require_once '../includes/functions.php';
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();

$zprava   = null;
$chyba    = null;
$pridano  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provest'])) {
    try {
        $pridano = rozdelitCviceniPodleStudentu($pdo);
        $zprava  = "Hotovo – přidáno $pridano nových skupin cvičení.";
    } catch (Throwable $e) {
        $chyba = 'Chyba: ' . htmlspecialchars($e->getMessage());
    }
}

$plan        = getCviceniRozdeleniNahled($pdo);
$celkemPridat = array_sum(array_column($plan, 'pridat'));

$jazyky = [];
$stmtJ = $pdo->query("SELECT id, zkratka FROM jazyk ORDER BY id");
foreach ($stmtJ->fetchAll(PDO::FETCH_ASSOC) as $j) {
    $jazyky[(int)$j['id']] = $j['zkratka'];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Rozdělení cvičení podle počtu studentů</title>
    <link rel="stylesheet" href="../stylepage.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1   { margin-bottom: 6px; }
        .info { color: #555; margin-bottom: 18px; font-size: .95em; }

        .msg-ok  { background:#d1e7dd; border:1px solid #a3cfbb; padding:10px 16px;
                   border-radius:6px; margin-bottom:16px; color:#0a3622; }
        .msg-err { background:#f8d7da; border:1px solid #f5c2c7; padding:10px 16px;
                   border-radius:6px; margin-bottom:16px; color:#58151c; }

        table  { border-collapse:collapse; width:100%; font-size:.92em; }
        th,td  { border:1px solid #ccc; padding:7px 10px; text-align:left; }
        th     { background:#f0f0f0; }
        tr.ok  { background:#e9f7ef; }
        tr.add { background:#fff8e1; }

        .badge { display:inline-block; border-radius:12px; padding:2px 10px;
                 font-size:.82em; font-weight:bold; }
        .badge-ok  { background:#198754; color:#fff; }
        .badge-add { background:#fd7e14; color:#fff; }

        .btn-run { background:#0d6efd; color:#fff; border:none; padding:10px 22px;
                   border-radius:6px; font-size:1em; cursor:pointer; margin-top:18px; }
        .btn-run:disabled { background:#aaa; cursor:default; }
        .btn-back { margin-top:10px; display:inline-block; color:#0d6efd;
                    text-decoration:none; font-size:.93em; }

        .summary { margin:14px 0 10px; font-size:.97em; }
        .empty   { color:#888; margin-top:30px; font-size:.97em; }
    </style>
</head>
<body>

<h1>Rozdělení cvičení podle počtu studentů</h1>
<p class="info">
    Pro každé cvičení s nastaveným maximem studentů na skupinu (24 / 12) systém spočítá
    <strong>CEIL(počet studentů ÷ max/skupinu)</strong> potřebných skupin
    a přidá chybějící prázdné řádky do přiřazení.
    Stávající přiřazení učitelů zůstanou nedotčena.
</p>

<?php if ($zprava): ?>
    <div class="msg-ok"><?= htmlspecialchars($zprava) ?></div>
<?php endif; ?>
<?php if ($chyba): ?>
    <div class="msg-err"><?= $chyba ?></div>
<?php endif; ?>

<?php if (empty($plan)): ?>
    <p class="empty">
        Žádné cvičení nemá nastaveno maximum studentů na skupinu, nebo nejsou zadány počty studentů v ročnících.<br>
        Nastavte <em>Max. počet</em> u cvičení v <a href="result-counting.php">Manuální editaci</a>
        a počty studentů v <a href="edit_rocniky.php">Editaci ročníků</a>.
    </p>
<?php else: ?>

    <p class="summary">
        Celkem předmětů v přehledu: <strong><?= count($plan) ?></strong> &nbsp;|&nbsp;
        Skupin k přidání: <strong><?= $celkemPridat ?></strong>
    </p>

    <table>
        <thead>
            <tr>
                <th>Předmět</th>
                <th>Semestr</th>
                <th>Jazyk</th>
                <th>Počet studentů</th>
                <th>Max / skupinu</th>
                <th>Potřeba skupin</th>
                <th>Aktuálně skupin</th>
                <th>Přidat</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($plan as $r): ?>
            <?php $trClass = $r['pridat'] > 0 ? 'add' : 'ok'; ?>
            <tr class="<?= $trClass ?>">
                <td>
                    <strong><?= htmlspecialchars($r['zkratka']) ?></strong><br>
                    <small><?= htmlspecialchars($r['nazev']) ?></small>
                </td>
                <td><?= htmlspecialchars($r['semestr'] ?? '—') ?></td>
                <td><?= htmlspecialchars($jazyky[$r['jazyk']] ?? '—') ?></td>
                <td><?= $r['pocet_studentu'] ?></td>
                <td><?= $r['max_na_skupinu'] ?></td>
                <td><?= $r['potreba_skupin'] ?></td>
                <td><?= $r['aktualni_skupiny'] ?></td>
                <td>
                    <?php if ($r['pridat'] > 0): ?>
                        <span class="badge badge-add">+<?= $r['pridat'] ?></span>
                    <?php else: ?>
                        <span class="badge badge-ok">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($celkemPridat > 0): ?>
        <form method="post">
            <button type="submit" name="provest" class="btn-run">
                Provést – přidat <?= $celkemPridat ?> skupin
            </button>
        </form>
    <?php else: ?>
        <p style="margin-top:14px; color:#198754; font-weight:bold;">
            ✓ Všechny předměty mají dostatečný počet skupin cvičení.
        </p>
    <?php endif; ?>

<?php endif; ?>

<a href="../index.php" class="btn-back">← Zpět na hlavní stránku</a>

<div id="navbar" style="display:none;">
    <ul>
        <h1 style="text-align:center;">Menu</h1>
        <li><a href="../index.php">Main</a></li>
        <li><a href="insert1.php">Import dat</a></li>
        <li><a href="result-counting.php">Manuální editace</a></li>
        <li><a href="edit_rocniky.php">Ročníky / studenti</a></li>
        <li><a href="overview-ucitele.php">Přehled kantorů</a></li>
        <li><a href="settings.php">Nastavení</a></li>
    </ul>
</div>
<button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
<script src="../webfunc.js"></script>
</body>
</html>
