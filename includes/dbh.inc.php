<?php
function atpuDatabaseConfigValue(string $envName, string $default): string {
    $value = getenv($envName);
    if ($value !== false && $value !== '') {
        return (string)$value;
    }

    return $default;
}

function connectToDatabase() {
    $host = atpuDatabaseConfigValue('DB_HOST', 'db');
    $db   = atpuDatabaseConfigValue('DB_NAME', 'atpu');
    $user = atpuDatabaseConfigValue('DB_USER', 'user');
    $pass = atpuDatabaseConfigValue('DB_PASSWORD', 'password');
    $charset = atpuDatabaseConfigValue('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Spustit DB migrace při každém připojení (idempotentní)
        require_once __DIR__ . '/functions.php';
        runMigrations($pdo);
        return $pdo;
    } catch (\PDOException $e) {
        die("Připojení k databázi selhalo: " . $e->getMessage());
    }
}
