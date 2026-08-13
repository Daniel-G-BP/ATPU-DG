<?php

/**
 * Jediný zdroj výpočtu vytíženosti pro web i Excel export.
 */

function workloadDefaultCoefficients(): array
{
    return [
        ['A1', 'standard', 'P', 2.25],
        ['A1', 'standard', 'C', 1.50],
        ['A1', 'standard', 'S', 1.50],
        ['A1', 'standard', 'R', 1.50],
        ['A1', 'c', 'P', 3.00],
        ['A1', 'c', 'C', 2.25],
        ['A1', 'c', 'S', 2.25],
        ['A1', 'd', 'P', 3.00],
        ['A1', 'dc', 'P', 4.50],
        ['A2', 'standard', 'KL', 0.30],
        ['A2', 'standard', 'ZAP', 0.20],
        ['A2', 'standard', 'ZK', 0.40],
        ['A2', 'standard', 'DZ', 0.70],
    ];
}

function ensureWorkloadDefaults(PDO $pdo, int $idVerze): void
{
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO uvazek_koeficient
            (IdVerze, oblast, druh, aktivita, hodnota)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach (workloadDefaultCoefficients() as [$oblast, $druh, $aktivita, $hodnota]) {
        $stmt->execute([$idVerze, $oblast, $druh, $aktivita, $hodnota]);
    }
}

function getWorkloadCoefficients(PDO $pdo, int $idVerze): array
{
    ensureWorkloadDefaults($pdo, $idVerze);

    $stmt = $pdo->prepare("
        SELECT oblast, druh, aktivita, hodnota
        FROM uvazek_koeficient
        WHERE IdVerze = ?
        ORDER BY oblast, druh, aktivita
    ");
    $stmt->execute([$idVerze]);

    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['oblast']][$row['druh']][$row['aktivita']] = (float)$row['hodnota'];
    }
    return $result;
}

function saveWorkloadCoefficients(PDO $pdo, int $idVerze, array $values): void
{
    ensureWorkloadDefaults($pdo, $idVerze);
    $allowed = [];
    foreach (workloadDefaultCoefficients() as [$oblast, $druh, $aktivita]) {
        $allowed[$oblast . '|' . $druh . '|' . $aktivita] = true;
    }

    $stmt = $pdo->prepare("
        UPDATE uvazek_koeficient
        SET hodnota = ?
        WHERE IdVerze = ? AND oblast = ? AND druh = ? AND aktivita = ?
    ");

    foreach ($values as $key => $rawValue) {
        $parts = explode('|', (string)$key);
        if (count($parts) !== 3 || !isset($allowed[$key]) || !is_numeric($rawValue)) {
            continue;
        }
        $value = (float)$rawValue;
        if ($value < 0 || $value > 1000) {
            throw new InvalidArgumentException('Koeficient musí být v rozsahu 0 až 1000.');
        }
        [$oblast, $druh, $aktivita] = $parts;
        $stmt->execute([$value, $idVerze, $oblast, $druh, $aktivita]);
    }
}

/**
 * Výchozí hranice přetíženosti v procentech kapacity (úvazku).
 */
const WORKLOAD_OVERLOAD_DEFAULT = 110.0;

/**
 * Výchozí počet pracovních bodů odpovídající plnému úvazku.
 */
const WORKLOAD_FULL_TIME_POINTS_DEFAULT = 1000.0;

/**
 * Vrátí konfigurovatelnou hranici přetíženosti pro aktivní verzi dat.
 * Hodnota je v procentech kapacity (např. 110 = 110 % úvazku).
 */
function getWorkloadOverloadThreshold(PDO $pdo, int $idVerze): float
{
    $stmt = $pdo->prepare("
        SELECT Hodnota
        FROM nastaveni
        WHERE Nazev = 'PretizeniProcent' AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idVerze]);
    $value = $stmt->fetchColumn();

    if ($value === false || !is_numeric($value)) {
        return WORKLOAD_OVERLOAD_DEFAULT;
    }

    $threshold = (float)$value;
    return ($threshold >= 50 && $threshold <= 300) ? $threshold : WORKLOAD_OVERLOAD_DEFAULT;
}

/**
 * Uloží hranici přetíženosti pro aktivní verzi dat (UPSERT přes UPDATE + INSERT).
 */
