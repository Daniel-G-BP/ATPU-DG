<?php
require_once __DIR__ . '/../includes/functions.php';
startAppSession();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Import dat ze STAGu</title>
    <link rel="stylesheet" href="../stylepages/stylepage.css">
    <link rel="stylesheet" href="../stylepage.css">
    <script src="../webfunc.js"></script>
    <style>
        .import-step {
            background: #f9f9f9;
            border-left: 4px solid #4a90d9;
            border-radius: 4px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .import-step h3 {
            margin: 0 0 6px 0;
            font-size: 1rem;
            color: #1a3a5c;
        }
        .import-step p.hint {
            margin: 0 0 12px 0;
            color: #555;
            font-size: 0.88rem;
        }
        .import-step form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .step-number {
            display: inline-block;
            background: #4a90d9;
            color: #fff;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            line-height: 26px;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .section-title {
            font-size: 1.05rem;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin: 24px 0 12px 0;
        }
        .version-badge {
            display: inline-block;
            background: #fef3cd;
            border: 1px solid #f0c040;
            border-radius: 4px;
            padding: 4px 12px;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        .advanced-section {
            background: #fff8f0;
            border-left: 4px solid #e09000;
            border-radius: 4px;
            padding: 14px 20px;
            margin-top: 24px;
        }
        .advanced-section h3 {
            margin: 0 0 6px 0;
            font-size: 0.95rem;
            color: #7a4800;
        }
        .advanced-section p.hint {
            margin: 0 0 10px 0;
            color: #7a4800;
            font-size: 0.85rem;
        }
        .advanced-section form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .msg-ok  { color: green; font-weight: bold; }
        .msg-err { color: red;   font-weight: bold; }
        .aj-section {
            background: #f0f4ff;
            border-left: 4px solid #4a6fa5;
            border-radius: 4px;
            padding: 14px 20px;
            margin-bottom: 12px;
        }
        .aj-section h3 { margin: 0 0 6px 0; font-size: 0.95rem; color: #1a2f5c; }
        .aj-section p.hint { margin: 0 0 10px 0; color: #445; font-size: 0.85rem; }
        .aj-section form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .aj-danger { background: #fff5f5; border-left-color: #c0392b; }
        .aj-danger h3 { color: #7a1a1a; }
        .aj-danger p.hint { color: #7a1a1a; }
        .toggle-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.92rem; cursor: pointer; user-select: none;
        }
        .toggle-label input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
        .aj-badge-on  { display:inline-block; background:#198754; color:#fff;
                        border-radius:10px; padding:2px 10px; font-size:.8rem; font-weight:bold; }
        .aj-badge-off { display:inline-block; background:#dc3545; color:#fff;
                        border-radius:10px; padding:2px 10px; font-size:.8rem; font-weight:bold; }
        .credentials-section {
            background: #eef8f0;
            border-left: 4px solid #198754;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 18px 0 20px 0;
        }
        .credentials-section.missing {
            background: #fff5f5;
            border-left-color: #c0392b;
        }
        .credentials-section h3 {
            margin: 0 0 6px 0;
            font-size: 1rem;
            color: #1b5e20;
        }
        .credentials-section.missing h3 {
            color: #7a1a1a;
        }
        .credentials-section p {
            margin: 0 0 10px 0;
            color: #444;
            font-size: 0.88rem;
        }
        .credentials-section form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .credentials-section input[type=text],
        .credentials-section input[type=password] {
            min-width: 210px;
        }
        .import-status-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.82);
        }
        .import-status-overlay[hidden] {
            display: none;
        }
        .import-status-box {
            width: min(520px, calc(100vw - 32px));
            background: #fff;
            border: 1px solid #b8cee6;
            border-left: 5px solid #2f74b5;
            border-radius: 6px;
            box-shadow: 0 12px 32px rgba(20, 45, 75, 0.16);
            padding: 22px 24px;
        }
        .import-status-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .import-status-spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #d6e4f2;
            border-top-color: #2f74b5;
            border-radius: 50%;
            animation: import-spin 0.8s linear infinite;
            flex: 0 0 auto;
        }
        .import-status-title {
            margin: 0;
            color: #1a3a5c;
            font-size: 1rem;
        }
        .import-status-detail,
        .import-status-logline,
        .import-status-time {
            margin: 6px 0 0 36px;
            color: #444;
            font-size: 0.9rem;
        }
        .import-status-logline {
            background: #f6f8fa;
            border: 1px solid #d8dee4;
            border-radius: 4px;
            color: #24292f;
            font-family: Consolas, "Courier New", monospace;
            min-height: 20px;
            padding: 8px 10px;
            white-space: pre-wrap;
        }
        .import-log-summary {
            background: #f6f8fa;
            border: 1px solid #d0d7de;
            border-left: 4px solid #57606a;
            border-radius: 4px;
            margin: 14px 0;
            padding: 12px 14px;
        }
        .import-log-summary h3 {
            margin: 0 0 8px 0;
            font-size: 0.95rem;
            color: #24292f;
        }
        .import-log-summary pre {
            background: #fff;
            border: 1px solid #d8dee4;
            border-radius: 4px;
            color: #24292f;
            font-size: 0.82rem;
            margin: 8px 0;
            max-height: 220px;
            overflow: auto;
            padding: 10px;
            white-space: pre-wrap;
        }
        .import-log-summary a {
            color: #0969da;
            font-weight: 600;
        }
        @keyframes import-spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div id="content" class="rounded-border">
        <?php
        include_once '../includes/functions.php';
        include_once '../includes/dbh.inc.php';

        $pdo = connectToDatabase();

        function currentAcademicYearStartForImportPage(): int {
            $month = (int)date('n');
            $year = (int)date('Y');
            return $month >= 9 ? $year : $year - 1;
        }

        function renderImportLogSummary(array $logInfo): void {
            if (empty($logInfo['path'])) {
                return;
            }

            $filename = basename((string)$logInfo['path']);
            $href = '../logs/importy/' . rawurlencode($filename);
            $tail = $logInfo['tail'] ?? [];

            echo "<div class='import-log-summary'>";
            echo "<h3>Poslednich 10 radku importu</h3>";
            if (!empty($tail)) {
                echo "<pre>" . h(implode(PHP_EOL, $tail)) . "</pre>";
            } else {
                echo "<p class='hint'>Import nevytvoril zadny textovy vystup.</p>";
            }
            echo "<p>Celý log: <a href='" . h($href) . "' target='_blank' rel='noopener'>" . h($filename) . "</a></p>";
            echo "</div>";
        }

        function currentImportLogTokenForImportPage(): ?string {
            return atpuNormalizeImportLogToken($_POST['import_log_token'] ?? null);
        }

        function renderImportProgressTokenInput(): void {
            echo '<input type="hidden" name="import_log_token" value="' . h(atpuCreateImportLogToken()) . '">';
        }

        if (isset($_POST['save_stag_credentials'])) {
            $stagUsername = trim($_POST['stag_username'] ?? '');
            $stagPassword = (string)($_POST['stag_password'] ?? '');

            if ($stagUsername === '' || $stagPassword === '') {
                echo "<p class='msg-err'>Vyplnte prihlasovaci jmeno i heslo do IS/STAG.</p>";
            } else {
                try {
                    saveStagCredentialsToSession($stagUsername, $stagPassword);
                    assertStagCredentialsAccepted();
                    markStagCredentialsVerified();
                    echo "<p class='msg-ok'>Prihlasovaci udaje byly overeny proti IS/STAG a ulozeny pouze do serverove session.</p>";
                } catch (Throwable $e) {
                    clearStagCredentialsFromSession();
                    echo "<p class='msg-err'>Prihlasovaci udaje se nepodarilo overit proti IS/STAG: " . h($e->getMessage()) . "</p>";
                }
            }
        }

        if (isset($_POST['clear_stag_credentials'])) {
            clearStagCredentialsFromSession();
            echo "<p class='msg-ok'>Prihlasovaci udaje do IS/STAG byly ze session odstraneny.</p>";
        }

        $stagCredentials = getStagCredentialsFromSession();

        // ── Zpracování akcí ──────────────────────────────────────────────────

        if (isset($_POST['create_version']) && !empty($_POST['verze_nazev'])) {
            $verzeNazev = trim($_POST['verze_nazev']);

            $stmt = $pdo->prepare("INSERT INTO verze (Nazev, Datum) VALUES (?, NOW())");
            $stmt->execute([$verzeNazev]);
            $newVerzeId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO nastaveni (Nazev, Popis, Hodnota, HodnotaChar, IdVerze)
                SELECT Nazev, Popis, Hodnota, HodnotaChar, ?
                FROM nastaveni
                WHERE IdVerze = 1
                  AND Nazev <> 'AktivniVerze'
            ");
            $stmt->execute([$newVerzeId]);

            $stmt = $pdo->prepare("
                UPDATE nastaveni
                SET Hodnota = ?
                WHERE Nazev = 'AktivniRok' AND IdVerze = ?
            ");
            $stmt->execute([currentAcademicYearStartForImportPage(), $newVerzeId]);

            $stmt = $pdo->prepare("UPDATE nastaveni SET Hodnota = ? WHERE Nazev = 'AktivniVerze'");
            $stmt->execute([$newVerzeId]);

            $stmt = $pdo->prepare("INSERT INTO teachers (name, surname, ucitIdno, idVerze) VALUES ('__', 'empty', 0, ?)");
            $stmt->execute([$newVerzeId]);

            echo "<p class='msg-ok'>✔ Vytvořena a aktivována nová verze: <strong>" . h($verzeNazev) . "</strong></p>";
        }

        if (isset($_POST['set_version']) && !empty($_POST['verze'])) {
            vyber_verzi($pdo);
            echo "<p class='msg-ok'>✔ Aktivní verze změněna.</p>";
        }

        if (isset($_POST['set_rok']) && !empty($_POST['rok'])) {
            $rok = (int)$_POST['rok'];
            aktualnirok($pdo, $rok);
            echo "<p class='msg-ok'>✔ Akademický rok nastaven.</p>";
        }

        if (isset($_POST['load_fakulta']) && !empty($_POST['fakulta'])) {
            if (!hasStagCredentials()) {
                echo "<p class='msg-err'>Pred importem nejdrive zadejte prihlasovaci udaje do IS/STAG.</p>";
            } else {
                $fakulta = $_POST['fakulta'];
                atpuStartImportLog('nacist-fakultu-' . $fakulta, getAktivniVerze($pdo), currentImportLogTokenForImportPage());
                try {
                    assertStagCredentialsValid();
                    getKatedry($pdo, $fakulta);
                    getStudijniProgram($pdo, $fakulta);
                    $logInfo = atpuFinishImportLog('Zakladni data fakulty ' . $fakulta . ' nactena.');
                    echo "<p class='msg-ok'>✔ Základní data fakulty <strong>" . h($fakulta) . "</strong> načtena (katedry a studijní programy).</p>";
                    renderImportLogSummary($logInfo);
                } catch (Throwable $e) {
                    $logInfo = atpuFinishImportLog('CHYBA: ' . $e->getMessage());
                    echo "<p class='msg-err'>" . h($e->getMessage()) . "</p>";
                    renderImportLogSummary($logInfo);
                }
            }
        }

        if (isset($_POST['import_katedra']) && !empty($_POST['katedra'])) {
            if (!hasStagCredentials()) {
                echo "<p class='msg-err'>Pred importem nejdrive zadejte prihlasovaci udaje do IS/STAG.</p>";
            } else {
                $katedra = $_POST['katedra'];
                atpuStartImportLog('import-katedra-' . $katedra, getAktivniVerze($pdo), currentImportLogTokenForImportPage());
                try {
                    assertStagCredentialsValid();
                    importJednuKatedruSeVsim($pdo, $katedra);
                    $logInfo = atpuFinishImportLog('Import katedry ' . $katedra . ' dokoncen.');
                    echo "<p class='msg-ok'>✔ Data katedry <strong>" . h($katedra) . "</strong> byla úspěšně naimportována a přiřazení učitelů provedeno.</p>";
                    renderImportLogSummary($logInfo);
                } catch (Throwable $e) {
                    $logInfo = atpuFinishImportLog('CHYBA: ' . $e->getMessage());
                    echo "<p class='msg-err'>" . h($e->getMessage()) . "</p>";
                    renderImportLogSummary($logInfo);
                }
            }
        }

        if (isset($_POST['import_all_katedry'])) {
            if (!hasStagCredentials()) {
                echo "<p class='msg-err'>Pred importem nejdrive zadejte prihlasovaci udaje do IS/STAG.</p>";
            } else {
                atpuStartImportLog('import-vsechny-katedry', getAktivniVerze($pdo), currentImportLogTokenForImportPage());
                try {
                    assertStagCredentialsValid();
                    insertAllKatedry($pdo);
                    $logInfo = atpuFinishImportLog('Import vsech ulozenych kateder dokoncen.');
                    echo "<p class='msg-ok'>✔ Data všech uložených kateder byla naimportována.</p>";
                    renderImportLogSummary($logInfo);
                } catch (Throwable $e) {
                    $logInfo = atpuFinishImportLog('CHYBA: ' . $e->getMessage());
                    echo "<p class='msg-err'>" . h($e->getMessage()) . "</p>";
                    renderImportLogSummary($logInfo);
                }
            }
        }

        if (isset($_POST['import_all_fakulty'])) {
            if (!hasStagCredentials()) {
                echo "<p class='msg-err'>Pred importem nejdrive zadejte prihlasovaci udaje do IS/STAG.</p>";
            } else {
                atpuStartImportLog('kompletni-import-utb', getAktivniVerze($pdo), currentImportLogTokenForImportPage());
                try {
                    assertStagCredentialsValid();
                    importAllFakultyAKatedry($pdo);
                    $logInfo = atpuFinishImportLog('Kompletni import vsech fakult dokoncen.');
                    echo "<p class='msg-ok'>✔ Kompletní import všech fakult dokončen.</p>";
                    renderImportLogSummary($logInfo);
                } catch (Throwable $e) {
                    $logInfo = atpuFinishImportLog('CHYBA: ' . $e->getMessage());
                    echo "<p class='msg-err'>" . h($e->getMessage()) . "</p>";
                    renderImportLogSummary($logInfo);
                }
            }
        }

        if (isset($_POST['set_zahrnout_aj'])) {
            $hodnota = isset($_POST['zahrnout_aj']) && $_POST['zahrnout_aj'] === '1';
            setZahrnoutAJ($pdo, $hodnota);
            echo "<p class='msg-ok'>✔ Nastavení anglických výuk bylo uloženo.</p>";
        }

        if (isset($_POST['odebrat_ucitele_aj'])) {
            $pocet = odebratUciteleAJ($pdo);
            echo "<p class='msg-ok'>✔ Odebrán učitel z <strong>$pocet</strong> anglických výuk.</p>";
        }

        if (isset($_POST['repopulate_zkouseni'])) {
            atpuStartImportLog('predvyplnit-zkouseni', getAktivniVerze($pdo), currentImportLogTokenForImportPage());
            autoPopulateZkouseniPrirazeni($pdo);
            $logInfo = atpuFinishImportLog('Predvyplneni zkouseni_prirazeni dokonceno.');
            echo "<p class='msg-ok'>✔ Tabulka zkouseni_prirazeni byla předvyplněna.</p>";
            renderImportLogSummary($logInfo);
        }

        // ── Načtení nastavení ZahrnoutAJ ────────────────────────────────────
        $zahrnoutAJ = getZahrnoutAJ($pdo);

        // ── Načtení aktuální verze ───────────────────────────────────────────
        $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmt->execute();
        $currentVersion = $stmt->fetchColumn();

        $currentVersionName = '';
        if ($currentVersion) {
            $stmt = $pdo->prepare("SELECT Nazev, Datum FROM verze WHERE IdVerze = ?");
            $stmt->execute([$currentVersion]);
            if ($v = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currentVersionName = h($v['Nazev']) . ' (' . h($v['Datum']) . ')';
            }
        }

        // ── Načtení aktuálního roku ──────────────────────────────────────────
        $currentRok = '';
        $currentRokValue = (int)getYear($pdo);
        $stmt = $pdo->prepare("
            SELECT r.rok, r.akademickyrok FROM nastaveni n
            JOIN roky r ON n.Hodnota = r.rok
            WHERE n.Nazev = 'AktivniRok' AND n.IdVerze = ?
            LIMIT 1
        ");
        $stmt->execute([$currentVersion]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $currentRokValue = (int)$r['rok'];
            $currentRok = $r['akademickyrok'];
        }
        ?>

        <h1>Import dat ze STAGu</h1>
        <p style="color:#555; margin-top:0;">Nastavte verzi a akademický rok, potom použijte hromadný import.</p>

        <div class="credentials-section <?= $stagCredentials ? '' : 'missing' ?>">
            <h3>Prihlasovaci udaje do IS/STAG</h3>
            <?php if ($stagCredentials): ?>
                <p>
                    Udaje jsou ulozeny pouze v serverove session pro aktualni praci s importem.
                    Aktivni uzivatel: <strong><?= h($stagCredentials['username']) ?></strong>.
                    <?php if (!empty($stagCredentials['verified_at'])): ?>
                        Overeno proti IS/STAG: <strong><?= h(date('d.m.Y H:i', (int)$stagCredentials['verified_at'])) ?></strong>.
                    <?php endif; ?>
                    Heslo se neuklada do databaze, do docker-compose.yml ani do repozitare.
                </p>
                <form method="post">
                    <input type="submit" name="clear_stag_credentials" value="Odstranit udaje ze session">
                </form>
            <?php else: ?>
                <p>
                    Pred importem zadejte vlastni udaje do IS/STAG. Server je pouzije pro volani STAG webovych sluzeb
                    a uchova je jen v session do ukonceni prace nebo rucniho odstraneni.
                </p>
                <form method="post" autocomplete="off" data-progress-message="Overuji prihlasovaci udaje proti IS/STAG...">
                    <input type="text" name="stag_username" placeholder="STAG prihlasovaci jmeno" autocomplete="username" required>
                    <input type="password" name="stag_password" placeholder="STAG heslo" autocomplete="current-password" required>
                    <input type="submit" name="save_stag_credentials" value="Ulozit do session">
                </form>
            <?php endif; ?>
        </div>

        <?php if ($currentVersionName): ?>
            <div class="version-badge">
                Aktivní verze: <strong><?= $currentVersionName ?></strong>
                <?php if ($currentRok): ?> &nbsp;|&nbsp; Rok: <strong><?= h($currentRok) ?></strong><?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════════════
             KROK 1 – Správa verzí
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title"><span class="step-number">1</span> Správa verzí</div>

        <div class="import-step">
            <h3>Vytvořit novou verzi dat</h3>
            <p class="hint">Každý import probíhá v rámci verze. Vytvořte novou verzi pro nový akademický rok nebo pro oddělení různých importů. Nová verze bude automaticky nastavena jako aktivní.</p>
                <form method="post" data-progress-message="Vytvarim novou verzi dat...">
                    <input type="text" name="verze_nazev" placeholder="Název verze (např. 2025/2026)" style="width:220px;" required>
                    <input type="submit" name="create_version" value="Vytvořit verzi">
                </form>
        </div>

        <div class="import-step">
            <h3>Přepnout na existující verzi</h3>
            <p class="hint">Pokud máte více verzí, zde vyberete, se kterou chcete pracovat. Změna verze ovlivní zobrazení dat v celé aplikaci.</p>
                <form method="post" data-progress-message="Prepinam aktivni verzi...">
                    <select name="verze">
                    <option value="">Vyberte verzi...</option>
                    <?php
                    $stmt = $pdo->query("SELECT IdVerze, Nazev, Datum FROM verze ORDER BY Datum DESC, IdVerze DESC");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                        $sel = ((int)$v['IdVerze'] === (int)$currentVersion) ? 'selected' : '';
                        echo "<option value='" . h($v['IdVerze']) . "' $sel>" . h($v['Nazev']) . " (" . h($v['Datum']) . ")</option>";
                    }
                    ?>
                </select>
                <input type="submit" name="set_version" value="Aktivovat verzi">
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             KROK 2 – Akademický rok
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title"><span class="step-number">2</span> Nastavit akademický rok</div>

        <div class="import-step">
            <h3>Vyberte akademický rok pro import</h3>
            <p class="hint">Tento rok se použije při stahování předmětů ze STAGu. Vyberte rok, pro který chcete sestavit úvazky (aktuální rok). Data loňského roku se načtou automaticky pro přiřazení učitelů.</p>
            <form method="post" data-progress-message="Ukladam akademicky rok...">
                <select name="rok">
                    <option value="">Vyberte rok...</option>
                    <?php
                    $stmt = $pdo->query("SELECT rok, akademickyrok FROM roky ORDER BY rok DESC");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rok) {
                        $sel = ((int)$rok['rok'] === $currentRokValue) ? 'selected' : '';
                        echo "<option value='" . h($rok['rok']) . "' $sel>" . h($rok['akademickyrok']) . "</option>";
                    }
                    ?>
                </select>
                <input type="submit" name="set_rok" value="Nastavit rok">
            </form>
        </div>

        <?php /*
        <!-- ═══════════════════════════════════════════════════════════════════
             KROK 3 – Načíst katedry a studijní programy fakulty
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title"><span class="step-number">3</span> Načíst strukturu fakulty</div>

        <div class="import-step">
            <h3>Načíst katedry a studijní programy fakulty</h3>
            <p class="hint">Stáhne ze STAGu seznam kateder (pracovišť) a studijních programů zvolené fakulty. Tento krok je nutné provést jednou před importem dat katedry (krok 4). Například pro FAI zvolte <em>FAI</em>.</p>
            <form method="post">
                <select name="fakulta">
                    <option value="">Vyberte fakultu...</option>
                    <?php
                    $stmt = $pdo->query("SELECT zkratka FROM cisfakulta ORDER BY zkratka");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                        echo "<option value='" . h($f['zkratka']) . "'>" . h($f['zkratka']) . "</option>";
                    }
                    ?>
                </select>
                <?php renderImportProgressTokenInput(); ?>
                <input type="submit" name="load_fakulta" value="Načíst katedry a programy">
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             KROK 4 – Import předmětů, učitelů a přiřazení katedry
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title"><span class="step-number">4</span> Importovat data katedry</div>

        <div class="import-step">
            <h3>Import předmětů, učitelů a přiřazení jedné katedry</h3>
            <p class="hint">
                Hlavní krok importu. Pro zvolenou katedru se stáhnou:
                <strong>předměty aktuálního i loňského roku</strong> (ZS + LS),
                <strong>rozvrhy loňského roku</strong> (pro výpočet podílů),
                <strong>seznam učitelů</strong> a
                <strong>automatické přiřazení učitelů</strong> k předmětům dle historických dat.
                Trvá déle – počkejte na potvrzení.
            </p>
            <form method="post">
                <select name="katedra">
                    <option value="">Vyberte katedru...</option>
                    <?php
                    $stmt = $pdo->query("
                        SELECT zkratka, nazev FROM pracoviste
                        WHERE IdVerze = (SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze' LIMIT 1)
                        ORDER BY zkratka
                    ");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $k) {
                        echo "<option value='" . h($k['zkratka']) . "'>" . h($k['zkratka']) . " – " . h($k['nazev']) . "</option>";
                    }
                    ?>
                </select>
                <?php renderImportProgressTokenInput(); ?>
                <input type="submit" name="import_katedra" value="Importovat katedru"
                    onclick="return confirm('Importovat data katedry? Stávající přiřazení budou přepočítána.');">
            </form>
        </div>
        */ ?>

        <!-- ═══════════════════════════════════════════════════════════════════
             Hromadný import
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title"><span class="step-number">3</span> Hromadný import</div>

        <?php /*
            <div class="advanced-section" style="margin-top:10px;">
                <h3>Importovat data všech načtených kateder najednou</h3>
                <p class="hint">Provede import dat pro každou katedru uloženou v databázi. Může trvat několik minut.</p>
                <form method="post">
                    <?php renderImportProgressTokenInput(); ?>
                    <input type="submit" name="import_all_katedry" value="Importovat všechny katedry"
                        onclick="return confirm('Spustit import dat pro všechny uložené katedry? Akce může trvat několik minut.');">
                </form>
            </div>
        */ ?>

            <div class="advanced-section" style="margin-top:10px;">
                <h3>Kompletní import celé univerzity (všechny fakulty)</h3>
                <p class="hint">⚠ Stáhne data pro všechny fakulty UTB (FAI, FAM, FLK, FMK, FHS, FT, IMS) a všechny jejich katedry. Trvá velmi dlouho. Doporučeno pouze pro první inicializaci nebo úplné obnovení dat.</p>
                <form method="post" data-progress-message="Spoustim kompletni import cele UTB...">
                    <?php renderImportProgressTokenInput(); ?>
                    <input type="submit" name="import_all_fakulty" value="Spustit kompletní import (celá UTB)"
                        onclick="return confirm('Spustit kompletní import celé UTB? Akce trvá velmi dlouho a přepíše stávající data.');">
                </form>
            </div>

            <div class="advanced-section" style="margin-top:10px; border-left-color: #2e7d32;">
                <h3 style="color:#1b5e20;">Předvyplnit zkoušení (A.2) z aktuálních dat</h3>
                <p class="hint" style="color:#1b5e20;">
                    Znovu spustí <code>autoPopulateZkouseniPrirazeni()</code> nad aktuální verzí.
                    Použij pokud sekce A.2 v exportu svítí prázdná, ale data v DB jsou OK.
                    Stávající manuálně upravené záznamy v zkouseni_prirazeni <strong>zůstanou zachovány</strong>
                    (ON DUPLICATE KEY UPDATE ignoruje existující řádky).
                </p>
                <form method="post" data-progress-message="Predvyplnuji zkouseni z aktualnich dat...">
                    <?php renderImportProgressTokenInput(); ?>
                    <input type="submit" name="repopulate_zkouseni" value="Předvyplnit zkouseni_prirazeni"
                        onclick="return confirm('Spustit předvyplnění tabulky zkouseni_prirazeni?');">
                </form>
            </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             SPRÁVA ANGLICKÝCH VÝUK
        ════════════════════════════════════════════════════════════════════ -->
        <div class="section-title">Nastavení anglických výuk</div>

        <div class="aj-section">
            <h3>
                Zahrnout anglické výuky do zobrazení a exportů
                &nbsp;
                <?php if ($zahrnoutAJ): ?>
                    <span class="aj-badge-on">AJ ZAPNUTO</span>
                <?php else: ?>
                    <span class="aj-badge-off">AJ VYPNUTO</span>
                <?php endif; ?>
            </h3>
            <p class="hint">
                Pokud je přepínač vypnut, anglické výuky se <strong>nezobrazují v Manuální editaci</strong>,
                <strong>nezapočítávají se při exportu úvazků</strong> a <strong>nezobrazují se v Přehledu učitele</strong>.
                Data v databázi zůstávají nedotčena.
            </p>
            <form method="post">
                <label class="toggle-label">
                    <input type="checkbox" name="zahrnout_aj" value="1" <?= $zahrnoutAJ ? 'checked' : '' ?>>
                    Zahrnout anglické výuky
                </label>
                <input type="submit" name="set_zahrnout_aj" value="Uložit nastavení">
            </form>
        </div>

        <div class="aj-section aj-danger">
            <h3>Odebrat učitele z anglických výuk</h3>
            <p class="hint">
                Nastaví <code>teacherid = NULL</code> u všech anglických výuk v aktivní verzi.
                Výukové záznamy zůstanou zachovány, pouze bez přiřazeného učitele.
                Tato akce je nevratná bez ručního přiřazení nebo re-importu.
            </p>
            <form method="post">
                <input type="submit" name="odebrat_ucitele_aj" value="Odebrat učitele z AJ výuk"
                    onclick="return confirm('Opravdu odebrat všechny učitele ze všech anglických výuk? Tato akce je nevratná.');">
            </form>
        </div>

    </div>
    <div id="import-status" class="import-status-overlay" hidden>
        <div class="import-status-box" role="status" aria-live="polite">
            <div class="import-status-header">
                <div class="import-status-spinner" aria-hidden="true"></div>
                <h2 class="import-status-title" id="import-status-title">Pracuji...</h2>
            </div>
            <p class="import-status-detail" id="import-status-detail">Pozadavek byl odeslan, cekam na odpoved serveru.</p>
            <p class="import-status-logline" id="import-status-logline">Cekam na prvni radek logu...</p>
            <p class="import-status-time" id="import-status-time">Cas behu: 0 s</p>
        </div>
    </div>
    <script>
        (function () {
            var overlay = document.getElementById('import-status');
            var title = document.getElementById('import-status-title');
            var detail = document.getElementById('import-status-detail');
            var logline = document.getElementById('import-status-logline');
            var time = document.getElementById('import-status-time');
            var details = [
                'Overuji spojeni a pripravuji pozadavek.',
                'Import muze trvat dele podle dostupnosti STAGu.',
                'Stranku nezavirejte, vysledek se zobrazi po dokonceni akce.'
            ];

            document.querySelectorAll('form[data-progress-message]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    var started = Date.now();
                    var detailIndex = 0;
                    var tokenInput = form.querySelector('input[name="import_log_token"]');
                    var logToken = tokenInput ? tokenInput.value : '';
                    title.textContent = form.getAttribute('data-progress-message') || 'Pracuji...';
                    detail.textContent = details[detailIndex];
                    logline.textContent = logToken ? 'Cekam na prvni radek logu...' : 'Pro tuto akci neni prubezny log dostupny.';
                    time.textContent = 'Cas behu: 0 s';
                    overlay.hidden = false;

                    window.setInterval(function () {
                        var seconds = Math.floor((Date.now() - started) / 1000);
                        time.textContent = 'Cas behu: ' + seconds + ' s';
                        if (seconds > 0 && seconds % 7 === 0) {
                            detailIndex = (detailIndex + 1) % details.length;
                            detail.textContent = details[detailIndex];
                        }
                    }, 1000);

                    if (logToken) {
                        window.setInterval(function () {
                            fetch('import-log-tail.php?token=' + encodeURIComponent(logToken), {
                                cache: 'no-store',
                                credentials: 'same-origin'
                            })
                                .then(function (response) { return response.json(); })
                                .then(function (data) {
                                    if (data && data.lastLine) {
                                        logline.textContent = data.lastLine;
                                    }
                                })
                                .catch(function () {
                                    logline.textContent = 'Log zatim nelze precist, import muze stale bezet.';
                                });
                        }, 1500);
                    }
                });
            });
        })();
    </script>
</body>
</html>
