<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';
$pdo = connectToDatabase();

// Získání a kontrola ID učitele z URL
$ucitelId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$ucitelId) {
    echo "<p>Neplatné nebo chybějící ID učitele.</p>";
    exit;
}

// Získání aktivní verze
$verzeId = $pdo->query("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'")->fetchColumn();
if (!$verzeId) {
    echo "<p>Chybí aktivní verze v tabulce 'nastaveni'.</p>";
    exit;
}

// Načtení údajů o učiteli
$ucitelDotaz = $pdo->prepare("SELECT name, surname FROM teachers WHERE id = ?");
$ucitelDotaz->execute([$ucitelId]);
$ucitel = $ucitelDotaz->fetch();

if (!$ucitel) {
    echo "<p>Učitel s ID $ucitelId nebyl nalezen.</p>";
    exit;
}

// Respektuje globální nastavení ZahrnoutAJ
$zahrnoutAJ = getZahrnoutAJ($pdo);
$ajCondition = $zahrnoutAJ ? '' : 'AND upp.jazyk != 2';

// Načtení dat o úvazku
$sql = "
SELECT
    p.zkratka,
    p.nazev,
    upp.typ,
    j.popis AS jazyk,
    upp.podil,
    ph.pocetJednotekPrednaska,
    ph.pocetJednotekCviceni,
    ph.pocetJednotekSeminar
FROM ucitelpredmetprirazeni upp
JOIN predmet p ON upp.predmetid = p.id
LEFT JOIN jazyk j ON upp.jazyk = j.id
LEFT JOIN predmet_hodiny ph ON p.id = ph.predmetid
WHERE upp.teacherid = ? AND upp.IdVerze = ?
  $ajCondition
ORDER BY p.zkratka, upp.typ
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$ucitelId, $verzeId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Úvazek učitele</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
</head>
<body>
    <div class="main-content">
        <h1>Úvazek učitele: <?= htmlspecialchars($ucitel['name']) . ' ' . htmlspecialchars($ucitel['surname']) ?></h1>

        <?php if (!$zahrnoutAJ): ?>
            <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:6px;
                        padding:7px 14px; margin-bottom:12px; font-size:.9em; color:#7a5a00;">
                ⚠ <strong>Anglické výuky nejsou zahrnuty</strong> — zobrazeny jen česky vyučované předměty.
            </div>
        <?php endif; ?>

        <?php if (empty($data)): ?>
            <p>Pro tohoto učitele nebyl nalezen žádný úvazek.</p>
        <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Zkratka</th>
                        <th>Název</th>
                        <th>Typ</th>
                        <th>Jazyk</th>
                        <th>Podíl</th>
                        <th>Přednáška</th>
                        <th>Cvičení</th>
                        <th>Seminář</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['zkratka']) ?></td>
                            <td><?= htmlspecialchars($row['nazev']) ?></td>
                            <td><?= htmlspecialchars($row['typ']) ?></td>
                            <td><?= htmlspecialchars($row['jazyk'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['podil']) ?></td>
                            <td><?= $row['typ'] === 'P' ? htmlspecialchars($row['pocetJednotekPrednaska']) : 0 ?></td>
                            <td><?= $row['typ'] === 'C' ? htmlspecialchars($row['pocetJednotekCviceni'])   : 0 ?></td>
                            <td><?= $row['typ'] === 'S' ? htmlspecialchars($row['pocetJednotekSeminar'])   : 0 ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
