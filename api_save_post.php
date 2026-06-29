<?php
// api_save_post.php
require_once __DIR__ . '/adminpanel/includes/db.php'; // Veritabanı bağlantı yolun
session_start();

header('Content-Type: application/json');

// 1. Giriş Kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

if (!$post_id) {
    echo json_encode(['status' => 'error', 'message' => 'Post ID eksik']);
    exit;
}

try {
    // 2. Önce bu kullanıcı bu yazıyı kaydetmiş mi diye bakıyoruz
    $check = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
    $check->execute([$user_id, $post_id]);
    
    if ($check->rowCount() > 0) {
        // --- DURUM 1: Zaten kayıtlı -> SİLİYORUZ (İçi Boşalacak) ---
        $delete = $pdo->prepare("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?");
        $delete->execute([$user_id, $post_id]);
        
        // Post tablosundaki sayacı düşür (Opsiyonel)
        $pdo->prepare("UPDATE posts SET save_count = save_count - 1 WHERE id = ?")->execute([$post_id]);
        
        echo json_encode(['status' => 'success', 'action' => 'removed']); // 'removed' cevabı döndü
    } else {
        // --- DURUM 2: Kayıtlı değil -> EKLİYORUZ (İçi Dolacak) ---
        $insert = $pdo->prepare("INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)");
        $insert->execute([$user_id, $post_id]);
        
        // Post tablosundaki sayacı artır (Opsiyonel)
        $pdo->prepare("UPDATE posts SET save_count = save_count + 1 WHERE id = ?")->execute([$post_id]);
        
        echo json_encode(['status' => 'success', 'action' => 'saved']); // 'saved' cevabı döndü
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>