<?php

function myMessage() {
    echo "hello world!";
}

/**
 * =========================
 * HELPER FUNKCE
 * =========================
 */

function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function safeEcho($message) {
    echo nl2br(h($message) . "\n");
}

function stagAuthContext() {
    require __DIR__ . '/config.php';

    $auth = base64_encode($username . ':' . $password);

    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic $auth\r\nAccept: application/json\r\n",
            'timeout' => 60,
            'ignore_errors' => true
        ]
    ]);
}

// function stagGetJson($url, $maxAttempts = 3, $sleepMs = 800) {
//     $lastError = null;
//     $lastHeaders = [];

//     for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
//         $context = stagAuthContext();
//         $response = @file_get_contents($url, false, $context);
//         $headers = $GLOBALS['http_response_header'] ?? [];
//         $lastHeaders = $headers;

//         if ($response !== false) {
//             $data = json_decode($response, true);

//             if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
//                 throw new Exception("JSON decode error for URL: $url | " . json_last_error_msg());
//             }

//             return $data;
//         }

//         $err = error_get_last();
//         $lastError = $err['message'] ?? 'Neznámá chyba';

//         if ($attempt < $maxAttempts) {
//             usleep($sleepMs * 1000);
//         }
//     }

//     throw new Exception(
//         "STAG request failed after {$maxAttempts} attempts: $url | error: {$lastError} | headers: " . implode(' | ', $lastHeaders)
//     );
// }

function stagGetJson($url, $maxAttempts = 3, $sleepMs = 800) {
    $lastError = null;
    $lastHeaders = [];
    $lastResponseSnippet = '';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $context = stagAuthContext();
        $response = @file_get_contents($url, false, $context);
        $headers = $GLOBALS['http_response_header'] ?? [];
        $lastHeaders = $headers;

        if ($response === false) {
            $err = error_get_last();
            $lastError = $err['message'] ?? 'Neznámá chyba';

            if ($attempt < $maxAttempts) {
                usleep($sleepMs * 1000);
                continue;
            }

            break;
        }

        $lastResponseSnippet = mb_substr($response, 0, 1000);

        // 1) první pokus: decode bez úprav
        $data = json_decode($response, true);
        if ($data !== null || json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        $firstJsonError = json_last_error_msg();

        // 2) druhý pokus: odstranění neplatných řídicích znaků
        $cleanResponse = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $response);

        $data = json_decode($cleanResponse, true);
        if ($data !== null || json_last_error() === JSON_ERROR_NONE) {
            safeEcho("Upozornění: JSON pro URL byl opraven vyčištěním řídicích znaků: $url");
            return $data;
        }

        $lastError = "JSON decode error for URL: $url | první pokus: $firstJsonError | po vyčištění: " . json_last_error_msg();

        if ($attempt < $maxAttempts) {
            usleep($sleepMs * 1000);
        }
    }

    throw new Exception(
        "STAG request failed after {$maxAttempts} attempts: $url | error: {$lastError} | headers: "
        . implode(' | ', $lastHeaders)
        . " | response snippet: " . $lastResponseSnippet
    );
}

function getAktivniVerze($pdo) {
    $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze' LIMIT 1");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getAktivniRok($pdo) {
    return getYear($pdo);
}

function getCurrentVersionValue($pdo, $nazev, $column = 'Hodnota') {
    $allowed = ['Hodnota', 'HodnotaChar'];
    if (!in_array($column, $allowed, true)) {
        throw new InvalidArgumentException("Nepovolený sloupec: $column");
    }

    $idVerze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("SELECT {$column} FROM nastaveni WHERE Nazev = ? AND IdVerze = ? LIMIT 1");
    $stmt->execute([$nazev, $idVerze]);
    return $stmt->fetchColumn();
}

function getKatedraByZkratka($pdo, $zkratka, $idVerze = null) {
    if ($idVerze === null) {
        $idVerze = getAktivniVerze($pdo);
    }

    $stmt = $pdo->prepare("
        SELECT idpracoviste
        FROM pracoviste
        WHERE zkratka = ? AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$zkratka, $idVerze]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['idpracoviste'] : null;
}

function findTeacherDbIdByUcitIdno($pdo, $ucitIdno, $idVerze = null) {
    if ($idVerze === null) {
        $idVerze = getAktivniVerze($pdo);
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM teachers
        WHERE ucitIdno = ? AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$ucitIdno, $idVerze]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : null;
}

function getPredmetIdByZkratkaARok($pdo, $zkratka, $rok, $semestr, $idVerze = null) {
    if ($idVerze === null) {
        $idVerze = getAktivniVerze($pdo);
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM predmet
        WHERE zkratka = ? AND rok = ? AND semestr = ? AND IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$zkratka, $rok, $semestr, $idVerze]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : null;
}

function normalizeForma($forma) {
    if ($forma === "Prezenční") return 1;
    if ($forma === "Kombinovaná") return 2;
    return 0;
}

function normalizeTypStudia($typ) {
    if ($typ === "Bakalářský") return 1;
    if ($typ === "Navazující") return 2;
    if ($typ === "Doktorský") return 3;
    return 0;
}

function normalizeJazyk($jazyk) {
    if ($jazyk === "Čeština") return 1;
    if ($jazyk === "Angličtina") return 2;
    return 0;
}

function normalizeFormaKod($forma) {
    $forma = trim((string)$forma);
    if ($forma === 'Prezenční') return 1;
    if ($forma === 'Kombinovaná') return 2;
    return 0;
}

function normalizeTypKod($typ) {
    $typ = trim((string)$typ);
    if ($typ === 'Bakalářský') return 1;
    if ($typ === 'Navazující') return 2;
    if ($typ === 'Doktorský') return 3;
    return 0;
}

function normalizeJazykKod($jazyk) {
    $jazyk = trim((string)$jazyk);
    if (in_array($jazyk, ['CZ', 'Čeština'], true)) return 1;
    if (in_array($jazyk, ['EN', 'Angličtina'], true)) return 2;
    return 0;
}

function normalizeVyukovaJednotka($jednotka) {
    return ($jednotka === 'HOD/SEM') ? 1 : 2;
}

function stringToIntArray($string) {
    $parts = explode(',', is_string($string) ? $string : '');
    $intArray = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && is_numeric($part)) {
            $intArray[] = (int)$part;
        }
    }

    return $intArray;
}

function getImportSemestry() {
    return ['ZS', 'LS'];
}

function normalizeImportSemestr($semestr, $pdo = null) {
    $allowed = getImportSemestry();

    if ($semestr !== null) {
        $semestr = strtoupper(trim((string)$semestr));
        if (in_array($semestr, $allowed, true)) {
            return $semestr;
        }
    }

    if ($pdo !== null) {
        $fromSettings = getSemestr($pdo);
        if ($fromSettings) {
            $fromSettings = strtoupper(trim((string)$fromSettings));
            if (in_array($fromSettings, $allowed, true)) {
                return $fromSettings;
            }
        }
    }

    return 'LS';
}

/**
 * =========================
 * INIT
 * =========================
 */

