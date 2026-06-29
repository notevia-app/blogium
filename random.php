<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Rastgele bir yazıya yönlendirme script'i.
 * Bu dosya, veritabanından rastgele bir yazı seçer ve kullanıcıyı o yazının sayfasına yönlendirir.
 */

// Veritabanı bağlantısını dahil et
require_once __DIR__ . '/adminpanel/includes/db.php';

try {
    // Veritabanından rastgele bir yazının sadece 'slug' bilgisini çek.
    // ORDER BY RAND() LIMIT 1 bu iş için en verimli yöntemdir.
    $stmt = $pdo->query("SELECT slug FROM posts ORDER BY RAND() LIMIT 1");
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    // Eğer bir yazı bulunduysa
    if ($post && !empty($post['slug'])) {
        // Yönlendirilecek post'un URL'sini oluştur
        $url = '/yazi/' . urlencode($post['slug']);
    } else {
        // Eğer veritabanında hiç yazı yoksa veya bir hata olursa, anasayfaya yönlendir
        $url = '/index.php';
    }

} catch (PDOException $e) {
    // Veritabanı hatası durumunda anasayfaya yönlendir ve hatayı logla
    error_log("Rastgele yazı çekme hatası: " . $e->getMessage());
    $url = '/index.php';
}

// Kullanıcıyı bulunan URL'ye yönlendir
header("Location: " . $url);
// Yönlendirme sonrası script'in çalışmasını durdur
exit;