<?php
require_once '../includes/dbh.inc.php';
require_once '../includes/functions.php';

$pdo = connectToDatabase();
$verze = getAktivniVerze($pdo);
$zahrnoutAJ = getZahrnoutAJ($pdo);

// Filtry + stránkování
$filtrUcitel  = $_GET['ucitel']  ?? '';
$filtrZkratka = trim($_GET['zkratka'] ?? '');
$filtrSemestr = $_GET['semestr'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);
$page  = (int)($_GET['page']  ?? 1);
if (!in_array($limit, [50, 100], true)) { $limit = 50; }
if ($page < 1) { $page = 1; }

// -----------------------------------------------
// POST – uložení záznamu
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save' && isset($_POST['id'])) {
        $id      = (int)$_POST['id'];
        $kl      = max(0, (int)($_POST['pocet_kl']  ?? 0));
        $zap     = max(0, (int)($_POST['pocet_zap'] ?? 0));
        $zk      = max(0, (int)($_POST['pocet_zk']  ?? 0));
        $dz      = max(0, (int)($_POST['pocet_dz']  ?? 0));

        $stmt = $pdo->prepare("
            UPDATE zkouseni_prirazeni
            SET pocet_kl = ?, pocet_zap = ?, pocet_zk = ?, pocet_dz = ?
            WHERE id = ? AND IdVerze = ?
        ");
        $stmt->execute([$kl, $zap, $zk, $dz, $id, $verze]);
        $flash = 'saved';
    }

    // Redirect zpět se zachováním filtrů + stránkování
    $params = array_filter([
        'ucitel'   => $_POST['return_ucitel']  ?? '',
        'zkratka'  => $_POST['return_zkratka'] ?? '',
        'semestr'  => $_POST['return_semestr'] ?? '',
        'limit'    => $_POST['return_limit']   ?? 50,
        'page'     => $_POST['return_page']    ?? 1,
        'flash'    => $flash ?? null,
    ]);
    header('Location: zkouseni.php' . (!empty($params) ? '?' . http_build_query($params) : ''));
    exit;
}

