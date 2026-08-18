<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Vložit externistu</title>
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
</head>
<body>
    <div class="main-content" align="center">
        <h1>Vložit nového externistu</h1>

        <?php
        include_once '../includes/functions.php';
        include_once '../includes/dbh.inc.php';
        $pdo = connectToDatabase();
        $idVerze = getAktivniVerze($pdo);

        function hExternista($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }

        $stmt = $pdo->query("SELECT id, zkratka FROM cistituly ORDER BY zkratka ASC");
        $tituly = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT DISTINCT zkratka
            FROM cisfakulta
            WHERE zkratka IS NOT NULL
              AND zkratka <> ''
            ORDER BY zkratka
        ");
        $fakulty = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
            SELECT idpracoviste, zkratka, nazev, nadrazenepracoviste
            FROM pracoviste
            WHERE IdVerze = ?
            ORDER BY nadrazenepracoviste, zkratka
        ");
        $stmt->execute([$idVerze]);
        $pracoviste = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $message = '';
        $error = '';

        if (isset($_POST['send'])) {
            $name    = trim($_POST["name"]    ?? '');
            $surname = trim($_POST["surname"] ?? '');
            $email   = trim($_POST["email"]   ?? '');
            $phone   = trim($_POST["phone"]   ?? '');
            $other   = trim($_POST["other"]   ?? '');
            $titulId = intval($_POST['titul'] ?? 0);
            $fakulta = trim($_POST['fakulta'] ?? '');
            $idPracoviste = ($_POST['pracoviste'] ?? '') !== '' ? (int)$_POST['pracoviste'] : null;

            if ($idPracoviste === null) {
                $error = 'Vyberte pracoviště externisty.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT idpracoviste
                    FROM pracoviste
                    WHERE idpracoviste = ?
                      AND IdVerze = ?
                      AND (? = '' OR nadrazenepracoviste = ?)
                    LIMIT 1
                ");
                $stmt->execute([$idPracoviste, $idVerze, $fakulta, $fakulta]);

                if (!$stmt->fetchColumn()) {
                    $error = 'Vybrané pracoviště nepatří do aktivní verze nebo neodpovídá zvolené fakultě.';
                }
            }

            if ($error === '') {
                $ucitIdno = -getSetUcitIdnoExternista($pdo); // externistu poznáme podle záporného ucitIdno

                $stmt = $pdo->prepare("
                    INSERT INTO teachers (name, surname, ucitIdno, IdVerze, idCisTituly, idPracoviste)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $surname, $ucitIdno, $idVerze, $titulId ?: null, $idPracoviste]);
                $teacherId = (int)$pdo->lastInsertId(); // BUG FIX: použít teachers.id, ne ucitIdno jako FK

                $stmt = $pdo->prepare("INSERT INTO kontakt (idTeacher, email, telefon, poznamka, IdVerze) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$teacherId, $email, $phone, $other, $idVerze]);

                $message = 'Externista <strong>' . hExternista($name) . ' ' . hExternista($surname) . '</strong> byl úspěšně vložen.';
            }
        }
        ?>

        <?php if ($message !== ''): ?>
            <p style="color:green; font-weight:bold;"><?= $message ?></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p style="color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; padding:8px 12px; border-radius:6px; font-weight:bold;">
                <?= hExternista($error) ?>
            </p>
        <?php endif; ?>

        <form method="post" class="editable-table" style="max-width: 520px; margin-top: 30px;">
            <table>
                <tr>
                    <td><label for="titul">Titul:</label></td>
                    <td>
                        <select name="titul" id="titul">
                            <option value="">-- vyberte titul --</option>
                            <?php foreach ($tituly as $titulOption): ?>
                                <option value="<?= hExternista($titulOption['id']) ?>"
                                    <?= !empty($_POST['titul']) && (int)$_POST['titul'] === (int)$titulOption['id'] ? 'selected' : '' ?>>
                                    <?= hExternista($titulOption['zkratka']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="fakulta">Fakulta:</label></td>
                    <td>
                        <select name="fakulta" id="fakulta" required>
                            <option value="">-- vyberte fakultu --</option>
                            <?php foreach ($fakulty as $fakultaOption): ?>
                                <option value="<?= hExternista($fakultaOption) ?>"
                                    <?= ($_POST['fakulta'] ?? '') === (string)$fakultaOption ? 'selected' : '' ?>>
                                    <?= hExternista($fakultaOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="pracoviste">Pracoviště:</label></td>
                    <td>
                        <select name="pracoviste" id="pracoviste" required>
                            <option value="">-- vyberte pracoviště --</option>
                            <?php foreach ($pracoviste as $pracovisteOption): ?>
                                <option value="<?= hExternista($pracovisteOption['idpracoviste']) ?>"
                                    data-fakulta="<?= hExternista($pracovisteOption['nadrazenepracoviste']) ?>"
                                    <?= !empty($_POST['pracoviste']) && (int)$_POST['pracoviste'] === (int)$pracovisteOption['idpracoviste'] ? 'selected' : '' ?>>
                                    <?= hExternista($pracovisteOption['nadrazenepracoviste'] . ' - ' . $pracovisteOption['zkratka'] . ' - ' . $pracovisteOption['nazev']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="pracoviste-help" style="display:block; margin-top:4px; color:#666;"></small>
                    </td>
                </tr>
                <tr><td><label for="name">Jméno:</label></td>
                    <td><input type="text" id="name" name="name" value="<?= hExternista($_POST['name'] ?? '') ?>" required></td></tr>
                <tr><td><label for="surname">Příjmení:</label></td>
                    <td><input type="text" id="surname" name="surname" value="<?= hExternista($_POST['surname'] ?? '') ?>" required></td></tr>
                <tr><td><label for="email">Email:</label></td>
                    <td><input type="text" id="email" name="email" value="<?= hExternista($_POST['email'] ?? '') ?>" required></td></tr>
                <tr><td><label for="phone">Telefon:</label></td>
                    <td><input type="text" id="phone" name="phone" value="<?= hExternista($_POST['phone'] ?? '') ?>" required></td></tr>
                <tr><td><label for="other">Jiné:</label></td>
                    <td><input type="text" id="other" name="other" value="<?= hExternista($_POST['other'] ?? '') ?>" required></td></tr>
                <tr><td colspan="2" style="text-align:center;">
                    <button type="submit" name="send" value="Vytvořit" class="button">Vytvořit</button>
                </td></tr>
            </table>
        </form>
    </div>
    <script>
        (function () {
            const fakulta = document.getElementById('fakulta');
            const pracoviste = document.getElementById('pracoviste');
            const pracovisteHelp = document.getElementById('pracoviste-help');

            function filterPracoviste() {
                const selectedFaculty = fakulta.value;
                let visibleWorkplaces = 0;
                Array.from(pracoviste.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = selectedFaculty !== '' && option.dataset.fakulta !== selectedFaculty;
                    if (!option.hidden) {
                        visibleWorkplaces++;
                    }
                });

                const selected = pracoviste.options[pracoviste.selectedIndex];
                if (selected && selected.hidden) {
                    pracoviste.value = '';
                }

                pracovisteHelp.textContent = selectedFaculty && visibleWorkplaces === 0
                    ? 'Pro zvolenou fakultu zatím nejsou v aktivní verzi načtena žádná pracoviště.'
                    : '';
            }

            fakulta.addEventListener('change', filterPracoviste);
            filterPracoviste();
        })();
    </script>
</body>
</html>
