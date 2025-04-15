<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../stylepages/stylepage_updatepocetstudentu.css"> 
</head>
<body>
    <div class="container">
        <h1>Edit</h1>
        <p>Here you can edit number of students</p>

        <?php
            include_once '../includes/functions.php';
            include_once '../includes/dbh.inc.php';
            $pdo = connectToDatabase();
            if(isset($_POST['update'])){
                $number = $_POST["number"];
                $stprIdno = $_POST["stprIdno"];
                updateStudentNumber($pdo, $number, $stprIdno);  //dodelat
                echo "Succesfully updated number of students: " . $number . " for stprIdno: " . $stprIdno;
            }
        ?>
        
        <!-- formular pro pocet studentu update -->
        <form method="post">
            <label for="studijni_program">Vyberte studijní program u kterého chcete upravit počet studentů:</label>
            <select id="stprIdno" name="stprIdno">
                <!-- Option for selection -->
                <option value="">Vyberte...</option>
                <?php
                $pdo = connectToDatabase();
                $stmt = $pdo->query("SELECT stprIdno, nazev FROM studijniprogram");
                $studijniProgramy = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($studijniProgramy as $program) {
                    echo "<option value='{$program['stprIdno']}'>{$program['nazev']}</option>";
                }
                ?>       
            </select>
            <!-- Vstupní pole pro zadání počtu studentů -->
            <label for="student_count">Počet studentů:</label>
            <input type="number" id="number" name="number" required>

            <!-- Tlačítko upravit -->
            <input type="submit"  name="update" value="Upravit">
        </form>
    </div>
    <a href=../index.php> back to main </a>
</body>
</html>
