<?php
require_once 'dbh.inc.php';
require_once 'functions.php';

$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['pocetStudentu']) && is_array($_POST['pocetStudentu'])) {
        foreach ($_POST['pocetStudentu'] as $id => $pocet) {
            $stmt = $pdo->prepare("
                UPDATE rocniky_studijniho_programu
                SET pocetStudentu = ?
                WHERE id = ? AND idVerze = ?
            ");
            $stmt->execute([
                $pocet !== '' ? (int)$pocet : null,
                (int)$id,
                $idVerze
            ]);
        }
    }

    // Zachovej filtry po uložení
    $filterParams = http_build_query(array_filter([
        'fakulta' => $_GET['fakulta'] ?? '',
        'nazev'   => $_GET['nazev']   ?? '',
        'rocnik'  => $_GET['rocnik']  ?? '',
        'idForma' => $_GET['idForma'] ?? '',
        'typ'     => $_GET['typ']     ?? '',
    ], fn($v) => $v !== ''));
    header("Location: ../pages/edit_rocniky.php?success=1" . ($filterParams ? "&$filterParams" : ''));
    exit();
}

// --- Filtry z GET parametrů ---
$filterFakulta = trim($_GET['fakulta'] ?? '');
$filterNazev   = trim($_GET['nazev']   ?? '');
$filterRocnik  = (isset($_GET['rocnik'])  && $_GET['rocnik']  !== '') ? (int)$_GET['rocnik']  : null;
$filterForma   = (isset($_GET['idForma']) && $_GET['idForma'] !== '') ? (int)$_GET['idForma'] : null;
$filterTyp     = (isset($_GET['typ'])     && $_GET['typ']     !== '') ? (int)$_GET['typ']     : null;

// --- Načtení distinct hodnot pro dropdown filtry ---
$stmtFakulty = $pdo->prepare("
    SELECT DISTINCT o.fakulta
    FROM obor o
    WHERE o.IdVerze = ? AND o.fakulta IS NOT NULL AND o.fakulta <> ''
    ORDER BY o.fakulta
");
$stmtFakulty->execute([$idVerze]);
$dostupneFakulty = $stmtFakulty->fetchAll(PDO::FETCH_COLUMN);

$stmtRocniky = $pdo->prepare("
    SELECT DISTINCT rocnik
    FROM rocniky_studijniho_programu
    WHERE idVerze = ?
    ORDER BY rocnik
");
$stmtRocniky->execute([$idVerze]);
$dostupneRocniky = $stmtRocniky->fetchAll(PDO::FETCH_COLUMN);

// --- Hlavní dotaz s filtry ---
$conditions = ['rsp.idVerze = ?'];
$params     = [$idVerze];

if ($filterFakulta !== '') {
    $conditions[] = 'o.fakulta = ?';
    $params[]     = $filterFakulta;
}
if ($filterNazev !== '') {
    $conditions[] = 'sp.nazev LIKE ?';
    $params[]     = '%' . $filterNazev . '%';
}
if ($filterRocnik !== null) {
    $conditions[] = 'rsp.rocnik = ?';
    $params[]     = $filterRocnik;
}
if ($filterForma !== null) {
    $conditions[] = 'rsp.idForma = ?';
    $params[]     = $filterForma;
}
if ($filterTyp !== null) {
    $conditions[] = 'sp.typ = ?';
    $params[]     = $filterTyp;
}

$whereClause = implode(' AND ', $conditions);

$query = "
    SELECT
        rsp.id,
        rsp.stprIdno,
        sp.nazev AS nazev_programu,
        rsp.rocnik,
        rsp.jazyk,
        rsp.idForma,
        rsp.pocetStudentu,
        sp.typ,
        o.fakulta
    FROM rocniky_studijniho_programu rsp
    LEFT JOIN studijniprogram sp
        ON rsp.stprIdno = sp.stprIdno
       AND sp.IdVerze   = rsp.idVerze
    LEFT JOIN (
        SELECT stprIdno, IdVerze, MIN(fakulta) AS fakulta
        FROM obor
        GROUP BY stprIdno, IdVerze
    ) o ON o.stprIdno = rsp.stprIdno AND o.IdVerze = rsp.idVerze
    WHERE $whereClause
    ORDER BY o.fakulta, rsp.jazyk, sp.nazev, rsp.idForma, rsp.rocnik
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rocnikyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

function prelozJazyk($jazyk) {
    return $jazyk == 1 ? 'Čeština' : ($jazyk == 2 ? 'Angličtina' : 'Neznámý');
}

function prelozFormu($forma) {
    return $forma == 1 ? 'Prezenční' : ($forma == 2 ? 'Kombinovaná' : 'Neznámá');
}

function prelozTyp($typ) {
    switch ($typ) {
        case 1: return 'Bakalářský';
        case 2: return 'Navazující';
        case 3: return 'Doktorský';
        default: return 'Neznámý';
    }
}
