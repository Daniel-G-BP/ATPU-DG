<?php
include_once "config.php";

// function connectToDatabase() {
//     global $servername, $userDb, $passwordDB, $database;

//     try {
//         $pdo = new PDO("mysql:host=$servername;dbname=$database", $userDb, $passwordDB);
//         $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//         return $pdo;
//     } catch (PDOException $e) {
//         echo "Connection failed: " . $e->getMessage();
//         exit(); // exit script if connection fails
//     }
// }

function connectToDatabase() {
    $host = 'db';             
    $db   = 'atpu';           
    $user = 'user';           
    $pass = 'password';       
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Připojení k databázi selhalo: " . $e->getMessage());
    }
}


?>
