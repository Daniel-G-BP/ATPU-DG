<?php
require_once '../includes/functions-overview-ucitele.php';
require_once '../includes/dbh.inc.php';
$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

$filters = [
    'search'  => trim((string)($_GET['q'] ?? '')),
    'katedra' => trim((string)($_GET['katedra'] ?? '')),
    'fakulta' => trim((string)($_GET['fakulta'] ?? '')),
];

$limit = in_array((int)($_GET['limit'] ?? 100), [50, 100], true) ? (int)($_GET['limit'] ?? 100) : 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalTeachers = getTeachersCount($pdo, $filters);
$totalPages = max(1, (int)ceil($totalTeachers / $limit));
$page = min($page, $totalPages);
$teachers = getTeachersData($pdo, $limit, ($page - 1) * $limit, $filters);
$exportTeachers = getTeachersSelectionData($pdo, $filters);

$overloadThreshold = getWorkloadOverloadThreshold($pdo, $idVerze);
$fullTimePoints = getWorkloadFullTimePoints($pdo, $idVerze);

// Číselníky pro filtry
$katedryStmt = $pdo->prepare("
    SELECT idpracoviste, zkratka, nazev, nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ?
    ORDER BY nadrazenepracoviste, zkratka
");
$katedryStmt->execute([$idVerze]);
$katedry = $katedryStmt->fetchAll(PDO::FETCH_ASSOC);

$fakultyStmt = $pdo->prepare("
    SELECT DISTINCT nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ? AND nadrazenepracoviste IS NOT NULL AND nadrazenepracoviste <> ''
    ORDER BY nadrazenepracoviste
");
$fakultyStmt->execute([$idVerze]);
$fakulty = $fakultyStmt->fetchAll(PDO::FETCH_COLUMN);

// Souhrn za aktuální filtr
$pocetPretizenych = 0;
foreach ($teachers as $t) {
    if (!empty($t['workload']['is_overloaded'])) {
        $pocetPretizenych++;
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled učitelů</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css"> 
    <link rel="stylesheet" href="../stylepage.css"> 
    <script src="../webfunc.js"></script>
    <style>
        .workload-meter { min-width: 210px; }
        .workload-track { height: 9px; border-radius: 6px; background: #e9ecef; overflow: hidden; margin-top: 5px; }
        .workload-fill { height: 100%; background: #6c9bd2; }
        .workload-low .workload-fill { background: #d6a64f; }
        .workload-good .workload-fill { background: #3c9a69; }
        .workload-high .workload-fill { background: #e08b3c; }
        .workload-high strong { color: #a35b12; }
        .workload-over .workload-fill { background: #c84f5a; }
        .workload-over strong { color: #b02a37; }
        .badge-over { display:inline-block; background:#fdf1f2; border:1px solid #c84f5a;
                      color:#b02a37; border-radius:4px; padding:1px 6px; font-size:.75rem;
                      font-weight:bold; margin-left:4px; }
        .workload-detail { color: #666; font-size: .82rem; margin-top: 3px; }
        .overview-filters { display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin:0 0 16px; }
        .overview-filters label { display:flex; flex-direction:column; gap:4px; }
        .overview-filters input, .overview-filters select, .overview-filters button { padding:7px 9px; }
        .teacher-export-box { border:1px solid #cfd8dc; border-radius:8px; padding:12px; margin:0 0 16px; background:#f7fafb; }
        .teacher-export-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
        .teacher-export-actions input[type="search"] { padding:7px 9px; min-width:220px; }
        .teacher-checkbox-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:6px 12px; max-height:260px; overflow:auto; padding:8px; border:1px solid #e1e5e8; background:#fff; }
        .teacher-checkbox-grid label { display:flex; align-items:flex-start; gap:6px; font-size:.9rem; }
        .teacher-checkbox-grid small { color:#777; }
        .pagination { display:flex; gap:6px; align-items:center; margin:16px 0; }
        .pagination a, .pagination span { padding:6px 10px; border:1px solid #ccc; border-radius:4px; text-decoration:none; }
        .pagination .active { background:#e9ecef; font-weight:bold; }
    </style>
</head>
<body>
    <div class="main-content">
        <h1>Seznam učitelů</h1>
        <form method="get" class="overview-filters">
            <label>Jméno nebo příjmení
                <input type="search" name="q" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Hledat učitele">
            </label>
            <label>Fakulta
                <select name="fakulta">
                    <option value="">— všechny —</option>
                    <?php foreach ($fakulty as $fakulta): ?>
                        <option value="<?= htmlspecialchars($fakulta) ?>"
                            <?= $filters['fakulta'] === (string)$fakulta ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fakulta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Katedra
                <select name="katedra">
                    <option value="">— všechny —</option>
                    <?php foreach ($katedry as $katedra): ?>
                        <option value="<?= (int)$katedra['idpracoviste'] ?>"
                            <?= $filters['katedra'] === (string)$katedra['idpracoviste'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($katedra['zkratka']) ?>
                            <?= $katedra['nadrazenepracoviste'] ? '(' . htmlspecialchars($katedra['nadrazenepracoviste']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Počet na stránce
                <select name="limit">
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </label>
            <button type="submit">Filtrovat</button>
            <a href="overview-ucitele.php">Reset</a>
        </form>

        <form method="post" action="export-prehled-vytizeni.php" class="teacher-export-box" id="teacherExportForm">
            <input type="hidden" name="q" value="<?= htmlspecialchars($filters['search']) ?>">
            <input type="hidden" name="katedra" value="<?= htmlspecialchars($filters['katedra']) ?>">
            <input type="hidden" name="fakulta" value="<?= htmlspecialchars($filters['fakulta']) ?>">

            <div class="teacher-export-actions">
                <strong>Výběr učitelů pro export:</strong>
                <span id="selectedTeacherCount">0 vybráno</span>
                <button type="button" id="selectAllTeachers">Vybrat vše z filtru</button>
                <button type="button" id="clearAllTeachers">Zrušit výběr</button>
                <input type="search" id="teacherCheckboxSearch" placeholder="Hledat v učitelích pro export">
                <button type="submit" style="border:1px solid #3c9a69; background:#e9f6ef; color:#1d5c3c; font-weight:bold;">
                    Exportovat vybrané do Excelu
                </button>
            </div>

            <div class="teacher-checkbox-grid">
                <?php foreach ($exportTeachers as $teacher): ?>
                    <?php
                    $labelText = trim(($teacher['surname'] ?? '') . ' ' . ($teacher['name'] ?? '') . ' ' . ($teacher['katedra'] ?? '') . ' ' . ($teacher['fakulta'] ?? ''));
                    $labelKey = function_exists('mb_strtolower') ? mb_strtolower($labelText, 'UTF-8') : strtolower($labelText);
                    ?>
                    <label data-teacher-label="<?= htmlspecialchars($labelKey) ?>">
                        <input type="checkbox" name="teacher_ids[]" value="<?= (int)$teacher['id_ucitel'] ?>">
                        <span>
                            <?= htmlspecialchars(trim(($teacher['surname'] ?? '') . ' ' . ($teacher['name'] ?? ''))) ?>
                            <small>
                                <?= htmlspecialchars($teacher['katedra'] ?? '') ?>
                                <?= !empty($teacher['fakulta']) ? '(' . htmlspecialchars($teacher['fakulta']) . ')' : '' ?>
                            </small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </form>
        <p>
            Celkem učitelů: <strong><?= $totalTeachers ?></strong> ·
            Přetížených na této stránce: <strong style="color:#b02a37;"><?= $pocetPretizenych ?></strong>
            <span style="color:#888; font-size:.9em;">
                (plný úvazek <?= number_format($fullTimePoints, 0, ',', ' ') ?> PB,
                hranice <?= number_format($overloadThreshold, 0, ',', ' ') ?> % – lze změnit v Nastavení)
            </span>
        </p>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Příjmení</th>
                    <th>Pracoviště</th>
                    <th>Telefon</th>
                    <th>Vytíženost</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td><?= htmlspecialchars($teacher['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($teacher['surname'] ?? '') ?></td>
                        <td>
                            <?= htmlspecialchars($teacher['katedra'] ?? '') ?>
                            <?php if (!empty($teacher['fakulta'])): ?>
                                <span style="color:#888;">(<?= htmlspecialchars($teacher['fakulta']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($teacher['telefon'] ?? '') ?></td>
                        <?php $w = $teacher['workload'] ?? null; ?>
                        <td class="workload-meter <?= $w ? workloadStatusClass($w['percent'], $overloadThreshold) : 'workload-neutral' ?>">
                            <?php if ($w): ?>
                                <strong>
                                    <?= number_format($w['total'], 0, ',', ' ') ?> /
                                    <?= number_format($w['capacity'], 0, ',', ' ') ?> PB
                                    (<?= $w['percent'] === null ? 'bez kapacity' : number_format($w['percent'], 1, ',', ' ') . ' %' ?>)
                                    <?php if (!empty($w['is_overloaded'])): ?>
                                        <span class="badge-over">PŘETÍŽEN</span>
                                    <?php endif; ?>
                                </strong>
                                <div class="workload-track">
                                    <div class="workload-fill" style="width:<?= min(100, max(0, (float)($w['percent'] ?? 0))) ?>%"></div>
                                </div>
                                <div class="workload-detail">A.1 <?= number_format($w['a1'], 1, ',', ' ') ?> · A.2 <?= number_format($w['a2'], 1, ',', ' ') ?> PB</div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <!-- <td>
                            <button type="button"
                                onclick="window.open(
                                    'uvazek-ucitele.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'UvazekUcitele',
                                    'width=800,height=600,resizable=yes,scrollbars=yes'
                                )">
                                Zobrazit úvazek
                            </button>
                            <form method="get" action="/pages/export-uvazek.php" style="display:inline;">
                                <input type="hidden" name="teacherId" value="<?= (int)$teacher['id_ucitel'] ?>">
                                <button type="submit">Export Excel</button>
                            </form>
                        </td> -->
                        <td>
                            <button type="button"
                                onclick="window.open(
                                    'uvazek-ucitele.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'UvazekUcitele',
                                    'width=800,height=600,resizable=yes,scrollbars=yes'
                                )">
                                Zobrazit úvazek
                            </button>

                            <button type="button"
                                onclick="window.open(
                                    'edit-ucitel.php?id=<?= (int)$teacher['id_ucitel'] ?>',
                                    'EditUcitel',
                                    'width=760,height=850,resizable=yes,scrollbars=yes'
                                )">
                                Detail / editace
                            </button>

                            <form method="get" action="/pages/export-uvazek.php" style="display:inline;">
                                <input type="hidden" name="teacherId" value="<?= (int)$teacher['id_ucitel'] ?>">
                                <button type="submit">Export Excel</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
            $pageParams = [
                'q' => $filters['search'],
                'katedra' => $filters['katedra'],
                'fakulta' => $filters['fakulta'],
                'limit' => $limit,
            ];
            ?>
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query($pageParams + ['page' => $page - 1]) ?>">‹ Předchozí</a>
            <?php endif; ?>
            <span class="active"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query($pageParams + ['page' => $page + 1]) ?>">Další ›</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'navbar.php'; ?>

    <script>
        (function () {
            const form = document.getElementById('teacherExportForm');
            const checkboxes = Array.from(form.querySelectorAll('input[name="teacher_ids[]"]'));
            const counter = document.getElementById('selectedTeacherCount');
            const search = document.getElementById('teacherCheckboxSearch');

            function updateCounter() {
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                counter.textContent = selected + ' vybráno';
            }

            document.getElementById('selectAllTeachers').addEventListener('click', function () {
                checkboxes.forEach((checkbox) => checkbox.checked = true);
                updateCounter();
            });

            document.getElementById('clearAllTeachers').addEventListener('click', function () {
                checkboxes.forEach((checkbox) => checkbox.checked = false);
                updateCounter();
            });

            search.addEventListener('input', function () {
                const needle = search.value.trim().toLocaleLowerCase('cs-CZ');
                checkboxes.forEach((checkbox) => {
                    const label = checkbox.closest('label');
                    label.style.display = label.dataset.teacherLabel.includes(needle) ? '' : 'none';
                });
            });

            form.addEventListener('submit', function (event) {
                if (!checkboxes.some((checkbox) => checkbox.checked)) {
                    event.preventDefault();
                    alert('Vyberte alespoň jednoho učitele pro export.');
                }
            });

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCounter));
            updateCounter();
        })();
    </script>

</body>
</html>
