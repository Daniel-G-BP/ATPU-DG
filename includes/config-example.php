<?php
// Zkopirovat tento soubor jako config.php pro pripadne spusteni mimo Docker.
// Prihlasovaci udaje do IS/STAG se zde neukladaji.
// Uzivatel je zadava na strance Import dat a aplikace je drzi pouze v serverove session.

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
