<?php
require_once '../vendor/autoload.php';
require_once '../includes/export-functions.php';


try {
    if (!isset($_GET['teacherId']) || !is_numeric($_GET['teacherId'])) {
        throw new Exception("Neplatné ID učitele.");
    }

    $teacherId = (int)$_GET['teacherId'];
    $filePath = exportUvazekDoExcelu($teacherId);

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
    readfile($filePath);
    unlink($filePath);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo "Chyba při exportu: " . $e->getMessage();
}
