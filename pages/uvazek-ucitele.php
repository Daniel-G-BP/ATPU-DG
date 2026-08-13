<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';
require_once '../includes/workload-functions.php';
$pdo = connectToDatabase();

// Získání a kontrola ID učitele z URL
$ucitelId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$ucitelId) {
    echo "<p>Neplatné nebo chybějící ID učitele.</p>";
    exit;
}

// Získání aktivní verze
$verzeId = $pdo->query("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'")->fetchColumn();
if (!$verzeId) {
    echo "<p>Chybí aktivní verze v tabulce 'nastaveni'.</p>";
    exit;
}

// Načtení údajů o učiteli
$ucitelDotaz = $pdo->prepare("SELECT name, surname FROM teachers WHERE id = ?");
$ucitelDotaz->execute([$ucitelId]);
$ucitel = $ucitelDotaz->fetch();

if (!$ucitel) {
    echo "<p>Učitel s ID $ucitelId nebyl nalezen.</p>";
    exit;
}

// Respektuje globální nastavení ZahrnoutAJ
$zahrnoutAJ = getZahrnoutAJ($pdo);

// BUG FIX: dříve zde bylo vlastní SQL, které přepisovalo číselné upp.jazyk
// textovým popisem (j.popis AS jazyk). resolveWorkloadKind() pak z "Angličtina"
// udělalo (int)0, takže cizojazyčné řádky dostaly standardní koeficient a detail
// se rozcházel se souhrnem i s exportem. Nyní se používá sdílená funkce.
$data = getTeacherAssignmentDetailRows($pdo, $ucitelId, (int)$verzeId);
$workload = calculateTeacherWorkload($pdo, $ucitelId, (int)$verzeId);
$soucetPb = round(array_sum(array_column($data, 'pb')), 2);
$a2Data = getTeacherA2ExportRows($pdo, $ucitelId, (int)$verzeId);
$a2Koeficienty = getWorkloadCoefficients($pdo, (int)$verzeId)['A2']['standard'] ?? [];
$soucetA2Pb = 0.0;
foreach ($a2Data as &$a2Row) {
    $a2Row['pb'] = round(
        (int)$a2Row['pocet_kl'] * (float)($a2Koeficienty['KL'] ?? 0) +
        (int)$a2Row['pocet_zap'] * (float)($a2Koeficienty['ZAP'] ?? 0) +
        (int)$a2Row['pocet_zk'] * (float)($a2Koeficienty['ZK'] ?? 0) +
        (int)$a2Row['pocet_dz'] * (float)($a2Koeficienty['DZ'] ?? 0),
        2
    );
    $soucetA2Pb += $a2Row['pb'];
}
unset($a2Row);
$soucetA2Pb = round($soucetA2Pb, 2);

function buildA1EditLink(array $row, int $ucitelId, int $verzeId): string
{
    return 'result-counting.php?' . http_build_query(array_filter([
        'idVerze' => $verzeId,
        'zkratka' => (string)($row['zkratka'] ?? ''),
        'ucitel' => $ucitelId,
        'semestr' => (string)($row['semestr'] ?? ''),
    ], static fn($value) => $value !== ''));
}