function onInit(PDO $pdo) {
    deleteAll($pdo);

    echo "<h3>Inicializace databáze...</h3>";

    try {
        //$pdo->beginTransaction();

        $pdo->exec("CREATE TABLE roky (
            rok INT PRIMARY KEY,
            akademickyrok VARCHAR(10)
        )");

        for ($year = 2023; $year <= 2099; $year++) {
            $akademicky = $year . "/" . ($year + 1);
            $stmt = $pdo->prepare("INSERT INTO roky (rok, akademickyrok) VALUES (?, ?)");
            $stmt->execute([$year, $akademicky]);
        }

        $pdo->exec("CREATE TABLE semestr (
            semestr VARCHAR(3) PRIMARY KEY,
            popis VARCHAR(15),
            aktualnisemestr INT DEFAULT 0,
            IdVerze INT
        )");
        $pdo->exec("INSERT INTO semestr (semestr, popis) VALUES ('ZS', 'Zimní semestr')");
        $pdo->exec("INSERT INTO semestr (semestr, popis) VALUES ('LS', 'Letní semestr')");

        $pdo->exec("CREATE TABLE cisfakulta (
            idcis INT AUTO_INCREMENT PRIMARY KEY,
            zkratka VARCHAR(5),
            IdVerze INT
        )");

        $fakulty = ['FAI', 'FAM', 'FLK', 'FMK', 'FHS', 'FT', 'IMS'];
        foreach ($fakulty as $fakulta) {
            $stmt = $pdo->prepare("INSERT INTO cisfakulta (zkratka) VALUES (?)");
            $stmt->execute([$fakulta]);
        }

        $pdo->exec("CREATE TABLE pracoviste (
            idpracoviste INT PRIMARY KEY AUTO_INCREMENT,
            idpracovistestag INT NOT NULL,
            zkratka VARCHAR(7),
            typpracoviste VARCHAR(2),
            nadrazenepracoviste VARCHAR(7),
            nazev VARCHAR(100),
            IdVerze INT,
            UNIQUE KEY uniq_pracoviste_stag (IdVerze, idpracovistestag),
            UNIQUE KEY uniq_pracoviste_zkratka (IdVerze, zkratka)
        )");

        $pdo->exec("CREATE TABLE predmet (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zkratka VARCHAR(20),
            nazev VARCHAR(255),
            rok INT,
            semestr VARCHAR(2),
            cviciciUcitIdno VARCHAR(200),
            seminariciUcitIdno VARCHAR(200),
            prednasejiciUcitIdno VARCHAR(200),
            vyucovaciJazyky VARCHAR(100),
            nahrazPredmety VARCHAR(100),
            idPracoviste INT,
            IdVerze INT,
            UNIQUE KEY uniq_predmet (IdVerze, rok, semestr, zkratka)
        )");

        $pdo->exec("CREATE TABLE predmetlast (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zkratka VARCHAR(20),
            nazev VARCHAR(255),
            rok INT,
            semestr VARCHAR(2),
            cviciciUcitIdno VARCHAR(200),
            seminariciUcitIdno VARCHAR(200),
            prednasejiciUcitIdno VARCHAR(200),
            vyucovaciJazyky VARCHAR(100),
            nahrazPredmety VARCHAR(100),
            idPracoviste INT NULL,
            IdVerze INT,
            UNIQUE KEY uniq_predmetlast (IdVerze, rok, semestr, zkratka)
        )");

        $pdo->exec("CREATE TABLE studijniprogram (
            idstudijniprogram INT PRIMARY KEY AUTO_INCREMENT,
            stprIdno INT,
            nazev VARCHAR(255),
            kod VARCHAR(50),
            platnyod INT,
            pocetprijimanych VARCHAR(50),
            stddelka VARCHAR(10),
            pocetstudentu INT,
            idForma INT,
            typ INT,
            jazyk INT,
            IdVerze INT,
            UNIQUE KEY uniq_studijniprogram (IdVerze, stprIdno)
        )");

        $pdo->exec("CREATE TABLE obor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            oborIdno INT NOT NULL,
            stprIdno INT NOT NULL,
            nazev VARCHAR(255),
            cisloOboru VARCHAR(50),
            typ INT,
            idForma INT,
            jazyk INT,
            fakulta VARCHAR(10),
            rokPlatnosti INT,
            IdVerze INT,
            UNIQUE KEY uniq_obor (IdVerze, oborIdno)
        )");

        $pdo->exec("CREATE TABLE studijni_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stplIdno INT NOT NULL,
            oborIdno INT NOT NULL,
            nazev VARCHAR(255),
            etapa INT DEFAULT NULL,
            pocetSemestru INT DEFAULT NULL,
            rokPlatnosti INT DEFAULT NULL,
            verzePlanu VARCHAR(20) DEFAULT NULL,
            kreditne VARCHAR(5) DEFAULT NULL,
            limitKreditu INT DEFAULT NULL,
            vyucJazyk VARCHAR(10) DEFAULT NULL,
            specializace VARCHAR(10) DEFAULT NULL,
            IdVerze INT,
            UNIQUE KEY uniq_studijni_plan (IdVerze, stplIdno)
        )");

        $pdo->exec("CREATE TABLE plan_predmet_obsazenost (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stplIdno INT NOT NULL,
            rocnik INT NOT NULL,
            rok INT NOT NULL,
            semestr VARCHAR(2) NOT NULL,
            predmet_zkratka VARCHAR(20) NOT NULL,
            nazev VARCHAR(255) DEFAULT NULL,
            katedra VARCHAR(10) DEFAULT NULL,
            typ_akce VARCHAR(20) DEFAULT NULL,
            typ_akce_zkr VARCHAR(5) DEFAULT NULL,
            krouzky TEXT DEFAULT NULL,
            roakIdno INT DEFAULT NULL,
            plan_obsazeni INT DEFAULT NULL,
            obsazeni INT DEFAULT NULL,
            pocet_vyuc_hodin INT DEFAULT NULL,
            platnost VARCHAR(2) DEFAULT NULL,
            vsichniUciteleUcitIdno TEXT DEFAULT NULL,
            IdVerze INT,
            UNIQUE KEY uniq_plan_predmet_obs (IdVerze, stplIdno, rocnik, rok, semestr, roakIdno)
        )");

        $pdo->exec("CREATE TABLE rocniky_studijniho_programu (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stprIdno INT NOT NULL,
            rocnik INT NOT NULL,
            jazyk INT NOT NULL,
            idForma INT NOT NULL,
            idVerze INT NOT NULL,
            pocetStudentu INT DEFAULT NULL,
            UNIQUE KEY uniq_rocnik_sp (idVerze, stprIdno, rocnik, idForma, jazyk)
        )");

        $pdo->exec("CREATE TABLE teachers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50),
            surname VARCHAR(50),
            ucitIdno INT,
            iddbversion INT,
            idCisTituly INT,
            titulPred VARCHAR(30),
            titulZa VARCHAR(30),
            idPracoviste INT,
            IdVerze INT,
            UNIQUE KEY uniq_teacher (IdVerze, ucitIdno)
        )");

        $pdo->exec("CREATE TABLE ucitelPredmety (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ucitIdno INT,
            predmetzkratka VARCHAR(20),
            iddbversion INT,
            IdVerze INT,
            UNIQUE KEY uniq_ucitelPredmety (IdVerze, ucitIdno, predmetzkratka)
        )");

        $pdo->exec("CREATE TABLE ucitelpredmetlast (
            id INT PRIMARY KEY AUTO_INCREMENT,
            predmetid INT,
            teacherid INT,
            typ VARCHAR(15),
            pocetSkupin INT DEFAULT 1,
            vyucHodin FLOAT DEFAULT NULL,
            poznamka TEXT DEFAULT NULL,
            IdVerze INT,
            UNIQUE KEY uniq_ucitelpredmetlast (IdVerze, predmetid, teacherid, typ)
        )");

        $pdo->exec("CREATE TABLE ucitelpredmetprirazeni (
            id INT PRIMARY KEY AUTO_INCREMENT,
            predmetid INT,
            teacherid INT NULL DEFAULT NULL,
            jazyk INT NOT NULL DEFAULT 0,
            typ VARCHAR(7),
            podil FLOAT DEFAULT 100,
            max_pocet_studentu INT NULL,
            IdVerze INT,
            UNIQUE KEY uniq_upp (predmetid, teacherid, typ, jazyk, IdVerze)
        )");

        $pdo->exec("CREATE TABLE seq_ucitIdno (
            num INT
        )");

        $pdo->exec("CREATE TABLE cistituly (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zkratka VARCHAR(20)
        )");

        $pdo->exec("CREATE TABLE verze (
            IdVerze INT AUTO_INCREMENT PRIMARY KEY,
            Nazev VARCHAR(255) NOT NULL,
            Datum DATE NOT NULL
        )");

        $pdo->exec("INSERT INTO verze (IdVerze, Nazev, Datum) VALUES (1, 'Výchozí', CURDATE())");

        $pdo->exec("CREATE TABLE nastaveni (
            IdNastaveni INT AUTO_INCREMENT PRIMARY KEY,
            Nazev VARCHAR(100),
            Popis TEXT,
            Hodnota INT NULL,
            HodnotaChar VARCHAR(100) NULL,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE errnumber (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ucitIdno INT,
            idVerze INT
        )");

        $pdo->exec("CREATE TABLE jazyk (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zkratka VARCHAR(7),
            popis VARCHAR(50),
            UNIQUE KEY uniq_jazyk_popis (popis)
        )");

        $pdo->exec("CREATE TABLE cviceni_max_studenti (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idUcitelPredmetPrirazeni INT,
            pocet INT
        )");

        $pdo->exec("CREATE TABLE vyukove_jednotky (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zkratka VARCHAR(20) UNIQUE NOT NULL,
            popis TEXT
        )");

        $pdo->exec("CREATE TABLE predmet_hodiny (
            id INT AUTO_INCREMENT PRIMARY KEY,
            predmetId INT NOT NULL,
            pocetJednotekSeminar INT DEFAULT 0,
            jednotkaSeminarTypId INT,
            pocetJednotekPrednaska INT DEFAULT 0,
            jednotkaPrednaskaTypId INT,
            pocetJednotekCviceni INT DEFAULT 0,
            jednotkaCviceniTypId INT,
            UNIQUE KEY uniq_predmet_hodiny (predmetId),
            FOREIGN KEY (predmetId) REFERENCES predmet(id)
        )");

        $pdo->exec("CREATE TABLE typ_vyuky (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nazev VARCHAR(20)
        )");

        $pdo->exec("CREATE TABLE kontakt (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idTeacher INT,
            email VARCHAR(250),
            telefon VARCHAR(250),
            poznamka VARCHAR(250),
            idVerze INT
        )");

        $pdo->exec("CREATE TABLE rozvrhova_akce (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roakIdno INT,
            predmet_zkratka VARCHAR(20),
            nazev_predmetu VARCHAR(255),
            katedra VARCHAR(10),
            rok INT,
            semestr VARCHAR(2),
            typ_akce VARCHAR(20),
            typ_akce_zkr VARCHAR(5),
            pocet_vyuc_hodin INT,
            krouzky TEXT,
            IdVerze INT,
            UNIQUE KEY uniq_rozvrhova_akce (IdVerze, roakIdno)
        )");

        $pdo->exec("CREATE TABLE rozvrhova_akce_ucitel (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roakIdno INT,
            ucitIdno INT,
            podil_na_vyuce FLOAT,
            IdVerze INT,
            UNIQUE KEY uniq_rozvrhova_akce_ucitel (IdVerze, roakIdno, ucitIdno)
        )");

        $pdo->exec("CREATE TABLE seq_ucitIdnoExternista (
            id INT PRIMARY KEY,
            cislo INT
        )");

        $pdo->exec("CREATE TABLE predmet_jazyk (
            id INT AUTO_INCREMENT PRIMARY KEY,
            predmetid INT NOT NULL,
            jazykid INT NOT NULL,
            UNIQUE KEY uniq_predmet_jazyk (predmetid, jazykid)
        )");

        $pdo->exec("CREATE VIEW vwPredmetJazyk AS
            SELECT DISTINCT predmetid, jazykid
            FROM predmet_jazyk
        ");

        $pdo->exec("INSERT INTO nastaveni (Nazev, Popis, Hodnota, HodnotaChar, IdVerze) VALUES
            ('AktivniVerze', 'ID aktivní verze', 1, NULL, 1),
            ('AktivniRok', 'ID aktivního roku', 2025, NULL, 1),
            ('AktivniKatedra', 'ID aktivní katedry', 0, NULL, 1),
            ('ResCountKatedra', 'Zobrazena katedra v result counting', 0, NULL, 1),
            ('AktivniSemestr', 'Kód aktivního semestru', NULL, NULL, 1),
            ('PredmetPrez', 'Zacatek zkratky predmetu pro prezencni studium', NULL, 'AP', 1),
            ('PredmetKomb', 'Zacatek zkratky predmetu pro kombinovane studium', NULL, 'AK', 1),
            ('PredmetAng', 'Zacatek zkratky predmetu pro anglickou vyuku', NULL, 'AE', 1),
            ('PocetTydnuZS', 'Počet výukových týdnů v zimním semestru', 14, NULL, 1),
            ('PocetTydnuLS', 'Počet výukových týdnů v letním semestru', 14, NULL, 1)
        ");

        $pdo->exec("INSERT INTO vyukove_jednotky (id, zkratka, popis) VALUES (1, 'HOD/SEM', 'Hodiny za semestr')");
        $pdo->exec("INSERT INTO vyukove_jednotky (id, zkratka, popis) VALUES (2, 'HOD/TYD', 'Hodiny za týden')");
        $pdo->exec("INSERT INTO seq_ucitIdnoExternista (id, cislo) VALUES (1, 0)");
        $pdo->exec("INSERT INTO jazyk (id, zkratka, popis) VALUES (1, 'ČJ', 'Čeština')");
        $pdo->exec("INSERT INTO jazyk (id, zkratka, popis) VALUES (2, 'AJ', 'Angličtina')");

        $tituly = [
            'Bc.', 'BcA.', 'Mgr.', 'MgA.', 'Ing.', 'Ing. arch.', 'MUDr.', 'MDDr.',
            'MVDr.', 'PhDr.', 'JUDr.', 'RNDr.', 'PharmDr.', 'ThDr.', 'PaedDr.',
            'ThLic.', 'Ph.D.', 'Th.D.', 'Dr.', 'CSc.'
        ];
        $stmtTitul = $pdo->prepare("INSERT INTO cistituly (zkratka) VALUES (?)");
        foreach ($tituly as $titul) {
            $stmtTitul->execute([$titul]);
        }

        //$pdo->commit();
        echo "<p style='color: green;'>Všechny tabulky byly úspěšně vytvořeny a inicializovány.</p>";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<p style='color: red;'>Chyba při inicializaci: " . h($e->getMessage()) . "</p>";
    }
}

/**
 * Nepoužívané, ponecháno kvůli kompatibilitě.
 */
function onInit_Insert($pdo) {
    onInit($pdo);
}

/**
 * =========================
 * GETTERS / NASTAVENÍ
 * =========================
 */

function getYear($pdo) {
    $rok = getCurrentVersionValue($pdo, 'AktivniRok', 'Hodnota');
    return $rok ? (int)$rok : null;
}

function getSemestr($pdo) {
    $semestr = getCurrentVersionValue($pdo, 'AktivniSemestr', 'HodnotaChar');
    return $semestr ? (string)$semestr : null;
}

function getKatedra($pdo) {
    $idVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        SELECT p.zkratka
        FROM nastaveni n
        JOIN pracoviste p ON n.Hodnota = p.idpracoviste
        WHERE n.Nazev = 'AktivniKatedra'
          AND n.IdVerze = ?
          AND p.IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idVerze, $idVerze]);

    $katedra = $stmt->fetchColumn();
    return $katedra ? (string)$katedra : null;
}

function getFakulta($pdo) {
    $idVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        SELECT nadrazenepracoviste
        FROM pracoviste
        WHERE IdVerze = ?
          AND nadrazenepracoviste IS NOT NULL
          AND nadrazenepracoviste != ''
        GROUP BY nadrazenepracoviste
        ORDER BY nadrazenepracoviste
        LIMIT 1
    ");
    $stmt->execute([$idVerze]);

    $fakulta = $stmt->fetchColumn();
    return $fakulta ? (string)$fakulta : null;
}

function aktualnirok($pdo, $rok) {
    $IdVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = :rok
        WHERE Nazev = 'AktivniRok' AND IdVerze = :idVerze
    ");
    $stmt->execute([
        ':rok' => $rok,
        ':idVerze' => $IdVerze
    ]);

    safeEcho("Aktivní rok nastaven na: $rok");
}

function aktualniSemestr($pdo, $semestr) {
    $IdVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        UPDATE nastaveni
        SET HodnotaChar = :semestr
        WHERE Nazev = 'AktivniSemestr' AND IdVerze = :idVerze
    ");
    $stmt->execute([
        ':semestr' => $semestr,
        ':idVerze' => $IdVerze
    ]);

    safeEcho("Aktivní semestr nastaven na: $semestr");
}

function setKatedra($pdo, $katedra) {
    $IdVerze = getAktivniVerze($pdo);
    $IdKatedra = getKatedraByZkratka($pdo, $katedra, $IdVerze);

    if (!$IdKatedra) {
        safeEcho("Katedra nenalezena: $katedra");
        return;
    }

    $query = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'AktivniKatedra' AND IdVerze = ?
    ");
    $query->execute([$IdKatedra, $IdVerze]);

    $query = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'ResCountKatedra' AND IdVerze = ?
    ");
    $query->execute([$IdKatedra, $IdVerze]);

    safeEcho("Katedra nastavena jako aktivní: $katedra");
}

function setKatedraZobrazeni($pdo, $katedra) {
    $idVerze = getAktivniVerze($pdo);
    $IdKatedra = getKatedraByZkratka($pdo, $katedra, $idVerze);

    if (!$IdKatedra) {
        safeEcho("Katedra nenalezena: $katedra");
        return;
    }

    $query = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'ResCountKatedra' AND IdVerze = ?
    ");
    $query->execute([$IdKatedra, $idVerze]);

    safeEcho("Katedra pro zobrazení nastavena: $katedra");
}

function setPrvniKatedra2($pdo) {
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE IdVerze = ? AND Nazev = 'ResCountKatedra'");
    $stmt->execute([$verze]);
    $IdKatedra = $stmt->fetchColumn();

    if ($IdKatedra) {
        setKatedraAll($pdo, $IdKatedra);
    }
}

function setPrvniKatedra($pdo) {
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("SELECT MIN(idpracoviste) FROM pracoviste WHERE IdVerze = ?");
    $stmt->execute([$verze]);
    $IdKatedra = $stmt->fetchColumn();

    if ($IdKatedra) {
        setKatedraAll($pdo, $IdKatedra);
    }
}

function setKatedraAll($pdo, $katedra) {
    $idVerze = getAktivniVerze($pdo);

    $query = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'AktivniKatedra' AND IdVerze = ?
    ");
    $query->execute([$katedra, $idVerze]);

    $query = $pdo->prepare("
        UPDATE nastaveni
        SET Hodnota = ?
        WHERE Nazev = 'ResCountKatedra' AND IdVerze = ?
    ");
    $query->execute([$katedra, $idVerze]);

    safeEcho("✅ Katedra nastavena jako aktivní: $katedra");
}

function getAktualniKatedraForResultCount($pdo) {
    $idVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        SELECT p.zkratka, p.nazev, p.nadrazenepracoviste
        FROM nastaveni n
        JOIN pracoviste p ON n.Hodnota = p.idpracoviste
        WHERE n.Nazev = 'ResCountKatedra'
          AND n.IdVerze = ?
          AND p.IdVerze = ?
        LIMIT 1
    ");
    $stmt->execute([$idVerze, $idVerze]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * =========================
 * INSERTY
 * =========================
 */

function insertUcitel($pdo, $name, $surname, $ucitIdno, $idPracoviste, $titulPred, $titulZa) {
    try {
        $IdVerze = getAktivniVerze($pdo);

        $query = "
            INSERT INTO teachers (name, surname, ucitIdno, IdVerze, idPracoviste, titulPred, titulZa)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                surname = VALUES(surname),
                idPracoviste = VALUES(idPracoviste),
                titulPred = VALUES(titulPred),
                titulZa = VALUES(titulZa),
                id = LAST_INSERT_ID(id)
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $surname, $ucitIdno, $IdVerze, $idPracoviste, $titulPred, $titulZa]);

        safeEcho("Učitel uložen: $name $surname ($ucitIdno)");
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
        return null;
    }
}

function insertKontaktUcitel($pdo, $idTeacher, $email = null, $telefon = null, $poznamka = null) {
    try {
        $idVerze = getAktivniVerze($pdo);

        $email = is_string($email) ? trim($email) : null;
        $telefon = is_string($telefon) ? trim($telefon) : null;
        $poznamka = is_string($poznamka) ? trim($poznamka) : null;

        if ($email === '') {
            $email = null;
        }

        if ($telefon === '') {
            $telefon = null;
        }

        if ($poznamka === '') {
            $poznamka = null;
        }

        if ($email === null && $telefon === null && $poznamka === null) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM kontakt
            WHERE idTeacher = ? AND idVerze = ?
            LIMIT 1
        ");
        $stmt->execute([$idTeacher, $idVerze]);
        $kontaktId = $stmt->fetchColumn();

        if ($kontaktId) {
            $stmt = $pdo->prepare("
                UPDATE kontakt
                SET email = ?, telefon = ?, poznamka = ?
                WHERE id = ?
            ");
            $stmt->execute([$email, $telefon, $poznamka, $kontaktId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO kontakt (idTeacher, email, telefon, poznamka, idVerze)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$idTeacher, $email, $telefon, $poznamka, $idVerze]);
        }

        safeEcho("Kontakt uložen pro teacher ID: $idTeacher");
    } catch (PDOException $e) {
        safeEcho("Chyba při ukládání kontaktu: " . $e->getMessage());
    }
}

function insertStudijniProgram($pdo, $stprIdno, $nazev, $kod, $platnyod, $pocetprijimanych, $stddelka, $forma, $typ, $jazyk) {
    try {
        $IdVerze = getAktivniVerze($pdo);

        $forma = normalizeForma($forma);
        $typ = normalizeTypStudia($typ);
        $jazyk = normalizeJazyk($jazyk);

        $query = "
            INSERT INTO studijniprogram (stprIdno, nazev, kod, platnyod, pocetprijimanych, stddelka, idForma, typ, jazyk, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nazev = VALUES(nazev),
                kod = VALUES(kod),
                platnyod = VALUES(platnyod),
                pocetprijimanych = VALUES(pocetprijimanych),
                stddelka = VALUES(stddelka),
                idForma = VALUES(idForma),
                typ = VALUES(typ),
                jazyk = VALUES(jazyk)
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$stprIdno, $nazev, $kod, $platnyod, $pocetprijimanych, $stddelka, $forma, $typ, $jazyk, $IdVerze]);

        safeEcho("Studijní program uložen: $stprIdno $nazev");

        insertRocnikySP($pdo, $stprIdno, (int)$stddelka, $forma, $jazyk);
    } catch (PDOException $e) {
        safeEcho("error: " . $e->getMessage());
    }
}

function insertRocnikySP($pdo, $stprIdno, $stddelka, $forma, $jazyk) {
    $query = "INSERT IGNORE INTO rocniky_studijniho_programu (stprIdno, rocnik, idForma, idVerze, jazyk) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $idVerze = getAktivniVerze($pdo);

    for ($rok = 1; $rok <= $stddelka; $rok++) {
        $stmt->execute([$stprIdno, $rok, $forma, $idVerze, $jazyk]);
        safeEcho("Ročník uložen: $rok., forma: $forma, stprIdno: $stprIdno, jazyk: $jazyk");
    }
}

function insertPredmetyByUcitel($pdo, $zkratka, $ucitIdno) {
    try {
        $IdVerze = getAktivniVerze($pdo);

        $query = "INSERT IGNORE INTO ucitelPredmety (predmetzkratka, ucitIdno, IdVerze) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$zkratka, $ucitIdno, $IdVerze]);

        safeEcho("PredmetByUcitel uložen: $zkratka / $ucitIdno");
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
    }
}

function insertPracoviste($pdo, $idpracovistestag, $zkratka, $typpracoviste, $nadrazenepracoviste, $nazev) {
    try {
        $IdVerze = getAktivniVerze($pdo);

        $query = "
            INSERT INTO pracoviste (idpracovistestag, zkratka, typpracoviste, nadrazenepracoviste, nazev, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                zkratka = VALUES(zkratka),
                typpracoviste = VALUES(typpracoviste),
                nadrazenepracoviste = VALUES(nadrazenepracoviste),
                nazev = VALUES(nazev),
                idpracoviste = LAST_INSERT_ID(idpracoviste)
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$idpracovistestag, $zkratka, $typpracoviste, $nadrazenepracoviste, $nazev, $IdVerze]);

        safeEcho("Pracoviště uloženo: $nazev");
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
        return null;
    }
}

// function insertObor($pdo, $oborIdno, $stprIdno, $nazev, $cisloOboru, $typ, $forma, $jazyk, $fakulta, $rokPlatnosti) {
//     } catch (PDOException $e) {
//         safeEcho("Chyba při ukládání studijního plánu: " . $e->getMessage());
//     }
// }

function insertObor($pdo, $oborIdno, $stprIdno, $nazev, $cisloOboru, $typ, $forma, $jazyk, $fakulta, $rokPlatnosti) {
    try {
        $idVerze = getAktivniVerze($pdo);

        $typ = normalizeTypKod($typ);
        $forma = normalizeFormaKod($forma);
        $jazyk = normalizeJazykKod($jazyk);

        $stmt = $pdo->prepare("
            INSERT INTO obor
            (oborIdno, stprIdno, nazev, cisloOboru, typ, idForma, jazyk, fakulta, rokPlatnosti, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                stprIdno = VALUES(stprIdno),
                nazev = VALUES(nazev),
                cisloOboru = VALUES(cisloOboru),
                typ = VALUES(typ),
                idForma = VALUES(idForma),
                jazyk = VALUES(jazyk),
                fakulta = VALUES(fakulta),
                rokPlatnosti = VALUES(rokPlatnosti)
        ");
        $stmt->execute([
            $oborIdno,
            $stprIdno,
            $nazev,
            $cisloOboru,
            $typ,
            $forma,
            $jazyk,
            $fakulta,
            $rokPlatnosti,
            $idVerze
        ]);

        safeEcho("Obor uložen: $nazev ($oborIdno)");
    } catch (PDOException $e) {
        safeEcho("Chyba při ukládání oboru: " . $e->getMessage());
    }
}

function insertStudijniPlan($pdo, $stplIdno, $oborIdno, $nazev, $etapa, $pocetSemestru, $rokPlatnosti, $verzePlanu, $kreditne, $limitKreditu, $vyucJazyk, $specializace) {
    try {
        $idVerze = getAktivniVerze($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO studijni_plan
            (stplIdno, oborIdno, nazev, etapa, pocetSemestru, rokPlatnosti, verzePlanu, kreditne, limitKreditu, vyucJazyk, specializace, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                oborIdno = VALUES(oborIdno),
                nazev = VALUES(nazev),
                etapa = VALUES(etapa),
                pocetSemestru = VALUES(pocetSemestru),
                rokPlatnosti = VALUES(rokPlatnosti),
                verzePlanu = VALUES(verzePlanu),
                kreditne = VALUES(kreditne),
                limitKreditu = VALUES(limitKreditu),
                vyucJazyk = VALUES(vyucJazyk),
                specializace = VALUES(specializace)
        ");
        $stmt->execute([
            $stplIdno,
            $oborIdno,
            $nazev,
            $etapa,
            $pocetSemestru,
            $rokPlatnosti,
            $verzePlanu,
            $kreditne,
            $limitKreditu,
            $vyucJazyk,
            $specializace,
            $idVerze
        ]);

        safeEcho("Studijní plán uložen: $nazev ($stplIdno)");
    } catch (PDOException $e) {
        safeEcho("Chyba při ukládání studijního plánu: " . $e->getMessage());
    }
}

// function insertStudijniPlan($pdo, $stplIdno, $oborIdno, $nazev, $etapa, $pocetSemestru, $rokPlatnosti, $verzePlanu, $kreditne, $limitKreditu, $vyucJazyk, $specializace) {
//     try {
//         $idVerze = getAktivniVerze($pdo);

//         $stmt = $pdo->prepare("
//             INSERT INTO studijni_plan
//             (stplIdno, oborIdno, nazev, etapa, pocetSemestru, rokPlatnosti, verzePlanu, kreditne, limitKreditu, vyucJazyk, specializace, IdVerze)
//             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
//             ON DUPLICATE KEY UPDATE
//                 oborIdno = VALUES(oborIdno),
//                 nazev = VALUES(nazev),
//                 etapa = VALUES(etapa),
//                 pocetSemestru = VALUES(pocetSemestru),
//                 rokPlatnosti = VALUES(rokPlatnosti),
//                 verzePlanu = VALUES(verzePlanu),
//                 kreditne = VALUES(kreditne),
//                 limitKreditu = VALUES(limitKreditu),
//                 vyucJazyk = VALUES(vyucJazyk),
//                 specializace = VALUES(specializace)
//         ");
//         $stmt->execute([
//             $stplIdno,
//             $oborIdno,
//             $nazev,
//             $etapa,
//             $pocetSemestru,
//             $rokPlatnosti,
//             $verzePlanu,
//             $kreditne,
//             $limitKreditu,
//             $vyucJazyk,
//             $specializace,
//             $idVerze
//         ]);

//         safeEcho("Studijní plán uložen: $nazev ($stplIdno)");
//     } catch (PDOException $e) {
//         safeEcho("Chyba při ukládání studijního plánu: " . $e->getMessage());
//     }
// }

function getOboryStudijnihoProgramu($pdo, $stprIdno) {
    $rok = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/programy/getOboryStudijnihoProgramu"
        . "?stprIdno=" . urlencode($stprIdno)
        . "&rok=" . urlencode($rok)
        . "&outputFormat=JSON";

    safeEcho("URL API getOboryStudijnihoProgramu: $api_url");

    try {
        $data = stagGetJson($api_url);

        if (empty($data['oborInfo'])) {
            safeEcho("Žádné obory pro stprIdno: $stprIdno");
            return;
        }

        foreach ($data['oborInfo'] as $obor) {
            insertObor(
                $pdo,
                $obor['oborIdno'] ?? null,
                $obor['stprIdno'] ?? $stprIdno,
                $obor['nazev'] ?? null,
                $obor['cisloOboru'] ?? null,
                $obor['typ'] ?? null,
                $obor['forma'] ?? null,
                $obor['jazyk'] ?? null,
                $obor['fakulta'] ?? null,
                $rok
            );
        }
    } catch (Exception $e) {
        safeEcho("Error in getOboryStudijnihoProgramu: " . $e->getMessage());
    }
}

function insertPlanPredmetObsazenost($pdo, $stplIdno, $rocnik, $rok, $semestr, array $ra) {
    try {
        $idVerze = getAktivniVerze($pdo);

        $predmetZkratka = trim((string)($ra['predmet'] ?? ''));
        if ($predmetZkratka === '') {
            return;
        }

        $platnost = trim((string)($ra['platnost'] ?? ''));
        if ($platnost !== '' && $platnost !== 'A') {
            return;
        }

        // odfiltrování technických/souhrnných řádků
        $den = $ra['den'] ?? null;
        $datumOd = $ra['datumOd']['value'] ?? null;
        $planObsazeni = isset($ra['planObsazeni']) && is_numeric($ra['planObsazeni']) ? (int)$ra['planObsazeni'] : null;

        if ($den === null && $datumOd === null) {
            return;
        }

        if ($planObsazeni === 777) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO plan_predmet_obsazenost
            (stplIdno, rocnik, rok, semestr, predmet_zkratka, nazev, katedra, typ_akce, typ_akce_zkr,
             krouzky, roakIdno, plan_obsazeni, obsazeni, pocet_vyuc_hodin, platnost, vsichniUciteleUcitIdno, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                predmet_zkratka = VALUES(predmet_zkratka),
                nazev = VALUES(nazev),
                katedra = VALUES(katedra),
                typ_akce = VALUES(typ_akce),
                typ_akce_zkr = VALUES(typ_akce_zkr),
                krouzky = VALUES(krouzky),
                plan_obsazeni = VALUES(plan_obsazeni),
                obsazeni = VALUES(obsazeni),
                pocet_vyuc_hodin = VALUES(pocet_vyuc_hodin),
                platnost = VALUES(platnost),
                vsichniUciteleUcitIdno = VALUES(vsichniUciteleUcitIdno)
        ");

        $stmt->execute([
            $stplIdno,
            $rocnik,
            $rok,
            $semestr,
            $predmetZkratka,
            $ra['nazev'] ?? null,
            $ra['katedra'] ?? null,
            $ra['typAkce'] ?? null,
            $ra['typAkceZkr'] ?? null,
            $ra['krouzky'] ?? null,
            $ra['roakIdno'] ?? null,
            $planObsazeni,
            isset($ra['obsazeni']) && is_numeric($ra['obsazeni']) ? (int)$ra['obsazeni'] : null,
            isset($ra['pocetVyucHodin']) && is_numeric($ra['pocetVyucHodin']) ? (int)$ra['pocetVyucHodin'] : null,
            $platnost,
            $ra['vsichniUciteleUcitIdno'] ?? null,
            $idVerze
        ]);
    } catch (PDOException $e) {
        safeEcho("Chyba při ukládání obsazenosti plánu: " . $e->getMessage());
    }
}

function insertPredmet($pdo, $zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok, $semestr, $idPracoviste) {
    try {
        $IdVerze = getAktivniVerze($pdo);

        $query = "
            INSERT INTO predmet
            (zkratka, nazev, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno, vyucovaciJazyky, rok, semestr, IdVerze, idPracoviste)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nazev = VALUES(nazev),
                cviciciUcitIdno = VALUES(cviciciUcitIdno),
                seminariciUcitIdno = VALUES(seminariciUcitIdno),
                prednasejiciUcitIdno = VALUES(prednasejiciUcitIdno),
                vyucovaciJazyky = VALUES(vyucovaciJazyky),
                semestr = VALUES(semestr),
                idPracoviste = COALESCE(VALUES(idPracoviste), idPracoviste),
                id = LAST_INSERT_ID(id)
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $zkratka,
            $nazev,
            $cviciciUcitIdno,
            $seminariciUcitIdno,
            $prednasejiciUcitIdno,
            $vyucovaciJazyky,
            $rok,
            $semestr,
            $IdVerze,
            $idPracoviste
        ]);

        $predmetId = (int)$pdo->lastInsertId();
        safeEcho("Předmět uložen: $nazev ($zkratka, $rok, $semestr)");

        return $predmetId;
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
        return null;
    }
}

function insertPredmetLast($pdo, $zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok, $semestr) {
    try {
        $idVerze = getAktivniVerze($pdo);

        $query = "
            INSERT INTO predmetlast
            (zkratka, nazev, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno, vyucovaciJazyky, rok, semestr, IdVerze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nazev = VALUES(nazev),
                cviciciUcitIdno = VALUES(cviciciUcitIdno),
                seminariciUcitIdno = VALUES(seminariciUcitIdno),
                prednasejiciUcitIdno = VALUES(prednasejiciUcitIdno),
                vyucovaciJazyky = VALUES(vyucovaciJazyky),
                semestr = VALUES(semestr),
                id = LAST_INSERT_ID(id)
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $zkratka,
            $nazev,
            $cviciciUcitIdno,
            $seminariciUcitIdno,
            $prednasejiciUcitIdno,
            $vyucovaciJazyky,
            $rok,
            $semestr,
            $idVerze
        ]);

        $predmetId = (int)$pdo->lastInsertId();
        safeEcho("Loňský předmět uložen: $nazev ($zkratka, $rok, $semestr)");

        return $predmetId;
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
        return null;
    }
}

function insertPredmetHodiny($pdo, $predmetId, $jednotekPrednasek, $jednotkaPrednasky, $jednotekCviceni, $jednotkaCviceni, $jednotekSeminare, $jednotkaSeminare) {
    try {
        if (!$predmetId) {
            safeEcho("Nelze uložit hodiny předmětu: chybí predmetId");
            return;
        }

        $jednotkaPrednasky = normalizeVyukovaJednotka($jednotkaPrednasky);
        $jednotkaCviceni = normalizeVyukovaJednotka($jednotkaCviceni);
        $jednotkaSeminare = normalizeVyukovaJednotka($jednotkaSeminare);

        $query = "
            INSERT INTO predmet_hodiny
            (predmetid, pocetJednotekSeminar, jednotkaSeminarTypId, pocetJednotekPrednaska, jednotkaPrednaskaTypId, jednotkaCviceniTypId, pocetJednotekCviceni)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pocetJednotekSeminar = VALUES(pocetJednotekSeminar),
                jednotkaSeminarTypId = VALUES(jednotkaSeminarTypId),
                pocetJednotekPrednaska = VALUES(pocetJednotekPrednaska),
                jednotkaPrednaskaTypId = VALUES(jednotkaPrednaskaTypId),
                jednotkaCviceniTypId = VALUES(jednotkaCviceniTypId),
                pocetJednotekCviceni = VALUES(pocetJednotekCviceni)
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $predmetId,
            (int)$jednotekSeminare,
            $jednotkaSeminare,
            (int)$jednotekPrednasek,
            $jednotkaPrednasky,
            $jednotkaCviceni,
            (int)$jednotekCviceni
        ]);

        safeEcho("Hodiny předmětu uloženy: seminář $jednotekSeminare, přednáška $jednotekPrednasek, cvičení $jednotekCviceni");
    } catch (PDOException $e) {
        safeEcho("Query failed: " . $e->getMessage());
    }
}

function insertPredmetJazyky($pdo, $predmetId, $vyucovaciJazyky) {
    if (!$predmetId) {
        return;
    }

    $jazyky = explode(',', is_string($vyucovaciJazyky) ? $vyucovaciJazyky : '');

    foreach ($jazyky as $jazyk) {
        $jazyk = trim($jazyk);
        if ($jazyk === '') {
            continue;
        }

        $stmt = $pdo->prepare("SELECT id FROM jazyk WHERE popis = ? LIMIT 1");
        $stmt->execute([$jazyk]);
        $jazykId = $stmt->fetchColumn();

        if (!$jazykId) {
            $stmt = $pdo->prepare("INSERT INTO jazyk (zkratka, popis) VALUES (?, ?)");
            $stmt->execute([mb_strtoupper(mb_substr($jazyk, 0, 2)), $jazyk]);
            $jazykId = $pdo->lastInsertId();
            safeEcho("Chyběl jazyk: $jazyk");
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO predmet_jazyk (predmetid, jazykid) VALUES (?, ?)");
        $stmt->execute([$predmetId, $jazykId]);
        safeEcho("Jazyk přiřazen k předmětu: $jazykId");
    }
}

function insertTeachedLastYC($pdo, $predmetid, $teacherid) {
    try {
        $IdVerze = getAktivniVerze($pdo);
        $query = "INSERT IGNORE INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'cvicici', ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        safeEcho("Loňský cvičící uložen: $predmetid, $teacherid");
    } catch (PDOException $e) {
        safeEcho("error: " . $e->getMessage());
    }
}

function insertTeachedLastYS($pdo, $predmetid, $teacherid) {
    try {
        $IdVerze = getAktivniVerze($pdo);
        $query = "INSERT IGNORE INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'seminarici', ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        safeEcho("Loňský seminářník uložen: $predmetid, $teacherid");
    } catch (PDOException $e) {
        safeEcho("error: " . $e->getMessage());
    }
}

function insertTeachedLastYP($pdo, $predmetid, $teacherid) {
    try {
        $IdVerze = getAktivniVerze($pdo);
        $query = "INSERT IGNORE INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'prednasejici', ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        safeEcho("Loňský přednášející uložen: $predmetid, $teacherid");
    } catch (PDOException $e) {
        safeEcho("error: " . $e->getMessage());
    }
}

// BUG FIX: přidán parametr $jazyk (dříve bylo vždy 0 = neplatný jazyk)
// BUG FIX: INSERT IGNORE nahrazen ON DUPLICATE KEY UPDATE (IGNORE tiše skrývalo chyby)
function insertCurrentY($pdo, $predmetid, $teacherid, $typ, $jazyk = 1) {
    try {
        $IdVerze = getAktivniVerze($pdo);
        $teacherid = (int)$teacherid;
        $jazyk = (int)$jazyk;

        $query = "
            INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, jazyk, typ, podil, IdVerze)
            VALUES (?, ?, ?, ?, 100, ?)
            ON DUPLICATE KEY UPDATE podil = VALUES(podil)
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $jazyk, $typ, $IdVerze]);

        safeEcho("Aktuální přiřazení uloženo: $predmetid, $teacherid, typ=$typ, jazyk=$jazyk");
    } catch (PDOException $e) {
        safeEcho("error: " . $e->getMessage());
    }
}

