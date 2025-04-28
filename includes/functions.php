<?php

function myMessage(){
    echo "hello world!";
}


function onInit(PDO $pdo) {
    // Smazání všech tabulek bezpečně
    deleteAll($pdo);

    echo "<h3>Inicializace databáze...</h3>";

    try {
        // Tabulka roky
        $pdo->exec("CREATE TABLE roky (
            rok INT PRIMARY KEY,
            akademickyrok VARCHAR(10)
        )");

        // Vložení let
        for ($year = 2023; $year <= 2099; $year++) {
            $akademicky = $year . "/" . ($year + 1);
            $stmt = $pdo->prepare("INSERT INTO roky (rok, akademickyrok) VALUES (?, ?)");
            $stmt->execute([$year, $akademicky]);
        }

        // Tabulka semestr
        $pdo->exec("CREATE TABLE semestr (
            semestr VARCHAR(3) PRIMARY KEY,
            popis VARCHAR(15),
            aktualnisemestr INT DEFAULT 0,
            IdVerze INT
        )");
        $pdo->exec("INSERT INTO semestr (semestr, popis) VALUES ('ZS', 'Zimní semestr')");
        $pdo->exec("INSERT INTO semestr (semestr, popis) VALUES ('LS', 'Letní semestr')");

        // Tabulka cisfakulta
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

        // Ostatní tabulky
        $pdo->exec("CREATE TABLE pracoviste (
            idpracoviste INT PRIMARY KEY AUTO_INCREMENT,
            idpracovistestag int NOT NULL,
            zkratka VARCHAR(7),
            typpracoviste VARCHAR(2),
            nadrazenepracoviste VARCHAR(7),
            nazev VARCHAR(50),
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE predmet (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zkratka VARCHAR(10),
            nazev VARCHAR(50),
            rok INT,
            cviciciUcitIdno VARCHAR(200),
            seminariciUcitIdno VARCHAR(200),
            prednasejiciUcitIdno VARCHAR(200),
            vyucovaciJazyky VARCHAR(30),
            nahrazPredmety VARCHAR(30),
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE predmetlast LIKE predmet");

        $pdo->exec("CREATE TABLE studijniprogram (
            idstudijniprogram INT PRIMARY KEY AUTO_INCREMENT,
            stprIdno INT,
            nazev VARCHAR(100),
            kod VARCHAR(20),
            platnyod INT,
            pocetprijimanych VARCHAR(50),
            stddelka VARCHAR(4),
            pocetstudentu INT,
            idForma INT,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE teachers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50),
            surname VARCHAR(50),
            ucitIdno INT,
            iddbversion INT,
            idCisTituly INT,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE ucitelPredmety (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ucitIdno INT,
            predmetzkratka VARCHAR(20),
            iddbversion INT,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE ucitelpredmetlast (
            id INT PRIMARY KEY AUTO_INCREMENT,
            predmetid INT,
            teacherid INT,
            typ VARCHAR(15),
            pocetSkupin INT DEFAULT 1,
            vyucHodin FLOAT DEFAULT NULL,
            poznamka TEXT DEFAULT NULL,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE ucitelpredmetprirazeni (
            id INT PRIMARY KEY AUTO_INCREMENT,
            predmetid INT,
            teacherid INT,
            jazyk INT,
            typ VARCHAR(7),
            podil FLOAT DEFAULT 100,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE seq_ucitIdno (
            num INT
        )");

        $pdo->exec("CREATE TABLE cistituly (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zkratka varchar(10)
        )");

        $pdo->exec("CREATE TABLE verze (
            IdVerze INT AUTO_INCREMENT PRIMARY KEY,
            Nazev VARCHAR(255) NOT NULL,
            Datum DATE NOT NULL
        )");

        $pdo->exec("CREATE TABLE nastaveni (
            IdNastaveni INT PRIMARY KEY,
            Nazev VARCHAR(100),
            Popis TEXT,
            Hodnota INT,
            HodnotaChar VARCHAR(100)
        )");

        $pdo->exec("CREATE TABLE errnumber (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ucitIdno INT
        )");

        $pdo->exec("CREATE TABLE jazyk (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zkratka varchar(7),
            popis varchar(20)
        )");

        $pdo->exec("CREATE TABLE cviceni_max_studenti (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idUcitelPredmetPrirazeni INT,
            pocet INT
        )");

        $pdo->exec("CREATE TABLE vyukove_jednotky (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zkratka VARCHAR(20) UNIQUE NOT NULL,  -- např. 'HOD/TYD'
            popis TEXT                             -- volitelný např. 'Hodin týdně'
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
            roakIdno INT, -- ID rozvrhové akce ve STAGu
            predmet_zkratka VARCHAR(20),
            nazev_predmetu VARCHAR(255),
            katedra VARCHAR(10),
            rok INT,
            semestr VARCHAR(2),
            typ_akce VARCHAR(20),
            typ_akce_zkr VARCHAR(5),
            pocet_vyuc_hodin INT,
            krouzky TEXT,
            IdVerze INT
        )");

        $pdo->exec("CREATE TABLE rozvrhova_akce_ucitel (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roakIdno INT,
            ucitIdno INT,
            podil_na_vyuce FLOAT, -- Např. 60.0
            IdVerze INT
        --    FOREIGN KEY (roakIdno) REFERENCES rozvrhova_akce(roakIdno)
        )");

        $pdo->exec("CREATE TABLE seq_ucitIdnoExternista (
            id INT PRIMARY KEY,
            cislo INT
        )");

        $pdo->exec("CREATE TABLE predmet_jazyk (
            id INT AUTO_INCREMENT PRIMARY KEY,
            predmetid INT NOT NULL,
            jazykid INT NOT NULL)");

        try {
            $pdo->exec("CREATE VIEW vwPredmetJazyk as 
            SELECT DISTINCT predmetid, jazykid 
            FROM predmet_jazyk");
        }
        catch (PDOException $e) {
            echo "Chyba při vytváření VIEW, pravděpodobně již existuje: " . $e->getMessage();
        }


        // Aktivní verze - základní záznam
        // $pdo->exec("INSERT IGNORE INTO nastaveni (IdNastaveni, Nazev, Popis, Hodnota) 
        //             VALUES (1, 'AktivniVerze', 'ID aktivní verze', 0);
        //             INSERT INTO nastaveni values (2, 'AktivniRok', 'ID aktivního roku', 2025);
        //             INSERT INTO nastaveni values (3, 'AktivniKatedra', 'ID aktivní katedry', 0);
        //             INSERT INTO vyukove_jednotky (id, zkratka, popis) values (1, 'HOD/SEM', 'Hodiny za semestr');
        //             INSERT INTO vyukove_jednotky (id, zkratka, popis) values (2, 'HOD/TYD', 'Hodiny za týden');
        //             INSERT INTO seq_ucitIdnoExternista (id, cislo) VALUES (1, 0);
        //             INSERT INTO jazyk (id, zkratka, popis) VALUES (2, 'AJ', 'Angličtina');
        //             INSERT INTO jazyk (id, zkratka, popis) VALUES (1, 'ČJ', 'Čeština');
        //             ");

        //pro docker
        $pdo->exec("INSERT IGNORE INTO nastaveni (IdNastaveni, Nazev, Popis, Hodnota) VALUES (1, 'AktivniVerze', 'ID aktivní verze', 0)");
        $pdo->exec("INSERT INTO nastaveni (IdNastaveni, Nazev, Popis, Hodnota) VALUES (2, 'AktivniRok', 'ID aktivního roku', 2025)");
        $pdo->exec("INSERT INTO nastaveni (IdNastaveni, Nazev, Popis, Hodnota) VALUES (3, 'AktivniKatedra', 'ID aktivní katedry', 0)");
        $pdo->exec("INSERT INTO nastaveni (IdNastaveni, Nazev, Popis, HodnotaChar) VALUES (11, 'PredmetPrez', 'Zacatek zkratky predmetu pro prezencnci studium', 'AP')");
        $pdo->exec("INSERT INTO nastaveni (IdNastaveni, Nazev, Popis, HodnotaChar) VALUES (12, 'PredmetKomb', 'Zacatek zkratky predmetu pro kombinovane studium', 'AK')");
        $pdo->exec("INSERT INTO nastaveni (IdNastaveni, Nazev, Popis, HodnotaChar) VALUES (13, 'PredmetAng', 'Zacatek zkratky predmetu pro anglickou vyuku', 'AE')");
        $pdo->exec("INSERT INTO vyukove_jednotky (id, zkratka, popis) VALUES (1, 'HOD/SEM', 'Hodiny za semestr')");
        $pdo->exec("INSERT INTO vyukove_jednotky (id, zkratka, popis) VALUES (2, 'HOD/TYD', 'Hodiny za týden')");
        $pdo->exec("INSERT INTO seq_ucitIdnoExternista (id, cislo) VALUES (1, 0)");
        $pdo->exec("INSERT INTO jazyk (id, zkratka, popis) VALUES (2, 'AJ', 'Angličtina')");
        $pdo->exec("INSERT INTO jazyk (id, zkratka, popis) VALUES (1, 'ČJ', 'Čeština')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Bc.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('BcA.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Mgr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('MgA.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Ing.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Ing. arch.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('MUDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('MDDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('MVDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('PhDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('JUDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('RNDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('PharmDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('ThDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('PaedDr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('ThLic.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Ph.D.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Th.D.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('Dr.')");
        $pdo->exec("INSERT INTO cistituly (zkratka) VALUES ('CSc.')");


        echo "<p style='color: green;'>Všechny tabulky byly úspěšně vytvořeny a inicializovány.</p>";

    } catch (PDOException $e) {
        echo "<p style='color: red;'>Chyba při inicializaci: " . $e->getMessage() . "</p>";
    }
}

//nepouzivam
function onInit_Insert($pdo){
    try {
        deleteAll($pdo);
    }
    catch (Exception $e) {
        echo "nothing to delete";
    }
    $query = "create table roky(
        rok int primary key,
        akademickyrok varchar(10),
        zvoleny int);
        INSERT INTO roky (rok, akademickyrok, zvoleny) VALUES (2023, '2023/2024', 1);
        INSERT INTO roky (rok, akademickyrok) VALUES (2024, '2024/2025');
        INSERT INTO roky (rok, akademickyrok) VALUES (2025, '2025/2026');
        INSERT INTO roky (rok, akademickyrok) VALUES (2026, '2026/2027');
        INSERT INTO roky (rok, akademickyrok) VALUES (2027, '2027/2028');
        INSERT INTO roky (rok, akademickyrok) VALUES (2028, '2028/2029');
        INSERT INTO roky (rok, akademickyrok) VALUES (2029, '2029/2030');
        INSERT INTO roky (rok, akademickyrok) VALUES (2030, '2030/2031');
        INSERT INTO roky (rok, akademickyrok) VALUES (2031, '2031/2032');
        INSERT INTO roky (rok, akademickyrok) VALUES (2032, '2032/2033');
        INSERT INTO roky (rok, akademickyrok) VALUES (2033, '2033/2034');
        INSERT INTO roky (rok, akademickyrok) VALUES (2034, '2034/2035');
        INSERT INTO roky (rok, akademickyrok) VALUES (2035, '2035/2036');
        INSERT INTO roky (rok, akademickyrok) VALUES (2036, '2036/2037');
        INSERT INTO roky (rok, akademickyrok) VALUES (2037, '2037/2038');
        INSERT INTO roky (rok, akademickyrok) VALUES (2038, '2038/2039');
        INSERT INTO roky (rok, akademickyrok) VALUES (2039, '2039/2040');
        INSERT INTO roky (rok, akademickyrok) VALUES (2040, '2040/2041');
        INSERT INTO roky (rok, akademickyrok) VALUES (2041, '2041/2042');
        INSERT INTO roky (rok, akademickyrok) VALUES (2042, '2042/2043');
        INSERT INTO roky (rok, akademickyrok) VALUES (2043, '2043/2044');
        INSERT INTO roky (rok, akademickyrok) VALUES (2044, '2044/2045');
        INSERT INTO roky (rok, akademickyrok) VALUES (2045, '2045/2046');
        INSERT INTO roky (rok, akademickyrok) VALUES (2046, '2046/2047');
        INSERT INTO roky (rok, akademickyrok) VALUES (2047, '2047/2048');
        INSERT INTO roky (rok, akademickyrok) VALUES (2048, '2048/2049');
        INSERT INTO roky (rok, akademickyrok) VALUES (2049, '2049/2050');
        INSERT INTO roky (rok, akademickyrok) VALUES (2050, '2050/2051');
        INSERT INTO roky (rok, akademickyrok) VALUES (2051, '2051/2052');
        INSERT INTO roky (rok, akademickyrok) VALUES (2052, '2052/2053');
        INSERT INTO roky (rok, akademickyrok) VALUES (2053, '2053/2054');
        INSERT INTO roky (rok, akademickyrok) VALUES (2054, '2054/2055');
        INSERT INTO roky (rok, akademickyrok) VALUES (2055, '2055/2056');
        INSERT INTO roky (rok, akademickyrok) VALUES (2056, '2056/2057');
        INSERT INTO roky (rok, akademickyrok) VALUES (2057, '2057/2058');
        INSERT INTO roky (rok, akademickyrok) VALUES (2058, '2058/2059');
        INSERT INTO roky (rok, akademickyrok) VALUES (2059, '2059/2060');
        INSERT INTO roky (rok, akademickyrok) VALUES (2060, '2060/2061');
        INSERT INTO roky (rok, akademickyrok) VALUES (2061, '2061/2062');
        INSERT INTO roky (rok, akademickyrok) VALUES (2062, '2062/2063');
        INSERT INTO roky (rok, akademickyrok) VALUES (2063, '2063/2064');
        INSERT INTO roky (rok, akademickyrok) VALUES (2064, '2064/2065');
        INSERT INTO roky (rok, akademickyrok) VALUES (2065, '2065/2066');
        INSERT INTO roky (rok, akademickyrok) VALUES (2066, '2066/2067');
        INSERT INTO roky (rok, akademickyrok) VALUES (2067, '2067/2068');
        INSERT INTO roky (rok, akademickyrok) VALUES (2068, '2068/2069');
        INSERT INTO roky (rok, akademickyrok) VALUES (2069, '2069/2070');
        INSERT INTO roky (rok, akademickyrok) VALUES (2070, '2070/2071');
        INSERT INTO roky (rok, akademickyrok) VALUES (2071, '2071/2072');
        INSERT INTO roky (rok, akademickyrok) VALUES (2072, '2072/2073');
        INSERT INTO roky (rok, akademickyrok) VALUES (2073, '2073/2074');
        INSERT INTO roky (rok, akademickyrok) VALUES (2074, '2074/2075');
        INSERT INTO roky (rok, akademickyrok) VALUES (2075, '2075/2076');
        INSERT INTO roky (rok, akademickyrok) VALUES (2076, '2076/2077');
        INSERT INTO roky (rok, akademickyrok) VALUES (2077, '2077/2078');
        INSERT INTO roky (rok, akademickyrok) VALUES (2078, '2078/2079');
        INSERT INTO roky (rok, akademickyrok) VALUES (2079, '2079/2080');
        INSERT INTO roky (rok, akademickyrok) VALUES (2080, '2080/2081');
        INSERT INTO roky (rok, akademickyrok) VALUES (2081, '2081/2082');
        INSERT INTO roky (rok, akademickyrok) VALUES (2082, '2082/2083');
        INSERT INTO roky (rok, akademickyrok) VALUES (2083, '2083/2084');
        INSERT INTO roky (rok, akademickyrok) VALUES (2084, '2084/2085');
        INSERT INTO roky (rok, akademickyrok) VALUES (2085, '2085/2086');
        INSERT INTO roky (rok, akademickyrok) VALUES (2086, '2086/2087');
        INSERT INTO roky (rok, akademickyrok) VALUES (2087, '2087/2088');
        INSERT INTO roky (rok, akademickyrok) VALUES (2088, '2088/2089');
        INSERT INTO roky (rok, akademickyrok) VALUES (2089, '2089/2090');
        INSERT INTO roky (rok, akademickyrok) VALUES (2090, '2090/2091');
        INSERT INTO roky (rok, akademickyrok) VALUES (2091, '2091/2092');
        INSERT INTO roky (rok, akademickyrok) VALUES (2092, '2092/2093');
        INSERT INTO roky (rok, akademickyrok) VALUES (2093, '2093/2094');
        INSERT INTO roky (rok, akademickyrok) VALUES (2094, '2094/2095');
        INSERT INTO roky (rok, akademickyrok) VALUES (2095, '2095/2096');
        INSERT INTO roky (rok, akademickyrok) VALUES (2096, '2096/2097');
        INSERT INTO roky (rok, akademickyrok) VALUES (2097, '2097/2098');
        INSERT INTO roky (rok, akademickyrok) VALUES (2098, '2098/2099');
        INSERT INTO roky (rok, akademickyrok) VALUES (2099, '2099/2100');
        create table semestr(
            semestr varchar(3) primary key,
            popis varchar(15),
            aktualnisemestr int,
            IdVerze INT);
        insert into semestr (semestr, popis) values ('ZS', 'Zimni semestr');
        insert into semestr (semestr, popis) values ('LS', 'Letni semestr');
        create table pracoviste(
            idpracoviste int primary key,
            zkratka varchar(7),
            typpracoviste varchar(2),
            nadrazenepracoviste varchar(7),
            nazev varchar(50),
            aktualnipracoviste int,
            IdVerze INT);
        create table cisfakulta(
            idcis int primary key auto_increment,
            zkratka varchar(5),
            IdVerze INT);
        insert into cisfakulta (zkratka) values ('FAI');
        insert into cisfakulta (zkratka) values ('FAM');
        insert into cisfakulta (zkratka) values ('FLK');
        insert into cisfakulta (zkratka) values ('FMK');
        insert into cisfakulta (zkratka) values ('FHS');
        insert into cisfakulta (zkratka) values ('FT');
        insert into cisfakulta (zkratka) values ('IMS');
        create table predmet(
            id int primary key auto_increment,
            zkratka varchar(10),
            nazev varchar(50),
            rok int,
            cviciciUcitIdno varchar(200),
            seminariciUcitIdno varchar(200),
            prednasejiciUcitIdno varchar(200),
            vyucovaciJazyky varchar(30),
            nahrazPredmety varchar(30),
            IdVerze INT);
        create table studijniprogram (
            stprIdno int primary key,
            nazev varchar(100),
            kod varchar(20),
            platnyod int,
            pocetprijimanych varchar(50),
            stddelka varchar(4),
            pocetstudentu int,
            IdVerze INT
            );
        create table teachers (
            id int(10),
            name varchar(50),
            surname varchar(50),
            ucitIdno int,
            iddbversion int,
            idCisTituly int,
            IdVerze INT);
        create table ucitelPredmety(
            id int primary key auto_increment,
            ucitIdno int,
            predmetzkratka varchar(20),
            iddbversion int,
            IdVerze INT);
        create table predmetlast(
            id int primary key auto_increment,
            zkratka varchar(10),
            nazev varchar(50),
            cviciciUcitIdno varchar(200),
            seminariciUcitIdno varchar(200),
            prednasejiciUcitIdno varchar(200),
            vyucovaciJazyky varchar(30),
            nahrazPredmety varchar(30),
            rok int,
            IdVerze INT);
        create table ucitelpredmetprirazeni(
            id int primary key auto_increment,
            predmetid int,
            teacherid int,
            IdVerze INT,
            typ varchar(15) #Cvicici, prednasejici, seminarici ucitelPredmety
            );
        create table ucitelpredmetlast(
            id int primary key auto_increment,
            predmetid int,
            teacherid int,
            IdVerze INT,
            typ varchar(15) #Cvicici, prednasejici, seminarici ucitelPredmety
            );    
        create table seq_ucitIdno(
            num int);
        CREATE TABLE verze (
            IdVerze INT AUTO_INCREMENT PRIMARY KEY,
            Nazev VARCHAR(255) NOT NULL,
            Datum DATE NOT NULL
        );
        ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
//    echo $query;
    echo nl2br("Deleted succesfully\n"); 

}

function getPredmetyUcitel($pdo, $ucitIdno){
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByUcitel?ucitIdno=" . $ucitIdno . "&lang=en&outputFormat=JSON&katedra=%25&rok=%25";
    // Vytvořt ověřovací údaje
    require('config.php');
    $auth = base64_encode("$username:$password");

    // autentikace
    $context = stream_context_create([
        'http' => [
            'header' => "Authorization: Basic $auth"
        ]
    ]);

    try {
        $response2 = file_get_contents($api_url, false, $context);

        if ($response2 === false) {
            throw new Exception("Failed to fetch data for ucitIdno: $ucitIdno");
        }

        $data2 = json_decode($response2, true);

        if (!isset($data2['predmetUcitele'])) {
            throw new Exception("Unexpected data structure for ucitIdno: $ucitIdno");
        }

        foreach($data2['predmetUcitele'] as $predmet) {
            insertPredmetyByUcitel($pdo ,$predmet['zkratka'], $ucitIdno);
            // echo nl2br($predmet['zkratka'] . "\n");
        }
    } catch (Exception $e) {
        //  error 
        ///insertErrNumber($pdo ,$ucitIdno);
        echo "Error: " . $e->getMessage();
    }

}

function getFakulta($pdo){
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT DISTINCT nadrazenepracoviste FROM pracoviste");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $katedra = (string) $result['nadrazenepracoviste'];
//    echo $year;
    return $katedra;
}

function getKatedry($pdo, $fakulta){
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/ciselniky/getSeznamPracovist?typPracoviste=%25&zkratka=%25&nadrazenePracoviste=%25&outputFormat=JSON";
    
    $response = file_get_contents($api_url);

    if ($response === FALSE) {
        echo "error in connection";
    }
    else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }
//        deleteKatedry($pdo);
        foreach ($data['pracoviste'] as $pracoviste) {
            if ($pracoviste['nadrazenePracoviste'] == $fakulta) {
                insertPracoviste($pdo, $pracoviste['cisloPracoviste'], $pracoviste['zkratka'], $pracoviste['typPracoviste'], $pracoviste['nadrazenePracoviste'], $pracoviste['nazev']);
            }
        }
    }
}

function getPredmetyByKatedra($pdo, $katedra){
    $year = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo?semestr=LS&outputFormat=JSON&katedra=" . $katedra . "&rok=" . $year;
    $response = file_get_contents($api_url);
    echo nl2br("\nURL API:" . $api_url . "\n");;

    if ($response === FALSE) {
        echo "error in connection";
    }
    else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }
//        deletePredmet($pdo);
        foreach ($data['predmetKatedryFullInfo'] as $predmet) {

            insertPredmet($pdo, $predmet['zkratka'], $predmet['nazev'], $predmet['cviciciUcitIdno'], $predmet['seminariciUcitIdno'], $predmet['prednasejiciUcitIdno'], $predmet['vyucovaciJazyky'], $predmet['rok']);
            insertPredmetHodiny($pdo, $predmet['zkratka'], $predmet['jednotekPrednasek'], $predmet['jednotkaPrednasky'], $predmet['jednotekCviceni'], $predmet['jednotkaCviceni'], $predmet['jednotekSeminare'], $predmet['jednotkaSeminare']);
            // $ints = parseStringToIntegers($predmet['cviciciUcitIdno']);

            //import jazyků
            $jazyky = explode(',', is_string($predmet['vyucovaciJazyky']) ? $predmet['vyucovaciJazyky'] : '');
            $predmetId = $pdo->lastInsertId();
            foreach ($jazyky as $jazyk) {
                $jazyk = trim($jazyk);

                // najít jazyk v tabulce jazyk
                $stmt = $pdo->prepare("SELECT id FROM jazyk WHERE popis = ?");
                $stmt->execute([$jazyk]);
                $jazykId = $stmt->fetchColumn();

                // pokud neexistuje, vložit nový jazyk
                if (!$jazykId) {
                    $stmt = $pdo->prepare("INSERT INTO jazyk (zkratka, popis) VALUES (?, ?)");
                    $stmt->execute([substr($jazyk, 0, 2), $jazyk]);
                    $jazykId = $pdo->lastInsertId();
                }

                // vložit do predmet_jazyk
                $stmt = $pdo->prepare("INSERT INTO predmet_jazyk (predmetid, jazykid) VALUES (?, ?)");
                $stmt->execute([$predmetId, $jazykId]);
                echo "K předmětu přidán jazyk: " . $jazykId;
            }
        }
    }
}

function getPredmetyByKatedraLast($pdo, $katedra){
    $year = getYear($pdo) - 1;
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo?semestr=LS&outputFormat=JSON&katedra=" . $katedra . "&rok=" . $year;
    $response = file_get_contents($api_url);
    echo nl2br("\nURL API:" . $api_url . "\n");;

    if ($response === FALSE) {
        echo "error in connection";
    }
    else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }
//        deletePredmetLast($pdo);
        foreach ($data['predmetKatedryFullInfo'] as $predmet) {
            insertPredmetLast($pdo, $predmet['zkratka'], $predmet['nazev'], $predmet['cviciciUcitIdno'], $predmet['seminariciUcitIdno'], $predmet['prednasejiciUcitIdno'], $predmet['vyucovaciJazyky'], $predmet['rok']);
            // $ints = parseStringToIntegers($predmet['cviciciUcitIdno']);
            // echo $ints;
            $jazyky = explode(',', is_string($predmet['vyucovaciJazyky']) ? $predmet['vyucovaciJazyky'] : '');
            $predmetId = $pdo->lastInsertId();
            foreach ($jazyky as $jazyk) {
                $jazyk = trim($jazyk);

                // najít jazyk v tabulce jazyk
                $stmt = $pdo->prepare("SELECT id FROM jazyk WHERE popis = ?");
                $stmt->execute([$jazyk]);
                $jazykId = $stmt->fetchColumn();

                // pokud neexistuje, vložit nový jazyk
                if (!$jazykId) {
                    $stmt = $pdo->prepare("INSERT INTO jazyk (zkratka, popis) VALUES (?, ?)");
                    $stmt->execute([substr($jazyk, 0, 2), $jazyk]);
                    $jazykId = $pdo->lastInsertId();
                }

                // vložit do predmet_jazyk
                $stmt = $pdo->prepare("INSERT INTO predmet_jazyk (predmetid, jazykid) VALUES (?, ?)");
                $stmt->execute([$predmetId, $jazykId]);
                echo "K předmětu přidán jazyk: " . $jazykId;
            }
        }
    }
}

