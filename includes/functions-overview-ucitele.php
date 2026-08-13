<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/workload-functions.php';

/**
 * Sestaví WHERE podmínky pro přehled vyučujících.
 * Sdílené mezi výpisem, počítadlem i exportem, aby filtry byly všude stejné.
 *
 * @param array $filters search, katedra (idpracoviste), fakulta (nadrazenepracoviste)
 */
function buildTeachersFilterSql(array $filters, int $idVerze): array {
    $where = ['t.IdVerze = ?', 't.ucitIdno != 0'];
    $params = [$idVerze];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(t.name LIKE ? OR t.surname LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if (!empty($filters['katedra'])) {
        $where[] = 'pr.idpracoviste = ?';
        $params[] = $filters['katedra'];
    }

    if (!empty($filters['fakulta'])) {
        $where[] = 'pr.nadrazenepracoviste = ?';
        $params[] = $filters['fakulta'];
    }

    if (array_key_exists('teacher_ids', $filters)) {
        $teacherIds = normalizeTeacherIds($filters['teacher_ids']);
        if ($teacherIds === []) {
            $where[] = '1 = 0';
        } else {
            $where[] = 't.id IN (' . implode(',', array_fill(0, count($teacherIds), '?')) . ')';
            $params = array_merge($params, $teacherIds);
        }
    }

    return ['sql' => 'WHERE ' . implode(' AND ', $where), 'params' => $params];
}

function normalizeTeacherIds($teacherIds): array
{
    if (!is_array($teacherIds)) {
        $teacherIds = [$teacherIds];
    }

    $ids = [];
    foreach ($teacherIds as $teacherId) {
        if (is_numeric($teacherId)) {
            $id = (int)$teacherId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }

    return array_values($ids);
}

/**
 * @param array $filters search, katedra, fakulta
 */
function getTeachersData($pdo, int $limit = 100, int $offset = 0, array $filters = []): array {
    $idVerze = getAktivniVerze($pdo);
    $limit = max(1, min(5000, $limit));
    $offset = max(0, $offset);

    $filter = buildTeachersFilterSql($filters, $idVerze);

    // BUG FIX: JOIN byl přes t.ucitIdno=k.idTeacher (špatně) – kontakt.idTeacher je FK na teachers.id
    // BUG FIX: chyběl filtr IdVerze – zobrazovali se učitelé ze všech verzí
    $sql = "
        SELECT
            t.id AS id_ucitel, t.name, t.surname, k.telefon, k.email,
            pr.zkratka AS katedra, pr.nadrazenepracoviste AS fakulta, pr.nazev AS pracoviste_nazev
        FROM teachers t
        LEFT JOIN kontakt k ON t.id = k.idTeacher AND k.idVerze = ?
        LEFT JOIN pracoviste pr ON pr.idpracoviste = t.idPracoviste AND pr.IdVerze = t.IdVerze
        {$filter['sql']}
        ORDER BY t.surname, t.name
        LIMIT $limit OFFSET $offset
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$idVerze], $filter['params']));
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $workloads = calculateTeacherWorkloads($pdo, $idVerze, array_column($teachers, 'id_ucitel'));
        foreach ($teachers as &$teacher) {
            $teacher['workload'] = $workloads[(int)$teacher['id_ucitel']] ?? null;
        }
        unset($teacher);
        return $teachers ?: [];
    } catch (PDOException $e) {
        error_log("Chyba při získávání učitelů: " . $e->getMessage());
        return [];
    }
}

/**
 * Lehčí seznam vyučujících pro checkboxy exportu (bez výpočtu úvazků).
 */
function getTeachersSelectionData(PDO $pdo, array $filters = []): array {
    $idVerze = getAktivniVerze($pdo);
    $filter = buildTeachersFilterSql($filters, $idVerze);

    $stmt = $pdo->prepare("
        SELECT
            t.id AS id_ucitel, t.name, t.surname,
            pr.zkratka AS katedra, pr.nadrazenepracoviste AS fakulta
        FROM teachers t
        LEFT JOIN pracoviste pr ON pr.idpracoviste = t.idPracoviste AND pr.IdVerze = t.IdVerze
        {$filter['sql']}
        ORDER BY t.surname, t.name
        LIMIT 5000
    ");
    $stmt->execute($filter['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getTeachersCount(PDO $pdo, array $filters = []): int {
    $idVerze = getAktivniVerze($pdo);
    $filter = buildTeachersFilterSql($filters, $idVerze);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM teachers t
        LEFT JOIN pracoviste pr ON pr.idpracoviste = t.idPracoviste AND pr.IdVerze = t.IdVerze
        {$filter['sql']}
    ");
    $stmt->execute($filter['params']);
    return (int)$stmt->fetchColumn();
}

/**
 * Všichni vyučující odpovídající filtru (bez stránkování) – pro hromadný export.
 */
function getAllTeachersForExport(PDO $pdo, array $filters = [], ?array $teacherIds = null): array {
    if ($teacherIds !== null) {
        $filters['teacher_ids'] = $teacherIds;
    }

    return getTeachersData($pdo, 5000, 0, $filters);
}