function insertErr($pdo, $ucitIdno) {
    $query = "INSERT INTO errnumber (ucitIdno, idVerze) VALUES (?, ?)";
    $stmt = $pdo->prepare($query);
    $idVerze = getAktivniVerze($pdo);
    $stmt->execute([$ucitIdno, $idVerze]);

    safeEcho("Vložen errNumber: $ucitIdno");
}

/**
 * =========================
 * MAZÁNÍ
 * =========================
 */

function deleteAll($pdo) {
    $views = ['vwPredmetJazyk'];

    foreach ($views as $view) {
        try {
            $pdo->exec("DROP VIEW IF EXISTS $view");
            safeEcho("Dropped view $view");
        } catch (PDOException $e) {
            safeEcho("Error dropping view $view: " . $e->getMessage());
        }
    }

    $tables = [
        'ucitelpredmetprirazeni',
        'ucitelpredmetlast',
        'teachers',
        'ucitelPredmety',
        'predmet_hodiny',
        'predmetlast',
        'predmet',
        'studijniprogram',
        'cisfakulta',
        'pracoviste',
        'roky',
        'semestr',
        'nastaveni',
        'verze',
        'seq_ucitIdno',
        'errnumber',
        'vyukove_jednotky',
        'typ_vyuky',
        'rozvrhova_akce',
        'rozvrhova_akce_ucitel',
        'seq_ucitIdnoExternista',
        'jazyk',
        'predmet_jazyk',
        'cviceni_max_studenti',
        'kontakt',
        'cistituly',
        'obor',
        'studijni_plan',
        'plan_predmet_obsazenost',
        'rocniky_studijniho_programu'
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS $table");
            safeEcho("Dropped table $table");
        } catch (PDOException $e) {
            safeEcho("Error dropping $table: " . $e->getMessage());
        }
    }
}

