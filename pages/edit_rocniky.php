<?php
require_once '../includes/functions_edit_rocniky.php';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Editace počtu studentů</title>
    <link rel="stylesheet" href="../stylepages/stylepage-edit-rocniky.css">
</head>
<body>
<div class="container">
    <h1>Editace počtu studentů</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">Změny byly úspěšně uloženy.</div>
    <?php endif; ?>

    <form method="post" action="../includes/functions_edit_rocniky.php">
        <table>
            <thead>
            <tr>
                <th>Název programu</th>
                <th>Ročník</th>
                <th>Jazyk</th>
                <th>Forma</th>
                <th>Typ</th>
                <th>Počet studentů</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rocnikyData as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nazev_programu']) ?></td>
                    <td><?= $row['rocnik'] ?></td>
                    <td><?= prelozJazyk($row['jazyk']) ?></td>
                    <td><?= prelozFormu($row['idForma']) ?></td>
                    <td><?= prelozTyp($row['typ']) ?></td>
                    <td>
                        <input type="number" name="pocetStudentu[<?= $row['id'] ?>]"
                            value="<?= htmlspecialchars($row['pocetStudentu']) ?>" min="0">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit">Uložit změny</button>
    </form>
</div>
</body>
</html>
