<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
//    $id = $_POST["id"];
    $name = $_POST["name"];
    $surname = $_POST["surname"];

    echo htmlspecialchars($name);

    try {
        require_once "dbh.inc.php";

        $query = "INSERT INTO teachers (name, surname) VALUES ( ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $surname]);

        // Close statement and database connection properly
        $stmt = null;
        $pdo = null;
        echo "Inserted succesfully";
        die(); // Optional: stop script execution
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
}
?>
