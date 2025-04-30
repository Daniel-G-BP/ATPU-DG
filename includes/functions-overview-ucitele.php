<?php

function getTeachersData($pdo): array {

    $sql = "SELECT t.id AS id_ucitel, t.name, t.surname, k.telefon, k.email FROM teachers t
            LEFT JOIN kontakt k ON t.ucitIdno=k.idTeacher;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $teachers ?: [];
    } catch (PDOException $e) {
        error_log("Chyba při získávání učitelů: " . $e->getMessage());
        return [];
    }
}