/**
 * =========================
 * STAG IMPORTY
 * =========================
 */

function getPredmetyUcitel($pdo, $ucitIdno) {
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByUcitel"
        . "?ucitIdno=" . urlencode($ucitIdno)
        . "&lang=en&outputFormat=JSON&katedra=%25&rok=%25";

    try {
        $data2 = stagGetJson($api_url);

        if (!isset($data2['predmetUcitele'])) {
            throw new Exception("Unexpected data structure for ucitIdno: $ucitIdno");
        }

        foreach ($data2['predmetUcitele'] as $predmet) {
            insertPredmetyByUcitel($pdo, $predmet['zkratka'], $ucitIdno);
        }
    } catch (Exception $e) {
        insertErr($pdo, $ucitIdno);
        safeEcho("Error: " . $e->getMessage());
    }
}

function getKatedry($pdo, $fakulta) {
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/ciselniky/getSeznamPracovist"
        . "?typPracoviste=%25&zkratka=%25&nadrazenePracoviste=%25&outputFormat=JSON";

    try {
        $data = stagGetJson($api_url);

        if (!isset($data['pracoviste'])) {
            safeEcho("Seznam pracovišť nebyl vrácen.");
            return;
        }

        foreach ($data['pracoviste'] as $pracoviste) {
            if (($pracoviste['nadrazenePracoviste'] ?? null) == $fakulta) {
                insertPracoviste(
                    $pdo,
                    $pracoviste['cisloPracoviste'],
                    $pracoviste['zkratka'],
                    $pracoviste['typPracoviste'],
                    $pracoviste['nadrazenePracoviste'],
                    $pracoviste['nazev']
                );
            }
        }
    } catch (Exception $e) {
        safeEcho("Error in getKatedry: " . $e->getMessage());
    }
}

