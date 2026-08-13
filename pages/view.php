<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Sestavy</title>
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/main-menu.php'; ?>

    <div id="navbar">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <?php renderMainMenuItems('../index.php'); ?>
        </ul>
    </div>

    <div id="content" class="rounded-border">
        <h1>Sestavy</h1>
        <ul>
            <li><a href="viewprograms.php">Sestava Studijní Programy</a></li>
        </ul>
    </div>

</body>
</html>
