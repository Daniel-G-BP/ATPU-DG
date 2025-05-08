<?php
require_once 'dbh.inc.php';
$pdo = connectToDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['pocetStudentu'] as $id => $pocet) {
        $stmt = $pdo->prepare("UPDATE rocniky_studijniho_programu SET pocetStudentu = ? WHERE id = ?");
        $stmt->execute([$pocet !== '' ? (int)$pocet : null, $id]);
    }
    header("Location: ../pages/edit_rocniky.php?success=1");
    exit();
}

// Dotaz pro načtení dat
$query = "
    SELECT 
        rsp.id,
        sp.nazev AS nazev_programu,
        rsp.rocnik,
        rsp.jazyk,
        rsp.idForma,
        rsp.pocetStudentu,
        sp.typ
    FROM 
        rocniky_studijniho_programu rsp
    LEFT JOIN 
        studijniprogram sp ON rsp.stprIdno = sp.stprIdno
    ORDER BY 
        rsp.jazyk, sp.nazev, rsp.idForma, rsp.rocnik, rsp.rocnik
";

$stmt = $pdo->query($query);
$rocnikyData = $stmt->fetchAll(PDO::FETCH_ASSOC);


function prelozJazyk($jazyk) {
    return $jazyk == 1 ? 'Čeština' : ($jazyk == 2 ? 'Angličtina' : 'Neznámý');
}

function prelozFormu($forma) {
    return $forma == 1 ? 'Prezenční' : ($forma == 2 ? 'Kombinovaná' : 'Neznámá');
}

function prelozTyp($typ) {
    switch ($typ) {
        case 1: return 'Bakalářský';
        case 2: return 'Navazující';
        case 3: return 'Doktorský';
        default: return 'Neznámý';
    }
}

