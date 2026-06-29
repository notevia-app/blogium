<?php
// api/db.php

// GÜVENLİK KONTROLÜ KODU BURADAN KALDIRILDI

// Hata raporlamayı açalım (geliştirme aşamasında faydalıdır)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'blo217mnet_emin');
define('DB_PASS', '+TAeCANSR0n{K~{%');
define('DB_NAME', 'blo217mnet_admin');

// Veritabanı bağlantısını oluşturma
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Veritabanı bağlantısı başarısız: " . $e->getMessage()
    ]);
    exit();
}
?>