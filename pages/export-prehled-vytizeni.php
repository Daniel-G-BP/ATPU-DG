<?php
require_once '../vendor/autoload.php';
require_once '../includes/export-functions.php';

try {
    $filters = [
        'search'  => trim((string)(($_POST['q'] ?? $_GET['q']) ?? '')),
        'katedra' => trim((string)(($_POST['katedra'] ?? $_GET['katedra']) ?? '')),
        'fakulta' => trim((string)(($_POST['fakulta'] ?? $_GET['fakulta']) ?? '')),
    ];
    $teacherIds = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $teacherIds = is_array($_POST['teacher_ids'] ?? null) ? $_POST['teacher_ids'] : [];
    }

    $filePath = exportPrehledVytizeni($filters, $teacherIds);

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
    header("Content-Length: " . filesize($filePath));
    readfile($filePath);
    unlink($filePath);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo "Chyba při exportu přehledu vytíženosti: " . htmlspecialchars($e->getMessage());
}
