
<?php
require_once __DIR__ . '/../includes/settings-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_titul'])) {
        $newTitul = trim($_POST['new_titul']);
        if ($newTitul !== '') {
            addTitul($newTitul);
        }
        header("Location: settings.php");
        exit;
    }
    if (isset($_POST['delete_titul'])) {
        $deleteId = intval($_POST['delete_id']);
        deleteTitul($deleteId);
        header("Location: settings.php");
        exit;
    }
}

$tituly = getTituly();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Nastavení systému</title>
    <link rel="stylesheet" href="../stylepages/settingspage.css">
    <link rel="stylesheet" href="../stylepage.css"> 
    <script src="../webfunc.js"></script>
</head>
<body>

<div class="content-wrapper">

    <h1>Nastavení systému</h1>

    <div class="section">
        <h2>Správa titulů</h2>
        <form method="post" class="form-inline">
            <input type="text" name="new_titul" placeholder="Nový titul" required class="form-control">
            <button type="submit" name="add_titul" class="btn btn-primary">Přidat titul</button>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Zkratka</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tituly as $titul): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($titul['id']); ?></td>
                        <td><?php echo htmlspecialchars($titul['zkratka']); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?php echo $titul['id']; ?>">
                                <button type="submit" name="delete_titul" class="btn btn-danger" onclick="return confirm('Opravdu smazat titul?');">Smazat</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="navbar" >
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="view.php">View</a></li>
            <li><a href="edit.php">Edit</a></li>
            <li><a href="insert1.php">Insert do DB</a></li>
            <li><a href="result-counting.php">Manuální Editace</a></li>
            <li><a href="overview-ucitele.php">Přehled kantoři</a></li>
            <li><a href="settings.php">Nastavní</a></li>
        </ul>
    </div>
    <button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
</body>
</html>