function buildA2EditLink(array $row, int $ucitelId): string
{
    return 'zkouseni.php?' . http_build_query(array_filter([
        'ucitel' => $ucitelId,
        'zkratka' => (string)($row['zkratka'] ?? ''),
        'semestr' => (string)($row['semestr'] ?? ''),
    ], static fn($value) => $value !== ''));
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Úvazek učitele</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <style>
        .row-action {
            display: inline-block;
            white-space: nowrap;
            text-decoration: none;
            color: #0b5cad;
            font-weight: 600;
        }
        .row-action:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <h1>Úvazek učitele: <?= htmlspecialchars($ucitel['name']) . ' ' . htmlspecialchars($ucitel['surname']) ?></h1>

        <div style="padding:14px; margin-bottom:16px; background:#f7fafb; border:1px solid #cfd8dc; border-radius:8px;">
            <strong style="font-size:1.15rem;">
                Celkem <?= number_format($workload['total'], 2, ',', ' ') ?> /
                <?= number_format($workload['capacity'], 0, ',', ' ') ?> PB
                (<?= $workload['percent'] === null ? 'bez kapacity' : number_format($workload['percent'], 1, ',', ' ') . ' %' ?>)
            </strong><br>
            A.1 přímá výuka: <?= number_format($workload['a1'], 2, ',', ' ') ?> PB ·
            A.2 zkoušení: <?= number_format($workload['a2'], 2, ',', ' ') ?> PB ·
            Ostatní činnosti: <?= number_format($workload['a3'] + $workload['b'] + $workload['c'] + $workload['d'], 2, ',', ' ') ?> PB
        </div>

        <?php if (!$zahrnoutAJ): ?>
            <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:6px;
                        padding:7px 14px; margin-bottom:12px; font-size:.9em; color:#7a5a00;">
                ⚠ <strong>Anglické výuky nejsou zahrnuty</strong> — zobrazeny jen česky vyučované předměty.
            </div>
        <?php endif; ?>

        <h2>A.1 Přímá výuka</h2>

        <?php if (empty($data)): ?>
            <p>Pro tohoto učitele nebyla nalezena žádná přímá výuka.</p>
        <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Zkratka</th>
                        <th>Název</th>
                        <th>Typ</th>
                        <th>Jazyk</th>
                        <th>Podíl</th>
                        <th>Přednáška</th>
                        <th>Cvičení</th>
                        <th>Seminář</th>
                        <th>PB</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['zkratka']) ?></td>
                            <td><?= htmlspecialchars($row['nazev']) ?></td>
                            <td><?= htmlspecialchars($row['typ']) ?></td>
                            <td><?= htmlspecialchars($row['jazyk_nazev'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['podil']) ?></td>
                            <td><?= $row['typ'] === 'P' ? htmlspecialchars($row['pocetJednotekPrednaska']) : 0 ?></td>
                            <td><?= $row['typ'] === 'C' ? htmlspecialchars($row['pocetJednotekCviceni'])   : 0 ?></td>
                            <td><?= $row['typ'] === 'S' ? htmlspecialchars($row['pocetJednotekSeminar'])   : 0 ?></td>
                            <td><strong><?= number_format($row['pb'], 2, ',', ' ') ?></strong></td>
                            <td>
                                <a class="row-action" href="<?= htmlspecialchars(buildA1EditLink($row, $ucitelId, (int)$verzeId)) ?>">
                                    Manuální editace →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#eef3f6; font-weight:bold;">
                        <td colspan="8" style="text-align:right;">Součet A.1 z řádků výše:</td>
                        <td><?= number_format($soucetPb, 2, ',', ' ') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <?php if (abs($soucetPb - $workload['a1']) > 0.02): ?>
                <div style="background:#f8d7da; border:1px solid #dc3545; border-radius:6px;
                            padding:10px 14px; margin-top:12px; color:#721c24;">
                    ⚠ <strong>Nesoulad výpočtu:</strong> součet řádků
                    (<?= number_format($soucetPb, 2, ',', ' ') ?> PB) neodpovídá souhrnu A.1
                    (<?= number_format($workload['a1'], 2, ',', ' ') ?> PB).
                    Rozdíl <?= number_format(abs($soucetPb - $workload['a1']), 2, ',', ' ') ?> PB –
                    zkontrolujte koeficienty a druh předmětu u jednotlivých řádků.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <h2>A.2 Zkoušení</h2>

        <?php if (empty($a2Data)): ?>
            <p>Pro tohoto učitele nebyly nalezeny žádné záznamy zkoušení.</p>
        <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Zkratka</th>
                        <th>Název</th>
                        <th>Semestr</th>
                        <th>Typ ukončení</th>
                        <th title="Klasifikovaný zápočet">KL</th>
                        <th title="Zápočet">ZAP</th>
                        <th title="Zkouška">ZK</th>
                        <th title="Dílčí zkouška">DZ</th>
                        <th>PB</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($a2Data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['zkratka']) ?></td>
                            <td><?= htmlspecialchars($row['nazev'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['semestr'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['typZkousky'] ?? '') ?></td>
                            <td><?= (int)$row['pocet_kl'] ?></td>
                            <td><?= (int)$row['pocet_zap'] ?></td>
                            <td><?= (int)$row['pocet_zk'] ?></td>
                            <td><?= (int)$row['pocet_dz'] ?></td>
                            <td><strong><?= number_format($row['pb'], 2, ',', ' ') ?></strong></td>
                            <td>
                                <a class="row-action" href="<?= htmlspecialchars(buildA2EditLink($row, $ucitelId)) ?>">
                                    Zkoušení →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#eef3f6; font-weight:bold;">
                        <td colspan="8" style="text-align:right;">Součet A.2 z řádků výše:</td>
                        <td><?= number_format($soucetA2Pb, 2, ',', ' ') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <?php if (abs($soucetA2Pb - $workload['a2']) > 0.02): ?>
                <div style="background:#f8d7da; border:1px solid #dc3545; border-radius:6px;
                            padding:10px 14px; margin-top:12px; color:#721c24;">
                    ⚠ <strong>Nesoulad výpočtu:</strong> součet řádků
                    (<?= number_format($soucetA2Pb, 2, ',', ' ') ?> PB) neodpovídá souhrnu A.2
                    (<?= number_format($workload['a2'], 2, ',', ' ') ?> PB).
                    Rozdíl <?= number_format(abs($soucetA2Pb - $workload['a2']), 2, ',', ' ') ?> PB –
                    zkontrolujte počty zkoušení a koeficienty A.2.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
