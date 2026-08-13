<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';
require_once '../includes/workload-functions.php';

$pdo = connectToDatabase();
$verze = getAktivniVerze($pdo);
$zahrnoutAJ = getZahrnoutAJ($pdo);

// -----------------------------
// FILTRY / ŘAZENÍ / STRÁNKOVÁNÍ
// -----------------------------
$filtrFakulta = $_GET['fakulta'] ?? '';
$vybranaKatedra = $_GET['katedra'] ?? '';
$filtrNazev = trim($_GET['nazev'] ?? '');
$filtrZkratka = trim($_GET['zkratka'] ?? '');
$filtrUcitel = $_GET['ucitel'] ?? '';
$filtrSemestr = $_GET['semestr'] ?? '';
$omezitUciteleNaFakultu = isset($_GET['omezit_ucitele_fakulta']) && $_GET['omezit_ucitele_fakulta'] === '1';
$limit = (int)($_GET['limit'] ?? 50);
$page = (int)($_GET['page'] ?? 1);
$sort = $_GET['sort'] ?? 'zkratka';
$order = strtolower($_GET['order'] ?? 'asc');

if (!in_array($limit, [50, 100], true)) {
    $limit = 50;
}
if ($page < 1) {
    $page = 1;
}
if (!in_array($order, ['asc', 'desc'], true)) {
    $order = 'asc';
}

$allowedSorts = [
    'nazev' => 'p.nazev',
    'zkratka' => 'p.zkratka',
    'rok' => 'p.rok',
    'semestr' => 'p.semestr',
    'typ' => 'upp.typ',
    'druh' => 'upp.druh_predmetu',
    'podil' => 'upp.podil',
    'jazyk' => 'j.popis',
    'teacher' => 't.surname',
    'max_studentu' => 'upp.max_pocet_studentu'
];
$orderBy = $allowedSorts[$sort] ?? 'p.zkratka';

// -----------------------------
// ULOŽENÍ ZVOLENÉ KATEDRY
// -----------------------------
if ($vybranaKatedra !== '') {
    $stmt = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'ResCountKatedra' AND IdVerze = ?
    ");
    $stmt->execute([$vybranaKatedra, $verze]);
}

