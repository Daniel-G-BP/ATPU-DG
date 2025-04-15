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

        if (isset($_POST['send'])) {
            $name = $_POST["name"];
            $surname = $_POST["surname"];
            $ucitIdno = -getSetUcitIdnoExternista($pdo); //externistu poznáme podle záporného ucitidno
            $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
            $stmtVerze->execute();
            $verze = $stmtVerze->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO teachers (name, surname, ucitIdno, IdVerze) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $surname, $ucitIdno, $verze]);

            echo "<p style='color:green; font-weight:bold;'>Externista <strong>$name $surname</strong> byl úspěšně vložen.</p>";
        }
        ?>

        <form method="post" class="editable-table" style="max-width: 400px; margin-top: 30px;">
            <table>
                <tr><td><label for="name">Jméno:</label></td>
                    <td><input type="text" id="name" name="name" required></td></tr>
                <tr><td><label for="surname">Příjmení:</label></td>
                    <td><input type="text" id="surname" name="surname" required></td></tr>
                <tr><td colspan="2" style="text-align:center;">
                    <button type="submit" name="send" value="Vytvořit" class="button">Vytvořit</button>
                </td></tr>
            </table>
        </form>
    </div>
</body>
</html>