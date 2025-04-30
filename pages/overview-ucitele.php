<?php
require_once '../includes/functions-overview-ucitele.php';
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();
$teachers = getTeachersData($pdo);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled učitelů</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css"> 
    <link rel="stylesheet" href="../stylepage.css"> 
    <script src="../webfunc.js"></script>
</head>
<body>
    <div class="main-content">
        <h1>Seznam učitelů</h1>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Příjmení</th>
                    <th>Telefon</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td><?= htmlspecialchars($teacher['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($teacher['surname'] ?? '') ?></td>
                        <td><?= htmlspecialchars($teacher['telefon'] ?? '') ?></td>
                        <td>
                            <button type="button"
                                onclick="window.open(
                                    'uvazek-ucitele.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'UvazekUcitele',
                                    'width=800,height=600,resizable=yes,scrollbars=yes'
                                )">
                                Zobrazit úvazek
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

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
</html>
