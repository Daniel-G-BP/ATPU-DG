<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="../stylepages/stylepage_viewteachares.css"> 
        <script src="webfunc.js"></script>
    </head>

    <body>
        <div>
            <h1>View</h1>
            <p>Here you can view data in our DB.</p>
            <?php
                include '../includes/functions.php';
                include '../includes/dbh.inc.php';
                $pdo = connectToDatabase();
                loadTeachers($pdo);

            ?>
        </div>
    </body>

</html>