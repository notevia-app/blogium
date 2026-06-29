<?php
// logout.php

// Oturumu başlatmak, veritabanına bağlanmak ve diğer temel işlemleri
// halletmesi için merkezi başlangıç dosyamızı çağırıyoruz.
require_once __DIR__ . '/init.php';

// 1. "Beni Hatırla" Jetonunu Veritabanından Sil
if (isset($_COOKIE['remember_me_token'])) {
    $token = $_COOKIE['remember_me_token'];

    try {
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log('Çıkış sırasında token silme hatası: ' . $e->getMessage());
    }
}

// 2. Tarayıcıdaki Çerezi Sil
setcookie('remember_me_token', '', [
    'expires' => time() - 3600, // 1 saat öncesi
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 3. PHP Oturumunu Tamamen Sonlandır
$_SESSION = [];
session_destroy();

// 4. Kullanıcıyı Yönlendir (GÜNCELLENDİ)
// Eğer kullanıcı bir sayfadan geldiyse (Referer varsa) oraya geri gönder.
// Yoksa anasayfaya gönder.
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: /');
}
exit;
?>