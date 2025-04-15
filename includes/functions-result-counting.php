<?php
require_once 'dbh.inc.php';
$pdo = connectToDatabase();

function getAktivniVerze($pdo) {
    $stmt = $pdo->prepare("SELECT Hodnota FROM nastaveni WHERE Nazev = 'AktivniVerze'");
    $stmt->execute();
    return $stmt->fetchColumn();
}

$verze = getAktivniVerze($pdo);

// UPDATE záznamu
if (isset($_POST['update'])) {
    foreach ($_POST['update'] as $assignmentId => $v) {
        $typ = $_POST['typ'][$assignmentId];
        $ucitIdno = $_POST['ucitel'][$assignmentId];
        $jazyk = $_POST['jazyk'][$assignmentId];
        $podil = $_POST['podil'][$assignmentId] ?? 100;

        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE ucitIdno = ?");
        $stmt->execute([$ucitIdno]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            $teacherid = $teacher['id'];
            $stmt = $pdo->prepare("UPDATE ucitelpredmetprirazeni
                                   SET teacherid = ?, typ = ?, podil = ?, jazyk = ?
                                   WHERE id = ? AND IdVerze = ?");
            $stmt->execute([$teacherid, $typ, $podil, $jazyk, $assignmentId, $verze]);
        }
    }
}

// ODEBRAT (nulovat učitele)
if (isset($_POST['delete'])) {
    foreach ($_POST['delete'] as $assignmentId => $v) {
        $stmt = $pdo->prepare("UPDATE ucitelpredmetprirazeni
                               SET teacherid = NULL, podil = 0
                               WHERE id = ? AND IdVerze = ?");
        $stmt->execute([$assignmentId, $verze]);
    }
}

// SMAZAT záznam
if (isset($_POST['smazat'])) {
    foreach ($_POST['smazat'] as $assignmentId => $v) {
        $stmt = $pdo->prepare("DELETE FROM ucitelpredmetprirazeni WHERE id = ? AND IdVerze = ?");
        $stmt->execute([$assignmentId, $verze]);
    }
}

// KOPÍROVAT záznam bez učitele
if (isset($_POST['kopirovat'])) {
    foreach ($_POST['kopirovat'] as $assignmentId => $v) {
        $typ = $_POST['typ'][$assignmentId];
        $jazyk = $_POST['jazyk'][$assignmentId];
        $stmt = $pdo->prepare("SELECT predmetid FROM ucitelpredmetprirazeni WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $predmetid = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO ucitelpredmetprirazeni (predmetid, teacherid, typ, podil, IdVerze, jazyk)
                               VALUES (?, NULL, ?, 0, ?, ?)");
        $stmt->execute([$predmetid, $typ, $verze, $jazyk]);
    }
}

header("Location: ../pages/result-counting.php?success=1");
exit;
?>