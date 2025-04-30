<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Vložit externistu</title>
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
</head>
<body>
    <div class="main-content" align="center">
        <h1>Vložit nového externistu</h1>

        <?php
        include_once '../includes/functions.php';
        include_once '../includes/dbh.inc.php';
        $pdo = connectToDatabase();

        $stmt = $pdo->query("SELECT id, zkratka FROM cistituly ORDER BY zkratka ASC");
        $tituly = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (isset($_POST['send'])) {
            $name = $_POST["name"];
            $surname = $_POST["surname"];
            $email = $_POST["email"];
            $phone = $_POST["phone"];
            $other = $_POST["other"];
            $titulId = intval($_POST['titul'] ?? 0);

            $ucitIdno = -getSetUcitIdnoExternista($pdo); //externistu poznáme podle záporného ucitidno
            $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
            $stmtVerze->execute();
            $idVerze = $stmtVerze->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO teachers (name, surname, ucitIdno, IdVerze, idCisTituly) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $surname, $ucitIdno, $idVerze, $titulId]);
            $stmt = $pdo->prepare("INSERT INTO kontakt (idTeacher, email, telefon, poznamka, IdVerze) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ucitIdno, $email, $phone, $other, $idVerze]);

            echo "<p style='color:green; font-weight:bold;'>Externista <strong>$name $surname</strong> byl úspěšně vložen.</p>";
        }
        ?>

        <form method="post" class="editable-table" style="max-width: 400px; margin-top: 30px;">
            <table>
                <select name="titul" id="titul">
                    <option value="">-- vyberte titul --</option>
                    <?php foreach ($tituly as $titulOption): ?>
                        <option value="<?php echo htmlspecialchars($titulOption['id']); ?>"
                            <?php if (!empty($_POST['titul']) && intval($_POST['titul']) === $titulOption['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($titulOption['zkratka']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <tr><td><label for="name">Jméno:</label></td>
                    <td><input type="text" id="name" name="name" required></td></tr>
                <tr><td><label for="surname">Příjmení:</label></td>
                    <td><input type="text" id="surname" name="surname" required></td></tr>
                <tr><td><label for="surname">Email:</label></td>
                    <td><input type="text" id="email" name="email" required></td></tr>
                <tr><td><label for="surname">Telefon:</label></td>
                    <td><input type="text" id="phone" name="phone" required></td></tr>
                <tr><td><label for="surname">Jiné:</label></td>
                    <td><input type="text" id="other" name="other" required></td></tr>
                <tr><td colspan="2" style="text-align:center;">
                    <button type="submit" name="send" value="Vytvořit" class="button">Vytvořit</button>
                </td></tr>
            </table>
        </form>
    </div>
</body>
</html>