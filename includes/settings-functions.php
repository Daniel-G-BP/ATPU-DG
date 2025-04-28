<?php
require_once 'dbh.inc.php';
$pdo = connectToDatabase();

function getTituly() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, zkratka FROM cistituly ORDER BY zkratka ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addTitul($zkratka) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO cistituly (zkratka) VALUES (:zkratka)");
    $stmt->execute([':zkratka' => $zkratka]);
}

function deleteTitul($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM cistituly WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

function getNastaveni() {
    global $pdo;
    $stmt = $pdo->query("SELECT IdNastaveni, HodnotaChar FROM nastaveni WHERE IdNastaveni IN (11, 12, 13) ORDER BY IdNastaveni ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateNastaveni($id, $hodnota) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE nastaveni SET HodnotaChar = :hodnota WHERE id = :id");
    $stmt->execute([':hodnota' => $hodnota, ':id' => $id]);
}
?>
