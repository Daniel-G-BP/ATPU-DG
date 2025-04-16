<?php 
//vyplnit a zmenit soubor na config.php

//login do STAGu
$username = "prihlasovaciJmeno";
$password = "heslo";

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