function saveWorkloadOverloadThreshold(PDO $pdo, int $idVerze, $value): void
{
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Hranice přetíženosti musí být číslo.');
    }

    $threshold = (float)$value;
    if ($threshold < 50 || $threshold > 300) {
        throw new InvalidArgumentException('Hranice přetíženosti musí být v rozsahu 50 až 300 %.');
    }

    $upd = $pdo->prepare("
        UPDATE nastaveni SET Hodnota = ?
        WHERE Nazev = 'PretizeniProcent' AND IdVerze = ?
    ");
    $upd->execute([$threshold, $idVerze]);

    if ($upd->rowCount() === 0) {
        $ins = $pdo->prepare("
            INSERT INTO nastaveni (Nazev, Popis, Hodnota, HodnotaChar, IdVerze)
            VALUES ('PretizeniProcent', 'Hranice přetíženosti vyučujícího v procentech úvazku', ?, NULL, ?)
        ");
        $ins->execute([$threshold, $idVerze]);
    }
}

/**
 * Vrátí počet pracovních bodů, který odpovídá úvazku 1,00.
 */
function getWorkloadFullTimePoints(PDO $pdo, int $idVerze): float
{
    $stmt = $pdo->prepare("
        SELECT Hodnota
        FROM nastaveni
        WHERE Nazev = 'PlnyUvazekPB' AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idVerze]);
    $value = $stmt->fetchColumn();

    if ($value === false || !is_numeric($value)) {
        return WORKLOAD_FULL_TIME_POINTS_DEFAULT;
    }

    $points = (float)$value;
    return ($points > 0 && $points <= 100000) ? $points : WORKLOAD_FULL_TIME_POINTS_DEFAULT;
}

/**
 * Uloží počet pracovních bodů pro úvazek 1,00.
 */
