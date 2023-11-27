<?php
require_once 'functions.php';
// Connexion a la base de donnees mysql
try{
    $pdo = new PDO('mysql:host=localhost;dbname=keyword_db;charset=utf8', 'root', '');
} catch(Exception $e){
    retour_json('400', false, "database connexion failed", null);
}

?>