// -----------------------------------------------
// Načtení učitelů pro filtr
// -----------------------------------------------
$stmtUc = $pdo->prepare("
    SELECT id, CONCAT(surname, ' ', name) AS fullname
    FROM teachers
    WHERE IdVerze = ?
    ORDER BY surname, name
");
$stmtUc->execute([$verze]);
$ucitele = $stmtUc->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------
// Načtení dat zkouseni_prirazeni
// -----------------------------------------------
$where  = ['zp.IdVerze = ?'];
$params = [$verze];

if ($filtrUcitel !== '') {
    $where[]  = 'zp.teacherid = ?';
    $params[] = (int)$filtrUcitel;
}
if ($filtrZkratka !== '') {
    $where[]  = 'p.zkratka LIKE ?';
    $params[] = '%' . $filtrZkratka . '%';
}
if ($filtrSemestr !== '') {
    $where[]  = 'p.semestr = ?';
    $params[] = $filtrSemestr;
}

$whereSQL = implode(' AND ', $where);

// Celkový počet pro stránkování
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM zkouseni_prirazeni zp
    JOIN predmet p  ON p.id = zp.predmetid AND p.IdVerze = zp.IdVerze
    JOIN teachers t ON t.id = zp.teacherid AND t.IdVerze = zp.IdVerze
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT
        zp.id,
        zp.teacherid,
        zp.pocet_kl,
        zp.pocet_zap,
        zp.pocet_zk,
        zp.pocet_dz,
        p.zkratka,
        p.nazev,
        p.semestr,
        p.typZkousky,
        COALESCE(p.aSkut, 0) + COALESCE(p.bSkut, 0) + COALESCE(p.cSkut, 0) AS celkem_skut,
        CONCAT(t.surname, ' ', t.name) AS ucitel_jmeno
    FROM zkouseni_prirazeni zp
    JOIN predmet p    ON p.id = zp.predmetid AND p.IdVerze = zp.IdVerze
    JOIN teachers t   ON t.id = zp.teacherid AND t.IdVerze = zp.IdVerze
    WHERE $whereSQL
    ORDER BY t.surname, t.name, p.zkratka
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Koeficienty pro PB náhled
$koef = ['kl' => 0.3, 'zap' => 0.2, 'zk' => 0.4, 'dz' => 0.7];

function buildZkouseniPageLink(int $targetPage): string {
    $p = $_GET;
    $p['page'] = $targetPage;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Zkoušení – A.2</title>
    <link rel="stylesheet" href="../stylepages/stylepage-overview-ucitele.css">
    <link rel="stylesheet" href="../stylepages/stylepage-result-counting.css">
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
    <style>
        .filters-box {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
            margin-bottom: 20px; padding: 14px;
            border: 1px solid #ddd; border-radius: 8px; background: #fafafa;
        }
        .filters-box .field { display: flex; flex-direction: column; min-width: 180px; }
        .filters-box label { font-weight: bold; margin-bottom: 4px; font-size: .9em; }
        .filters-box select, .filters-box input {
            padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: .9em;
        }
        .btn-filter { padding: 7px 18px; background: #1976d2; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .btn-reset  { padding: 7px 18px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; }

        table.a2-table { width: 100%; border-collapse: collapse; font-size: .9em; }
        table.a2-table th {
            background: #1976d2; color: #fff; padding: 8px 6px;
            text-align: center; white-space: nowrap;
        }
        table.a2-table td { padding: 6px 6px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; }
        table.a2-table tr:nth-child(even) { background: #f5f8ff; }
        table.a2-table tr:hover { background: #e3f0ff; }

        .num-input {
            width: 70px; text-align: center; border: 1px solid #ccc;
            border-radius: 4px; padding: 4px; font-size: .9em;
        }
        .num-input:focus { border-color: #1976d2; outline: none; }
        .btn-save {
            padding: 5px 14px; background: #43a047; color: #fff;
            border: none; border-radius: 4px; cursor: pointer; font-size: .85em;
        }
        .pb-preview { font-size: .8em; color: #555; white-space: nowrap; }
        .tag-typ {
            display: inline-block; padding: 2px 7px; border-radius: 10px;
            font-size: .8em; font-weight: bold;
        }
        .tag-kl  { background: #e8f5e9; color: #2e7d32; }
        .tag-zap { background: #fff3e0; color: #e65100; }
        .tag-zk  { background: #e3f2fd; color: #1565c0; }
        .tag-dz  { background: #fce4ec; color: #880e4f; }
        .tag-szz { background: #f3e5f5; color: #6a1b9a; }
        .flash-ok  { background:#d4edda; border:1px solid #28a745; border-radius:6px; padding:8px 16px; margin-bottom:14px; font-size:.92em; color:#155724; }
        .info-box  { background:#e3f2fd; border:1px solid #90caf9; border-radius:6px; padding:8px 16px; margin-bottom:14px; font-size:.88em; color:#0d47a1; }
        .pagination { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
        .pagination a {
            padding:5px 12px; border:1px solid #ccc; border-radius:4px;
            text-decoration:none; color:#1976d2; background:#fff; font-size:.88em;
        }
        .pagination a:hover { background:#e3f0ff; }
        .pagination .active {
            padding:5px 12px; border:1px solid #1976d2; border-radius:4px;
            background:#1976d2; color:#fff; font-size:.88em; font-weight:bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Zkoušení — část A.2</h1>

    <?php if (isset($_GET['flash']) && $_GET['flash'] === 'saved'): ?>
        <div class="flash-ok">✔ Záznam byl uložen.</div>
    <?php endif; ?>

    <div class="info-box">
        💡 Data jsou automaticky předvyplněna po importu ze STAGu (počty studentů × podíl učitele).
        Zde je můžete ručně upravit. Změny se projeví při příštím exportu úvázkového listu.
    </div>

    <!-- Filtr -->
    <form method="GET" class="filters-box">
        <div class="field">
            <label for="ucitel">Učitel</label>
            <select name="ucitel" id="ucitel">
                <option value="">— Všichni učitelé —</option>
                <?php foreach ($ucitele as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ((string)$u['id'] === (string)$filtrUcitel) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['fullname']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="zkratka">Zkratka předmětu</label>
            <input type="text" name="zkratka" id="zkratka" value="<?= htmlspecialchars($filtrZkratka) ?>" placeholder="např. SK0AR">
        </div>
        <div class="field">
            <label for="semestr">Semestr</label>
            <select name="semestr" id="semestr">
                <option value="">— Oba —</option>
                <option value="ZS" <?= $filtrSemestr === 'ZS' ? 'selected' : '' ?>>ZS</option>
                <option value="LS" <?= $filtrSemestr === 'LS' ? 'selected' : '' ?>>LS</option>
            </select>
        </div>
        <div class="field">
            <label for="limit">Záznamů na stránce</label>
            <select name="limit" id="limit">
                <option value="50"  <?= $limit === 50  ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
        <input type="hidden" name="page" value="1">
        <div class="field" style="flex-direction:row; gap:8px; align-items:flex-end;">
            <button type="submit" class="btn-filter">Filtrovat</button>
            <a href="zkouseni.php" style="text-decoration:none;"><button type="button" class="btn-reset">Reset</button></a>
        </div>
    </form>

    <?php if (empty($zaznamy) && $totalRecords === 0): ?>
        <p>Žádné záznamy. <?= empty($filtrUcitel) && empty($filtrZkratka) ? 'Importujte data ze STAGu nebo spusťte předvyplnění.' : 'Žádná data neodpovídají filtru.' ?></p>
    <?php else: ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
            <strong>Celkem záznamů: <?= $totalRecords ?> &nbsp;|&nbsp; Stránka <?= $page ?> z <?= $totalPages ?> &nbsp;|&nbsp; Zobrazeno <?= count($zaznamy) ?></strong>
            <div class="pagination" style="margin:0;">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(buildZkouseniPageLink(1)) ?>">« První</a>
                    <a href="<?= htmlspecialchars(buildZkouseniPageLink($page - 1)) ?>">‹ Předchozí</a>
                <?php endif; ?>
                <span class="active"><?= $page ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars(buildZkouseniPageLink($page + 1)) ?>">Další ›</a>
                    <a href="<?= htmlspecialchars(buildZkouseniPageLink($totalPages)) ?>">Poslední »</a>
                <?php endif; ?>
            </div>
        </div>

        <table class="a2-table">
            <thead>
                <tr>
                    <th>Předmět</th>
                    <th>Název</th>
                    <th>Sem.</th>
                    <th>Typ ukončení</th>
                    <th title="Celkem studentů ze STAGu (aSkut+bSkut+cSkut)">Celkem STAG</th>
                    <th title="Klasifikovaný zápočet">kl</th>
                    <th title="Zápočet">zap</th>
                    <th title="Zkouška">zk</th>
                    <th title="Dílčí zkouška">dz</th>
                    <th>PB náhled</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zaznamy as $z):
                    $pbNahled = round(
                        $z['pocet_kl']  * $koef['kl']  +
                        $z['pocet_zap'] * $koef['zap'] +
                        $z['pocet_zk']  * $koef['zk']  +
                        $z['pocet_dz']  * $koef['dz'],
                        2
                    );
                    $typClass = match($z['typZkousky']) {
                        'Klasifikovaný zápočet'    => 'tag-kl',
                        'Zápočet'                  => 'tag-zap',
                        'Zkouška'                  => 'tag-zk',
                        'Dílčí zkouška'            => 'tag-dz',
                        'Státní závěrečná zkouška' => 'tag-szz',
                        default                    => '',
                    };
                ?>
                    <tr id="row_<?= $z['id'] ?>">
                        <td><strong><?= htmlspecialchars($z['zkratka']) ?></strong></td>
                        <td><?= htmlspecialchars($z['nazev']) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($z['semestr'] ?? '') ?></td>
                        <td><span class="tag-typ <?= $typClass ?>"><?= htmlspecialchars($z['typZkousky'] ?? '—') ?></span></td>
                        <td style="text-align:center;"><?= (int)$z['celkem_skut'] ?></td>
                        <form method="POST" action="zkouseni.php" style="display:contents;">
                            <input type="hidden" name="action"         value="save">
                            <input type="hidden" name="id"             value="<?= $z['id'] ?>">
                            <input type="hidden" name="return_ucitel"  value="<?= htmlspecialchars($filtrUcitel) ?>">
                            <input type="hidden" name="return_zkratka" value="<?= htmlspecialchars($filtrZkratka) ?>">
                            <input type="hidden" name="return_semestr" value="<?= htmlspecialchars($filtrSemestr) ?>">
                            <input type="hidden" name="return_limit"   value="<?= $limit ?>">
                            <input type="hidden" name="return_page"    value="<?= $page ?>">
                            <td><input type="number" name="pocet_kl"  class="num-input" value="<?= (int)$z['pocet_kl']  ?>" min="0" data-rowid="<?= $z['id'] ?>"></td>
                            <td><input type="number" name="pocet_zap" class="num-input" value="<?= (int)$z['pocet_zap'] ?>" min="0" data-rowid="<?= $z['id'] ?>"></td>
                            <td><input type="number" name="pocet_zk"  class="num-input" value="<?= (int)$z['pocet_zk']  ?>" min="0" data-rowid="<?= $z['id'] ?>"></td>
                            <td><input type="number" name="pocet_dz"  class="num-input" value="<?= (int)$z['pocet_dz']  ?>" min="0" data-rowid="<?= $z['id'] ?>"></td>
                            <td class="pb-preview" id="pb_<?= $z['id'] ?>"><?= $pbNahled ?> PB</td>
                            <td><button type="submit" class="btn-save">Uložit</button></td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:14px;">
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(buildZkouseniPageLink(1)) ?>">« První</a>
                <a href="<?= htmlspecialchars(buildZkouseniPageLink($page - 1)) ?>">‹ Předchozí</a>
            <?php endif; ?>
            <span class="active"><?= $page ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars(buildZkouseniPageLink($page + 1)) ?>">Další ›</a>
                <a href="<?= htmlspecialchars(buildZkouseniPageLink($totalPages)) ?>">Poslední »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <script>
    const koef = {kl: 0.3, zap: 0.2, zk: 0.4, dz: 0.7};
    document.querySelectorAll('input.num-input').forEach(input => {
        input.addEventListener('input', () => {
            const rowId = input.dataset.rowid;
            const row   = document.getElementById('row_' + rowId);
            const kl    = parseFloat(row.querySelector('[name="pocet_kl"]').value)  || 0;
            const zap   = parseFloat(row.querySelector('[name="pocet_zap"]').value) || 0;
            const zk    = parseFloat(row.querySelector('[name="pocet_zk"]').value)  || 0;
            const dz    = parseFloat(row.querySelector('[name="pocet_dz"]').value)  || 0;
            const pb    = (kl * koef.kl + zap * koef.zap + zk * koef.zk + dz * koef.dz).toFixed(2);
            document.getElementById('pb_' + rowId).textContent = pb + ' PB';
        });
    });
    </script>
</body>
</html>
