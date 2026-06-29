<?php
// handle_like.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sadece giriş yapmış kullanıcılar işlem yapabilir
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek metodu.']);
    exit;
}

require_once __DIR__ . '/adminpanel/includes/db.php';
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı bağlantı hatası.']);
    exit;
}

$post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$post_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz yazı kimliği.']);
    exit;
}

try {
    // 1. Kullanıcının bu yazıyı zaten beğenip beğenmediğini kontrol et
    $stmt = $pdo->prepare("SELECT id FROM user_likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing_like = $stmt->fetch();

    if ($existing_like) {
        // 2. Beğeni varsa, beğeniyi geri çek (UNLIKE)
        $delete_stmt = $pdo->prepare("DELETE FROM user_likes WHERE id = ?");
        $delete_stmt->execute([$existing_like['id']]);

        // Yazının like_count'ını azalt
        $update_stmt = $pdo->prepare("UPDATE posts SET like_count = GREATEST(0, like_count - 1) WHERE id = ?");
        $update_stmt->execute([$post_id]);

        $action = 'unliked';
    } else {
        // 3. Beğeni yoksa, beğeniyi ekle (LIKE)
        $insert_stmt = $pdo->prepare("INSERT INTO user_likes (user_id, post_id) VALUES (?, ?)");
        $insert_stmt->execute([$user_id, $post_id]);

        // Yazının like_count'ını artır
        $update_stmt = $pdo->prepare("UPDATE posts SET like_count = like_count + 1 WHERE id = ?");
        $update_stmt->execute([$post_id]);

        $action = 'liked';
    }

    // 4. Yeni beğeni sayısını al ve JSON olarak döndür
    $count_stmt = $pdo->prepare("SELECT like_count FROM posts WHERE id = ?");
    $count_stmt->execute([$post_id]);
    $new_like_count = $count_stmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'new_like_count' => (int)$new_like_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Like/Unlike Error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.']);
}