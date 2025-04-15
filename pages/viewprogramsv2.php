<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../stylepages/stylepage_viewprograms.css"> 
        <script src="../webfunc.js"></script>
    </head>

    <body>

        <h1>View</h1>
        <p>Here you can view data in our DB.</p>
        <?php
            include '../includes/functions.php';
            include '../includes/dbh.inc.php';
            $pdo = connectToDatabase();
            loadStudijniProgramyV2($pdo);

        ?>

        <button onclick="exportToExcel()">Export to Excel</button>

        
        <a href=../index.php> back to main </a>
    </body>

</html>