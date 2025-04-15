<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
</head>
<body>
<div class="main-content">
<?php
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();

$ucitelId = $_GET['id'] ?? null;
if (!$ucitelId) {
    echo "Učitel nenalezen.";
    exit;
}

$stmt = $pdo->prepare("SELECT name, surname FROM teachers WHERE id = ?");
$stmt->execute([$ucitelId]);
$ucitel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ucitel) {
    echo "Učitel nenalezen.";
    exit;
}

echo "<h1>Úvazek: {$ucitel['surname']} {$ucitel['name']}</h1>";

$query = "
SELECT 
    p.zkratka, p.nazev, upp.typ, j.popis AS jazyk, upp.podil,
    ph.pocetJednotekPrednaska, ph.pocetJednotekCviceni, ph.pocetJednotekSeminar
FROM ucitelpredmetprirazeni upp
JOIN predmet p ON upp.predmetid = p.id
LEFT JOIN jazyk j ON upp.jazyk = j.id
LEFT JOIN predmet_hodiny ph ON p.id = ph.predmetid
WHERE upp.teacherid = ? AND upp.IdVerze = (SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze')
ORDER BY p.zkratka, upp.typ";

$stmt = $pdo->prepare($query);
$stmt->execute([$ucitelId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Předmět</th><th>Typ</th><th>Jazyk</th><th>Hodin týdně</th><th>Podíl (%)</th><th>Reálný úvazek</th></tr>";

foreach ($data as $row) {
    $hodin = 0;
    switch ($row['typ']) {
        case 'P': $hodin = $row['pocetJednotekPrednaska']; break;
        case 'C': $hodin = $row['pocetJednotekCviceni']; break;
        case 'S': $hodin = $row['pocetJednotekSeminar']; break;
    }
    $hodin = floatval($hodin);
    $uvazek = round(($hodin * $row['podil']) / 100.0, 2);

    echo "<tr>
        <td>{$row['zkratka']} – {$row['nazev']}</td>
        <td>{$row['typ']}</td>
        <td>{$row['jazyk']}</td>
        <td>{$hodin}</td>
        <td>{$row['podil']}</td>
        <td><strong>{$uvazek}</strong></td>
    </tr>";
}

echo "</table>";
?>
</div>
</body>
</html>