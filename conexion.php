<?php
$host = "localhost";
$port = "5432";
$dbname = "proyecto2";
$user = "postgres";
$password = "dumbo";

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
} catch (PDOException $e) {
    die("Error crítico de conexión: " . $e->getMessage());
}
?>