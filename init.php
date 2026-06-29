<?php
// init.php - Sitenin başlangıç dosyası (Periyodik Yenileme Aktif)

// 1. Oturumu her zaman başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Veritabanı bağlantısını dahil et
require_once __DIR__ . '/adminpanel/includes/db.php';

// 3. Otomatik giriş kontrolünü yap (Beni Hatırla)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me_token'])) {
    try {
        $token = $_COOKIE['remember_me_token'];

        $stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token = ? AND expires > NOW()");
        $stmt->execute([$token]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        // Eğer veritabanında geçerli bir jeton bulunduysa...
        if ($auth_token) {
            
            // --- YENİ EKLENDİ: PERİYODİK YENİLEME MANTIĞI ---
            // Kullanıcı aktif olduğu için jetonun ömrünü 30 gün daha uzatalım.

            // a) Yeni son kullanma tarihini belirle (şimdiki zamandan 30 gün sonrası).
            $new_expires = new DateTime('+30 days');

            // b) Veritabanındaki son kullanma tarihini güncelle.
            $update_stmt = $pdo->prepare("UPDATE auth_tokens SET expires = ? WHERE token = ?");
            $update_stmt->execute([$new_expires->format('Y-m-d H:i:s'), $token]);

            // c) Tarayıcıdaki çerezin son kullanma tarihini de güncelle.
            // Bu, tarayıcının çerezi erken silmesini engeller.
            setcookie('remember_me_token', $token, [
                'expires' => $new_expires->getTimestamp(),
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            // --- YENİLEME MANTIĞI BİTİŞ ---


            // Jeton geçerli olduğu için kullanıcı bilgilerini çek ve oturumu başlat.
            // Bu kısım aynı kalıyor.
            $user_stmt = $pdo->prepare("SELECT id, username, email FROM blog_users WHERE id = ?");
            $user_stmt->execute([$auth_token['user_id']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
            } else {
                // Kullanıcı veritabanında bulunamazsa (silinmişse), çerezi temizle.
                setcookie('remember_me_token', '', time() - 3600, '/');
            }
        }
    } catch (PDOException $e) {
        error_log('Otomatik giriş/yenileme hatası: ' . $e->getMessage());
    }
}