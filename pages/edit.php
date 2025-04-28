<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../stylepage.css"> 
    <script src="../webfunc.js"></script>
    <style>
        .rounded-border {
            margin: 30px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 15px;
            background-color: #f9f9f9;
        }

        .rounded-border a {
            color: #007BFF;
            text-decoration: none;
        }

        .rounded-border a:hover {
            text-decoration: underline;
        }
    </style>
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
            <li><a href="sttings.php">Nastavní</a></li>
        </ul>
    </div>
    <div class="rounded-border">
        <h2>Editace dat v databázi</h2>
        <ul>
            <!-- <li><a href="editpocetstudentu.php" target="_blank">Edit Počet Studentů</a></li> -->
            <li><a href="#" onclick="window.open('editpocetstudentu.php', 'Pocet studentu', 'width=600,height=500'); return false;">Pocet studentu</a>
            <li><a href="#" onclick="window.open('insertExternista.php', 'Externista', 'width=600,height=500'); return false;">Přidat externistu</a>
            </li>
        </ul>
    </div>
</body>
</html>