function saveWorkloadFullTimePoints(PDO $pdo, int $idVerze, $value): void
{
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Plný úvazek musí být číslo.');
    }

    $points = (float)$value;
    if ($points <= 0 || $points > 100000) {
        throw new InvalidArgumentException('Plný úvazek musí být v rozsahu 1 až 100000 PB.');
    }

    $upd = $pdo->prepare("
        UPDATE nastaveni SET Hodnota = ?
        WHERE Nazev = 'PlnyUvazekPB' AND IdVerze = ?
    ");
    $upd->execute([$points, $idVerze]);

    if ($upd->rowCount() === 0) {
        $ins = $pdo->prepare("
            INSERT INTO nastaveni (Nazev, Popis, Hodnota, HodnotaChar, IdVerze)
            VALUES ('PlnyUvazekPB', 'Počet pracovních bodů odpovídající úvazku 1,00', ?, NULL, ?)
        ");
        $ins->execute([$points, $idVerze]);
    }
}

function getWorkloadWeeks(PDO $pdo, int $idVerze): array
{
    $stmt = $pdo->prepare("
        SELECT Nazev, Hodnota
        FROM nastaveni
        WHERE IdVerze = ? AND Nazev IN ('PocetTydnuZS', 'PocetTydnuLS')
    ");
    $stmt->execute([$idVerze]);
    $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'ZS' => max(1, (int)($values['PocetTydnuZS'] ?? 14)),
        'LS' => max(1, (int)($values['PocetTydnuLS'] ?? 14)),
    ];
}

function resolveWorkloadKind(array $row): string
{
    $kind = (string)($row['druh_predmetu'] ?? 'auto');
    if (in_array($kind, ['standard', 'c', 'd', 'dc'], true)) {
        return $kind;
    }
    return (int)($row['jazyk'] ?? 0) === 2 ? 'c' : 'standard';
}

function workloadA1Coefficient(array $coefficients, string $kind, string $type): float
{
    $type = strtoupper($type);
    if ($type === 'P' && isset($coefficients['A1'][$kind][$type])) {
        return (float)$coefficients['A1'][$kind][$type];
    }
    if ($kind === 'c' && isset($coefficients['A1']['c'][$type])) {
        return (float)$coefficients['A1']['c'][$type];
    }
    return (float)($coefficients['A1']['standard'][$type] ?? 0);
}

function workloadHoursAndUnit(array $row): array
{
    return match (strtoupper((string)($row['typ'] ?? ''))) {
        'P' => [(float)($row['pocetJednotekPrednaska'] ?? 0), (int)($row['jednotkaPrednaskaTypId'] ?? 2)],
        'C' => [(float)($row['pocetJednotekCviceni'] ?? 0), (int)($row['jednotkaCviceniTypId'] ?? 2)],
        'S' => [(float)($row['pocetJednotekSeminar'] ?? 0), (int)($row['jednotkaSeminarTypId'] ?? 2)],
        default => [0.0, 2],
    };
}

function workloadSubjectUsesFinalYearWeeks(array $row): bool
{
    $code = strtoupper((string)($row['zkratka'] ?? ''));
    if (strlen($code) < 3) {
        return false;
    }
    return in_array($code[2], ['6', '0'], true);
}

function workloadSubjectUsesBlockWeeks(array $row): bool
{
    $code = strtoupper((string)($row['zkratka'] ?? ''));
    if (strlen($code) < 2) {
        return false;
    }
    return $code[1] === 'K';
}

function workloadWeeksForRow(array $row, array $weeks): float
{
    if (workloadSubjectUsesBlockWeeks($row)) {
        return 1.0;
    }

    if (workloadSubjectUsesFinalYearWeeks($row)) {
        return 12.0;
    }

    $semester = (string)($row['semestr'] ?? 'ZS');
    return max(1.0, (float)($weeks[$semester] ?? 14));
}

function workloadSemesterHours(array $row, array $weeks): float
{
    [$hours, $unitId] = workloadHoursAndUnit($row);
    if ($unitId === 1) {
        return $hours;
    }

    $rowWeeks = workloadWeeksForRow($row, $weeks);
    if ($unitId === 3) {
        return $hours * ($rowWeeks / 4.0);
    }

    return $hours * $rowWeeks;
}

function workloadWeeklyHoursForExport(array $row, array $weeks): float
{
    [$hours, $unitId] = workloadHoursAndUnit($row);
    if ($unitId === 2) {
        return $hours;
    }
    if ($unitId === 3) {
        return round($hours / 4.0, 4);
    }
    return round($hours / workloadWeeksForRow($row, $weeks), 4);
}

/**
 * Sdílený SELECT pro všechna místa, která počítají A.1 (web souhrn, detail i export).
 *
 * @param bool $withJazykNazev Přidá textový popis jazyka jako 'jazyk_nazev'.
 *                             Popis se ZÁMĚRNĚ nevrací pod klíčem 'jazyk' –
 *                             ten musí zůstat číselné ID pro resolveWorkloadKind().
 */
function workloadAssignmentSelectSql(bool $withJazykNazev = false): string
{
    $jazykNazevColumn = $withJazykNazev ? ', j.popis AS jazyk_nazev' : '';
    $jazykNazevJoin = $withJazykNazev ? 'LEFT JOIN jazyk j ON j.id = upp.jazyk' : '';

    return "
        SELECT
            upp.id,
            upp.teacherid,
            upp.typ,
            upp.podil,
            upp.jazyk,
            upp.druh_predmetu,
            p.zkratka,
            p.nazev,
            p.semestr,
            ph.pocetJednotekPrednaska,
            ph.jednotkaPrednaskaTypId,
            ph.pocetJednotekCviceni,
            ph.jednotkaCviceniTypId,
            ph.pocetJednotekSeminar,
            ph.jednotkaSeminarTypId
            $jazykNazevColumn
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p ON p.id = upp.predmetid AND p.IdVerze = upp.IdVerze
        LEFT JOIN predmet_hodiny ph ON ph.predmetid = p.id
        $jazykNazevJoin
    ";
}

function getTeacherWorkloadProfiles(PDO $pdo, int $idVerze, ?array $teacherIds = null): array
{
    $where = 't.IdVerze = ?';
    $params = [$idVerze];
    if ($teacherIds !== null) {
        $teacherIds = array_values(array_unique(array_map('intval', $teacherIds)));
        if ($teacherIds === []) {
            return [];
        }
        $where .= ' AND t.id IN (' . implode(',', array_fill(0, count($teacherIds), '?')) . ')';
        $params = array_merge($params, $teacherIds);
    }

    $stmt = $pdo->prepare("
        SELECT
            t.id AS teacherid,
            COALESCE(un.uvazek, 1) AS uvazek,
            un.pozice,
            un.nastup,
            COALESCE(un.vyjimka, 0) AS vyjimka,
            COALESCE(un.pb_a3, 0) AS pb_a3,
            COALESCE(un.pb_b, 0) AS pb_b,
            COALESCE(un.pb_c, 0) AS pb_c,
            COALESCE(un.pb_d, 0) AS pb_d,
            un.poznamka AS uvazek_poznamka
        FROM teachers t
        LEFT JOIN ucitel_uvazek_nastaveni un
            ON un.teacherid = t.id AND un.IdVerze = t.IdVerze
        WHERE $where
    ");
    $stmt->execute($params);

    $profiles = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profiles[(int)$row['teacherid']] = $row;
    }
    return $profiles;
}

function getTeacherWorkloadProfile(PDO $pdo, int $teacherId, int $idVerze): array
{
    $profiles = getTeacherWorkloadProfiles($pdo, $idVerze, [$teacherId]);
    if (!isset($profiles[$teacherId])) {
        throw new InvalidArgumentException('Učitel nebyl nalezen v aktivní verzi.');
    }
    return $profiles[$teacherId];
}

function saveTeacherWorkloadProfile(PDO $pdo, int $teacherId, int $idVerze, array $data): void
{
    getTeacherWorkloadProfile($pdo, $teacherId, $idVerze);

    $uvazek = is_numeric($data['uvazek'] ?? null) ? (float)$data['uvazek'] : 1.0;
    if ($uvazek < 0 || $uvazek > 2) {
        throw new InvalidArgumentException('Úvazek musí být v rozsahu 0 až 2.');
    }

    $points = [];
    foreach (['pb_a3', 'pb_b', 'pb_c', 'pb_d'] as $field) {
        $points[$field] = is_numeric($data[$field] ?? null) ? (float)$data[$field] : 0.0;
        if ($points[$field] < 0) {
            throw new InvalidArgumentException('Doplňkové pracovní body nesmí být záporné.');
        }
    }

    $pozice = trim((string)($data['pozice'] ?? '')) ?: null;
    $nastup = trim((string)($data['nastup'] ?? '')) ?: null;
    if ($nastup !== null) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $nastup);
        if (!$date || $date->format('Y-m-d') !== $nastup) {
            throw new InvalidArgumentException('Datum nástupu není platné.');
        }
    }
    $poznamka = trim((string)($data['uvazek_poznamka'] ?? '')) ?: null;
    $vyjimka = isset($data['vyjimka']) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO ucitel_uvazek_nastaveni
            (teacherid, IdVerze, uvazek, pozice, nastup, vyjimka, pb_a3, pb_b, pb_c, pb_d, poznamka)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            uvazek = VALUES(uvazek),
            pozice = VALUES(pozice),
            nastup = VALUES(nastup),
            vyjimka = VALUES(vyjimka),
            pb_a3 = VALUES(pb_a3),
            pb_b = VALUES(pb_b),
            pb_c = VALUES(pb_c),
            pb_d = VALUES(pb_d),
            poznamka = VALUES(poznamka)
    ");
    $stmt->execute([
        $teacherId, $idVerze, $uvazek, $pozice, $nastup, $vyjimka,
        $points['pb_a3'], $points['pb_b'], $points['pb_c'], $points['pb_d'], $poznamka,
    ]);
}

