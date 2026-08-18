<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$token = atpuNormalizeImportLogToken($_GET['token'] ?? null);
if ($token === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = atpuFindImportLogByToken($token);
if ($path === null) {
    echo json_encode([
        'exists' => false,
        'lastLine' => null,
        'tail' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tail = atpuReadLastLogLines($path, 10);

echo json_encode([
    'exists' => true,
    'file' => basename($path),
    'lastLine' => $tail ? end($tail) : null,
    'tail' => $tail,
    'mtime' => filemtime($path),
], JSON_UNESCAPED_UNICODE);
