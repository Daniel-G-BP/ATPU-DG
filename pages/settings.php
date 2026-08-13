<?php
ob_start();
require_once __DIR__ . '/../includes/settings-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_workload_coefficients'])) {
        try {
            saveUvazekKoeficienty($_POST['coefficient'] ?? []);
            header("Location: settings.php?workload_saved=1");
            exit;
        } catch (Throwable $e) {
            $workloadError = $e->getMessage();
        }
    }
    if (isset($_POST['save_pretizeni'])) {
        try {
            savePretizeniProcent($_POST['pretizeni_procent'] ?? null);
            header("Location: settings.php?pretizeni_saved=1");
            exit;
        } catch (Throwable $e) {
            $pretizeniError = $e->getMessage();
        }
    }
    if (isset($_POST['save_plny_uvazek'])) {
        try {
            savePlnyUvazekPB($_POST['plny_uvazek_pb'] ?? null);
            header("Location: settings.php?plny_uvazek_saved=1");
            exit;
        } catch (Throwable $e) {
            $plnyUvazekError = $e->getMessage();
        }
    }
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
$workloadCoefficients = getUvazekKoeficienty();
$pretizeniProcent = getPretizeniProcent();
$plnyUvazekPB = getPlnyUvazekPB();
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
        <h2>Hranice přetíženosti</h2>
        <p>
            Vyučující je označen jako <strong>přetížený</strong>, pokud jeho vytíženost
            překročí zde nastavené procento úvazku. Nastavení platí pro aktivní verzi dat.
        </p>

        <?php if (isset($_GET['pretizeni_saved'])): ?>
            <p style="padding:10px; background:#d4edda; border:1px solid #28a745; color:#155724;">
                Hranice přetíženosti byla uložena.
            </p>
        <?php endif; ?>
        <?php if (!empty($pretizeniError)): ?>
            <p style="padding:10px; background:#f8d7da; border:1px solid #dc3545; color:#721c24;">
                <?= htmlspecialchars($pretizeniError) ?>
            </p>
        <?php endif; ?>

        <form method="post">
            <label style="display:inline-flex; align-items:center; gap:8px;">
                Přetížení nad
                <input type="number" name="pretizeni_procent" step="0.1" min="50" max="300"
                       value="<?= htmlspecialchars((string)$pretizeniProcent) ?>" style="width:100px;">
                % úvazku
            </label>
            <button type="submit" name="save_pretizeni">Uložit</button>
            <p style="font-size:.85rem; color:#666; margin-top:6px;">
                Povolený rozsah je 50–300 %. Výchozí hodnota je 110 %.
            </p>
        </form>

        <?php if (isset($_GET['plny_uvazek_saved'])): ?>
            <p style="padding:10px; background:#d4edda; border:1px solid #28a745; color:#155724;">
                Hodnota plného úvazku byla uložena.
            </p>
        <?php endif; ?>
        <?php if (!empty($plnyUvazekError)): ?>
            <p style="padding:10px; background:#f8d7da; border:1px solid #dc3545; color:#721c24;">
                <?= htmlspecialchars($plnyUvazekError) ?>
            </p>
        <?php endif; ?>

        <form method="post" style="margin-top:18px;">
            <label style="display:inline-flex; align-items:center; gap:8px;">
                Plný úvazek
                <input type="number" name="plny_uvazek_pb" step="1" min="1" max="100000"
                       value="<?= htmlspecialchars((string)$plnyUvazekPB) ?>" style="width:110px;">
                PB
            </label>
            <button type="submit" name="save_plny_uvazek">Uložit</button>
            <p style="font-size:.85rem; color:#666; margin-top:6px;">
                Tato hodnota tvoří jmenovatel vytíženosti: např. při 500 PB se 1149 PB zobrazí jako 1149 / 500 PB.
            </p>
        </form>
    </div>

    <div class="section">
        <h2>Koeficienty pracovního úvazku</h2>
        <p>
            Tyto hodnoty jsou zdrojem výpočtu v aplikaci i při exportu do listu
            <strong>Pomocný</strong>. Nastavení platí pro aktivní verzi dat.
        </p>

        <?php if (isset($_GET['workload_saved'])): ?>
            <p style="padding:10px; background:#d4edda; border:1px solid #28a745; color:#155724;">
                Koeficienty byly uloženy.
            </p>
        <?php endif; ?>
        <?php if (!empty($workloadError)): ?>
            <p style="padding:10px; background:#f8d7da; border:1px solid #dc3545; color:#721c24;">
                <?= htmlspecialchars($workloadError) ?>
            </p>
        <?php endif; ?>

        <?php
        $a1Kinds = [
            'standard' => 'Běžný předmět',
            'c' => 'Cizojazyčný',
            'd' => 'Doktorský',
            'dc' => 'Doktorský cizojazyčný',
        ];
        $a1Types = ['P' => 'Přednáška', 'C' => 'Cvičení', 'S' => 'Seminář', 'R' => 'Ateliér'];
        $a2Types = ['KL' => 'Klasifikovaný zápočet', 'ZAP' => 'Zápočet', 'ZK' => 'Zkouška', 'DZ' => 'Dílčí zkouška'];
        ?>

        <form method="post">
            <h3>A.1 – přímá výuka</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Druh předmětu</th>
                        <?php foreach ($a1Types as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($a1Kinds as $kind => $kindLabel): ?>
                        <tr>
                            <th><?= htmlspecialchars($kindLabel) ?></th>
                            <?php foreach ($a1Types as $type => $typeLabel): ?>
                                <td>
                                    <?php if (isset($workloadCoefficients['A1'][$kind][$type])): ?>
                                        <input type="number" step="0.01" min="0"
                                               name="coefficient[A1|<?= $kind ?>|<?= $type ?>]"
                                               value="<?= htmlspecialchars((string)$workloadCoefficients['A1'][$kind][$type]) ?>"
                                               style="width:90px;">
                                    <?php else: ?>
                                        <span title="Použije se koeficient běžného předmětu">výchozí</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>A.2 – zkoušení</h3>
            <table class="table">
                <thead><tr><th>Aktivita</th><th>Koeficient</th></tr></thead>
                <tbody>
                    <?php foreach ($a2Types as $type => $label): ?>
                        <tr>
                            <th><?= htmlspecialchars($label) ?></th>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       name="coefficient[A2|standard|<?= $type ?>]"
                                       value="<?= htmlspecialchars((string)($workloadCoefficients['A2']['standard'][$type] ?? 0)) ?>"
                                       style="width:90px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" name="save_workload_coefficients" class="btn btn-success">
                Uložit koeficienty
            </button>
        </form>
    </div>

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

<?php include 'navbar.php'; ?>
</body>
</html>