function calculateTeacherWorkloads(PDO $pdo, int $idVerze, ?array $teacherIds = null): array
{
    $profiles = getTeacherWorkloadProfiles($pdo, $idVerze, $teacherIds);
    $fullTimePoints = getWorkloadFullTimePoints($pdo, $idVerze);
    $result = [];
    foreach ($profiles as $teacherId => $profile) {
        $capacity = max(0.0, (float)$profile['uvazek'] * $fullTimePoints);
        $result[$teacherId] = [
            'teacherid' => $teacherId,
            'a1' => 0.0,
            'a2' => 0.0,
            'a3' => (float)$profile['pb_a3'],
            'b' => (float)$profile['pb_b'],
            'c' => (float)$profile['pb_c'],
            'd' => (float)$profile['pb_d'],
            'uvazek' => (float)$profile['uvazek'],
            'capacity' => $capacity,
            'total' => 0.0,
            'percent' => null,
        ];
    }
    if ($result === []) {
        return [];
    }

    $coefficients = getWorkloadCoefficients($pdo, $idVerze);
    $weeks = getWorkloadWeeks($pdo, $idVerze);
    $ids = array_keys($result);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$idVerze], $ids);
    $ajCondition = getZahrnoutAJ($pdo) ? '' : ' AND upp.jazyk != 2';
    $a2AjCondition = getZahrnoutAJ($pdo) ? '' : "
          AND EXISTS (
              SELECT 1
              FROM ucitelpredmetprirazeni upp_cz
              WHERE upp_cz.IdVerze = zp.IdVerze
                AND upp_cz.predmetid = zp.predmetid
                AND upp_cz.teacherid = zp.teacherid
                AND upp_cz.jazyk != 2
          )
    ";

    $stmt = $pdo->prepare(workloadAssignmentSelectSql() . "
        WHERE upp.IdVerze = ?
          AND upp.teacherid IN ($placeholders)
          $ajCondition
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teacherId = (int)$row['teacherid'];
        $type = strtoupper((string)$row['typ']);
        $coefficient = workloadA1Coefficient($coefficients, resolveWorkloadKind($row), $type);
        $share = max(0.0, (float)$row['podil']) / 100.0;
        $result[$teacherId]['a1'] += workloadSemesterHours($row, $weeks) * $share * $coefficient;
    }

    $stmt = $pdo->prepare("
        SELECT teacherid,
               SUM(pocet_kl) AS kl,
               SUM(pocet_zap) AS zap,
               SUM(pocet_zk) AS zk,
               SUM(pocet_dz) AS dz
        FROM zkouseni_prirazeni zp
        WHERE zp.IdVerze = ? AND zp.teacherid IN ($placeholders)
        $a2AjCondition
        GROUP BY zp.teacherid
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teacherId = (int)$row['teacherid'];
        $a2 = $coefficients['A2']['standard'] ?? [];
        $result[$teacherId]['a2'] =
            (float)$row['kl'] * (float)($a2['KL'] ?? 0) +
            (float)$row['zap'] * (float)($a2['ZAP'] ?? 0) +
            (float)$row['zk'] * (float)($a2['ZK'] ?? 0) +
            (float)$row['dz'] * (float)($a2['DZ'] ?? 0);
    }

    $overloadThreshold = getWorkloadOverloadThreshold($pdo, $idVerze);

    foreach ($result as &$row) {
        foreach (['a1', 'a2', 'a3', 'b', 'c', 'd'] as $field) {
            $row[$field] = round((float)$row[$field], 2);
        }
        $row['total'] = round($row['a1'] + $row['a2'] + $row['a3'] + $row['b'] + $row['c'] + $row['d'], 2);
        $row['percent'] = $row['capacity'] > 0 ? round(($row['total'] / $row['capacity']) * 100, 1) : null;
        $row['overload_threshold'] = $overloadThreshold;
        $row['is_overloaded'] = $row['percent'] !== null && $row['percent'] > $overloadThreshold;
    }
    unset($row);

    return $result;
}

