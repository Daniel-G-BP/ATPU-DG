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
        details summary {
            cursor: pointer;
            color: #7a4800;
            font-weight: bold;
            font-size: 0.9rem;
            padding: 4px 0;
        }
        .msg-ok  { color: green; font-weight: bold; }
        .msg-err { color: red;   font-weight: bold; }
    </style>
</head>

<body>
    <div id="navbar">
        <ul>
            <h1 style="text-align: center;">Menu</h1>
            <li><a href="../index.php">Main</a></li>
            <li><a href="view.php">View</a></li>
            <li><a href="edit.php">Edit</a></li>
            <li><a href="insert1.php">Import dat</a></li>
            <li><a href="result-counting.php">Manuální editace</a></li>
            <li><a href="overview-ucitele.php">Přehled učitelé</a></li>
            <li><a href="settings.php">Nastavení</a></li>
        </ul>
    </div>

    <div id="content" class="rounded-border">
        <?php
        include_once '../includes/functions.php';
        include_once '../includes/dbh.inc.php';

        $pdo = connectToDatabase();

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
            $fakulta = $_POST['fakulta'];
            getKatedry($pdo, $fakulta);
            getStudijniProgram($pdo, $fakulta);
            echo "<p class='msg-ok'>✔ Základní data fakulty <strong>" . h($fakulta) . "</strong> načtena (katedry a studijní programy).</p>";
        }

        if (isset($_POST['import_katedra']) && !empty($_POST['katedra'])) {
            $katedra = $_POST['katedra'];
            importJednuKatedruSeVsim($pdo, $katedra);
            echo "<p class='msg-ok'>✔ Data katedry <strong>" . h($katedra) . "</strong> byla úspěšně naimportována a přiřazení učitelů provedeno.</p>";
        }

        if (isset($_POST['import_all_katedry'])) {
            insertAllKatedry($pdo);
            echo "<p class='msg-ok'>✔ Data všech uložených kateder byla naimportována.</p>";
        }

        if (isset($_POST['import_all_fakulty'])) {
            importAllFakultyAKatedry($pdo);
            echo "<p class='msg-ok'>✔ Kompletní import všech fakult dokončen.</p>";
        }

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
        $stmt = $pdo->prepare("
            SELECT r.akademickyrok FROM nastaveni n
            JOIN roky r ON n.Hodnota = r.rok
            WHERE n.Nazev = 'AktivniRok' AND n.IdVerze = ?
            LIMIT 1
        ");
        $stmt->execute([$currentVersion]);
        if ($r = $stmt->fetchColumn()) {
            $currentRok = $r;
        }
        ?>

        <h1>Import dat ze STAGu</h1>
        <p style="color:#555; margin-top:0;">Postupujte podle kroků 1–4. Každý krok je nutné dokončit před tím, než přejdete na následující.</p>

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
            <form method="post">
                <input type="text" name="verze_nazev" placeholder="Název verze (např. 2025/2026)" style="width:220px;" required>
                <input type="submit" name="create_version" value="Vytvořit verzi">
            </form>
        </div>

        <div class="import-step">
            <h3>Přepnout na existující verzi</h3>
            <p class="hint">Pokud máte více verzí, zde vyberete, se kterou chcete pracovat. Změna verze ovlivní zobrazení dat v celé aplikaci.</p>
            <form method="post">
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
            <form method="post">
                <select name="rok">
                    <option value="">Vyberte rok...</option>
                    <?php
                    $stmt = $pdo->query("SELECT rok, akademickyrok FROM roky ORDER BY rok DESC");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rok) {
                        echo "<option value='" . h($rok['rok']) . "'>" . h($rok['akademickyrok']) . "</option>";
                    }
                    ?>
                </select>
                <input type="submit" name="set_rok" value="Nastavit rok">
            </form>
        </div>

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
                <input type="submit" name="import_katedra" value="Importovat katedru"
                    onclick="return confirm('Importovat data katedry? Stávající přiřazení budou přepočítána.');">
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             POKROČILÉ – Hromadný import
        ════════════════════════════════════════════════════════════════════ -->
        <details>
            <summary>▸ Pokročilé – hromadný import</summary>

            <div class="advanced-section" style="margin-top:10px;">
                <h3>Importovat data všech načtených kateder najednou</h3>
                <p class="hint">Provede krok 4 pro každou katedru, která je v databázi (tj. všechny katedry načtené v kroku 3). Může trvat několik minut.</p>
                <form method="post">
                    <input type="submit" name="import_all_katedry" value="Importovat všechny katedry"
                        onclick="return confirm('Spustit import dat pro všechny uložené katedry? Akce může trvat několik minut.');">
                </form>
            </div>

            <div class="advanced-section" style="margin-top:10px;">
                <h3>Kompletní import celé univerzity (všechny fakulty)</h3>
                <p class="hint">⚠ Stáhne data pro všechny fakulty UTB (FAI, FAM, FLK, FMK, FHS, FT, IMS) a všechny jejich katedry. Trvá velmi dlouho. Doporučeno pouze pro první inicializaci nebo úplné obnovení dat.</p>
                <form method="post">
                    <input type="submit" name="import_all_fakulty" value="Spustit kompletní import (celá UTB)"
                        onclick="return confirm('Spustit kompletní import celé UTB? Akce trvá velmi dlouho a přepíše stávající data.');">
                </form>
            </div>
        </details>

    </div>
</body>
</html>
