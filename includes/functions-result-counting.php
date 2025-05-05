<?php
require_once 'dbh.inc.php';
$pdo = connectToDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $typ = $_POST['typ'] ?? null;
    $jazyk = $_POST['jazyk'] ?? null;
    $ucitel = $_POST['ucitel'] ?? null;
    $podil = $_POST['podil'] ?? null;
    $max_studentu = $_POST['max_studentu'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$id || !$action) {
        // Nesmí se nic vypsat, jinak header nefunguje
        header("Location: ../pages/result-counting.php?error=missing");
        exit;
    }

    switch ($action) {
        case 'update':
            if ($typ !== 'C') {
                $max_studentu = null;
            }

            $stmt = $pdo->prepare("
                UPDATE ucitelpredmetprirazeni
                SET typ = :typ,
                    jazyk = :jazyk,
                    teacherid = :ucitel,
                    podil = :podil,
                    max_pocet_studentu = :max_studentu
                WHERE id = :id
            ");
            $stmt->execute([
                ':typ' => $typ,
                ':jazyk' => $jazyk,
                ':ucitel' => $ucitel,
                ':podil' => $podil,
                ':max_studentu' => $max_studentu,
                ':id' => $id
            ]);

            header("Location: ../pages/result-counting.php?updated=$id");
            exit;

        case 'odebrat':
            $pdo->prepare("UPDATE ucitelpredmetprirazeni SET teacherid = NULL WHERE id = ?")->execute([$id]);
            header("Location: ../pages/result-counting.php?cleared=$id");
            exit;

        case 'smazat':
            $pdo->prepare("DELETE FROM ucitelpredmetprirazeni WHERE id = ?")->execute([$id]);
            header("Location: ../pages/result-counting.php?deleted=$id");
            exit;

        case 'kopirovat':
            $stmt = $pdo->prepare("SELECT * FROM ucitelpredmetprirazeni WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                unset($row['id']);
                $columns = implode(", ", array_keys($row));
                $placeholders = implode(", ", array_fill(0, count($row), '?'));

                $pdo->prepare("INSERT INTO ucitelpredmetprirazeni ($columns) VALUES ($placeholders)")
                    ->execute(array_values($row));
            }

            header("Location: ../pages/result-counting.php?copied=$id");
            exit;

        default:
            header("Location: ../pages/result-counting.php?error=unknown_action");
            exit;
    }
}



// <!-- <?php
// require_once 'dbh.inc.php';
// $pdo = connectToDatabase();

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {

//     // === UPDATE ===
//     if (isset($_POST['update'])) {
//         $id = array_key_first($_POST['update']);

//         $typ = $_POST['typ'][$id] ?? '';
//         $jazyk = $_POST['jazyk'][$id] ?? '';
//         $podil = $_POST['podil'][$id] ?? null;
//         $ucitel = $_POST['ucitel'][$id] ?? null;

//         $stmt = $pdo->prepare("
//             UPDATE ucitelpredmetprirazeni
//             SET typ = :typ,
//                 jazyk = :jazyk,
//                 podil = :podil,
//                 teacherid = :ucitel
//             WHERE id = :id
//         ");
//         $stmt->execute([
//             ':typ' => $typ,
//             ':jazyk' => $jazyk,
//             ':podil' => $podil,
//             ':ucitel' => $ucitel,
//             ':id' => $id
//         ]);

//         header("Location: ../pages/result-counting.php?updated=$id");
//         exit;
//     }

//     // === SMAZAT ===
//     if (isset($_POST['smazat'])) {
//         $id = array_key_first($_POST['smazat']);
//         $pdo->prepare("DELETE FROM ucitelpredmetprirazeni WHERE id = ?")->execute([$id]);
//         header("Location: ../pages/result-counting.php?deleted=$id");
//         exit;
//     }

//     // === ODEBRAT UČITELE ===
//     if (isset($_POST['odebrat'])) {
//         $id = array_key_first($_POST['odebrat']);
//         $pdo->prepare("UPDATE ucitelpredmetprirazeni SET teacherid = NULL WHERE id = ?")->execute([$id]);
//         header("Location: ../pages/result-counting.php?cleared=$id");
//         exit;
//     }

//     // === KOPÍROVAT ===
//     if (isset($_POST['kopirovat'])) {
//         $id = array_key_first($_POST['kopirovat']);
//         $stmt = $pdo->prepare("SELECT * FROM ucitelpredmetprirazeni WHERE id = ?");
//         $stmt->execute([$id]);
//         $row = $stmt->fetch(PDO::FETCH_ASSOC);

//         if ($row) {
//             unset($row['id']);
//             $columns = implode(", ", array_keys($row));
//             $placeholders = implode(", ", array_fill(0, count($row), '?'));
//             $pdo->prepare("INSERT INTO ucitelpredmetprirazeni ($columns) VALUES ($placeholders)")
//                 ->execute(array_values($row));
//         }

//         header("Location: ../pages/result-counting.php?copied=$id");
//         exit;
//     }
// }
// ?> 