function calculateTeacherWorkload(PDO $pdo, int $teacherId, int $idVerze): array
{
    $rows = calculateTeacherWorkloads($pdo, $idVerze, [$teacherId]);
    if (!isset($rows[$teacherId])) {
        throw new InvalidArgumentException('Učitel nebyl nalezen v aktivní verzi.');
    }
    return $rows[$teacherId];
}

function getTeacherA1ExportRows(PDO $pdo, int $teacherId, int $idVerze): array
{
    $weeks = getWorkloadWeeks($pdo, $idVerze);
    $ajCondition = getZahrnoutAJ($pdo) ? '' : ' AND upp.jazyk != 2';
    $stmt = $pdo->prepare(workloadAssignmentSelectSql() . "
        WHERE upp.IdVerze = ? AND upp.teacherid = ? $ajCondition
        ORDER BY p.zkratka, p.semestr, upp.jazyk, upp.typ, upp.id
    ");
    $stmt->execute([$idVerze, $teacherId]);

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kind = resolveWorkloadKind($row);
        $key = implode('|', [$row['zkratka'], $row['semestr'], $row['jazyk'], $kind]);
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'zkratka' => $row['zkratka'],
                'nazev' => $row['nazev'],
                'semestr' => $row['semestr'],
                'jazyk' => (int)$row['jazyk'],
                'druh' => $kind === 'standard' ? '' : $kind,
                'tydny' => workloadWeeksForRow($row, $weeks),
                'prednaska' => 0.0,
                'cviceni' => 0.0,
                'seminar' => 0.0,
                'skupinyP' => 0.0,
                'skupinyC' => 0.0,
                'skupinyS' => 0.0,
            ];
        }

        $type = strtoupper((string)$row['typ']);
        $hours = workloadWeeklyHoursForExport($row, $weeks);
        $effectiveGroups = max(0.0, (float)$row['podil']) / 100.0;
        if ($type === 'P') {
            $grouped[$key]['prednaska'] = $hours;
            $grouped[$key]['skupinyP'] += $effectiveGroups;
        } elseif ($type === 'C') {
            $grouped[$key]['cviceni'] = $hours;
            $grouped[$key]['skupinyC'] += $effectiveGroups;
        } elseif ($type === 'S') {
            $grouped[$key]['seminar'] = $hours;
            $grouped[$key]['skupinyS'] += $effectiveGroups;
        }
    }

    return array_values($grouped);
}

