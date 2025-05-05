<?php
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();

$jazyky = $pdo->query("SELECT * FROM jazyk")->fetchAll(PDO::FETCH_ASSOC);
$ucitele = $pdo->query("SELECT * FROM teachers order by name, surname")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT upp.id, p.nazev, p.zkratka, t.name, t.surname,
           upp.typ, upp.podil, upp.jazyk as jazykid,
           upp.max_pocet_studentu, upp.teacherid
    FROM predmet p
    JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
    LEFT JOIN teachers t ON upp.teacherid = t.id
    WHERE upp.IdVerze = (SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze')
    ORDER BY p.zkratka, upp.id
");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$barvaPozadi = [
    'P' => '#d1e7dd',
    'C' => '#fce7cf',
    'S' => '#cff4fc'
];
?>

<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Počítání výsledků</title>
  <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
  <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css"> 
  <link rel="stylesheet" href="../stylepage.css"> 
  <script src="../webfunc.js"></script>
</head>
<body>

    <h1>Počítání výsledků</h1>

    <table class="editable-table">
    <thead>
        <tr>
        <th>Předmět</th>
        <th>Zkratka</th>
        <th>Typ výuky</th>
        <th>Podíl</th>
        <th>Jazyk</th>
        <th>Učitel</th>
        <th>Max. počet</th>
        <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assignments as $row): ?>
        <tr style="background-color: <?= $barvaPozadi[$row['typ']] ?? '' ?>">
            <form method="POST" action="../includes/functions-result-counting.php">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">

            <td><?= htmlspecialchars($row['nazev']) ?></td>
            <td><?= htmlspecialchars($row['zkratka']) ?></td>

            <td>
                <select name="typ" class="typ-vyuky">
                <option value="P" <?= $row['typ'] === 'P' ? 'selected' : '' ?>>Přednáška</option>
                <option value="C" <?= $row['typ'] === 'C' ? 'selected' : '' ?>>Cvičení</option>
                <option value="S" <?= $row['typ'] === 'S' ? 'selected' : '' ?>>Seminář</option>
                </select>
            </td>

            <td>
                <input type="number" name="podil" value="<?= $row['podil'] ?>" min="0" max="100" step="0.01">
            </td>

            <td>
                <select name="jazyk">
                <?php foreach ($jazyky as $jazyk): ?>
                    <option value="<?= $jazyk['id'] ?>" <?= $jazyk['id'] == $row['jazykid'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($jazyk['popis']) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </td>

            <td>
                <select name="ucitel">
                <?php foreach ($ucitele as $ucitel): ?>
                    <option value="<?= $ucitel['id'] ?>" <?= $ucitel['id'] == $row['teacherid'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ucitel['name'] . ' ' . $ucitel['surname']) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </td>

            <td>
                <select name="max_studentu" class="max-studentu-select" <?= $row['typ'] !== 'C' ? 'disabled' : '' ?>>
                <option value="24" <?= $row['max_pocet_studentu'] == 24 ? 'selected' : '' ?>>24</option>
                <option value="12" <?= $row['max_pocet_studentu'] == 12 ? 'selected' : '' ?>>12</option>
                <option value="1"  <?= $row['max_pocet_studentu'] == 1  ? 'selected' : '' ?>>X</option>
                </select>
            </td>

            <td>
                <button type="submit" name="action" value="update">Uložit</button>
                <button type="submit" name="action" value="odebrat" id="odebrat">Odebrat</button>
                <button type="submit" name="action" value="smazat" id="smazat">Smazat</button>
                <button type="submit" name="action" value="kopirovat">Kopírovat</button>
            </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </tbody>
    </table>



    <div id="navbar" style="display: none;">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="view.php">View</a></li>
            <li><a href="edit.php">Edit</a></li>
            <li><a href="insert1.php">Insert do DB</a></li>
            <li><a href="result-counting.php">Manuální Editace</a></li>
            <li><a href="overview-ucitele.php">Přehled kantoři</a></li>
            <li><a href="settings.php">Nastavní</a></li>
        </ul>
    </div>
    <button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
    <script src="../js/result-counting.js"></script>
</body>
</html>
