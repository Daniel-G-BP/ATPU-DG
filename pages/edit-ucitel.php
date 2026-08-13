<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';
require_once '../includes/workload-functions.php';

$pdo = connectToDatabase();

$idUcitel = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idUcitel <= 0) {
    exit('Neplatné ID učitele.');
}

function h2($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * Načtení detailu učitele + kontaktu pouze pro aktivní verzi
 */
function getUcitelDetail(PDO $pdo, int $idUcitel): ?array
{
    $idVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        SELECT 
            t.id AS id_ucitel,
            t.name,
            t.surname,
            t.titulPred,
            t.titulZa,
            t.ucitIdno,
            t.IdVerze,
            k.email,
            k.telefon,
            k.poznamka
        FROM teachers t
        LEFT JOIN kontakt k
            ON k.idTeacher = t.id
           AND k.idVerze = t.IdVerze
        WHERE t.id = ?
          AND t.IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idUcitel, $idVerze]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Uložení změn pouze do aktivní verze
 */
function saveUcitelDetail(PDO $pdo, int $idUcitel, array $data): void
{
    $idVerze = getAktivniVerze($pdo);

    $name = trim((string)($data['name'] ?? ''));
    $surname = trim((string)($data['surname'] ?? ''));
    $titulPred = trim((string)($data['titulPred'] ?? ''));
    $titulZa = trim((string)($data['titulZa'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $telefon = trim((string)($data['telefon'] ?? ''));
    $poznamka = trim((string)($data['poznamka'] ?? ''));

    $titulPred = $titulPred === '' ? null : $titulPred;
    $titulZa = $titulZa === '' ? null : $titulZa;
    $email = $email === '' ? null : $email;
    $telefon = $telefon === '' ? null : $telefon;
    $poznamka = $poznamka === '' ? null : $poznamka;

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE teachers
            SET name = ?, surname = ?, titulPred = ?, titulZa = ?
            WHERE id = ?
              AND IdVerze = ?
        ");
        $stmt->execute([$name, $surname, $titulPred, $titulZa, $idUcitel, $idVerze]);

        $stmt = $pdo->prepare("
            SELECT id
            FROM kontakt
            WHERE idTeacher = ? AND idVerze = ?
            LIMIT 1
        ");
        $stmt->execute([$idUcitel, $idVerze]);
        $kontaktId = $stmt->fetchColumn();

        if ($kontaktId) {
            $stmt = $pdo->prepare("
                UPDATE kontakt
                SET email = ?, telefon = ?, poznamka = ?
                WHERE id = ?
            ");
            $stmt->execute([$email, $telefon, $poznamka, $kontaktId]);
        } else {
            if ($email !== null || $telefon !== null || $poznamka !== null) {
                $stmt = $pdo->prepare("
                    INSERT INTO kontakt (idTeacher, email, telefon, poznamka, idVerze)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$idUcitel, $email, $telefon, $poznamka, $idVerze]);
            }
        }

        saveTeacherWorkloadProfile($pdo, $idUcitel, $idVerze, $data);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        saveUcitelDetail($pdo, $idUcitel, $_POST);
        $message = 'Údaje byly uloženy.';
    } catch (Throwable $e) {
        $error = 'Chyba při ukládání: ' . $e->getMessage();
    }
}

$teacher = getUcitelDetail($pdo, $idUcitel);

if (!$teacher) {
    exit('Učitel nebyl nalezen v aktuální verzi.');
}
$teacher = array_merge($teacher, getTeacherWorkloadProfile($pdo, $idUcitel, (int)$teacher['IdVerze']));
$workload = calculateTeacherWorkload($pdo, $idUcitel, (int)$teacher['IdVerze']);
$fullTimePoints = getWorkloadFullTimePoints($pdo, (int)$teacher['IdVerze']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Detail učitele</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
            background: #fff;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
        }

        form {
            max-width: 700px;
        }

        .row {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input, textarea {
            width: 100%;
            padding: 9px 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .readonly {
            background: #f5f5f5;
        }

        .actions {
            margin-top: 20px;
        }

        button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 8px;
        }

        button[type="submit"] {
            background: #28a745;
            color: white;
        }

        .btn-close {
            background: #6c757d;
            color: white;
        }

        .msg {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .err {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .workload-summary {
            max-width: 700px;
            margin-bottom: 20px;
            padding: 14px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            background: #f7fafb;
        }

        .workload-summary strong {
            font-size: 18px;
        }

        .workload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .checkbox-row label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-row input {
            width: auto;
        }

        .section-title {
            margin-top: 28px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>
<body>

    <h1>Detail / editace učitele</h1>

    <div class="meta">
        ID učitele: <?= (int)$teacher['id_ucitel'] ?> |
        Učit IDNO: <?= h2($teacher['ucitIdno'] ?? '') ?> |
        Verze: <?= (int)$teacher['IdVerze'] ?>
    </div>

    <div class="workload-summary">
        <strong>
            Aktuální vytíženost:
            <?= h2(number_format($workload['total'], 2, ',', ' ')) ?> /
            <?= h2(number_format($workload['capacity'], 0, ',', ' ')) ?> PB
            (<?= $workload['percent'] === null ? 'bez kapacity' : h2(number_format($workload['percent'], 1, ',', ' ') . ' %') ?>)
        </strong>
        <div>
            A.1 <?= h2(number_format($workload['a1'], 2, ',', ' ')) ?> PB ·
            A.2 <?= h2(number_format($workload['a2'], 2, ',', ' ')) ?> PB ·
            ostatní <?= h2(number_format($workload['a3'] + $workload['b'] + $workload['c'] + $workload['d'], 2, ',', ' ')) ?> PB
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="msg"><?= h2($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="err"><?= h2($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <label for="name">Jméno</label>
            <input type="text" id="name" name="name" value="<?= h2($teacher['name'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="surname">Příjmení</label>
            <input type="text" id="surname" name="surname" value="<?= h2($teacher['surname'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="titulPred">Titul před</label>
            <input type="text" id="titulPred" name="titulPred" value="<?= h2($teacher['titulPred'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="titulZa">Titul za</label>
            <input type="text" id="titulZa" name="titulZa" value="<?= h2($teacher['titulZa'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="email">E-mail</label>
            <input type="text" id="email" name="email" value="<?= h2($teacher['email'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= h2($teacher['telefon'] ?? '') ?>">
        </div>

        <div class="row">
            <label for="poznamka">Poznámka</label>
            <textarea id="poznamka" name="poznamka"><?= h2($teacher['poznamka'] ?? '') ?></textarea>
        </div>

        <h2 class="section-title">Pracovní úvazek</h2>

        <div class="workload-grid">
            <div class="row">
                <label for="uvazek">Velikost úvazku</label>
                <input type="number" id="uvazek" name="uvazek" min="0" max="2" step="0.01"
                       value="<?= h2($teacher['uvazek'] ?? 1) ?>">
                <small>1,00 odpovídá kapacitě <?= h2(number_format($fullTimePoints, 0, ',', ' ')) ?> PB.</small>
            </div>

            <div class="row">
                <label for="pozice">Pozice</label>
                <input type="text" id="pozice" name="pozice" value="<?= h2($teacher['pozice'] ?? '') ?>">
            </div>

            <div class="row">
                <label for="nastup">Datum nástupu</label>
                <input type="date" id="nastup" name="nastup" value="<?= h2($teacher['nastup'] ?? '') ?>">
            </div>

            <div class="row checkbox-row">
                <label for="vyjimka">
                    <input type="checkbox" id="vyjimka" name="vyjimka" value="1" <?= !empty($teacher['vyjimka']) ? 'checked' : '' ?>>
                    Výjimka v posledních 3 letech
                </label>
            </div>
        </div>

        <h3>Souhrnné body činností neevidovaných po položkách</h3>
        <div class="workload-grid">
            <div class="row">
                <label for="pb_a3">A.3 – ostatní pedagogická činnost</label>
                <input type="number" id="pb_a3" name="pb_a3" min="0" step="0.01" value="<?= h2($teacher['pb_a3'] ?? 0) ?>">
            </div>
            <div class="row">
                <label for="pb_b">B – tvůrčí činnost</label>
                <input type="number" id="pb_b" name="pb_b" min="0" step="0.01" value="<?= h2($teacher['pb_b'] ?? 0) ?>">
            </div>
            <div class="row">
                <label for="pb_c">C – administrativní činnost</label>
                <input type="number" id="pb_c" name="pb_c" min="0" step="0.01" value="<?= h2($teacher['pb_c'] ?? 0) ?>">
            </div>
            <div class="row">
                <label for="pb_d">D – další činnost</label>
                <input type="number" id="pb_d" name="pb_d" min="0" step="0.01" value="<?= h2($teacher['pb_d'] ?? 0) ?>">
            </div>
        </div>

        <div class="row">
            <label for="uvazek_poznamka">Poznámka k úvazku</label>
            <textarea id="uvazek_poznamka" name="uvazek_poznamka"><?= h2($teacher['uvazek_poznamka'] ?? '') ?></textarea>
        </div>

        <div class="actions">
            <button type="submit">Uložit</button>
            <button type="button" class="btn-close" onclick="window.close()">Zavřít</button>
        </div>
    </form>

</body>
</html>