// -----------------------------
// DATA PRO FILTRY
// -----------------------------
$katedryStmt = $pdo->prepare("
    SELECT idpracoviste, zkratka, nazev, nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ?
    ORDER BY nadrazenepracoviste, zkratka
");
$katedryStmt->execute([$verze]);
$katedry = $katedryStmt->fetchAll(PDO::FETCH_ASSOC);

$fakultyStmt = $pdo->prepare("
    SELECT DISTINCT nadrazenepracoviste
    FROM pracoviste
    WHERE IdVerze = ?
      AND nadrazenepracoviste IS NOT NULL
      AND nadrazenepracoviste <> ''
    ORDER BY nadrazenepracoviste
");
$fakultyStmt->execute([$verze]);
$fakulty = $fakultyStmt->fetchAll(PDO::FETCH_COLUMN);

$jazykyStmt = $pdo->query("SELECT * FROM jazyk ORDER BY popis");
$jazyky = $jazykyStmt->fetchAll(PDO::FETCH_ASSOC);

$uciteleStmt = $pdo->prepare("
    SELECT
        t.id,
        t.name,
        t.surname,
        t.ucitIdno,
        pr.nadrazenepracoviste AS fakulta,
        pr.zkratka AS katedra
    FROM teachers t
    LEFT JOIN pracoviste pr
        ON pr.idpracoviste = t.idPracoviste
       AND pr.IdVerze = t.IdVerze
    WHERE t.IdVerze = ?
    ORDER BY surname, name
");
$uciteleStmt->execute([$verze]);
$ucitele = $uciteleStmt->fetchAll(PDO::FETCH_ASSOC);
$teacherWorkloads = calculateTeacherWorkloads($pdo, $verze, array_column($ucitele, 'id'));

$uciteleById = [];
$uciteleByFakulta = [];
foreach ($ucitele as &$ucitel) {
    $teacherId = (int)$ucitel['id'];
    $ucitel['workload'] = $teacherWorkloads[$teacherId] ?? null;
    $uciteleById[$teacherId] = $ucitel;

    $fakultaUcitele = (string)($ucitel['fakulta'] ?? '');
    if ($fakultaUcitele !== '') {
        $uciteleByFakulta[$fakultaUcitele][] = $ucitel;
    }
}
unset($ucitel);

// -----------------------------
// WHERE
// -----------------------------
$where = [];
$params = [];

$where[] = "upp.IdVerze = ?";
$params[] = $verze;

$where[] = "p.IdVerze = ?";
$params[] = $verze;

if ($vybranaKatedra !== '') {
    $where[] = "p.idPracoviste = ?";
    $params[] = $vybranaKatedra;
}

if ($filtrFakulta !== '') {
    $where[] = "pr.nadrazenepracoviste = ?";
    $params[] = $filtrFakulta;
}

if ($filtrNazev !== '') {
    $where[] = "p.nazev LIKE ?";
    $params[] = '%' . $filtrNazev . '%';
}

if ($filtrZkratka !== '') {
    $where[] = "p.zkratka LIKE ?";
    $params[] = '%' . $filtrZkratka . '%';
}

if ($filtrUcitel !== '') {
    if ($filtrUcitel === 'bez') {
        $where[] = "upp.teacherid IS NULL";
    } else {
        $where[] = "upp.teacherid = ?";
        $params[] = (int)$filtrUcitel;
    }
}

if ($filtrSemestr !== '') {
    $where[] = "p.semestr = ?";
    $params[] = $filtrSemestr;
}

if (!$zahrnoutAJ) {
    $where[] = "upp.jazyk != 2";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -----------------------------
// COUNT
// -----------------------------
$countSql = "
    SELECT COUNT(*)
    FROM predmet p
    JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
    JOIN pracoviste pr ON p.idPracoviste = pr.idpracoviste AND pr.IdVerze = p.IdVerze
    LEFT JOIN teachers t ON upp.teacherid = t.id AND t.IdVerze = upp.IdVerze
    LEFT JOIN jazyk j ON upp.jazyk = j.id
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// -----------------------------
// HLAVNÍ DOTAZ
// -----------------------------
$sql = "
    SELECT
        upp.id,
        upp.IdVerze,
        upp.predmetid,
        p.nazev,
        p.zkratka,
        p.rok,
        p.semestr,
        pr.nadrazenepracoviste,
        pr.zkratka AS katedra_zkratka,
        pr.nazev AS katedra_nazev,
        t.name,
        t.surname,
        upp.typ,
        upp.druh_predmetu,
        upp.podil,
        upp.jazyk AS jazykid,
        upp.max_pocet_studentu,
        upp.teacherid
    FROM predmet p
    JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
    JOIN pracoviste pr ON p.idPracoviste = pr.idpracoviste AND pr.IdVerze = p.IdVerze
    LEFT JOIN teachers t ON upp.teacherid = t.id AND t.IdVerze = upp.IdVerze
    LEFT JOIN jazyk j ON upp.jazyk = j.id
    $whereSql
    ORDER BY $orderBy $order, p.zkratka ASC, p.semestr ASC, upp.id ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$unmatchedScheduleTeachers = getUnmatchedScheduleTeachersForCurrentFilter(
    $pdo,
    (int)$verze,
    $filtrZkratka,
    $filtrSemestr,
    $filtrNazev,
    $vybranaKatedra,
    $filtrFakulta
);

$barvaPozadi = [
    'P' => '#d1e7dd',
    'C' => '#fce7cf',
    'S' => '#cff4fc'
];

function buildSortLink($column, $currentSort, $currentOrder)
{
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $params['page'] = 1;
    return '?' . http_build_query($params);
}

function buildPageLink($targetPage)
{
    $params = $_GET;
    $params['page'] = $targetPage;
    return '?' . http_build_query($params);
}

function renderStateFields($verze, $vybranaKatedra, $filtrFakulta, $filtrNazev, $filtrZkratka, $filtrUcitel, $filtrSemestr, $omezitUciteleNaFakultu, $limit, $page, $sort, $order)
{
    $fields = [
        'idVerze' => $verze,
        'return_katedra' => $vybranaKatedra,
        'return_nazev' => $filtrNazev,
        'return_zkratka' => $filtrZkratka,
        'return_limit' => $limit,
        'return_page' => $page,
        'return_sort' => $sort,
        'return_order' => $order,
        'return_ucitel' => $filtrUcitel,
        'return_fakulta' => $filtrFakulta,
        'return_semestr' => $filtrSemestr,
        'return_omezit_ucitele_fakulta' => $omezitUciteleNaFakultu ? '1' : '',
    ];

    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '">' . PHP_EOL;
    }
}

function teacherOptionLabel(array $ucitel, bool $showWorkplace = false): string
{
    $label = trim((string)$ucitel['surname'] . ' ' . (string)$ucitel['name']);

    if ($showWorkplace) {
        $details = trim((string)($ucitel['fakulta'] ?? '') . ' ' . (string)($ucitel['katedra'] ?? ''));
        if ($details !== '') {
            $label .= ' (' . $details . ')';
        } else {
            $label .= ' (bez pracoviště)';
        }
    }

    $workload = $ucitel['workload'] ?? null;
    if (is_array($workload)) {
        $percent = $workload['percent'] === null
            ? 'bez kapacity'
            : number_format((float)$workload['percent'], 1, ',', ' ') . ' %';
        $label .= ' — ' . number_format((float)$workload['total'], 0, ',', ' ')
            . ' / ' . number_format((float)$workload['capacity'], 0, ',', ' ')
            . ' PB (' . $percent . ')';
    }

    return $label;
}

function getUnmatchedScheduleTeachersForCurrentFilter(PDO $pdo, int $idVerze, string $filtrZkratka, string $filtrSemestr, string $filtrNazev, string $vybranaKatedra, string $filtrFakulta): array
{
    if ($filtrZkratka === '') {
        return [];
    }

    $rokCurrent = getYear($pdo);
    $rokLast = $rokCurrent - 1;
    $where = [
        'ra.IdVerze = ?',
        'ra.rok = ?',
        "UPPER(SUBSTRING(ra.typ_akce_zkr, 1, 1)) IN ('P', 'C', 'S')",
        't.id IS NULL',
    ];
    $params = [$idVerze, $rokLast];

    if ($filtrZkratka !== '') {
        $where[] = 'p.zkratka LIKE ?';
        $params[] = '%' . $filtrZkratka . '%';
    }
    if ($filtrSemestr !== '') {
        $where[] = 'p.semestr = ?';
        $params[] = $filtrSemestr;
    }
    if ($filtrNazev !== '') {
        $where[] = 'p.nazev LIKE ?';
        $params[] = '%' . $filtrNazev . '%';
    }
    if ($vybranaKatedra !== '') {
        $where[] = 'p.idPracoviste = ?';
        $params[] = $vybranaKatedra;
    }
    if ($filtrFakulta !== '') {
        $where[] = 'pr.nadrazenepracoviste = ?';
        $params[] = $filtrFakulta;
    }

    $whereSql = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT
            p.zkratka,
            p.nazev,
            p.semestr,
            UPPER(SUBSTRING(ra.typ_akce_zkr, 1, 1)) AS typ,
            rau.ucitIdno,
            SUM(ra.pocet_vyuc_hodin * rau.podil_na_vyuce / 100) AS hodiny,
            totals.total_hodiny,
            ROUND(SUM(ra.pocet_vyuc_hodin * rau.podil_na_vyuce / 100) / totals.total_hodiny * 100, 2) AS chybejici_podil
        FROM rozvrhova_akce ra
        JOIN rozvrhova_akce_ucitel rau
          ON rau.roakIdno = ra.roakIdno AND rau.IdVerze = ra.IdVerze
        JOIN predmet p
          ON p.zkratka = ra.predmet_zkratka
         AND p.IdVerze = ra.IdVerze
         AND p.rok = ?
         AND p.semestr = ra.semestr
        JOIN pracoviste pr
          ON pr.idpracoviste = p.idPracoviste AND pr.IdVerze = p.IdVerze
        JOIN (
            SELECT
                ra2.predmet_zkratka,
                ra2.semestr,
                UPPER(SUBSTRING(ra2.typ_akce_zkr, 1, 1)) AS typ,
                SUM(ra2.pocet_vyuc_hodin * rau2.podil_na_vyuce / 100) AS total_hodiny
            FROM rozvrhova_akce ra2
            JOIN rozvrhova_akce_ucitel rau2
              ON rau2.roakIdno = ra2.roakIdno AND rau2.IdVerze = ra2.IdVerze
            WHERE ra2.IdVerze = ?
              AND ra2.rok = ?
              AND UPPER(SUBSTRING(ra2.typ_akce_zkr, 1, 1)) IN ('P', 'C', 'S')
            GROUP BY ra2.predmet_zkratka, ra2.semestr, UPPER(SUBSTRING(ra2.typ_akce_zkr, 1, 1))
        ) totals
          ON totals.predmet_zkratka = ra.predmet_zkratka
         AND totals.semestr = ra.semestr
         AND totals.typ = UPPER(SUBSTRING(ra.typ_akce_zkr, 1, 1))
        LEFT JOIN teachers t
          ON t.ucitIdno = rau.ucitIdno AND t.IdVerze = ra.IdVerze
        WHERE $whereSql
        GROUP BY p.zkratka, p.nazev, p.semestr, UPPER(SUBSTRING(ra.typ_akce_zkr, 1, 1)), rau.ucitIdno, totals.total_hodiny
        ORDER BY p.zkratka, p.semestr, UPPER(SUBSTRING(ra.typ_akce_zkr, 1, 1)), chybejici_podil DESC, rau.ucitIdno
    ");
    $stmt->execute(array_merge([$rokCurrent, $idVerze, $rokLast], $params));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderTeacherOptionsForRow(array $row, array $allTeachers, array $teachersByFaculty, array $teachersById, bool $limitToFaculty): void
{
    echo '<option value="">-- bez učitele --</option>' . PHP_EOL;

    $selectedTeacherId = $row['teacherid'] !== null ? (int)$row['teacherid'] : null;
    if ($selectedTeacherId !== null && isset($teachersById[$selectedTeacherId])) {
        $ucitel = $teachersById[$selectedTeacherId];
        $workload = $ucitel['workload'] ?? [];
        echo '<option value="' . htmlspecialchars((string)$selectedTeacherId) . '"'
            . ' data-total="' . htmlspecialchars((string)($workload['total'] ?? '')) . '"'
            . ' data-capacity="' . htmlspecialchars((string)($workload['capacity'] ?? '')) . '"'
            . ' data-percent="' . htmlspecialchars((string)($workload['percent'] ?? '')) . '" selected>'
            . htmlspecialchars(teacherOptionLabel($ucitel, true))
            . '</option>' . PHP_EOL;
    }
}

$teacherOptionsForJs = array_map(static function (array $teacher): array {
    $workload = $teacher['workload'] ?? [];
    return [
        'id' => (int)$teacher['id'],
        'faculty' => (string)($teacher['fakulta'] ?? ''),
        'label' => teacherOptionLabel($teacher, false),
        'labelWithWorkplace' => teacherOptionLabel($teacher, true),
        'total' => $workload['total'] ?? null,
        'capacity' => $workload['capacity'] ?? null,
        'percent' => $workload['percent'] ?? null,
    ];
}, $ucitele);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Počítání výsledků</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
    <style>
        .filters-box {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
            margin-bottom: 20px;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }

        .filters-box .field {
            display: flex;
            flex-direction: column;
            min-width: 180px;
        }

        .filters-box label {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .filters-box input,
        .filters-box select,
        .filters-box button {
            padding: 6px 8px;
        }

        .top-bar {
            margin-bottom: 15px;
        }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin: 16px 0;
        }

        .pagination a,
        .pagination span {
            padding: 6px 10px;
            border: 1px solid #ccc;
            text-decoration: none;
            border-radius: 4px;
            background: white;
            color: black;
        }

        .pagination .active {
            font-weight: bold;
            background: #e9ecef;
        }

        .table-info {
            margin: 10px 0 15px 0;
        }

        th a {
            color: inherit;
            text-decoration: none;
        }

        th a:hover {
            text-decoration: underline;
        }

        .actions-inline {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .small-muted {
            color: #666;
            font-size: 0.95em;
        }

        .bulk-save-wrap {
            margin: 18px 0;
            display: flex;
            justify-content: flex-end;
        }

        .editable-table select,
        .editable-table input[type="number"] {
            width: 100%;
            box-sizing: border-box;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .row-form {
            margin: 0;
        }

        .btn-save,
        .btn-remove,
        .btn-delete,
        .btn-copy,
        .btn-save-all {
            border: none !important;
            color: #fff !important;
            padding: 6px 10px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-weight: 600 !important;
        }

        .btn-save {
            background: #28a745 !important;
        }

        .btn-remove {
            background: #fd7e14 !important;
        }

        .btn-delete {
            background: #dc3545 !important;
        }

        .btn-copy {
            background: #17a2b8 !important;
        }

        .btn-save-all {
            background: #0069d9 !important;
            padding: 10px 16px !important;
        }

        .teacher-workload-preview {
            display: block;
            margin-top: 4px;
            font-size: .78rem;
            line-height: 1.25;
            color: #555;
        }

        .workload-low { color: #8a5a00; }
        .workload-good { color: #146c43; }
        .workload-over { color: #b02a37; font-weight: 700; }
    </style>
</head>
<body>

    <h1>Počítání výsledků</h1>

    <?php
    // Flash zprávy – výsledky akcí (uložení, kopírování, chyby)
    if (isset($_GET['copied'])):
    ?>
        <div style="background:#d4edda; border:1px solid #28a745; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724;">
            ✔ Záznam byl zkopírován. Nový řádek najdete níže – upravte ho dle potřeby.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#f8d7da; border:1px solid #dc3545; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#721c24;">
            <?php
            $err = $_GET['error'];
            $msg = $_GET['msg'] ?? '';
            if ($err === 'db' && str_contains($msg, 'Duplicate')) {
                echo '⚠ Kopírování selhalo: záznam se stejnou kombinací předmětu, učitele, typu a jazyka již existuje. Pokud chcete více skupin cvičení/semináře, nastavte <strong>Max. počet studentů</strong> na skupinu a systém skupiny vypočítá automaticky.';
            } elseif ($err === 'db') {
                echo '⚠ Chyba databáze: ' . htmlspecialchars($msg);
            } elseif ($err === 'not_found') {
                echo '⚠ Záznam nebyl nalezen.';
            } elseif ($err === 'unknown_action') {
                echo '⚠ Neznámá akce.';
            } else {
                echo '⚠ Chyba: ' . htmlspecialchars($err);
            }
            ?>
        </div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div style="background:#d4edda; border:1px solid #28a745; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724;">
            ✔ Záznam byl uložen.
        </div>
    <?php elseif (isset($_GET['updated_all'])): ?>
        <div style="background:#d4edda; border:1px solid #28a745; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724;">
            ✔ Všechny záznamy na stránce byly uloženy.
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div style="background:#d4edda; border:1px solid #28a745; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724;">
            ✔ Záznam byl smazán.
        </div>
    <?php elseif (isset($_GET['cleared'])): ?>
        <div style="background:#d4edda; border:1px solid #28a745; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724;">
            ✔ Učitel byl odebrán ze záznamu.
        </div>
    <?php endif; ?>

    <?php if (!$zahrnoutAJ): ?>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:6px;
                    padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#7a5a00;">
            ⚠ <strong>Anglické výuky jsou skryty.</strong>
            Zobrazují se pouze česky vyučované předměty.
            Nastavení změníš v <a href="insert1.php">Import dat → Nastavení anglických výuk</a>.
        </div>
    <?php endif; ?>

    <form method="GET" class="filters-box">
        <div class="field">
            <label for="fakulta">Fakulta</label>
            <select name="fakulta" id="fakulta">
                <option value="">-- Všechny fakulty --</option>
                <?php foreach ($fakulty as $fakulta): ?>
                    <option value="<?= htmlspecialchars($fakulta) ?>" <?= ((string)$fakulta === (string)$filtrFakulta) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fakulta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="katedra">Katedra</label>
            <select name="katedra" id="katedra">
                <option value="">-- Všechny katedry --</option>
                <?php foreach ($katedry as $kat): ?>
                    <option value="<?= htmlspecialchars($kat['idpracoviste']) ?>" <?= ((string)$kat['idpracoviste'] === (string)$vybranaKatedra) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kat['nadrazenepracoviste'] . ' - ' . $kat['zkratka'] . ' - ' . $kat['nazev']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="nazev">Název předmětu</label>
            <input type="text" name="nazev" id="nazev" value="<?= htmlspecialchars($filtrNazev) ?>" placeholder="např. Databáze">
        </div>

        <div class="field">
            <label for="zkratka">Zkratka</label>
            <input type="text" name="zkratka" id="zkratka" value="<?= htmlspecialchars($filtrZkratka) ?>" placeholder="např. ADBMO">
        </div>

        <div class="field">
            <label for="semestr">Semestr</label>
            <select name="semestr" id="semestr">
                <option value="">-- Oba semestry --</option>
                <option value="ZS" <?= $filtrSemestr === 'ZS' ? 'selected' : '' ?>>ZS</option>
                <option value="LS" <?= $filtrSemestr === 'LS' ? 'selected' : '' ?>>LS</option>
            </select>
        </div>

        <div class="field">
            <label for="limit">Počet záznamů</label>
            <select name="limit" id="limit">
                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>

        <div class="field">
            <label for="ucitel">Učitel</label>
            <select name="ucitel" id="ucitel">
                <option value="">-- Všichni učitelé --</option>
                <option value="bez" <?= $filtrUcitel === 'bez' ? 'selected' : '' ?>>-- bez učitele --</option>
                <?php foreach ($ucitele as $ucitel): ?>
                    <option value="<?= htmlspecialchars($ucitel['id']) ?>" <?= ((string)$ucitel['id'] === (string)$filtrUcitel) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(teacherOptionLabel($ucitel)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="omezit_ucitele_fakulta">Výběr učitele</label>
            <label style="display:flex; align-items:center; gap:6px; padding-top:6px; font-size:.9em;">
                <input type="checkbox" name="omezit_ucitele_fakulta" id="omezit_ucitele_fakulta" value="1" <?= $omezitUciteleNaFakultu ? 'checked' : '' ?>>
                Jen učitelé fakulty daného předmětu
            </label>
        </div>

        <div class="field">
            <button type="submit">Filtrovat</button>
        </div>

        <div class="field">
            <a href="result-counting.php" style="padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; text-decoration:none; background:white;">Reset</a>
        </div>
    </form>

    <div class="table-info">
        <strong>Počet záznamů:</strong> <?= $totalRecords ?>
        <span class="small-muted">
            | Stránka <?= $page ?> z <?= $totalPages ?>
            | Zobrazeno <?= count($assignments) ?> záznamů
        </span>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(buildPageLink(1)) ?>">« První</a>
            <a href="<?= htmlspecialchars(buildPageLink($page - 1)) ?>">‹ Předchozí</a>
        <?php endif; ?>

        <span class="active"><?= $page ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(buildPageLink($page + 1)) ?>">Další ›</a>
            <a href="<?= htmlspecialchars(buildPageLink($totalPages)) ?>">Poslední »</a>
        <?php endif; ?>
    </div>

    <form method="POST" action="../includes/functions-result-counting.php">
        <?php renderStateFields($verze, $vybranaKatedra, $filtrFakulta, $filtrNazev, $filtrZkratka, $filtrUcitel, $filtrSemestr, $omezitUciteleNaFakultu, $limit, $page, $sort, $order); ?>

        <?php if ($omezitUciteleNaFakultu): ?>
            <div style="background:#e7f3ff; border:1px solid #7db7e8; border-radius:6px; padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#174f7a;">
                V rozbalovacích seznamech se zobrazují pouze učitelé fakulty, pod kterou patří daný předmět.
                Aktuálně přiřazený učitel mimo fakultu zůstává viditelný jako „mimo filtr“, aby se při uložení neodebral omylem.
            </div>
        <?php endif; ?>

        <?php if (!empty($unmatchedScheduleTeachers)): ?>
            <div style="background:#fff8e6; border:1px solid #f0b44c; border-radius:6px; padding:10px 16px; margin-bottom:14px; color:#6f4700;">
                <strong>Nespárovaní učitelé z loňského rozvrhu:</strong>
                <table style="width:100%; border-collapse:collapse; margin-top:8px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border-bottom:1px solid #e3c28a; padding:4px;">Předmět</th>
                            <th style="text-align:left; border-bottom:1px solid #e3c28a; padding:4px;">Semestr</th>
                            <th style="text-align:left; border-bottom:1px solid #e3c28a; padding:4px;">Typ</th>
                            <th style="text-align:left; border-bottom:1px solid #e3c28a; padding:4px;">STAG ucitIdno</th>
                            <th style="text-align:right; border-bottom:1px solid #e3c28a; padding:4px;">Hodiny</th>
                            <th style="text-align:right; border-bottom:1px solid #e3c28a; padding:4px;">Chybějící podíl</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unmatchedScheduleTeachers as $missing): ?>
                            <tr>
                                <td style="padding:4px;">
                                    <strong><?= htmlspecialchars($missing['zkratka']) ?></strong>
                                    <?= htmlspecialchars($missing['nazev'] ?? '') ?>
                                </td>
                                <td style="padding:4px;"><?= htmlspecialchars($missing['semestr']) ?></td>
                                <td style="padding:4px;">
                                    <?= htmlspecialchars(match ($missing['typ']) {
                                        'P' => 'Přednáška',
                                        'C' => 'Cvičení',
                                        'S' => 'Seminář',
                                        default => (string)$missing['typ'],
                                    }) ?>
                                </td>
                                <td style="padding:4px;"><code><?= htmlspecialchars((string)$missing['ucitIdno']) ?></code></td>
                                <td style="padding:4px; text-align:right;"><?= number_format((float)$missing['hodiny'], 2, ',', ' ') ?></td>
                                <td style="padding:4px; text-align:right;"><strong><?= number_format((float)$missing['chybejici_podil'], 2, ',', ' ') ?> %</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="bulk-save-wrap">
            <button type="submit" name="action" value="update_all" class="btn-save-all">Uložit vše na stránce</button>
        </div>

        <div class="table-scroll">
        <table class="editable-table">
            <thead>
                <tr>
                    <th><a href="<?= htmlspecialchars(buildSortLink('nazev', $sort, $order)) ?>">Předmět</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('zkratka', $sort, $order)) ?>">Zkratka</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('rok', $sort, $order)) ?>">Rok</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('semestr', $sort, $order)) ?>">Semestr</a></th>
                    <th>Fakulta - katedra</th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('typ', $sort, $order)) ?>">Typ výuky</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('druh', $sort, $order)) ?>">Druh pro PB</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('podil', $sort, $order)) ?>">Podíl</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('jazyk', $sort, $order)) ?>">Jazyk</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('teacher', $sort, $order)) ?>">Učitel</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('max_studentu', $sort, $order)) ?>">Max. počet</a></th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $row): ?>
                    <tr style="background-color: <?= $barvaPozadi[$row['typ']] ?? '' ?>">
                        <td><?= htmlspecialchars($row['nazev']) ?></td>
                        <td><?= htmlspecialchars($row['zkratka']) ?></td>
                        <td><?= htmlspecialchars((string)$row['rok']) ?></td>
                        <td><?= htmlspecialchars($row['semestr']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['nadrazenepracoviste'] . ' - ' . $row['katedra_zkratka'] . ' - ' . $row['katedra_nazev']) ?>
                        </td>

                        <td>
                            <select name="bulk[<?= (int)$row['id'] ?>][typ]" class="typ-vyuky">
                                <option value="P" <?= $row['typ'] === 'P' ? 'selected' : '' ?>>Přednáška</option>
                                <option value="C" <?= $row['typ'] === 'C' ? 'selected' : '' ?>>Cvičení</option>
                                <option value="S" <?= $row['typ'] === 'S' ? 'selected' : '' ?>>Seminář</option>
                            </select>
                        </td>

                        <td>
                            <select name="bulk[<?= (int)$row['id'] ?>][druh_predmetu]">
                                <option value="auto" <?= ($row['druh_predmetu'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automaticky dle jazyka</option>
                                <option value="standard" <?= ($row['druh_predmetu'] ?? '') === 'standard' ? 'selected' : '' ?>>Běžný</option>
                                <option value="c" <?= ($row['druh_predmetu'] ?? '') === 'c' ? 'selected' : '' ?>>Cizojazyčný</option>
                                <option value="d" <?= ($row['druh_predmetu'] ?? '') === 'd' ? 'selected' : '' ?>>Doktorský</option>
                                <option value="dc" <?= ($row['druh_predmetu'] ?? '') === 'dc' ? 'selected' : '' ?>>Doktorský cizojazyčný</option>
                            </select>
                        </td>

                        <td>
                            <input type="number" name="bulk[<?= (int)$row['id'] ?>][podil]" value="<?= htmlspecialchars((string)$row['podil']) ?>" min="0" max="100" step="0.01">
                        </td>

                        <td>
                            <select name="bulk[<?= (int)$row['id'] ?>][jazyk]">
                                <?php foreach ($jazyky as $jazyk): ?>
                                    <option value="<?= htmlspecialchars($jazyk['id']) ?>" <?= ((string)$jazyk['id'] === (string)$row['jazykid']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jazyk['popis']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <select name="bulk[<?= (int)$row['id'] ?>][ucitel]" class="teacher-select"
                                    data-faculty="<?= htmlspecialchars((string)($row['nadrazenepracoviste'] ?? '')) ?>">
                                <?php renderTeacherOptionsForRow($row, $ucitele, $uciteleByFakulta, $uciteleById, $omezitUciteleNaFakultu); ?>
                            </select>
                            <small class="teacher-workload-preview"></small>
                        </td>

                        <td>
                            <select name="bulk[<?= (int)$row['id'] ?>][max_studentu]">
                                <option value="">--</option>
                                <option value="24" <?= $row['max_pocet_studentu'] == 24 ? 'selected' : '' ?>>24</option>
                                <option value="12" <?= $row['max_pocet_studentu'] == 12 ? 'selected' : '' ?>>12</option>
                                <option value="1" <?= $row['max_pocet_studentu'] == 1 ? 'selected' : '' ?>>X</option>
                            </select>
                        </td>

                        <td>
                            <div class="actions-inline">
                                <button type="submit" name="action" value="update_one_<?= (int)$row['id'] ?>" class="btn-save">Uložit</button>
                                <button type="submit" name="action" value="odebrat_<?= (int)$row['id'] ?>" class="btn-remove">Odebrat</button>
                                <button type="submit" name="action" value="smazat_<?= (int)$row['id'] ?>" class="btn-delete" onclick="return confirm('Opravdu smazat tento záznam?')">Smazat</button>
                                <button type="submit" name="action" value="kopirovat_<?= (int)$row['id'] ?>" class="btn-copy">Kopírovat</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="12">Nebyla nalezena žádná data.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </form>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(buildPageLink(1)) ?>">« První</a>
            <a href="<?= htmlspecialchars(buildPageLink($page - 1)) ?>">‹ Předchozí</a>
        <?php endif; ?>

        <span class="active"><?= $page ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(buildPageLink($page + 1)) ?>">Další ›</a>
            <a href="<?= htmlspecialchars(buildPageLink($totalPages)) ?>">Poslední »</a>
        <?php endif; ?>
    </div>

    <div id="navbar" style="display: none;">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="view.php">View</a></li>
            <li><a href="edit.php">Edit</a></li>
            <li><a href="insert1.php">Insert do DB</a></li>
            <li><a href="result-counting.php">Manuální Editace</a></li>
            <li><a href="zkouseni.php">Zkoušení (A2)</a></li>
            <li><a href="overview-ucitele.php">Přehled kantoři</a></li>
            <li><a href="settings.php">Nastavení</a></li>
        </ul>
    </div>

    <button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
    <script src="../js/result-counting.js"></script>
    <script>
        const workloadTeachers = <?= json_encode(
            $teacherOptionsForJs,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        const restrictTeachersToFaculty = <?= $omezitUciteleNaFakultu ? 'true' : 'false' ?>;
        const workloadTeachersById = new Map(workloadTeachers.map((teacher) => [String(teacher.id), teacher]));

        document.querySelectorAll('.teacher-select').forEach((select) => {
            const preview = select.parentElement.querySelector('.teacher-workload-preview');
            const updatePreview = () => {
                const option = select.options[select.selectedIndex];
                if (!option || !option.value || !option.dataset.total) {
                    preview.textContent = '';
                    preview.className = 'teacher-workload-preview';
                    return;
                }
                const total = Number(option.dataset.total);
                const capacity = Number(option.dataset.capacity);
                const percent = option.dataset.percent === '' ? null : Number(option.dataset.percent);
                preview.textContent = `Aktuálně: ${total.toLocaleString('cs-CZ')} / ${capacity.toLocaleString('cs-CZ')} PB`
                    + (percent === null ? ' (bez kapacity)' : ` (${percent.toLocaleString('cs-CZ')} %)`);
                preview.className = 'teacher-workload-preview '
                    + (percent === null ? '' : percent > 100 ? 'workload-over' : percent >= 80 ? 'workload-good' : 'workload-low');
            };

            const hydrateOptions = () => {
                if (select.dataset.hydrated === '1') {
                    return;
                }
                select.dataset.hydrated = '1';
                const selectedId = select.value;
                const faculty = select.dataset.faculty || '';
                const candidates = restrictTeachersToFaculty
                    ? workloadTeachers.filter((teacher) => teacher.faculty === faculty)
                    : workloadTeachers;
                const candidateIds = new Set(candidates.map((teacher) => String(teacher.id)));
                const fragment = document.createDocumentFragment();
                fragment.append(new Option('-- bez učitele --', ''));

                const appendTeacher = (teacher, outsideFilter = false) => {
                    const label = restrictTeachersToFaculty && !outsideFilter
                        ? teacher.label
                        : teacher.labelWithWorkplace + (outsideFilter ? ' - mimo filtr' : '');
                    const option = new Option(label, String(teacher.id));
                    option.dataset.total = teacher.total ?? '';
                    option.dataset.capacity = teacher.capacity ?? '';
                    option.dataset.percent = teacher.percent ?? '';
                    fragment.append(option);
                };

                candidates.forEach((teacher) => appendTeacher(teacher));
                if (selectedId && !candidateIds.has(selectedId) && workloadTeachersById.has(selectedId)) {
                    appendTeacher(workloadTeachersById.get(selectedId), true);
                }
                select.replaceChildren(fragment);
                select.value = selectedId;
                updatePreview();
            };

            select.addEventListener('pointerdown', hydrateOptions, {once: true});
            select.addEventListener('focus', hydrateOptions, {once: true});
            select.addEventListener('change', updatePreview);
            updatePreview();
        });
    </script>
</body>
</html>