function getPredmetyByKatedra($pdo, $katedra, $semestr = null) {
    $year = getYear($pdo);
    $semestr = normalizeImportSemestr($semestr, $pdo);

    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo"
        . "?semestr=" . urlencode($semestr)
        . "&outputFormat=JSON&katedra=" . urlencode($katedra)
        . "&rok=" . urlencode($year);

    safeEcho("URL API: $api_url");

    $idPracoviste = getKatedraByZkratka($pdo, $katedra);

    try {
        $data = stagGetJson($api_url);

        if (empty($data['predmetKatedryFullInfo'])) {
            safeEcho("Žádné předměty pro katedru: $katedra, semestr: $semestr");
            return;
        }

        foreach ($data['predmetKatedryFullInfo'] as $predmet) {
            $predmetId = insertPredmet(
                $pdo,
                $predmet['zkratka'] ?? null,
                $predmet['nazev'] ?? null,
                $predmet['cviciciUcitIdno'] ?? null,
                $predmet['seminariciUcitIdno'] ?? null,
                $predmet['prednasejiciUcitIdno'] ?? null,
                $predmet['vyucovaciJazyky'] ?? null,
                $predmet['rok'] ?? $year,
                $semestr,
                $idPracoviste
            );

            insertPredmetHodiny(
                $pdo,
                $predmetId,
                $predmet['jednotekPrednasek'] ?? 0,
                $predmet['jednotkaPrednasky'] ?? 'HOD/TYD',
                $predmet['jednotekCviceni'] ?? 0,
                $predmet['jednotkaCviceni'] ?? 'HOD/TYD',
                $predmet['jednotekSeminare'] ?? 0,
                $predmet['jednotkaSeminare'] ?? 'HOD/TYD'
            );

            insertPredmetJazyky($pdo, $predmetId, $predmet['vyucovaciJazyky'] ?? '');
        }
    } catch (Exception $e) {
        safeEcho("Error in getPredmetyByKatedra: " . $e->getMessage());
    }
}

// function getOboryStudijnihoProgramu($pdo, $stprIdno) {
//                 $obor['fakulta'] ?? null,
//                 $rok
//             );
//         }
//     } catch (Exception $e) {
//         safeEcho("Error in getOboryStudijnihoProgramu: " . $e->getMessage());
//     }
// }

function getPlanyOboru($pdo, $oborIdno) {
    $rok = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/programy/getPlanyOboru"
        . "?oborIdno=" . urlencode($oborIdno)
        . "&rok=" . urlencode($rok)
        . "&outputFormat=JSON";

    safeEcho("URL API getPlanyOboru: $api_url");

    try {
        $data = stagGetJson($api_url);

        if (empty($data['planInfo'])) {
            return;
        }

        foreach ($data['planInfo'] as $plan) {
            insertStudijniPlan(
                $pdo,
                $plan['stplIdno'] ?? null,
                $plan['oborIdno'] ?? $oborIdno,
                $plan['nazev'] ?? null,
                $plan['etapa'] ?? null,
                $plan['pocetSemestru'] ?? null,
                $plan['rokPlatnosti'] ?? $rok,
                $plan['verze'] ?? null,
                $plan['kreditne'] ?? null,
                $plan['limitKreditu'] ?? null,
                $plan['vyucJazyk'] ?? null,
                $plan['specializace'] ?? null
            );
        }
    } catch (Exception $e) {
        safeEcho("Error in getPlanyOboru: " . $e->getMessage());
    }
}

function getRozvrhByPlanForRocnik($pdo, $stplIdno, $rocnik, $semestr) {
    $rok = getYear($pdo);
    $semestr = normalizeImportSemestr($semestr, $pdo);

    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/rozvrhy/getRozvrhByPlan"
        . "?stplIdno=" . urlencode($stplIdno)
        . "&rocnik=" . urlencode($rocnik)
        . "&rok=" . urlencode($rok)
        . "&semestr=" . urlencode($semestr)
        . "&jenRozvrhoveAkce=true"
        . "&outputFormat=JSON";

    safeEcho("URL API getRozvrhByPlanForRocnik: $api_url");

    try {
        $data = stagGetJson($api_url);

        if (empty($data['rozvrhovaAkce'])) {
            return;
        }

        foreach ($data['rozvrhovaAkce'] as $ra) {
            insertPlanPredmetObsazenost($pdo, $stplIdno, $rocnik, $rok, $semestr, $ra);
        }

        safeEcho("Obsazenost plánu importována pro stplIdno $stplIdno, ročník $rocnik, semestr $semestr");
    } catch (Exception $e) {
        safeEcho("Error in getRozvrhByPlanForRocnik: " . $e->getMessage());
    }
}

function getPredmetyByKatedraLast($pdo, $katedra, $semestr = null) {
    $year = getYear($pdo) - 1;
    $semestr = normalizeImportSemestr($semestr, $pdo);

    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo"
        . "?semestr=" . urlencode($semestr)
        . "&outputFormat=JSON&katedra=" . urlencode($katedra)
        . "&rok=" . urlencode($year);

    safeEcho("URL API: $api_url");

    try {
        $data = stagGetJson($api_url);

        if (empty($data['predmetKatedryFullInfo'])) {
            safeEcho("Žádné loňské předměty pro katedru: $katedra, semestr: $semestr");
            return;
        }

        foreach ($data['predmetKatedryFullInfo'] as $predmet) {
            insertPredmetLast(
                $pdo,
                $predmet['zkratka'] ?? null,
                $predmet['nazev'] ?? null,
                $predmet['cviciciUcitIdno'] ?? null,
                $predmet['seminariciUcitIdno'] ?? null,
                $predmet['prednasejiciUcitIdno'] ?? null,
                $predmet['vyucovaciJazyky'] ?? null,
                $predmet['rok'] ?? $year,
                $semestr
            );
        }
    } catch (Exception $e) {
        safeEcho("Error in getPredmetyByKatedraLast: " . $e->getMessage());
    }
}

function getStudijniProgram($pdo, $fakulta) {
    $rok = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/programy/getStudijniProgramy"
        . "?kod=%25&pouzePlatne=true&fakulta=" . urlencode($fakulta)
        . "&outputFormat=JSON&rok=" . urlencode($rok);

    safeEcho("URL API getStudijniProgram: $api_url");

    try {
        $data = stagGetJson($api_url);

        if (empty($data['programInfo'])) {
            safeEcho("Žádné studijní programy pro fakultu: $fakulta");
            return;
        }

        foreach ($data['programInfo'] as $stp) {
            insertStudijniProgram(
                $pdo,
                $stp['stprIdno'] ?? null,
                $stp['nazev'] ?? null,
                $stp['kod'] ?? null,
                $stp['platnyOd'] ?? null,
                $stp['pocetPrijimanych'] ?? null,
                $stp['stdDelka'] ?? 0,
                $stp['forma'] ?? null,
                $stp['typ'] ?? null,
                $stp['jazyk'] ?? null
            );
        }
    } catch (Exception $e) {
        safeEcho("Error in getStudijniProgram: " . $e->getMessage());
    }
}

// function importStudijniPlanyAFakulty($pdo, $fakulta) {
//     $idVerze = getAktivniVerze($pdo);

//     $stmt = $pdo->prepare("SELECT stprIdno, stddelka FROM studijniprogram WHERE IdVerze = ? AND kod IS NOT NULL");
//     $stmt->execute([$idVerze]);
//     $programy = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     foreach ($programy as $program) {
//         $stprIdno = (int)$program['stprIdno'];
//         $stddelka = (int)$program['stddelka'];

//         if ($stprIdno <= 0) {
//             continue;
//         }

//         getOboryStudijnihoProgramu($pdo, $stprIdno);

//         $stmtObory = $pdo->prepare("SELECT oborIdno FROM obor WHERE IdVerze = ? AND stprIdno = ?");
//         $stmtObory->execute([$idVerze, $stprIdno]);
//         $obory = $stmtObory->fetchAll(PDO::FETCH_COLUMN);

//         foreach ($obory as $oborIdno) {
//             getPlanyOboru($pdo, (int)$oborIdno);

//             $stmtPlan = $pdo->prepare("
//                 SELECT stplIdno
//                 FROM studijni_plan
//                 WHERE IdVerze = ? AND oborIdno = ?
//                 ORDER BY CAST(COALESCE(verzePlanu, '0') AS UNSIGNED) DESC, stplIdno DESC
//                 LIMIT 1
//             ");
//             $stmtPlan->execute([$idVerze, (int)$oborIdno]);
//             $stplIdno = $stmtPlan->fetchColumn();

//             if (!$stplIdno) {
//                 continue;
//             }

//             for ($rocnik = 1; $rocnik <= max(1, $stddelka); $rocnik++) {
//                 foreach (getImportSemestry() as $semestr) {
//                     getRozvrhByPlanForRocnik($pdo, (int)$stplIdno, $rocnik, $semestr);
//                 }
//             }
//         }
//     }
// }