function getTeacherA2ExportRows(PDO $pdo, int $teacherId, int $idVerze): array
{
    $ajCondition = getZahrnoutAJ($pdo) ? '' : "
          AND EXISTS (
              SELECT 1
              FROM ucitelpredmetprirazeni upp_cz
              WHERE upp_cz.IdVerze = zp.IdVerze
                AND upp_cz.predmetid = zp.predmetid
                AND upp_cz.teacherid = zp.teacherid
                AND upp_cz.jazyk != 2
          )
    ";

    $stmt = $pdo->prepare("
        SELECT
            p.zkratka,
            p.nazev,
            p.semestr,
            p.typZkousky,
            zp.pocet_kl,
            zp.pocet_zap,
            zp.pocet_zk,
            zp.pocet_dz
        FROM zkouseni_prirazeni zp
        JOIN predmet p ON p.id = zp.predmetid AND p.IdVerze = zp.IdVerze
        WHERE zp.teacherid = ? AND zp.IdVerze = ?
          AND (zp.pocet_kl + zp.pocet_zap + zp.pocet_zk + zp.pocet_dz) > 0
          $ajCondition
        ORDER BY p.zkratka
    ");
    $stmt->execute([$teacherId, $idVerze]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Detailní řádky úvazku vyučujícího pro zobrazení na webu.
 *
 * Staví na stejném SQL a stejných výpočetních funkcích jako calculateTeacherWorkloads()
 * a Excel export, aby detail, souhrn i export nemohly rozejít.
 *
 * POZOR: popis jazyka se vrací jako 'jazyk_nazev'. Klíč 'jazyk' musí zůstat
 * číselné ID, protože z něj resolveWorkloadKind() určuje cizojazyčnou výuku.
 */
function getTeacherAssignmentDetailRows(PDO $pdo, int $teacherId, int $idVerze): array
{
    $coefficients = getWorkloadCoefficients($pdo, $idVerze);
    $weeks = getWorkloadWeeks($pdo, $idVerze);
    $ajCondition = getZahrnoutAJ($pdo) ? '' : ' AND upp.jazyk != 2';

    $stmt = $pdo->prepare(workloadAssignmentSelectSql(true) . "
        WHERE upp.IdVerze = ? AND upp.teacherid = ? $ajCondition
        ORDER BY p.zkratka, upp.typ
    ");
    $stmt->execute([$idVerze, $teacherId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['pb'] = round(
            workloadSemesterHours($row, $weeks)
            * (max(0.0, (float)$row['podil']) / 100.0)
            * workloadA1Coefficient($coefficients, resolveWorkloadKind($row), (string)$row['typ']),
            2
        );
    }
    unset($row);

    return $rows;
}

/**
 * CSS třída podle vytíženosti. Hranice přetížení je konfigurovatelná
 * (viz getWorkloadOverloadThreshold), výchozí hodnota je 110 % úvazku.
 */
function workloadStatusClass(?float $percent, ?float $overloadThreshold = null): string
{
    if ($percent === null) {
        return 'workload-neutral';
    }

    $threshold = $overloadThreshold ?? WORKLOAD_OVERLOAD_DEFAULT;

    if ($percent > $threshold) {
        return 'workload-over';
    }
    if ($percent > 100) {
        return 'workload-high';
    }
    if ($percent >= 80) {
        return 'workload-good';
    }
    return 'workload-low';
}
