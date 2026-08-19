<?php
require_once 'dbh.inc.php';
require_once 'functions.php';

$pdo = connectToDatabase();

function buildReturnUrl(): string
{
    $params = [];

    if (isset($_POST['return_katedra']) && $_POST['return_katedra'] !== '') {
        $params['katedra'] = $_POST['return_katedra'];
    }

    if (isset($_POST['return_fakulta']) && $_POST['return_fakulta'] !== '') {
        $params['fakulta'] = $_POST['return_fakulta'];
    }

    if (isset($_POST['return_nazev']) && $_POST['return_nazev'] !== '') {
        $params['nazev'] = $_POST['return_nazev'];
    }

    if (isset($_POST['return_zkratka']) && $_POST['return_zkratka'] !== '') {
        $params['zkratka'] = $_POST['return_zkratka'];
    }

    if (isset($_POST['return_limit']) && $_POST['return_limit'] !== '') {
        $params['limit'] = (int)$_POST['return_limit'];
    }

    if (isset($_POST['return_page']) && $_POST['return_page'] !== '') {
        $params['page'] = (int)$_POST['return_page'];
    }

    if (isset($_POST['return_sort']) && $_POST['return_sort'] !== '') {
        $params['sort'] = $_POST['return_sort'];
    }

    if (isset($_POST['return_order']) && $_POST['return_order'] !== '') {
        $params['order'] = $_POST['return_order'];
    }

    if (isset($_POST['return_ucitel']) && $_POST['return_ucitel'] !== '') {
        $params['ucitel'] = $_POST['return_ucitel'];
    }

    if (isset($_POST['return_semestr']) && $_POST['return_semestr'] !== '') {
        $params['semestr'] = $_POST['return_semestr'];
    }

    if (isset($_POST['return_omezit_ucitele_fakulta']) && $_POST['return_omezit_ucitele_fakulta'] === '1') {
        $params['omezit_ucitele_fakulta'] = '1';
    }

    $url = '../pages/result-counting.php';
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function redirectBack(array $extra = []): void
{
    $baseUrl = buildReturnUrl();
    if (!empty($extra)) {
        $baseUrl .= (strpos($baseUrl, '?') !== false ? '&' : '?') . http_build_query($extra);
    }
    header('Location: ' . $baseUrl);
    exit;
}

function loadAssignment(PDO $pdo, int $id, int $idVerze): ?array
{
    $stmt = $pdo->prepare("
        SELECT upp.*
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        WHERE upp.id = ? AND upp.IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $idVerze]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function teacherExists(PDO $pdo, ?int $teacherId, int $idVerze): bool
{
    if ($teacherId === null) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM teachers
        WHERE id = ? AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$teacherId, $idVerze]);

    return (bool)$stmt->fetchColumn();
}

function languageExists(PDO $pdo, ?int $jazykId): bool
{
    if ($jazykId === null) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM jazyk
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$jazykId]);

    return (bool)$stmt->fetchColumn();
}

function postString(string $name): string
{
    return trim((string)($_POST[$name] ?? ''));
}

function selectedAssignmentIds(): array
{
    $ids = $_POST['selected_ids'] ?? [];
    if (!is_array($ids)) {
        return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));

    return $ids;
}

function selectedAssignmentPlaceholders(array $ids): string
{
    return implode(', ', array_fill(0, count($ids), '?'));
}

function buildFilteredAssignmentWhere(PDO $pdo, int $idVerze): array
{
    $where = [
        'upp.IdVerze = ?',
        'p.IdVerze = ?',
    ];
    $params = [$idVerze, $idVerze];

    $katedra = postString('return_katedra');
    $fakulta = postString('return_fakulta');
    $nazev = postString('return_nazev');
    $zkratka = postString('return_zkratka');
    $ucitel = postString('return_ucitel');
    $semestr = postString('return_semestr');

    if ($katedra !== '') {
        $where[] = 'p.idPracoviste = ?';
        $params[] = (int)$katedra;
    }
    if ($fakulta !== '') {
        $where[] = 'pr.nadrazenepracoviste = ?';
        $params[] = $fakulta;
    }
    if ($nazev !== '') {
        $where[] = 'p.nazev LIKE ?';
        $params[] = '%' . $nazev . '%';
    }
    if ($zkratka !== '') {
        $where[] = 'p.zkratka LIKE ?';
        $params[] = '%' . $zkratka . '%';
    }
    if ($ucitel !== '') {
        if ($ucitel === 'bez') {
            $where[] = 'upp.teacherid IS NULL';
        } else {
            $where[] = 'upp.teacherid = ?';
            $params[] = (int)$ucitel;
        }
    }
    if ($semestr !== '') {
        $where[] = 'p.semestr = ?';
        $params[] = $semestr;
    }
    if (!getZahrnoutAJ($pdo)) {
        $where[] = 'upp.jazyk != 2';
    }

    return [implode(' AND ', $where), $params];
}

