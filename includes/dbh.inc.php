<?php
include_once "config.php";

function connectToDatabase() {
    global $servername, $user, $passwordDB, $database;

    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$database", $user, $passwordDB);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        exit(); // exit script if connection fails
    }
}

?>
