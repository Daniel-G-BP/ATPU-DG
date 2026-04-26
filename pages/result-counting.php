<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';

$pdo = connectToDatabase();
$verze = getAktivniVerze($pdo);

// -----------------------------
// FILTRY / ŘAZENÍ / STRÁNKOVÁNÍ
// -----------------------------
$filtrFakulta = $_GET['fakulta'] ?? '';
$vybranaKatedra = $_GET['katedra'] ?? '';
$filtrNazev = trim($_GET['nazev'] ?? '');
$filtrZkratka = trim($_GET['zkratka'] ?? '');
$filtrUcitel = $_GET['ucitel'] ?? '';
$filtrSemestr = $_GET['semestr'] ?? '';
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
    SELECT id, name, surname, ucitIdno
    FROM teachers
    WHERE IdVerze = ?
    ORDER BY surname, name
");
$uciteleStmt->execute([$verze]);
$ucitele = $uciteleStmt->fetchAll(PDO::FETCH_ASSOC);

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

function renderStateFields($verze, $vybranaKatedra, $filtrFakulta, $filtrNazev, $filtrZkratka, $filtrUcitel, $filtrSemestr, $limit, $page, $sort, $order)
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
    ];

    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '">' . PHP_EOL;
    }
}
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
    </style>
</head>
<body>

    <h1>Počítání výsledků</h1>

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
                        <?= htmlspecialchars($ucitel['surname'] . ' ' . $ucitel['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
        <?php renderStateFields($verze, $vybranaKatedra, $filtrFakulta, $filtrNazev, $filtrZkratka, $filtrUcitel, $filtrSemestr, $limit, $page, $sort, $order); ?>

        <div class="bulk-save-wrap">
            <button type="submit" name="action" value="update_all" class="btn-save-all">Uložit vše na stránce</button>
        </div>

        <table class="editable-table">
            <thead>
                <tr>
                    <th><a href="<?= htmlspecialchars(buildSortLink('nazev', $sort, $order)) ?>">Předmět</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('zkratka', $sort, $order)) ?>">Zkratka</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('rok', $sort, $order)) ?>">Rok</a></th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('semestr', $sort, $order)) ?>">Semestr</a></th>
                    <th>Fakulta - katedra</th>
                    <th><a href="<?= htmlspecialchars(buildSortLink('typ', $sort, $order)) ?>">Typ výuky</a></th>
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
                            <select name="bulk[<?= (int)$row['id'] ?>][ucitel]">
                                <option value="">-- bez učitele --</option>
                                <?php foreach ($ucitele as $ucitel): ?>
                                    <option value="<?= htmlspecialchars($ucitel['id']) ?>" <?= ((string)$ucitel['id'] === (string)$row['teacherid']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ucitel['surname'] . ' ' . $ucitel['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                        <td colspan="11">Nebyla nalezena žádná data.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
            <li><a href="overview-ucitele.php">Přehled kantoři</a></li>
            <li><a href="settings.php">Nastavení</a></li>
        </ul>
    </div>

    <button id="toggleButton" onclick="toggleNavbarRC()">Zobrazit Menu</button>
    <script src="../js/result-counting.js"></script>
</body>
</html>