function countFilteredAssignments(PDO $pdo, int $idVerze): int
{
    [$whereSql, $params] = buildFilteredAssignmentWhere($pdo, $idVerze);
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        JOIN pracoviste pr
          ON p.idPracoviste = pr.idpracoviste
         AND pr.IdVerze = p.IdVerze
        LEFT JOIN teachers t
          ON upp.teacherid = t.id
         AND t.IdVerze = upp.IdVerze
        WHERE $whereSql
    ");
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function clearSelectedAssignments(PDO $pdo, array $ids, int $idVerze): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = selectedAssignmentPlaceholders($ids);
    $stmt = $pdo->prepare("
        UPDATE ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        SET upp.teacherid = NULL,
            upp.upraveno_rucne = 1
        WHERE upp.IdVerze = ?
          AND upp.id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$idVerze], $ids));

    return $stmt->rowCount();
}

function deleteSelectedAssignments(PDO $pdo, array $ids, int $idVerze): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = selectedAssignmentPlaceholders($ids);
    $stmt = $pdo->prepare("
        DELETE upp
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        WHERE upp.IdVerze = ?
          AND upp.id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$idVerze], $ids));

    return $stmt->rowCount();
}

function clearFilteredAssignments(PDO $pdo, int $idVerze): int
{
    [$whereSql, $params] = buildFilteredAssignmentWhere($pdo, $idVerze);
    $stmt = $pdo->prepare("
        UPDATE ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        JOIN pracoviste pr
          ON p.idPracoviste = pr.idpracoviste
         AND pr.IdVerze = p.IdVerze
        LEFT JOIN teachers t
          ON upp.teacherid = t.id
         AND t.IdVerze = upp.IdVerze
        SET upp.teacherid = NULL,
            upp.upraveno_rucne = 1
        WHERE $whereSql
    ");
    $stmt->execute($params);

    return $stmt->rowCount();
}

function deleteFilteredAssignments(PDO $pdo, int $idVerze): int
{
    [$whereSql, $params] = buildFilteredAssignmentWhere($pdo, $idVerze);
    $stmt = $pdo->prepare("
        DELETE upp
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
          ON p.id = upp.predmetid
         AND p.IdVerze = upp.IdVerze
        JOIN pracoviste pr
          ON p.idPracoviste = pr.idpracoviste
         AND pr.IdVerze = p.IdVerze
        LEFT JOIN teachers t
          ON upp.teacherid = t.id
         AND t.IdVerze = upp.IdVerze
        WHERE $whereSql
    ");
    $stmt->execute($params);

    return $stmt->rowCount();
}

function normalizeValue($value)
{
    return ($value === '' || $value === null) ? null : $value;
}

function updateAssignment(PDO $pdo, int $id, int $idVerze, array $data): void
{
    $row = loadAssignment($pdo, $id, $idVerze);
    if (!$row) {
        throw new Exception('Záznam nenalezen.');
    }

    $typ = $data['typ'] ?? $row['typ'];
    $jazyk = normalizeValue($data['jazyk'] ?? $row['jazyk']);
    $druhPredmetu = (string)($data['druh_predmetu'] ?? $row['druh_predmetu'] ?? 'auto');
    $ucitel = normalizeValue($data['ucitel'] ?? $row['teacherid']);
    $podil = normalizeValue($data['podil'] ?? $row['podil']);
    $maxStudentu = normalizeValue($data['max_studentu'] ?? $row['max_pocet_studentu']);

    if (!in_array($typ, ['P', 'C', 'S'], true)) {
        throw new Exception('Neplatný typ.');
    }

    if (!in_array($druhPredmetu, ['auto', 'standard', 'c', 'd', 'dc'], true)) {
        throw new Exception('Neplatný druh předmětu pro výpočet úvazku.');
    }

    $jazyk = ($jazyk === null) ? null : (int)$jazyk;
    $ucitel = ($ucitel === null) ? null : (int)$ucitel;
    $podil = ($podil === null) ? 0 : (float)$podil;
    $maxStudentu = ($maxStudentu === null) ? null : (int)$maxStudentu;

    if ($podil < 0 || $podil > 100) {
        throw new Exception('Podíl mimo rozsah.');
    }

    if (!teacherExists($pdo, $ucitel, $idVerze)) {
        throw new Exception('Učitel neexistuje v této verzi.');
    }

    if (!languageExists($pdo, $jazyk)) {
        throw new Exception('Jazyk neexistuje.');
    }

    if ($typ !== 'C') {
        $maxStudentu = null;
    }

    $stmt = $pdo->prepare("
        UPDATE ucitelpredmetprirazeni
        SET typ = :typ,
            jazyk = :jazyk,
            druh_predmetu = :druh_predmetu,
            teacherid = :teacherid,
            podil = :podil,
            max_pocet_studentu = :max_pocet_studentu,
            upraveno_rucne = 1
        WHERE id = :id
          AND IdVerze = :idVerze
    ");
    $stmt->execute([
        ':typ' => $typ,
        ':jazyk' => $jazyk,
        ':druh_predmetu' => $druhPredmetu,
        ':teacherid' => $ucitel,
        ':podil' => $podil,
        ':max_pocet_studentu' => $maxStudentu,
        ':id' => $id,
        ':idVerze' => $idVerze
    ]);

    if ($typ === 'C' && $maxStudentu !== null) {
        $stmt = $pdo->prepare("
            UPDATE ucitelpredmetprirazeni
            SET max_pocet_studentu = :max_pocet_studentu,
                upraveno_rucne = 1
            WHERE predmetid = :predmetid
              AND IdVerze = :idVerze
              AND typ = 'C'
        ");
        $stmt->execute([
            ':max_pocet_studentu' => $maxStudentu,
            ':predmetid' => $row['predmetid'],
            ':idVerze' => $idVerze
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack(['error' => 'invalid_request']);
}

$action = $_POST['action'] ?? '';
$idVerze = isset($_POST['idVerze']) ? (int)$_POST['idVerze'] : getAktivniVerze($pdo);
$bulk = $_POST['bulk'] ?? [];

if (!$idVerze || $action === '') {
    redirectBack(['error' => 'missing']);
}

try {
    if ($action === 'bulk_clear_selected') {
        $affected = clearSelectedAssignments($pdo, selectedAssignmentIds(), $idVerze);
        redirectBack(['bulk_cleared' => $affected]);
    }

    if ($action === 'bulk_delete_selected') {
        $affected = deleteSelectedAssignments($pdo, selectedAssignmentIds(), $idVerze);
        redirectBack(['bulk_deleted' => $affected]);
    }

    if ($action === 'bulk_clear_filtered') {
        $affected = clearFilteredAssignments($pdo, $idVerze);
        redirectBack(['bulk_cleared' => $affected]);
    }

    if ($action === 'bulk_delete_filtered') {
        $affected = deleteFilteredAssignments($pdo, $idVerze);
        redirectBack(['bulk_deleted' => $affected]);
    }

    if ($action === 'update_all') {
        $pdo->beginTransaction();

        foreach ($bulk as $id => $data) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }

            try {
                updateAssignment($pdo, $id, $idVerze, $data);
            } catch (Throwable $e) {
                throw new Exception('Chyba u řádku ID ' . $id . ': ' . $e->getMessage());
            }
        }

        $pdo->commit();
        redirectBack(['updated_all' => 1]);
    }

    if (!preg_match('/^(update_one|odebrat|smazat|kopirovat)_(\d+)$/', $action, $m)) {
        redirectBack(['error' => 'unknown_action']);
    }

    $realAction = $m[1];
    $id = (int)$m[2];

    if ($id <= 0) {
        redirectBack(['error' => 'invalid_id']);
    }

    $row = loadAssignment($pdo, $id, $idVerze);
    if (!$row) {
        redirectBack(['error' => 'not_found']);
    }

    switch ($realAction) {
        case 'update_one':
            $pdo->beginTransaction();
            $data = $bulk[$id] ?? [];
            updateAssignment($pdo, $id, $idVerze, $data);
            $pdo->commit();
            redirectBack(['updated' => $id]);

        case 'odebrat':
            $stmt = $pdo->prepare("
                UPDATE ucitelpredmetprirazeni
                SET teacherid = NULL,
                    upraveno_rucne = 1
                WHERE id = ? AND IdVerze = ?
            ");
            $stmt->execute([$id, $idVerze]);
            redirectBack(['cleared' => $id]);

        case 'smazat':
            $stmt = $pdo->prepare("
                DELETE FROM ucitelpredmetprirazeni
                WHERE id = ? AND IdVerze = ?
            ");
            $stmt->execute([$id, $idVerze]);
            redirectBack(['deleted' => $id]);

        case 'kopirovat':
            unset($row['id']);
            $row['upraveno_rucne'] = 1;

            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));

            $stmt = $pdo->prepare("
                INSERT INTO ucitelpredmetprirazeni ($columns)
                VALUES ($placeholders)
            ");

            $stmt->execute(array_values($row));
            redirectBack(['copied' => $id]);
    }

    redirectBack(['error' => 'unknown_action']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectBack(['error' => 'db', 'msg' => $e->getMessage()]);
}
