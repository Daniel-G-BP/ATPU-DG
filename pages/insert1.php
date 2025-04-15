<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="..stylepages/stylepage.css">  
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
            <li><a href="overview-ucitele.php">Přehled učitelé</a></li>
        </ul>
    </div>

    <div id="content" class="rounded-border">
        <?php
        include_once '../includes/functions.php';
        include_once '../includes/dbh.inc.php';
        $pdo = connectToDatabase();


         // Načtení aktuální verze
        $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmt->execute();
        $currentVersion = $stmt->fetchColumn();

        // Vytvoření nové verze a její nastavení jako aktivní
    if (isset($_POST['create_version']) && !empty($_POST['verze_nazev'])) {
        $verzeNazev = $_POST['verze_nazev'];
        $stmt = $pdo->prepare("INSERT INTO verze (Nazev, Datum) VALUES (?, NOW())");
        $stmt->execute([$verzeNazev]);
        $newVerzeId = $pdo->lastInsertId();

        // Nastavit jako aktivní
        $stmt = $pdo->prepare("UPDATE nastaveni SET Hodnota = ? WHERE Nazev = 'AktivniVerze'");
        $stmt->execute([$newVerzeId]);

        echo "<p style='color: green;'>Vytvořena a nastavena nová verze: <strong>$verzeNazev</strong></p>";
}

        
        if(isset($_POST['load'])){
            $fakulta = $_POST['fakulta'];
            getYear($pdo);
            getKatedry($pdo, $fakulta);
            getStudijniProgram($pdo, $fakulta);
        }

        if(isset($_POST['predmety'])){
            $katedra = $_POST['katedra'];
            setKatedra($pdo, $katedra);
            //gerRozvrhoveAkceLastYearKatedra($pdo);
            getPredmetyByKatedra($pdo, $katedra);
            getPredmetyByKatedraLast($pdo, $katedra);
            getUcitele($pdo);
            teachedlastyear($pdo);
            gerRozvrhoveAkceLastYearKatedra($pdo); // musi byt spusteny za getUcitele
            //insertTeacherAssingByLastYear($pdo);
            assignTeachersFromRozvrh($pdo);
        }

        if(isset($_POST['x'])){
            $rok = $_POST['rok'];
            aktualnirok($pdo, $rok);
        }

        if(isset($_POST['semestr'])){
            $semestr = $_POST['semestr'];
            aktualniSemestr($pdo, $semestr);
        }

        if(isset($_POST['oninit'])){
            onInit($pdo);
        }

        if(isset($_POST['set_version'])){
            vyber_verzi($pdo);
        }

        //pro test
        if(isset($_POST['RozvrhAkce'])){
            assignTeachersFromRozvrh($pdo);
        }
        
        
        ?>

        

        <h1>INSERT STAG</h1>

        <form method="post">
            <p>Vytvořit novou verzi:</p>
            <input type="text" name="verze_nazev" placeholder="Název nové verze" required>
            <input type="submit" name="create_version" value="Vytvořit a nastavit">
        </form>

        <form method="post">
            <p>Vyberte aktivní verzi:</p>
            <select name="verze">
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->query("SELECT IdVerze, Nazev, Datum FROM verze ORDER BY Datum DESC");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                    $selected = ($v['IdVerze'] == $currentVersion) ? "selected" : "";
                    echo "<option value='{$v['IdVerze']}' $selected>{$v['Nazev']} ({$v['Datum']})</option>";
                }
                ?>
            </select>
            <input type="submit" name="set_version" value="Zvolit">
        </form>

        <?php
        if ($currentVersion) {
            $stmt = $pdo->prepare("SELECT Nazev, Datum FROM verze WHERE IdVerze = ?");
            $stmt->execute([$currentVersion]);
            if ($verze = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<p style='color: darkred;'><strong>Aktuální verze:</strong> {$verze['Nazev']} ({$verze['Datum']})</p>";
            }
        }
        ?>

        <!-- <p>Začít/začít od začátku:</p>
        <form method="post">
            <input type="submit" name="oninit" value="load">
        </form> -->

        <p>Vyberte akademický rok:</p>
        <form method="post">
            <select name="rok">
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->query("SELECT rok, akademickyrok FROM roky");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rok) {
                    echo "<option value='{$rok['rok']}'>{$rok['akademickyrok']}</option>";
                }
                ?>
            </select>
            <input type="submit" name="x" value="Zvolit">
        </form>

        <p>Vyberte semestr:</p>
        <form method="post">
            <select name="semestr">
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->query("SELECT semestr FROM semestr");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $semestr) {
                    echo "<option value='{$semestr['semestr']}'>{$semestr['semestr']}</option>";
                }
                ?>
            </select>
            <input type="submit" name="semestr_submit" value="Zvolit">
        </form>

        <p>Načíst katedry fakulty:</p>
        <form method="post">
            <select name="fakulta">
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->query("SELECT zkratka FROM cisfakulta");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $zkratka) {
                    echo "<option value='{$zkratka['zkratka']}'>{$zkratka['zkratka']}</option>";
                }
                ?>
            </select>
            <input type="submit" name="load" value="Zvolit">
        </form>

        <p>Načíst předměty katedry:</p>
        <form method="post">
            <select name="katedra">
                <option value="">Vyberte...</option>
                <?php
                $stmt = $pdo->query("SELECT zkratka, nazev FROM pracoviste 
                WHERE idverze=(SELECT hodnota FROM nastaveni WHERE nazev='AktivniVerze') 
                ORDER BY zkratka");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $zkratka) {
                    echo "<option value='{$zkratka['zkratka']}'>{$zkratka['zkratka']} - {$zkratka['nazev']}</option>";
                }
                ?>
            </select>
            <input type="submit" name="predmety" value="Zvolit">
        </form>

        <!-- Pro test:

        <form method="post">
            <input type="submit" name="RozvrhAkce" value="RozvrhAkce">
        </form> -->


    </div>



</body>
</html>
