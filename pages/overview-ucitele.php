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
                        <!-- <td>
                            <button type="button"
                                onclick="window.open(
                                    'uvazek-ucitele.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'UvazekUcitele',
                                    'width=800,height=600,resizable=yes,scrollbars=yes'
                                )">
                                Zobrazit úvazek
                            </button>
                            <form method="get" action="/pages/export-uvazek.php" style="display:inline;">
                                <input type="hidden" name="teacherId" value="<?= (int)$teacher['id_ucitel'] ?>">
                                <button type="submit">Export Excel</button>
                            </form>
                        </td> -->
                        <td>
                            <button type="button"
                                onclick="window.open(
                                    'uvazek-ucitele.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'UvazekUcitele',
                                    'width=800,height=600,resizable=yes,scrollbars=yes'
                                )">
                                Zobrazit úvazek
                            </button>

                            <button type="button"
                                onclick="window.open(
                                    'edit-ucitel.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'EditUcitel',
                                    'width=700,height=500,resizable=yes,scrollbars=yes'
                                )">
                                Detail / editace
                            </button>

                            <form method="get" action="/pages/export-uvazek.php" style="display:inline;">
                                <input type="hidden" name="teacherId" value="<?= (int)$teacher['id_ucitel'] ?>">
                                <button type="submit">Export Excel</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php include 'navbar.php'; ?>

</body>
</html>
