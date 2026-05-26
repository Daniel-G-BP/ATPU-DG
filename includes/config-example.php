<?php
// Zkopírovat tento soubor jako config.php a vyplnit záložní hodnoty.
// Přihlašovací údaje do STAGu se primárně načítají z environment variables
// nastavených v docker-compose.yml (STAG_USERNAME, STAG_PASSWORD).
// Záložní hodnoty níže se použijí pouze při spuštění mimo Docker.

//login do STAGu
$username = getenv('STAG_USERNAME') ?: "prihlasovaciJmeno";
$password = getenv('STAG_PASSWORD') ?: "heslo";

// login DB bez dockeru

//$servername = "localhost"; //pokud bez dockeru, toto tento řádek odkomentovat 
$user = "root";
$passwordDB = "hesloDoDatabaze";
$database = "atpu";

//pro docker

$host = 'db';             
$db   = 'atpu';           
$user = 'user';           
$pass = 'password';       
$charset = 'utf8mb4';
