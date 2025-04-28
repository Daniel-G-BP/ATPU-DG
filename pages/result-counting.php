<?php
require_once '../includes/dbh.inc.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css"> 
    <link rel="stylesheet" href="../stylepage.css"> 
    <script src="../webfunc.js"></script>
</head>

<body>
<?php
$pdo = connectToDatabase();

echo "<form method='post' action='../includes/functions-result-counting.php'>";
echo "<table border='1' class='editable-table'>";
echo "<tr><th>Předmět</th><th>Zkratka</th><th>Typ</th><th>Podíl (%)</th><th>Jazyk</th><th>Učitel</th><th>Změnit učitele</th><th>Akce</th></tr>";


$stmt = $pdo->prepare("SELECT upp.id, p.id AS predmetid, p.nazev, p.zkratka, t.ucitIdno, t.name, t.surname, upp.typ, upp.podil, upp.jazyk as jazykid
                       FROM predmet p
                       JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
                       LEFT JOIN teachers t ON upp.teacherid = t.id
                       WHERE upp.IdVerze = (SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze')
                       ORDER BY p.zkratka, upp.id");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Načti všechny učitele a jazyky pro dropdown
$teachers = $pdo->query("SELECT ucitIdno, name, surname FROM teachers ORDER BY surname")->fetchAll(PDO::FETCH_ASSOC);
$jazyky = $pdo->query("SELECT id, popis FROM jazyk ORDER BY popis")->fetchAll(PDO::FETCH_ASSOC);

foreach ($assignments as $row) {
    $assignmentId = $row['id'];
    echo "<tr>";
    echo "<td>{$row['nazev']}</td>";
    echo "<td>{$row['zkratka']}</td>";
    echo "<td>
            <select name='typ[{$assignmentId}]'>
                <option value='P' " . ($row['typ'] === 'P' ? "selected" : "") . ">Přednáška</option>
                <option value='C' " . ($row['typ'] === 'C' ? "selected" : "") . ">Cvičení</option>
                <option value='S' " . ($row['typ'] === 'S' ? "selected" : "") . ">Seminář</option>
            </select>
          </td>";
    echo "<td><input type='number' name='podil[{$assignmentId}]' value='{$row['podil']}' min='0' max='100' step='0.01'></td>";

    echo "<td>
            <select name='jazyk[{$assignmentId}]'>";
        foreach ($jazyky as $jazyk) {
            $selected = ($jazyk['id'] == $row['jazykid']) ? "selected" : "";
            echo "<option value='{$jazyk['id']}' $selected>{$jazyk['popis']}</option>";
    }
    echo "</select></td>";

    echo "<td>{$row['surname']} {$row['name']}</td>";
    echo "<td><select name='ucitel[{$assignmentId}]'>";
    foreach ($teachers as $t) {
        $selected = ($t['ucitIdno'] == $row['ucitIdno']) ? "selected" : "";
        echo "<option value='{$t['ucitIdno']}' $selected>{$t['surname']} {$t['name']}</option>";
    }
    echo "</select></td>";
    echo "<td>
            <button type='submit' name='update[{$assignmentId}]'>Uložit</button>
            <button type='submit' name='odebrat[{$assignmentId}]'>Odebrat</button>
            <button type='submit' name='smazat[{$assignmentId}]'>Smazat řádek</button>
            <button type='submit' name='kopirovat[{$assignmentId}]'>Kopírovat řádek</button>
          </td>";
    echo "</tr>";
}
echo "</table>";
echo "</form>";
?>

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

</body>