function importStudijniPlanyAFakulty($pdo, $fakulta) {
    $idVerze = getAktivniVerze($pdo);

    $stmt = $pdo->prepare("
        SELECT stprIdno, stddelka
        FROM studijniprogram
        WHERE IdVerze = ?
    ");
    $stmt->execute([$idVerze]);
    $programy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($programy as $program) {
        $stprIdno = (int)$program['stprIdno'];
        $stddelka = (int)$program['stddelka'];

        if ($stprIdno <= 0) {
            continue;
        }

        getOboryStudijnihoProgramu($pdo, $stprIdno);

        $stmtObory = $pdo->prepare("
            SELECT oborIdno
            FROM obor
            WHERE IdVerze = ?
              AND stprIdno = ?
              AND fakulta = ?
        ");
        $stmtObory->execute([$idVerze, $stprIdno, $fakulta]);
        $obory = $stmtObory->fetchAll(PDO::FETCH_COLUMN);

        foreach ($obory as $oborIdno) {
            getPlanyOboru($pdo, (int)$oborIdno);

            $stmtPlan = $pdo->prepare("
                SELECT stplIdno
                FROM studijni_plan
                WHERE IdVerze = ? AND oborIdno = ?
                ORDER BY CAST(COALESCE(verzePlanu, '0') AS UNSIGNED) DESC, stplIdno DESC
                LIMIT 1
            ");
            $stmtPlan->execute([$idVerze, (int)$oborIdno]);
            $stplIdno = $stmtPlan->fetchColumn();

            if (!$stplIdno) {
                continue;
            }

            for ($rocnik = 1; $rocnik <= max(1, $stddelka); $rocnik++) {
                foreach (getImportSemestry() as $semestr) {
                    getRozvrhByPlanForRocnik($pdo, (int)$stplIdno, $rocnik, $semestr);
                }
            }
        }
    }
}

function getUcitele($pdo) {
    $katedra = getKatedra($pdo);

    if (!$katedra) {
        safeEcho("Aktivní katedra není nastavena.");
        return;
    }

    getAllUcitele($pdo, $katedra);
}

function getAllUcitele($pdo, $katedra) {
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/ucitel/getUciteleKatedry"
        . "?lang=en&outputFormat=JSON&katedra=" . urlencode($katedra) . "&jenAktualni=true";

    $idPracoviste = getKatedraByZkratka($pdo, $katedra);

    try {
        $data = stagGetJson($api_url);

        if (!isset($data['ucitel'])) {
            safeEcho("No teachers found in the response for katedra: $katedra");
            return;
        }

        foreach ($data['ucitel'] as $teacher) {
            $teacherId = insertUcitel(
                $pdo,
                $teacher['jmeno'] ?? '',
                $teacher['prijmeni'] ?? '',
                $teacher['ucitIdno'] ?? null,
                $idPracoviste,
                $teacher['titulPred'] ?? null,
                $teacher['titulZa'] ?? null
            );

            if ($teacherId) {
                insertKontaktUcitel(
                    $pdo,
                    $teacherId,
                    $teacher['email'] ?? null,
                    $teacher['telefon'] ?? null,
                    null
                );
            }
        }
    } catch (Exception $e) {
        safeEcho("Error in getAllUcitele: " . $e->getMessage());
    }
}

function gerRozvrhoveAkceLastYearKatedra($pdo) {
    $katedra = getKatedra($pdo);
    if (!$katedra) {
        safeEcho("Aktivní katedra není nastavena.");
        return;
    }

    foreach (getImportSemestry() as $semestr) {
        getAllRozvrhoveAkceLastYearKatedra($pdo, $katedra, $semestr);
    }
}

function getAllRozvrhoveAkceLastYearKatedra($pdo, $katedra, $semestr = null) {
    $semestr = normalizeImportSemestr($semestr, $pdo);
    $year = getYear($pdo) - 1;
    $verze = getAktivniVerze($pdo);

    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/rozvrhy/getRozvrhByKatedra"
        . "?semestr=" . urlencode($semestr)
        . "&jenRozvrhoveAkce=true&outputFormat=JSON&katedra=" . urlencode($katedra)
        . "&rok=" . urlencode($year);

    safeEcho("URL API: $api_url");

    try {
        $data = stagGetJson($api_url);

        if ($data === null || !isset($data['rozvrhovaAkce'])) {
            safeEcho("Error decoding JSON or no data found.");
            return;
        }

        foreach ($data['rozvrhovaAkce'] as $ra) {
            $roakIdno = $ra['roakIdno'] ?? null;
            $predmet = $ra['predmet'] ?? null;
            $nazev = $ra['nazev'] ?? null;
            $pocetHodin = $ra['pocetVyucHodin'] ?? 0;

            $stmt = $pdo->prepare("
                INSERT INTO rozvrhova_akce
                (roakIdno, predmet_zkratka, nazev_predmetu, katedra, rok, semestr, typ_akce, typ_akce_zkr, pocet_vyuc_hodin, krouzky, IdVerze)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    predmet_zkratka = VALUES(predmet_zkratka),
                    nazev_predmetu = VALUES(nazev_predmetu),
                    katedra = VALUES(katedra),
                    rok = VALUES(rok),
                    semestr = VALUES(semestr),
                    typ_akce = VALUES(typ_akce),
                    typ_akce_zkr = VALUES(typ_akce_zkr),
                    pocet_vyuc_hodin = VALUES(pocet_vyuc_hodin),
                    krouzky = VALUES(krouzky)
            ");
            $stmt->execute([
                $roakIdno,
                $predmet,
                $nazev,
                $katedra,
                $year,
                $semestr,
                $ra['typAkce'] ?? null,
                $ra['typAkceZkr'] ?? null,
                $pocetHodin,
                $ra['krouzky'] ?? null,
                $verze
            ]);

            safeEcho("Rozvrhová akce uložena: $predmet, nazev: $nazev, semestr: $semestr");

            $ucitIds = explode(',', is_string($ra['vsichniUciteleUcitIdno'] ?? '') ? $ra['vsichniUciteleUcitIdno'] : '');
            $podilyStr = $ra['vsichniUciteleJmenaTitulySPodily'] ?? '';
            preg_match_all("/\((\d+)\)/", $podilyStr, $matches);
            $podily = $matches[1] ?? [];

            foreach ($ucitIds as $index => $ucitIdno) {
                $ucitIdno = trim($ucitIdno);
                if ($ucitIdno === '' || !is_numeric($ucitIdno)) {
                    continue;
                }

                $podil = isset($podily[$index]) ? (float)$podily[$index] : 100.0;

                $stmt = $pdo->prepare("
                    INSERT INTO rozvrhova_akce_ucitel (roakIdno, ucitIdno, podil_na_vyuce, IdVerze)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        podil_na_vyuce = VALUES(podil_na_vyuce)
                ");
                $stmt->execute([$roakIdno, $ucitIdno, $podil, $verze]);

                safeEcho("Rozvrhova_akce_ucitel uloženo: ucitIdno $ucitIdno, podíl $podil");
            }
        }

        safeEcho("Rozvrhové akce úspěšně zpracovány pro semestr $semestr.");
    } catch (Exception $e) {
        safeEcho("Error in getAllRozvrhoveAkceLastYearKatedra: " . $e->getMessage());
    }
}

/**
 * =========================
 * LOGIKA PŘIŘAZENÍ
 * =========================
 */

function teachedlastyear($pdo) {
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("SELECT id, zkratka, semestr, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno FROM predmetlast WHERE IdVerze = ?");
    $stmt->execute([$verze]);
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($predmety as $predmet) {
        if (!empty($predmet['cviciciUcitIdno'])) {
            foreach (stringToIntArray($predmet['cviciciUcitIdno']) as $cvicici) {
                insertTeachedLastYC($pdo, $predmet['id'], $cvicici);
            }
        }

        if (!empty($predmet['seminariciUcitIdno'])) {
            foreach (stringToIntArray($predmet['seminariciUcitIdno']) as $seminarici) {
                insertTeachedLastYS($pdo, $predmet['id'], $seminarici);
            }
        }

        if (!empty($predmet['prednasejiciUcitIdno'])) {
            foreach (stringToIntArray($predmet['prednasejiciUcitIdno']) as $prednasejici) {
                insertTeachedLastYP($pdo, $predmet['id'], $prednasejici);
            }
        }
    }
}

function insertTeacherAssingByLastYear($pdo) {
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, upl.teacherid, upl.typ
        FROM predmet p
        JOIN predmetlast pl
            ON p.zkratka = pl.zkratka
           AND p.semestr = pl.semestr
           AND p.rok = pl.rok + 1
           AND pl.IdVerze = ?
        JOIN ucitelpredmetlast upl
            ON pl.id = upl.predmetid
           AND upl.IdVerze = ?
        WHERE p.IdVerze = ?
    ");
    $stmt->execute([$verze, $verze, $verze]);
    $lasty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // BUG FIX: pro každý předmět dotazujeme jazyk z vwPredmetJazyk a předáváme ho insertCurrentY()
    // Dříve se volalo insertCurrentY() bez jazyku → vkládalo jazyk=0 (neplatný FK)
    $stmtJazyky = $pdo->prepare("SELECT jazykid FROM vwPredmetJazyk WHERE predmetid = ?");

    foreach ($lasty as $last) {
        $stmtJazyky->execute([$last['id']]);
        $jazykIds = $stmtJazyky->fetchAll(PDO::FETCH_COLUMN);

        if (empty($jazykIds)) {
            $jazykIds = [1]; // fallback: čeština
        }

        // BUG FIX: ucitelpredmetlast.typ stores 'prednasejici'/'cvicici'/'seminarici'
        // but ucitelpredmetprirazeni.typ expects 'P'/'C'/'S' – map before insert
        $typMap = ['prednasejici' => 'P', 'cvicici' => 'C', 'seminarici' => 'S'];
        $mappedTyp = $typMap[$last['typ']] ?? $last['typ'];

        foreach ($jazykIds as $jazykId) {
            insertCurrentY($pdo, $last['id'], $last['teacherid'], $mappedTyp, (int)$jazykId);
        }
    }
}

function assignTeachersFromRozvrhForSemestr($pdo, $semestr) {
    $verze = getAktivniVerze($pdo);
    $rokLast = getYear($pdo) - 1;
    $rokCurrent = getYear($pdo);
    $semestr = normalizeImportSemestr($semestr, $pdo);

    $stmt = $pdo->prepare("
        SELECT ra.predmet_zkratka, ra.typ_akce_zkr, rau.ucitIdno, ra.pocet_vyuc_hodin, rau.podil_na_vyuce
        FROM rozvrhova_akce ra
        JOIN rozvrhova_akce_ucitel rau ON ra.roakIdno = rau.roakIdno
        WHERE ra.rok = ? AND ra.semestr = ? AND ra.IdVerze = ?
    ");
    $stmt->execute([$rokLast, $semestr, $verze]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $zkratka = $row['predmet_zkratka'];
        $typ = strtoupper(substr((string)$row['typ_akce_zkr'], 0, 1));
        $ucitIdno = $row['ucitIdno'];
        $hodin = (float)$row['pocet_vyuc_hodin'] * ((float)$row['podil_na_vyuce'] / 100.0);

        if (!in_array($typ, ['P', 'C', 'S'], true)) {
            continue;
        }

        $data[$zkratka][$typ]['total'] = ($data[$zkratka][$typ]['total'] ?? 0) + $hodin;
        $data[$zkratka][$typ]['teachers'][$ucitIdno] = ($data[$zkratka][$typ]['teachers'][$ucitIdno] ?? 0) + $hodin;
    }

    $stmtPredmety = $pdo->prepare("SELECT id, zkratka FROM predmet WHERE IdVerze = ? AND rok = ? AND semestr = ?");
    $stmtPredmety->execute([$verze, $rokCurrent, $semestr]);
    $predmety = $stmtPredmety->fetchAll(PDO::FETCH_ASSOC);

    foreach ($predmety as $predmet) {
        $predmetId = (int)$predmet['id'];
        $zkratka = $predmet['zkratka'];

        $stmtJazyky = $pdo->prepare("SELECT jazykid FROM vwPredmetJazyk WHERE predmetid = ?");
        $stmtJazyky->execute([$predmetId]);
        $jazykIds = $stmtJazyky->fetchAll(PDO::FETCH_COLUMN);

        if (empty($jazykIds)) {
            $jazykIds = [0];
        }

        $stmtHod = $pdo->prepare("
            SELECT pocetJednotekPrednaska, pocetJednotekCviceni, pocetJednotekSeminar
            FROM predmet_hodiny
            WHERE predmetid = ?
        ");
        $stmtHod->execute([$predmetId]);
        $hodiny = $stmtHod->fetch(PDO::FETCH_ASSOC);

        if (!$hodiny) {
            continue;
        }

        foreach (['P' => 'pocetJednotekPrednaska', 'C' => 'pocetJednotekCviceni', 'S' => 'pocetJednotekSeminar'] as $typ => $sloupec) {
            if (empty($hodiny[$sloupec])) {
                continue;
            }

            $hasTeacher = isset($data[$zkratka][$typ]);
            $info = $data[$zkratka][$typ] ?? null;
            $total = $info['total'] ?? 0;

            foreach ($jazykIds as $jazykId) {
                $jazykId = (int)$jazykId;

                if ($hasTeacher && $total > 0) {
                    foreach ($info['teachers'] as $ucitIdno => $hodinUcitele) {
                        $teacherId = findTeacherDbIdByUcitIdno($pdo, $ucitIdno, $verze);
                        if (!$teacherId) {
                            continue;
                        }

                        $podil = round(($hodinUcitele / $total) * 100, 2);

                        $stmtInsert = $pdo->prepare("
                            INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, podil, IdVerze, jazyk)
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                podil = VALUES(podil)
                        ");
                        $stmtInsert->execute([$predmetId, $teacherId, $typ, $podil, $verze, $jazykId]);
                    }
                } else {
                    // NULL teacherid nelze použít s ON DUPLICATE KEY UPDATE (NULL != NULL v UNIQUE KEY),
                    // proto nejdřív smažeme stávající placeholder a pak vložíme nový.
                    $stmtDel = $pdo->prepare("
                        DELETE FROM ucitelpredmetprirazeni
                        WHERE predmetid = ? AND teacherid IS NULL AND typ = ? AND jazyk = ? AND IdVerze = ?
                    ");
                    $stmtDel->execute([$predmetId, $typ, $jazykId, $verze]);

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, podil, IdVerze, jazyk)
                        VALUES (?, NULL, ?, 0, ?, ?)
                    ");
                    $stmtInsert->execute([$predmetId, $typ, $verze, $jazykId]);
                }
            }
        }
    }

    safeEcho("Učitelé byli přiřazeni dle rozvrhu a jazyků pro semestr $semestr.");
}

function assignTeachersFromRozvrh($pdo, $semestr = null) {
    if ($semestr !== null) {
        assignTeachersFromRozvrhForSemestr($pdo, $semestr);
        return;
    }

    foreach (getImportSemestry() as $sem) {
        assignTeachersFromRozvrhForSemestr($pdo, $sem);
    }
}

function getSetUcitIdnoExternista($pdo) {
    $stmtPredmety = $pdo->prepare("SELECT cislo FROM seq_ucitIdnoExternista WHERE id = 1");
    $stmtPredmety->execute();
    $cislo = (int)$stmtPredmety->fetchColumn();
    $cislo++;

    safeEcho("UcitIdno: " . (-$cislo));

    $stmtUp = $pdo->prepare("UPDATE seq_ucitIdnoExternista SET cislo = ? WHERE id = 1");
    $stmtUp->execute([$cislo]);

    return $cislo;
}

function getSemestrZeZkratky($string) {
    $length = strlen($string);
    $digits = '';

    for ($i = 0; $i < $length; $i++) {
        if (is_numeric($string[$i])) {
            $digits .= $string[$i];
        }
    }

    return $digits !== '' ? $digits : null;
}

/**
 * =========================
 * HROMADNÝ IMPORT
 * =========================
 */

function insertAllKatedry($pdo) {
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("
        SELECT zkratka
        FROM pracoviste
        WHERE IdVerze = ?
        ORDER BY zkratka
    ");
    $stmt->execute([$verze]);
    $katedry = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($katedry as $katedra) {
        foreach (getImportSemestry() as $semestr) {
            getPredmetyByKatedra($pdo, $katedra, $semestr);
            getPredmetyByKatedraLast($pdo, $katedra, $semestr);
            getAllRozvrhoveAkceLastYearKatedra($pdo, $katedra, $semestr);
        }

        getAllUcitele($pdo, $katedra);
    }

    teachedlastyear($pdo);
    insertTeacherAssingByLastYear($pdo);
    assignTeachersFromRozvrh($pdo);
    setPrvniKatedra($pdo);
}

function vycistitPredmetJazyk($pdo) {
    $stmt = $pdo->query("
        SELECT predmetid
        FROM predmet_jazyk
        GROUP BY predmetid
        HAVING COUNT(*) > 1
    ");
    $predmety = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $deleteStmt = $pdo->prepare("
        DELETE FROM predmet_jazyk
        WHERE predmetid = :predmetid AND jazykid != 1
    ");

    foreach ($predmety as $predmetid) {
        $deleteStmt->execute([':predmetid' => $predmetid]);
    }

    safeEcho("Hotovo!");
}

// 
function getA2RozpadPredmetProgramRocnik($pdo, $katedra = null, $semestr = null) {
    $idVerze = getAktivniVerze($pdo);
    $rok = getYear($pdo);
    $semestr = normalizeImportSemestr($semestr, $pdo);

    $sql = "
        SELECT
            sp.stprIdno,
            sp.nazev AS nazev_programu,
            o.oborIdno,
            o.nazev AS nazev_oboru,
            pp.rocnik,
            pp.semestr,
            pp.predmet_zkratka,
            COALESCE(p.nazev, pp.nazev_predmetu) AS nazev_predmetu,
            COALESCE(pr.zkratka, pp.katedra) AS katedra,
            COALESCE(rsp.pocetStudentu, 0) AS pocet_studentu,
            pp.stag_obsazeni,
            pp.stag_plan_obsazeni
        FROM (
            SELECT
                y.stplIdno,
                y.rocnik,
                y.rok,
                y.semestr,
                y.predmet_zkratka,
                MAX(y.nazev_predmetu) AS nazev_predmetu,
                MAX(y.katedra) AS katedra,
                CASE
                    WHEN MAX(CASE WHEN y.typ_akce_zkr = 'Př' THEN 1 ELSE 0 END) = 1
                        THEN MAX(CASE WHEN y.typ_akce_zkr = 'Př' THEN y.obsazeni END)
                    ELSE SUM(CASE WHEN y.typ_akce_zkr IN ('Cv', 'Se') THEN y.obsazeni ELSE 0 END)
                END AS stag_obsazeni,
                CASE
                    WHEN MAX(CASE WHEN y.typ_akce_zkr = 'Př' THEN 1 ELSE 0 END) = 1
                        THEN MAX(CASE WHEN y.typ_akce_zkr = 'Př' THEN y.plan_obsazeni END)
                    ELSE SUM(CASE WHEN y.typ_akce_zkr IN ('Cv', 'Se') THEN y.plan_obsazeni ELSE 0 END)
                END AS stag_plan_obsazeni
            FROM (
                SELECT
                    x.stplIdno,
                    x.rocnik,
                    x.rok,
                    x.semestr,
                    x.predmet_zkratka,
                    x.nazev AS nazev_predmetu,
                    x.katedra,
                    x.typ_akce_zkr,
                    COALESCE(NULLIF(x.krouzky, ''), CONCAT('ROAK:', x.roakIdno)) AS skupina,
                    MAX(COALESCE(x.obsazeni, 0)) AS obsazeni,
                    MAX(COALESCE(x.plan_obsazeni, 0)) AS plan_obsazeni
                FROM plan_predmet_obsazenost x
                WHERE x.IdVerze = ?
                  AND x.rok = ?
                  AND x.semestr = ?
                  AND x.platnost = 'A'
                GROUP BY
                    x.stplIdno,
                    x.rocnik,
                    x.rok,
                    x.semestr,
                    x.predmet_zkratka,
                    x.nazev,
                    x.katedra,
                    x.typ_akce_zkr,
                    COALESCE(NULLIF(x.krouzky, ''), CONCAT('ROAK:', x.roakIdno))
            ) y
            GROUP BY
                y.stplIdno,
                y.rocnik,
                y.rok,
                y.semestr,
                y.predmet_zkratka
        ) pp
        JOIN studijni_plan spl
            ON spl.stplIdno = pp.stplIdno
           AND spl.IdVerze = ?
        JOIN obor o
            ON o.oborIdno = spl.oborIdno
           AND o.IdVerze = ?
        JOIN studijniprogram sp
            ON sp.stprIdno = o.stprIdno
           AND sp.IdVerze = ?
        LEFT JOIN rocniky_studijniho_programu rsp
            ON rsp.stprIdno = sp.stprIdno
           AND rsp.rocnik = pp.rocnik
           AND rsp.idForma = sp.idForma
           AND rsp.jazyk = sp.jazyk
           AND rsp.idVerze = ?
        LEFT JOIN predmet p
            ON p.zkratka = pp.predmet_zkratka
           AND p.rok = pp.rok
           AND p.semestr = pp.semestr
           AND p.IdVerze = ?
        LEFT JOIN pracoviste pr
            ON pr.idpracoviste = p.idPracoviste
           AND pr.IdVerze = ?
        WHERE 1 = 1
    ";

    $params = [
        $idVerze,
        $rok,
        $semestr,
        $idVerze,
        $idVerze,
        $idVerze,
        $idVerze,
        $idVerze,
        $idVerze
    ];

    if ($katedra !== null && $katedra !== '') {
        $sql .= " AND COALESCE(pr.zkratka, pp.katedra) = ? ";
        $params[] = $katedra;
    }

    $sql .= "
        ORDER BY
            sp.nazev,
            o.nazev,
            pp.rocnik,
            pp.predmet_zkratka
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getA2PredmetyStudijniProgramy($pdo, $katedra = null, $semestr = null) {
    return getA2RozpadPredmetProgramRocnik($pdo, $katedra, $semestr);
}

function getA2PredmetyProExport($pdo, $katedra = null, $semestr = null) {
    $rows = getA2RozpadPredmetProgramRocnik($pdo, $katedra, $semestr);

    $aggr = [];

    foreach ($rows as $row) {
        $subjectKey = implode('|', [
            (string)($row['katedra'] ?? ''),
            (string)($row['semestr'] ?? ''),
            (string)($row['predmet_zkratka'] ?? '')
        ]);

        $programKey = implode('|', [
            (string)($row['stprIdno'] ?? ''),
            (string)($row['rocnik'] ?? '')
        ]);

        if (!isset($aggr[$subjectKey])) {
            $aggr[$subjectKey] = [
                'katedra' => $row['katedra'] ?? null,
                'semestr' => $row['semestr'] ?? null,
                'predmet_zkratka' => $row['predmet_zkratka'] ?? null,
                'nazev_predmetu' => $row['nazev_predmetu'] ?? null,
                'pocet_studentu' => 0,
                'stag_obsazeni_max' => null,
                'stag_plan_obsazeni_max' => null,
                '_programy' => []
            ];
        }

        if (!isset($aggr[$subjectKey]['_programy'][$programKey])) {
            $aggr[$subjectKey]['_programy'][$programKey] = true;
            $aggr[$subjectKey]['pocet_studentu'] += (int)($row['pocet_studentu'] ?? 0);
        }

        $stagObs = isset($row['stag_obsazeni']) ? (int)$row['stag_obsazeni'] : null;
        $stagPlan = isset($row['stag_plan_obsazeni']) ? (int)$row['stag_plan_obsazeni'] : null;

        if ($stagObs !== null) {
            $aggr[$subjectKey]['stag_obsazeni_max'] = $aggr[$subjectKey]['stag_obsazeni_max'] === null
                ? $stagObs
                : max($aggr[$subjectKey]['stag_obsazeni_max'], $stagObs);
        }

        if ($stagPlan !== null) {
            $aggr[$subjectKey]['stag_plan_obsazeni_max'] = $aggr[$subjectKey]['stag_plan_obsazeni_max'] === null
                ? $stagPlan
                : max($aggr[$subjectKey]['stag_plan_obsazeni_max'], $stagPlan);
        }
    }

    foreach ($aggr as &$item) {
        unset($item['_programy']);
    }
    unset($item);

    uasort($aggr, function ($a, $b) {
        return [$a['semestr'], $a['predmet_zkratka']] <=> [$b['semestr'], $b['predmet_zkratka']];
    });

    return array_values($aggr);
}


/**
 * =========================
 * UI / LOAD FUNKCE
 * =========================
 */

function loadPredmety($pdo) {
    $stmt = $pdo->query("SELECT id, zkratka, nazev FROM predmet");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Predmety</h2>";
    echo "<ul>";
    foreach ($predmety as $predmet) {
        $zkratkaJs = json_encode($predmet['zkratka']);
        echo "<li><a href='javascript:void(0);' onclick='selectTeacher($zkratkaJs);'>" . h($predmet['nazev']) . "</a></li>";
        loadUcitelePredmetu($pdo, $predmet['id']);
    }
    echo "</ul>";
}

function loadUcitelePredmetu($pdo, $predmet) {
    $query = "
        SELECT upp.typ, t.name, t.surname
        FROM predmet p
        JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
        JOIN teachers t ON upp.teacherid = t.id
        WHERE p.id = ? AND upp.teacherid > 0
        ORDER BY upp.typ
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$predmet]);
    $ucitele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<ul>";
    foreach ($ucitele as $ucitel) {
        echo "<li>" . h($ucitel['typ']) . ": " . h($ucitel['surname']) . " " . h($ucitel['name']) . "</li>";
    }
    echo "</ul>";
}

function loadPredmetyV2($pdo) {
    $stmt = $pdo->query("
        SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname
        FROM predmet p
        JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
        JOIN teachers t ON upp.teacherid = t.id
        WHERE upp.teacherid > 0
    ");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Predmety</h2>";
    echo '<div class="grid-container">';
    foreach ($predmety as $predmet) {
        echo "<div class='grid-item'>" . h($predmet['nazev']) . "</div>
        <div class='grid-item'>" . h($predmet['zkratka']) . "</div>
        <div class='grid-item'>" . h($predmet['name']) . " " . h($predmet['surname']) . "</div>
        <div class='grid-item'>" . h($predmet['typ']) . "</div>
        <div class='grid-item'>X</div>";
    }
    echo '</div>';
}

function loadPredmetyV3($pdo) {
    $stmt = $pdo->query("
        SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname, t.ucitIdno
        FROM predmet p
        JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
        JOIN teachers t ON upp.teacherid = t.id
        WHERE upp.teacherid > 0
        ORDER BY p.nazev, upp.typ
    ");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Predmety</h2>";
    echo '<table>
    <tr>
        <th>Predmet</th>
        <th>Zkratka</th>
        <th>Name and Surname</th>
        <th>Typ</th>
        <th>Vymazat</th>
    </tr>';

    foreach ($predmety as $predmet) {
        echo "
        <tr>
            <td value='" . h($predmet['id']) . "'>" . h($predmet['nazev']) . "</td>
            <td>" . h($predmet['zkratka']) . "</td>
            <td value='" . h($predmet['ucitIdno']) . "'>" . h($predmet['name']) . " " . h($predmet['surname']) . "</td>
            <td value='" . h($predmet['typ']) . "'>" . h($predmet['typ']) . "</td>
            <td>X</td>
        </tr>";
    }
    echo '</table>';
}

function loadPredmetyV4($pdo) {
    $stmt = $pdo->query("
        SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname, t.ucitIdno
        FROM predmet p
        JOIN ucitelpredmetprirazeni upp ON p.id = upp.predmetid
        JOIN teachers t ON upp.teacherid = t.id
        WHERE upp.teacherid > 0
        ORDER BY p.nazev, upp.typ
    ");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Predmety</h2>";
    echo '<table>
    <tr>
        <th>Predmet</th>
        <th>Zkratka</th>
        <th>Name and Surname</th>
        <th>Typ</th>
        <th>Vymazat</th>
    </tr>';

    foreach ($predmety as $predmet) {
        echo "
        <tr>
            <td value='" . h($predmet['id']) . "'>" . h($predmet['nazev']) . "</td>
            <td>" . h($predmet['zkratka']) . "</td>
            <td value='" . h($predmet['ucitIdno']) . "'>" . h($predmet['name']) . " " . h($predmet['surname']) . "</td>
            <td value='" . h($predmet['typ']) . "'>" . h($predmet['typ']) . "</td>
            <td class='delete-row'
                data-predmetid='" . h($predmet['id']) . "'
                data-teacherid='" . h($predmet['ucitIdno']) . "'
                data-typ='" . h($predmet['typ']) . "'>X</td>
        </tr>";
    }
    echo '</table>';
}

function loadStudijniProgramy($pdo) {
    $stmt = $pdo->query("SELECT * FROM studijniprogram");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Programs</h2>";

    foreach ($programs as $program) {
        echo "<div class='card'>
            <h2>" . h($program['nazev']) . "</h2>
            <div class='ul-card'>
                <ul>
                    <li>kod: " . h($program['kod']) . "</li>
                    <li>platnyod: " . h($program['platnyod']) . "</li>
                    <li>pocetprijmanych: " . h($program['pocetprijimanych']) . "</li>
                </ul>
            </div>
        </div>";
    }
}

function loadStudijniProgramyV2($pdo) {
    $stmt = $pdo->query("SELECT * FROM studijniprogram");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Programs</h2>
    <table id='dataTable'>
    <tr>
        <th>Nazev</th>
        <th>Kod</th>
        <th>PlatnyOd</th>
        <th>PocetPrijimanych</th>
    </tr>";

    foreach ($programs as $program) {
        echo "
        <tr>
            <td>" . h($program['nazev']) . "</td>
            <td>" . h($program['kod']) . "</td>
            <td>" . h($program['platnyod']) . "</td>
            <td>" . h($program['pocetprijimanych']) . "</td>
        </tr>";
    }

    echo "</table>";
}

function vyber_verzi($pdo) {
    if (isset($_POST['verze']) && !empty($_POST['verze'])) {
        $idVerze = (int)$_POST['verze'];

        $stmt = $pdo->prepare("
            UPDATE nastaveni
            SET Hodnota = ?
                    WHERE Nazev = 'AktivniVerze'
        ");
        $stmt->execute([$idVerze]);
    }
}

function getPredmetInfo($pdo, $katedra) {
    $year = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo?semestr=LS&outputFormat=JSON&katedra=" . $katedra . "&rok=" . $year;
    $response = file_get_contents($api_url);
    echo nl2br("\nURL API getPredmetInfo:" . $api_url . "\n");

    $idPracoviste = getKatedraByZkratka($pdo, $katedra);

    if ($response === FALSE) {
        echo "error in connection";
    } else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }
        foreach ($data['predmetKatedryFullInfo'] as $predmet) {
            insertPredmet($pdo, $predmet['zkratka'], $predmet['nazev'], $predmet['cviciciUcitIdno'], $predmet['seminariciUcitIdno'], $predmet['prednasejiciUcitIdno'], $predmet['vyucovaciJazyky'], $predmet['rok'], $idPracoviste);
        }
    }
}

// =====================================================================
// ROZDĚLENÍ CVIČENÍ PODLE POČTU STUDENTŮ
// =====================================================================

/**
 * Vrátí počet studentů pro každý predmet (id => pocet).
 * Sčítá pocetStudentu přes řetěz: predmet → plan_predmet_obsazenost
 * → studijni_plan → obor → studijniprogram → rocniky_studijniho_programu
 */
function getPocetStudentuNaPredmety($pdo) {
    $idVerze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("
        SELECT
            p.id   AS predmet_id,
            COALESCE(SUM(DISTINCT rsp.pocetStudentu), 0) AS pocet_studentu
        FROM predmet p
        LEFT JOIN plan_predmet_obsazenost ppo
            ON  ppo.predmet_zkratka = p.zkratka
            AND ppo.rok             = p.rok
            AND ppo.semestr         = p.semestr
            AND ppo.IdVerze         = p.IdVerze
            AND ppo.platnost        = 'A'
        LEFT JOIN studijni_plan spl
            ON  spl.stplIdno  = ppo.stplIdno
            AND spl.IdVerze   = p.IdVerze
        LEFT JOIN obor o
            ON  o.oborIdno  = spl.oborIdno
            AND o.IdVerze   = p.IdVerze
        LEFT JOIN studijniprogram sp
            ON  sp.stprIdno = o.stprIdno
            AND sp.IdVerze  = p.IdVerze
        LEFT JOIN rocniky_studijniho_programu rsp
            ON  rsp.stprIdno = sp.stprIdno
            AND rsp.rocnik   = ppo.rocnik
            AND rsp.idForma  = sp.idForma
            AND rsp.jazyk    = sp.jazyk
            AND rsp.idVerze  = p.IdVerze
        WHERE p.IdVerze = ?
        GROUP BY p.id
    ");
    $stmt->execute([$idVerze]);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(int)$row['predmet_id']] = (int)$row['pocet_studentu'];
    }
    return $result;
}

/**
 * Vrátí plán rozdělení – pro každé cvičení s nastaveným max_pocet_studentu
 * spočítá, kolik skupin je potřeba a kolik chybí.
 * max_pocet_studentu = 1 (hodnota "X") se přeskakuje.
 */
function getCviceniRozdeleniNahled($pdo) {
    $idVerze       = getAktivniVerze($pdo);
    $poctyStudentu = getPocetStudentuNaPredmety($pdo);

    // Počty aktuálních cvičení skupin (NULL i obsazené) per predmet+jazyk+max
    $stmt = $pdo->prepare("
        SELECT
            p.id                    AS predmet_id,
            p.zkratka,
            p.nazev,
            p.semestr,
            upp.jazyk,
            upp.max_pocet_studentu,
            COUNT(*)                AS aktualni_skupiny
        FROM ucitelpredmetprirazeni upp
        JOIN predmet p
            ON  p.id      = upp.predmetid
            AND p.IdVerze = upp.IdVerze
        WHERE upp.typ                  = 'C'
          AND upp.max_pocet_studentu   IS NOT NULL
          AND upp.max_pocet_studentu   > 1
          AND upp.IdVerze              = ?
        GROUP BY p.id, p.zkratka, p.nazev, p.semestr, upp.jazyk, upp.max_pocet_studentu
        ORDER BY p.semestr, p.zkratka
    ");
    $stmt->execute([$idVerze]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $plan = [];
    foreach ($rows as $row) {
        $pocetSt     = $poctyStudentu[(int)$row['predmet_id']] ?? 0;
        if ($pocetSt <= 0) continue;

        $maxNaSkup   = (int)$row['max_pocet_studentu'];
        $potreba     = (int)ceil($pocetSt / $maxNaSkup);
        $aktualni    = (int)$row['aktualni_skupiny'];
        $pridat      = max(0, $potreba - $aktualni);

        $plan[] = [
            'predmet_id'      => (int)$row['predmet_id'],
            'zkratka'         => $row['zkratka'],
            'nazev'           => $row['nazev'],
            'semestr'         => $row['semestr'],
            'jazyk'           => (int)$row['jazyk'],
            'pocet_studentu'  => $pocetSt,
            'max_na_skupinu'  => $maxNaSkup,
            'potreba_skupin'  => $potreba,
            'aktualni_skupiny'=> $aktualni,
            'pridat'          => $pridat,
        ];
    }
    return $plan;
}

/**
 * Provede rozdělení – přidá chybějící NULL řádky cvičení.
 * Vrátí počet přidaných řádků.
 */
function rozdelitCviceniPodleStudentu($pdo) {
    $idVerze      = getAktivniVerze($pdo);
    $plan         = getCviceniRozdeleniNahled($pdo);
    $celkemPridano = 0;

    $stmt = $pdo->prepare("
        INSERT INTO ucitelpredmetprirazeni
            (predmetid, teacherid, typ, podil, IdVerze, jazyk, max_pocet_studentu)
        VALUES
            (?, NULL, 'C', 0, ?, ?, ?)
    ");

    foreach ($plan as $item) {
        for ($i = 0; $i < $item['pridat']; $i++) {
            $stmt->execute([
                $item['predmet_id'],
                $idVerze,
                $item['jazyk'],
                $item['max_na_skupinu'],
            ]);
            $celkemPridano++;
        }
    }
    return $celkemPridano;
}
