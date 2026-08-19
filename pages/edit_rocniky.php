<?php
require_once '../includes/functions_edit_rocniky.php';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Editace počtu studentů</title>
    <link rel="stylesheet" href="../stylepages/stylepage-edit-rocniky.css">
</head>
<body>
<div class="container">
    <h1>Editace počtu studentů v ročnících studijních programů</h1>
    <p>Tyto hodnoty se používají jako hlavní zdroj pro A2 a pro budoucí export studentů po předmětech.</p>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">Změny byly úspěšně uloženy.</div>
    <?php endif; ?>

    <!-- Filtrační formulář (GET) -->
    <form method="get" action="" class="filter-form">
        <div class="filter-row">
            <div class="filter-group">
                <label for="f-fakulta">Fakulta</label>
                <select name="fakulta" id="f-fakulta">
                    <option value="">-- Vše --</option>
                    <?php foreach ($dostupneFakulty as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>"
                            <?= ($filterFakulta === $f) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="f-nazev">Název programu</label>
                <input type="text" name="nazev" id="f-nazev"
                       placeholder="Hledat..." value="<?= htmlspecialchars($filterNazev) ?>">
            </div>

            <div class="filter-group">
                <label for="f-rocnik">Ročník</label>
                <select name="rocnik" id="f-rocnik">
                    <option value="">-- Vše --</option>
                    <?php foreach ($dostupneRocniky as $r): ?>
                        <option value="<?= (int)$r ?>"
                            <?= ($filterRocnik === (int)$r) ? 'selected' : '' ?>>
                            <?= (int)$r ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="f-forma">Forma</label>
                <select name="idForma" id="f-forma">
                    <option value="">-- Vše --</option>
                    <option value="1" <?= ($filterForma === 1) ? 'selected' : '' ?>>Prezenční</option>
                    <option value="2" <?= ($filterForma === 2) ? 'selected' : '' ?>>Kombinovaná</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="f-typ">Typ</label>
                <select name="typ" id="f-typ">
                    <option value="">-- Vše --</option>
                    <option value="1" <?= ($filterTyp === 1) ? 'selected' : '' ?>>Bakalářský</option>
                    <option value="2" <?= ($filterTyp === 2) ? 'selected' : '' ?>>Navazující</option>
                    <option value="3" <?= ($filterTyp === 3) ? 'selected' : '' ?>>Doktorský</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">Filtrovat</button>
                <a href="edit_rocniky.php" class="btn-reset">Zrušit filtry</a>
            </div>
        </div>
    </form>

    <!-- Počet výsledků -->
    <p class="result-count">
        Zobrazeno: <strong><?= count($rocnikyData) ?></strong> záznamů
    </p>
    <p class="result-count">
        Po změně počtu studentů nejdříve klikněte na <strong>Uložit změny</strong>.
        Odkaz <strong>Nastavit cvičení</strong> potom ukáže předměty daného ročníku, u kterých se v Manuální editaci nastavuje velikost cvičících skupin.
    </p>

    <!-- Editační formulář (POST) — filtry jsou v URL, data v POST body -->
    <?php
    $filterQuery = http_build_query(array_filter([
        'fakulta' => $filterFakulta,
        'nazev'   => $filterNazev,
        'rocnik'  => $filterRocnik !== null ? $filterRocnik : '',
        'idForma' => $filterForma  !== null ? $filterForma  : '',
        'typ'     => $filterTyp    !== null ? $filterTyp    : '',
    ], fn($v) => $v !== '' && $v !== null));
    ?>
    <form method="post" action="../includes/functions_edit_rocniky.php<?= $filterQuery ? '?' . $filterQuery : '' ?>">

        <table>
            <thead>
            <tr>
                <th>Fakulta</th>
                <th>Název programu</th>
                <th>Ročník</th>
                <th>Jazyk</th>
                <th>Forma</th>
                <th>Typ</th>
                <th>Počet studentů</th>
                <th>Cvičení</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rocnikyData)): ?>
                <tr>
                    <td colspan="8" class="no-results">Žádné záznamy neodpovídají zadaným filtrům.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rocnikyData as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['fakulta'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($row['nazev_programu']) ?></td>
                        <td><?= (int)$row['rocnik'] ?></td>
                        <td><?= prelozJazyk($row['jazyk']) ?></td>
                        <td><?= prelozFormu($row['idForma']) ?></td>
                        <td><?= prelozTyp($row['typ']) ?></td>
                        <td>
                            <input type="number" name="pocetStudentu[<?= $row['id'] ?>]"
                                   value="<?= htmlspecialchars($row['pocetStudentu']) ?>" min="0">
                        </td>
                        <td>
                            <a class="btn-cviceni" href="rocnik-cviceni.php?rocnik_id=<?= (int)$row['id'] ?>" target="_blank">
                                Nastavit cvičení
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($rocnikyData)): ?>
            <button type="submit">Uložit změny</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