function getYear($pdo){
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT r.rok FROM roky r
                        JOIN nastaveni n
                        ON r.rok=n.hodnota
                        ;");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || !isset($result['rok'])) {
        return null; // nebo nějaká výchozí hodnota, např. 0 nebo aktuální rok
    }

    return (int) $result['rok'];


    //     $year = (int) $result['rok'];
// //    echo $year;
//     return $year;
}

function getSemestr($pdo){
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT semestr FROM semestr WHERE aktualnisemestr=1;");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $semestr = (string) $result['semestr'];
//    echo $year;
    return $semestr;
}

function getKatedra($pdo){
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT zkratka FROM pracoviste WHERE idpracoviste= (SELECT hodnota FROM nastaveni where nazev='AktivniKatedra');");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $katedra = (string) $result['zkratka'];
//    echo $year;
    return $katedra;
}

function getStudijniProgram($pdo, $fakulta){
    $rok = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/programy/getStudijniProgramy?kod=%25&pouzePlatne=true&fakulta=" . $fakulta . "&outputFormat=JSON&rok=" . $rok;
    echo nl2br("\nURL API getPredmetInfo:" . $api_url . "\n");;

    $response = file_get_contents($api_url);

    if ($response === FALSE) {
        echo "error in connection";
    }
    else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }

        foreach ($data['programInfo'] as $stp) {
            insertStudijniProgram($pdo, $stp['stprIdno'], $stp['nazev'], $stp['kod'], $stp['platnyOd'], $stp['pocetPrijimanych'], $stp['stdDelka'], $stp['forma']);
            // echo $teacher['jmeno'] . " " . $teacher['prijmeni'];
        }

    }
}

