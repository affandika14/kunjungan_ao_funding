<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "formaofunding";

try {
    $conn = new PDO(
        "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Koneksi database gagal: " . $e->getMessage());
    echo "Koneksi database gagal. Silakan hubungi administrator.";
}