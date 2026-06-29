<?php
/**
 * Menü Güncelleme İşlemi
 */
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
    header("HTTP/1.1 403 Forbidden");
    exit;
}

require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['title'], $_POST['url'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);

    if ($id && !empty($title) && !empty($url)) {
        try {
            $stmt = $pdo->prepare("UPDATE header_menu SET title = ?, url = ? WHERE id = ?");
            $stmt->execute([$title, $url, $id]);
            $_SESSION['message'] = 'Menü başarıyla güncellendi.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Güncelleme sırasında bir veritabanı hatası oluştu.';
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'Lütfen tüm alanları doldurun.';
        $_SESSION['message_type'] = 'error';
    }
} else {
    $_SESSION['message'] = 'Geçersiz istek.';
    $_SESSION['message_type'] = 'error';
}

header("Location: manage_menu.php");
exit;