<?php
// --- OTURUM SÜRESİ AYARI (1 SAAT) ---
ini_set('session.gc_maxlifetime', 3600); // Sunucu tarafında oturumu 1 saat (3600 sn) tut
session_set_cookie_params(3600); // Tarayıcı çerezini 1 saat tut

session_start();

// Eğer son işlemden bu yana 1 saat (3600 saniye) geçtiyse oturumu kapat
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();     // Değişkenleri temizle
    session_destroy();   // Oturumu yok et
    header("Location: index.php?timeout=1"); // Giriş sayfasına yönlendir
    exit;
}

// Son işlem zamanını şu an olarak güncelle
$_SESSION['LAST_ACTIVITY'] = time();
// ------------------------------------
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /adminpanel");
    exit;
}

require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);

    // Slug'ı küçük harf yap, baştaki ve sondaki boşlukları temizle
    $slug = strtolower(trim($slug));

    // Boşlukları "-" yap (örneğin: Yapay Zeka → yapay-zeka)
    $slug = preg_replace('/\s+/', '-', $slug);

    // Geçerli mi kontrol et
    if ($name && $slug) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
    }
}

header("Location: categories.php");
exit;
