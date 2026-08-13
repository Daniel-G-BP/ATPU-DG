<?php
require_once '../vendor/autoload.php';
require_once '../includes/export-functions.php';

try {
    $filters = [
        'katedra' => trim((string)($_GET['katedra'] ?? '')),
        'fakulta' => trim((string)($_GET['fakulta'] ?? '')),
        'semestr' => trim((string)($_GET['semestr'] ?? '')),
        'zkratka' => trim((string)($_GET['zkratka'] ?? '')),
        'nazev'   => trim((string)($_GET['nazev'] ?? '')),
    ];

    $filePath = exportNepokrytePredmety($filters);

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
    header("Content-Length: " . filesize($filePath));
    readfile($filePath);
    unlink($filePath);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo "Chyba při exportu nepokryté výuky: " . htmlspecialchars($e->getMessage());
}
