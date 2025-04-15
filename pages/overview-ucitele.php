<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
</head>
<body>
<div id="navbar">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="view.php">View</a></li>
            <li><a href="edit.php">Edit</a></li>
            <li><a href="insert1.php">Insert do DB</a></li>
            <li><a href="result-counting.php">Manuální Editace</a></li>
            <li><a href="overview-ucitele.php">Přehled kantoři</a></li>
        </ul>
    </div>

<div class="main-content">
<?php
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();

echo "<h1>Seznam učitelů</h1>";
echo "<ul>";

$stmt = $pdo->query("SELECT id, name, surname FROM teachers ORDER BY surname");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $row['id'];
    $jmeno = htmlspecialchars($row['surname'] . ' ' . $row['name']);
    echo "<li><a href=\"#\" onclick=\"window.open('uvazek-ucitele.php?id=$id', 'Ucitel$id', 'width=1000,height=700'); return false;\">$jmeno</a></li>";

}

echo "</ul>";
?>
</div>

</body>
</html>