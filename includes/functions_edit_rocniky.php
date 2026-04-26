<?php
require_once 'dbh.inc.php';
require_once 'functions.php';

$pdo = connectToDatabase();
$idVerze = getAktivniVerze($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['pocetStudentu']) && is_array($_POST['pocetStudentu'])) {
        foreach ($_POST['pocetStudentu'] as $id => $pocet) {
            $stmt = $pdo->prepare("
                UPDATE rocniky_studijniho_programu
                SET pocetStudentu = ?
                WHERE id = ? AND idVerze = ?
            ");
            $stmt->execute([
                $pocet !== '' ? (int)$pocet : null,
                (int)$id,
                $idVerze
            ]);
        }
    }

    header("Location: ../pages/edit_rocniky.php?success=1");
    exit();
}

$query = "
    SELECT 
        rsp.id,
        rsp.stprIdno,
        sp.nazev AS nazev_programu,
        rsp.rocnik,
        rsp.jazyk,
        rsp.idForma,
        rsp.pocetStudentu,
        sp.typ
    FROM rocniky_studijniho_programu rsp
    LEFT JOIN studijniprogram sp
        ON rsp.stprIdno = sp.stprIdno
       AND sp.IdVerze = rsp.idVerze
    WHERE rsp.idVerze = ?
    ORDER BY rsp.jazyk, sp.nazev, rsp.idForma, rsp.rocnik
";

$stmt = $pdo->prepare($query);
$stmt->execute([$idVerze]);
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
