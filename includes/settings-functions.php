<?php
require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/workload-functions.php';
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

function getUvazekKoeficienty(): array {
    global $pdo;
    return getWorkloadCoefficients($pdo, getAktivniVerze($pdo));
}

function saveUvazekKoeficienty(array $values): void {
    global $pdo;
    saveWorkloadCoefficients($pdo, getAktivniVerze($pdo), $values);
}

function getPretizeniProcent(): float {
    global $pdo;
    return getWorkloadOverloadThreshold($pdo, getAktivniVerze($pdo));
}

function savePretizeniProcent($value): void {
    global $pdo;
    saveWorkloadOverloadThreshold($pdo, getAktivniVerze($pdo), $value);
}

function getPlnyUvazekPB(): float {
    global $pdo;
    return getWorkloadFullTimePoints($pdo, getAktivniVerze($pdo));
}

function savePlnyUvazekPB($value): void {
    global $pdo;
    saveWorkloadFullTimePoints($pdo, getAktivniVerze($pdo), $value);
}
