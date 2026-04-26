<!-- Tato stránka je nahrazena editací v settings.php (ročníky studijních programů) -->
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Editace počtu studentů</title>
    <link rel="stylesheet" href="../stylepages/stylepage_updatepocetstudentu.css">
    <link rel="stylesheet" href="../stylepage.css">
</head>
<body>
    <div id="navbar">
        <ul>
            <h1 style="text-align:center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="settings.php">Nastavení</a></li>
        </ul>
    </div>
    <div id="content" class="rounded-border">
        <h1>Editace počtu studentů</h1>
        <p>Počty studentů se editují na stránce <a href="settings.php">Nastavení</a> v sekci <em>Ročníky studijních programů</em>.</p>
        <?php
            include_once '../includes/functions.php';
            include_once '../includes/dbh.inc.php';
            $pdo = connectToDatabase();
            $idVerze = getAktivniVerze($pdo);

            if (isset($_POST['update'])) {
                $number   = (int)$_POST['number'];
                $stprIdno = (int)$_POST['stprIdno'];
                $rocnik   = (int)($_POST['rocnik'] ?? 1);

                // BUG FIX: dříve volalo neexistující updateStudentNumber() – nyní přímý UPDATE
                $stmt = $pdo->prepare("
                    UPDATE rocniky_studijniho_programu
                    SET pocetStudentu = ?
                    WHERE stprIdno = ? AND rocnik = ? AND idVerze = ?
                ");
                $stmt->execute([$number, $stprIdno, $rocnik, $idVerze]);

                echo "<p style='color:green;'>✔ Počet studentů aktualizován: "
                    . htmlspecialchars($number, ENT_QUOTES, 'UTF-8')
                    . " (stprIdno=" . htmlspecialchars($stprIdno, ENT_QUOTES, 'UTF-8') . ")</p>";
            }
        ?>

        <form method="post" style="margin-top:20px;">
            <label for="stprIdno">Studijní program:</label>
            <select id="stprIdno" name="stprIdno" required>
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->prepare("SELECT stprIdno, nazev FROM studijniprogram WHERE IdVerze = ? ORDER BY nazev");
                $stmt->execute([$idVerze]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    echo "<option value='" . (int)$p['stprIdno'] . "'>"
                        . htmlspecialchars($p['nazev'], ENT_QUOTES, 'UTF-8') . "</option>";
                }
                ?>
            </select>
            <label for="rocnik">Ročník:</label>
            <input type="number" id="rocnik" name="rocnik" min="1" max="5" value="1" required>
            <label for="number">Počet studentů:</label>
            <input type="number" id="number" name="number" min="0" required>
            <input type="submit" name="update" value="Uložit">
        </form>
    </div>
</body>
</html>