function getUcitele($pdo){

    // API endpoint
        $katedra = getFakulta($pdo);
        $api_url = "https://stag-ws.utb.cz/ws/services/rest2/ucitel/getUciteleKatedry?lang=en&outputFormat=JSON&katedra=" . $katedra . "&jenAktualni=true";
    
    // tahání dat
        $response = file_get_contents($api_url);
    
    // bylo úspěšné?
        if ($response === FALSE) {
        // pokud nebylo úspěšné
            echo "Error occurred while fetching data from the API.";
        } else {
            $data = json_decode($response, true);
            if ($data === NULL) {
            echo "Error decoding JSON response.";
        } else {
    //        $api_response = $data;
            
            if (isset($data['ucitel'])) {
//                deleteUcitele($pdo);
                    // každej učitel smyčka
                foreach ($data['ucitel'] as $teacher) {
                    insertUcitel($pdo, $teacher['jmeno'], $teacher['prijmeni'], $teacher['ucitIdno']);
                    getPredmetyUcitel($pdo ,$teacher['ucitIdno']);
                    // echo $teacher['jmeno'] . " " . $teacher['prijmeni'];
                }
            } else {
                    echo "No teachers found in the response.";
                    }
    //            var_dump($api_response);
            }
        }
    }

function insertUcitel($pdo ,$name, $surname, $ucitIdno){
    try {
        // Načtení aktuální verze z tabulky nastaveni
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        // Vložení učitele s verzí
        $query = "INSERT INTO teachers (name, surname, ucitIdno, IdVerze) VALUES (?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $surname, $ucitIdno, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ importován učitel: " . $name . " " . $surname . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function insertStudijniProgram($pdo, $stprIdno, $nazev, $kod, $platnyod, $pocetprijimanych, $stddelka, $forma){
    try{
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        //forma 1 Prezenční, 2 Kombinovaná, 0 nevyplněno
        if($forma=="Prezenční"){
            $forma=1;
        }
        else if ($forma=="Kombinovaná"){
            $forma=2;
        }
        else $forma=0;

        $query = "INSERT INTO studijniprogram (stprIdno, nazev, kod, platnyod, pocetprijimanych, stddelka, idForma, IdVerze) VALUES (?, ?, ?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$stprIdno, $nazev, $kod, $platnyod, $pocetprijimanych, $stddelka, $forma, $IdVerze]);

        echo nl2br("Studijni program uspesne nahran: " . $stprIdno . $nazev . "\n");
    } catch(PDOException $e) {
        echo "error: " . $e->getMessage();
    }
}

function insertPredmetyByUcitel($pdo, $zkratka, $ucitIdno){
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO ucitelPredmety (predmetzkratka, ucitIdno, IdVerze) VALUES (?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$zkratka, $ucitIdno, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ insertován predmetByUcitel:  zkratka předmětu: " . $zkratka . ", ucitIdno: " . $ucitIdno . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function insertPracoviste($pdo, $idpracovistestag, $zkratka, $typpracoviste, $nadrazenepracoviste, $nazev){
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO pracoviste (idpracovistestag, zkratka, typpracoviste, nadrazenepracoviste, nazev, IdVerze) VALUES (?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$idpracovistestag, $zkratka, $typpracoviste, $nadrazenepracoviste, $nazev, $IdVerze]);

        echo nl2br("Pracoviště ÚSPĚŠNĚ insertováno: " . $nazev . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function insertPredmet($pdo, $zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok){
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO predmet (zkratka, nazev, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno, vyucovaciJazyky, rok, IdVerze) VALUES (?, ?, ?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok, $IdVerze]);

        echo nl2br("Predmet ÚSPĚŠNĚ insertován: " . $nazev . " " . $rok . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function insertPredmetLast($pdo, $zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok){
    try {
        $idVerze=getAktivniVerze($pdo);
        $query = "INSERT INTO predmetlast (zkratka, nazev, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno, vyucovaciJazyky, rok, idVerze) VALUES (?, ?, ?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$zkratka, $nazev, $cviciciUcitIdno, $seminariciUcitIdno, $prednasejiciUcitIdno, $vyucovaciJazyky, $rok, $idVerze]);

        echo nl2br("ÚSPĚŠNĚ insertován minulý předmět: " . $nazev . " " . $rok . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

//insertuje cvicici z posledniho roku
function insertTeachedLastYC($pdo, $predmetid, $teacherid) {
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'cvicici', ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ naimportováno: " . implode(', ', [$predmetid, $teacherid]) . "\n");
    } catch (PDOException $e) {
        echo "error: " . $e->getMessage();
    }
}

//insertuje seminarici z posledniho roku
function insertTeachedLastYS($pdo, $predmetid, $teacherid) {
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'seminarici', ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ naimportováno: " . implode(', ', [$predmetid, $teacherid]) . "\n");
    } catch (PDOException $e) {
        echo "error: " . $e->getMessage();
    }
}

//insertuje prednasejici z posledniho roku
function insertTeachedLastYP($pdo, $predmetid, $teacherid) {
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO ucitelpredmetlast (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, 'prednasejici', ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ naimportováno: " . implode(', ', [$predmetid, $teacherid]) . "\n");
    } catch (PDOException $e) {
        echo "error: " . $e->getMessage();
    }
}

//prirazuje ucitele dle posledniho roku k predmetum tohoto roku na zaklade joinu v tabulce
function insertTeacherAssingByLastYear($pdo){
    $verze = getAktivniVerze($pdo);
    $stmt = $pdo->prepare("SELECT p.id, p.zkratka, p.rok, pl.zkratka, pl.rok, upl.teacherid, upl.typ 
        FROM predmet p
        JOIN predmetlast pl ON p.zkratka = pl.zkratka AND pl.IdVerze = ?
        JOIN ucitelpredmetlast upl ON pl.id = upl.predmetid AND upl.IdVerze = ?
        WHERE p.IdVerze = ?");
    $stmt->execute([$verze, $verze, $verze]);

    // $stmt = $pdo->query("select p.id, p.zkratka, p.rok, pl.zkratka, pl.rok, upl.teacherid, upl.typ from predmet p
    // join predmetlast pl
    // on p.zkratka=pl.zkratka
    // join ucitelpredmetlast upl
    // on pl.id=upl.predmetid;");
    $lasty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($lasty as $last){
        insertCurrentY($pdo, $last['id'], $last['teacherid'], $last['typ']);
    }
}

//zapisuje do tabulky UcitelPredmetPrirazeni
function insertCurrentY($pdo, $predmetid, $teacherid, $typ) {
    try {
        $stmtVerze = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
        $stmtVerze->execute();
        $IdVerze = $stmtVerze->fetchColumn();

        $query = "INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, IdVerze) VALUES (?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$predmetid, $teacherid, $typ, $IdVerze]);

        echo nl2br("ÚSPĚŠNĚ naimportováno: " . implode(', ', [$predmetid, $teacherid, $typ]) . "\n");
    } catch (PDOException $e) {
        echo "error: " . $e->getMessage();
    }
}

// function deleteAll($pdo){
//     $query = "DROP TABLE cisfakulta;
//     DROP TABLE nastaveni;
//     DROP TABLE pracoviste;
//     DROP TABLE predmet;
//     DROP TABLE predmetlast;
//     DROP TABLE roky;
//     DROP TABLE semestr;
//     DROP TABLE seq_ucitidno;
//     DROP TABLE studijniprogram;
//     DROP TABLE teachers;
//     DROP TABLE ucitelPredmety;
//     DROP TABLE ucitelpredmetlast;
//     DROP TABLE predmetprirazeni;
//     DROP TABLE ucitelPredmety;
//     DROP TABLE predmetlast; 
//     DROP TABLE ucitelpredmetprirazeni;
//     DROP TABLE verze;
//     DROP TABLE errnumber;

//     ";
//     $stmt = $pdo->prepare($query);
//     $stmt->execute();
//     echo nl2br("Deleted succesfully\n"); 
// }

function deleteAll($pdo){
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
        'cistituly'
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS $table");
            echo nl2br("Dropped table $table\n");
        } catch (PDOException $e) {
            echo nl2br("Error dropping $table: " . $e->getMessage() . "\n");
        }
    }
}


// function aktualnirok($pdo, $rok){
//     $query = "UPDATE roky SET zvoleny=0;
//         UPDATE roky SET zvoleny=1 WHERE rok=" . $rok . ";";
//     $stmt = $pdo->prepare($query);
//     $stmt->execute();
//     echo $query;
//     echo nl2br("Deleted succesfully\n"); 
// }

function aktualnirok($pdo, $rok){
    $query = "UPDATE nastaveni SET hodnota=" . $rok . " WHERE nazev='AktivniRok';";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    echo $query;
//    echo nl2br("Deleted succesfully\n"); 
}

//s dockerem nefunguje vice update v jednom query
// function aktualniSemestr($pdo, $semestr){
//         $query = "UPDATE semestr SET aktualnisemestr=0;
//                   UPDATE semestr SET aktualnisemestr=1 WHERE semestr='" . $semestr . "';";
//         $stmt = $pdo->prepare($query);
//         $stmt->execute();
//         echo $query;
//         echo nl2br("Deleted succesfully\n"); 
// }

function aktualniSemestr($pdo, $semestr) {
    // Nejprve vynulujeme všechny
    $stmt1 = $pdo->prepare("UPDATE semestr SET aktualnisemestr = 0");
    $stmt1->execute();

    // Pak nastavíme zvolený semestr jako aktuální
    $stmt2 = $pdo->prepare("UPDATE semestr SET aktualnisemestr = 1 WHERE semestr = :semestr");
    $stmt2->execute([':semestr' => $semestr]);
}

function setKatedra($pdo, $katedra){

    $stmtIdKatedra = $pdo->prepare("SELECT idpracoviste FROM pracoviste WHERE zkratka='" . $katedra . "';");
    $stmtIdKatedra->execute();
    $IdKatedra = $stmtIdKatedra->fetchColumn();

    $query = $pdo->prepare ("UPDATE nastaveni SET hodnota=? WHERE nazev='AktivniKatedra'");
    $query->execute([$IdKatedra]);
    
    // echo $query;
    echo nl2br("Katedra nastavena jako aktivni: " . $katedra . "\n"); 
}    

function teachedlastyear($pdo){
//    deleteUcitelePredmety($pdo);
    $stmt = $pdo->query("SELECT id, zkratka, cviciciUcitIdno, seminariciUcitIdno, prednasejiciUcitIdno FROM predmetlast;");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($predmety as $predmet){
        if($predmet['cviciciUcitIdno'] > 1){
            $arrCvicici = stringToIntArray($predmet['cviciciUcitIdno']);
            foreach($arrCvicici as $cvicici){
                echo $cvicici;
                insertTeachedLastYC($pdo, $predmet['id'], $cvicici);
            }
        }
        if($predmet['seminariciUcitIdno'] > 1){
            $arrSeminarici = stringToIntArray($predmet['seminariciUcitIdno']);
            foreach($arrSeminarici as $seminarici){
                insertTeachedLastYS($pdo, $predmet['id'], $seminarici);
            }
        }
        if($predmet['prednasejiciUcitIdno'] > 1){
            $arrPrednasejici = stringToIntArray($predmet['prednasejiciUcitIdno']);
            foreach($arrPrednasejici as $prednasejici){
                insertTeachedLastYP($pdo, $predmet['id'], $prednasejici);
            }
        }
    }
}

function stringToIntArray($string) {
    $parts = explode(',', is_string($string) ? $string : '');
   
    $intArray = array();
    
    foreach ($parts as $part) {
        $intArray[] = (int) trim($part);
    }
    
    return $intArray;
}

function loadPredmety($pdo){
    $stmt = $pdo->query("SELECT id, zkratka, nazev FROM predmet;");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // show list of teachers
    echo "<h2>Predmety</h2>";
    echo "<ul>";
    foreach ($predmety as $predmet) {
        echo "<li><a href='javascript:void(0);' onclick='selectTeacher({$predmet['zkratka']});'>{$predmet['nazev']}</a></li>";
        echo loadUcitelePredmetu($pdo, $predmet['id']);
        }
    echo "</ul>";

}

function loadUcitelePredmetu($pdo, $predmet){
    $query = "select upp.typ, t.name, t.surname from predmet p
    join ucitelpredmetprirazeni upp
    on p.id=upp.predmetid
    join teachers t
    on upp.teacherid=t.ucitidno
    where p.id=?
    order by upp.typ;";
    
    // Připravte a spusťte dotaz se zadaným parametrem
    $stmt = $pdo->prepare($query);
    $stmt->execute([$predmet]);
    
    // Načíst všechny řádky jako asociativní pole
    $ucitele = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Zobrazit seznam učitelů
    // echo "<p>Ucitele</p>";
    echo "<ul>";
    foreach ($ucitele as $ucitel) {
        echo "<li>{$ucitel['typ']}: {$ucitel['surname']} {$ucitel['name']}</li>";
    }
    echo "</ul>";
}

function loadPredmetyV2($pdo){
    $stmt = $pdo->query("SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname FROM predmet p
    join ucitelpredmetprirazeni upp
    on p.id=upp.predmetid
    join teachers t
    on upp.teacherid=t.ucitIdno;;");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Zobrazit seznam predmetu
    echo "<h2>Predmety</h2>";
    echo '<div class="grid-container">';
    foreach ($predmety as $predmet) {
        echo "<div class='grid-item'>{$predmet['nazev']}</div>
        <div class='grid-item'>{$predmet['zkratka']}</div>
        <div class='grid-item'>{$predmet['name']} {$predmet['surname']}</div>
        <div class='grid-item'>{$predmet['typ']}</div>
        <div class='grid-item'>X</div>";
        // echo "<li><a href='javascript:void(0);' onclick='selectTeacher({$predmet['zkratka']});'>{$predmet['nazev']}</a></li>";
        // echo loadUcitelePredmetu($pdo, $predmet['id']);
        }
        echo '</div>';;
}

function loadPredmetyV3($pdo){
    $stmt = $pdo->query("SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname, t.ucitIdno FROM predmet p
    join ucitelpredmetprirazeni upp
    on p.id=upp.predmetid
    join teachers t
    on upp.teacherid=t.ucitIdno
    order by p.nazev, upp.typ;");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Zobrazit seznam predmetů
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
        <td value={$predmet['id']}>{$predmet['nazev']}</td>
        <td>{$predmet['zkratka']}</td>
        <td value={$predmet['ucitIdno']}>{$predmet['name']} {$predmet['surname']}</td>
        <td value={$predmet['typ']}>{$predmet['typ']}</td>
        <td>X</td>
        <tr>
        ";
        // echo "<li><a href='javascript:void(0);' onclick='selectTeacher({$predmet['zkratka']});'>{$predmet['nazev']}</a></li>";
        // echo loadUcitelePredmetu($pdo, $predmet['id']);
        }
    echo '</table>';
}

function loadPredmetyV4($pdo){
    $stmt = $pdo->query("SELECT p.id, p.zkratka, p.nazev, upp.typ, t.name, t.surname, t.ucitIdno FROM predmet p
    join ucitelpredmetprirazeni upp
    on p.id=upp.predmetid
    join teachers t
    on upp.teacherid=t.ucitIdno
    order by p.nazev, upp.typ;");
    $predmety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
   // Zobrazit seznam predmetů
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
        <td value='{$predmet['id']}'>{$predmet['nazev']}</td>
        <td>{$predmet['zkratka']}</td>
        <td value='{$predmet['ucitIdno']}'>{$predmet['name']} {$predmet['surname']}</td>
        <td value='{$predmet['typ']}'>{$predmet['typ']}</td>
        <td class='delete-row' data-predmetid='{$predmet['id']}' data-teacherid='{$predmet['ucitIdno']}' data-typ='{$predmet['typ']}'>X</td>
        <tr>
        ";
        // echo "<li><a href='javascript:void(0);' onclick='selectTeacher({$predmet['zkratka']});'>{$predmet['nazev']}</a></li>";
        // echo loadUcitelePredmetu($pdo, $predmet['id']);
    }
    echo '</table>';
}

function loadStudijniProgramy($pdo) {
    $stmt = $pdo->query("SELECT * FROM studijniprogram;");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // show list of studijni programy
    echo "<h2>Programs</h2>";

    foreach ($programs as $program) {
        echo "<div class='card'>
            <h2>{$program['nazev']}</h3> 
            <div class='ul-card'>
            <ul>
                <li>kod: {$program['kod']}</li>
                <li>platnyod: {$program['platnyod']}</li> 
                <li>pocetprijmanych: {$program['pocetprijimanych']}</li>
            </ul>
            </div>
            </div>";
    }
}

function loadStudijniProgramyV2($pdo) {
    $stmt = $pdo->query("SELECT * FROM studijniprogram;");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //  studijni programy
    echo "<h2>Programs</h2>
    <table id='dataTable'>
    <tr>
    <th>Nazev</th>
    <th>Kod</th>
    <th>PlatnyOd</th>
    <th>PocetPrijimanych</th>
    <tr/>";

    foreach ($programs as $program) {
        // echo "<div class='card'>
        //     <h2>{$program['nazev']}</h3> 
        //     <div class='ul-card'>
        //     <ul> 
        //         <li>kod: {$program['kod']}</li>
        //         <li>platnyod: {$program['platnyod']}</li> 
        //         <li>pocetprijmanych: {$program['pocetprijimanych']}</li>
        //     </ul>
        //     </div>
        //     </div>";
        echo "
        <tr>
        <td>{$program['nazev']}</td>
        <td>{$program['kod']}</td>
        <td>{$program['platnyod']}</td>
        <td>{$program['pocetprijimanych']}</td>
        <tr/>
        ";
    }
}

function vyber_verzi($pdo) {
    if (isset($_POST['verze']) && !empty($_POST['verze'])) {
        $idVerze = $_POST['verze'];
        $stmt = $pdo->prepare("UPDATE nastaveni SET hodnota = ? WHERE nazev = 'AktivniVerze'");
        $stmt->execute([$idVerze]);
    }
}

function getPredmetInfo($pdo, $katedra){
    $year = getYear($pdo);
    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/predmety/getPredmetyByKatedraFullInfo?semestr=LS&outputFormat=JSON&katedra=" . $katedra . "&rok=" . $year;
    $response = file_get_contents($api_url);
    echo nl2br("\nURL API getPredmetInfo:" . $api_url . "\n");;

    if ($response === FALSE) {
        echo "error in connection";
    }
    else {
        $data = json_decode($response, true);
        if ($data === NULL) {
            echo "Error decoding JSON response.";
        }
//        deletePredmet($pdo);
        foreach ($data['predmetKatedryFullInfo'] as $predmet) {
            insertPredmet($pdo, $predmet['zkratka'], $predmet['nazev'], $predmet['cviciciUcitIdno'], $predmet['seminariciUcitIdno'], $predmet['prednasejiciUcitIdno'], $predmet['vyucovaciJazyky'], $predmet['rok']);
            // $ints = parseStringToIntegers($predmet['cviciciUcitIdno']);
            // echo $ints;
        }
    }
}

//1 prednaska, 2 cviceni, 3 seminar - proc jsem to sem psal?
function insertPredmetHodiny($pdo, $zkratka, $jednotekPrednasek, $jednotkaPrednasky, $jednotekCviceni, $jednotkaCviceni, $jednotekSeminare, $jednotkaSeminare){
    try {
        $stmtIDPredmet = $pdo->prepare("SELECT id FROM predmet WHERE zkratka='" . $zkratka . "';");
        $stmtIDPredmet->execute();
        $IdPredmet = $stmtIDPredmet->fetchColumn();

        if ($jednotkaPrednasky=='HOD/SEM') {
            $jednotkaPrednasky=1;
        } else $jednotkaPrednasky=2;
        
        if ($jednotkaCviceni=='HOD/SEM') {
            $jednotkaCviceni=1;
        } else $jednotkaCviceni=2;
        
        if ($jednotkaSeminare=='HOD/SEM') {
            $jednotkaSeminare=1;
        } else $jednotkaSeminare=2;
        

        //prednasky
        $query = "INSERT INTO predmet_hodiny (predmetid, pocetJednotekSeminar, jednotkaSeminarTypId, pocetJednotekPrednaska, jednotkaPrednaskaTypId, jednotkaCviceniTypId, pocetJednotekCviceni) VALUES (?, ?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$IdPredmet, $jednotekSeminare, $jednotkaSeminare, $jednotekPrednasek, $jednotkaPrednasky, $jednotkaCviceni, $jednotekCviceni]);

        echo nl2br("ÚSPĚŠNĚ importováno: seminář: " . $jednotekSeminare . ", přednáška: " . $jednotekPrednasek . ", cvičení: " . $jednotekCviceni . "\n");
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function getAktivniVerze($pdo) {
    $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// function gerRozvrhoveAkceLastYearKatedra($pdo){
//     $semestr= getSemestr($pdo);
//     $year = getYear($pdo) - 1;
//     $katedra =getKatedra($pdo);
//     $verze = getAktivniVerze($pdo);

//     $api_url = "https://stag-ws.utb.cz/ws/services/rest2/rozvrhy/getRozvrhByKatedra?semestr=" . $semestr . "&jenRozvrhoveAkce=true&outputFormat=JSON&katedra=" . $katedra . "&rok=" . $year;
//     $response = file_get_contents($api_url);
//     echo nl2br("URL API:" . $api_url);;

//     if ($response === FALSE) {
//         echo "error in connection";
//     }
//     else {
//         $data = json_decode($response, true);
//         if ($data === NULL) {
//             echo "Error decoding JSON response.";
//         }
// //        deletePredmetLast($pdo);
//         // foreach ($data['predmetKatedryFullInfo'] as $predmet) {
//         //     insertPredmetLast($pdo, $predmet['zkratka'], $predmet['nazev'], $predmet['cviciciUcitIdno'], $predmet['seminariciUcitIdno'], $predmet['prednasejiciUcitIdno'], $predmet['vyucovaciJazyky'], $predmet['rok']);
//         //     // $ints = parseStringToIntegers($predmet['cviciciUcitIdno']);
//         //     // echo $ints;
//         }
//     // }
// }

function gerRozvrhoveAkceLastYearKatedra($pdo) {
    $semestr = getSemestr($pdo);
    $year = getYear($pdo) - 1;
    $katedra = getKatedra($pdo);
    $verze = getAktivniVerze($pdo);

    $api_url = "https://stag-ws.utb.cz/ws/services/rest2/rozvrhy/getRozvrhByKatedra?semestr=$semestr&jenRozvrhoveAkce=true&outputFormat=JSON&katedra=$katedra&rok=$year";
    $response = file_get_contents($api_url);
    echo nl2br("\nURL API:" . $api_url) . "\n";

    if ($response === FALSE) {
        echo "Error in connection to STAG.";
        return;
    }

    $data = json_decode($response, true);
    if ($data === NULL || !isset($data['rozvrhovaAkce'])) {
        echo "Error decoding JSON or no data found.";
        return;
    }

    foreach ($data['rozvrhovaAkce'] as $ra) {
        $roakIdno = $ra['roakIdno'];
        $predmet = $ra['predmet'];
        $nazev = $ra['nazev'];
        $typ = strtoupper(substr($ra['typAkceZkr'], 0, 1)); // P/C/S
        $pocetHodin = $ra['pocetVyucHodin'];

        // Insert rozvrhova_akce
        $stmt = $pdo->prepare("INSERT INTO rozvrhova_akce (roakIdno, predmet_zkratka, nazev_predmetu, katedra, rok, semestr, typ_akce, typ_akce_zkr, pocet_vyuc_hodin, krouzky, IdVerze)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $roakIdno, $predmet, $nazev, $katedra, $year, $semestr,
            $ra['typAkce'], $ra['typAkceZkr'], $pocetHodin,
            $ra['krouzky'], $verze
        ]);
        echo nl2br("\n ÚSPĚŠNĚ importována rozvrhová akce: " . $predmet . ", nazev: " . $nazev . ", typ: " . $ra['typAkce']);
        // // Zpracování učitelů + podílu

            $ucitIds = explode(',', is_string($ra['vsichniUciteleUcitIdno']) ? $ra['vsichniUciteleUcitIdno'] : '');
            $podilyStr = $ra['vsichniUciteleJmenaTitulySPodily'];
            preg_match_all("/\((\d+)\)/", $podilyStr, $matches);
            $podily = $matches[1];

            foreach ($ucitIds as $index => $ucitIdno) {
                $ucitIdno = trim($ucitIdno);
                if (!is_numeric($ucitIdno) || $ucitIdno === '') continue; // přeskočí neplatné hodnoty

                $podil = isset($podily[$index]) ? floatval($podily[$index]) : 100;

                // Insert přímo s ucitIdno bez návaznosti na teachers.id
                $stmt = $pdo->prepare("INSERT INTO rozvrhova_akce_ucitel (roakIdno, ucitIdno, podil_na_vyuce, IdVerze)
                                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$roakIdno, $ucitIdno, $podil, $verze]);
                echo nl2br("\nImport do rozvrhova_akce_ucitel : ucitIdno: " . $ucitIdno . ", podíl: " . $podil);
            }
        // $ucitIds = explode(',', $ra['vsichniUciteleUcitIdno']);
        // $podilyStr = $ra['vsichniUciteleJmenaTitulySPodily'];
        // preg_match_all("/\((\d+)\)/", $podilyStr, $matches);
        // $podily = $matches[1];

        // foreach ($ucitIds as $index => $ucitIdno) {
        //     $stmt = $pdo->prepare("SELECT id FROM teachers WHERE ucitIdno = ?");
        //     $stmt->execute([trim($ucitIdno)]);
        //     $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        //     if (!$teacher) continue;
        //     $ucitelId = $teacher['id'];
        //     $podil = isset($podily[$index]) ? floatval($podily[$index]) : 100;

        //     // Insert učitel-akce
        //     $stmt = $pdo->prepare("INSERT INTO rozvrhova_akce_ucitel (roakIdno, ucitIdno, podil_na_vyuce, IdVerze)
        //                            VALUES (?, ?, ?, ?)");
        //     $stmt->execute([$roakIdno, $ucitelId, $podil, $verze]);
        // }
    }

    echo ("\nRozvrhové akce úspěšně zpracovány.");
}

function assignTeachersFromRozvrh($pdo) {
    $verze = getAktivniVerze($pdo);
    $rok = getYear($pdo) - 1;
    $semestr = getSemestr($pdo);

    $stmt = $pdo->prepare("
        SELECT ra.predmet_zkratka, ra.typ_akce_zkr, rau.ucitIdno, ra.pocet_vyuc_hodin, rau.podil_na_vyuce
        FROM rozvrhova_akce ra
        JOIN rozvrhova_akce_ucitel rau ON ra.roakIdno = rau.roakIdno
        WHERE ra.rok = ? AND ra.semestr = ?
    ");
    $stmt->execute([$rok, $semestr]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $zkratka = $row['predmet_zkratka'];
        $typ = strtoupper(substr($row['typ_akce_zkr'], 0, 1));
        $ucitIdno = $row['ucitIdno'];
        $hodin = $row['pocet_vyuc_hodin'] * ($row['podil_na_vyuce'] / 100.0);

        $data[$zkratka][$typ]['total'] = ($data[$zkratka][$typ]['total'] ?? 0) + $hodin;
        $data[$zkratka][$typ]['teachers'][$ucitIdno] = ($data[$zkratka][$typ]['teachers'][$ucitIdno] ?? 0) + $hodin;
    }

    $stmtPredmety = $pdo->prepare("SELECT id, zkratka FROM predmet WHERE IdVerze = ?");
    $stmtPredmety->execute([$verze]);
    $predmety = $stmtPredmety->fetchAll(PDO::FETCH_ASSOC);

    foreach ($predmety as $predmet) {
        $predmetId = $predmet['id'];
        $zkratka = $predmet['zkratka'];

        // Získání všech jazyků daného předmětu
        $stmtJazyky = $pdo->prepare("SELECT jazykid FROM vwPredmetJazyk WHERE predmetid = ?");
        $stmtJazyky->execute([$predmetId]);
        $jazykIds = $stmtJazyky->fetchAll(PDO::FETCH_COLUMN);

        if (empty($jazykIds)) {
            $jazykIds = [null]; // fallback pokud nemá žádný jazyk
        }

        // Hodinové jednotky
        $stmtHod = $pdo->prepare("SELECT pocetJednotekPrednaska, pocetJednotekCviceni, pocetJednotekSeminar 
                                  FROM predmet_hodiny WHERE predmetid = ?");
        $stmtHod->execute([$predmetId]);
        $hodiny = $stmtHod->fetch(PDO::FETCH_ASSOC);

        foreach (['P' => 'pocetJednotekPrednaska', 'C' => 'pocetJednotekCviceni', 'S' => 'pocetJednotekSeminar'] as $typ => $sloupec) {
            if (empty($hodiny[$sloupec])) continue;

            $hasTeacher = isset($data[$zkratka][$typ]);
            $info = $data[$zkratka][$typ] ?? null;
            $total = $info['total'] ?? 0;

            foreach ($jazykIds as $jazykId) {
                if ($hasTeacher) {
                    foreach ($info['teachers'] as $ucitIdno => $hodinUcitele) {
                        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE ucitIdno = ?");
                        $stmt->execute([$ucitIdno]);
                        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$teacher) continue;

                        $teacherId = $teacher['id'];
                        $podil = round(($hodinUcitele / $total) * 100, 2);

                        $stmt = $pdo->prepare("INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, podil, IdVerze, jazyk)
                                               VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$predmetId, $teacherId, $typ, $podil, $verze, $jazykId]);
                    }
                } else {
                    // Vytvořit prázdný záznam s jazykem
                    $stmt = $pdo->prepare("INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, podil, IdVerze, jazyk)
                                           VALUES (?, NULL, ?, 0, ?, ?)");
                    $stmt->execute([$predmetId, $typ, $verze, $jazykId]);
                }
            }
        }
    }

    echo "Učitelé (včetně prázdných záznamů) byli přiřazeni dle rozvrhu a jazyků.";
}

function getSetUcitIdnoExternista($pdo){
    
    $stmtPredmety = $pdo->prepare("SELECT cislo FROM seq_ucitIdnoExternista WHERE id=1");
    $stmtPredmety->execute();
    $cislo = $stmtPredmety->fetchColumn();//->fetchColumn();   
    $cislo = $cislo + 1;

    echo ("UcitIdno: " . -$cislo);

    $stmtUp = $pdo->prepare("UPDATE seq_ucitIdnoExternista SET cislo=? WHERE id=1");
    $stmtUp->execute([$cislo]);
    
    return $cislo;
}

