<?php
require_once __DIR__ . '/functions.php';

function getTeachersData($pdo): array {
    $idVerze = getAktivniVerze($pdo);

    // BUG FIX: JOIN byl přes t.ucitIdno=k.idTeacher (špatně) – kontakt.idTeacher je FK na teachers.id
    // BUG FIX: chyběl filtr IdVerze – zobrazovali se učitelé ze všech verzí
    $sql = "
        SELECT t.id AS id_ucitel, t.name, t.surname, k.telefon, k.email
        FROM teachers t
        LEFT JOIN kontakt k ON t.id = k.idTeacher AND k.idVerze = :idVerze
        WHERE t.IdVerze = :idVerze2
          AND t.ucitIdno != 0
        ORDER BY t.surname, t.name
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':idVerze' => $idVerze, ':idVerze2' => $idVerze]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $teachers ?: [];
    } catch (PDOException $e) {
        error_log("Chyba při získávání učitelů: " . $e->getMessage());
        return [];
    }
}
