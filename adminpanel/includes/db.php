<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: /adminpanel");
    exit;
}

// *** ZAMAN DİLİMİ AYARI (ÇÖZÜM) ***
// Sunucu ve veritabanı saatleri arasındaki farktan kaynaklanan sorunları önler.
// Bu satır, tüm date() ve time() fonksiyonlarının İstanbul saatine göre çalışmasını sağlar.
date_default_timezone_set('Europe/Istanbul');


$host = 'localhost';
$db = 'blo217mnet_admin';
$user = 'blo217mnet_emin';
$pass = '+TAeCANSR0n{K~{%';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Üretim ortamında kullanıcıya detaylı hata göstermek yerine loglamak daha iyidir.
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    die("Sistemde bir sorun oluştu. Lütfen daha sonra tekrar deneyin.");
}