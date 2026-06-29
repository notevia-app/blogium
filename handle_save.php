<?php
// handle_save.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için giriş yapmalı veya kayıt olmalısınız.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
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
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz yazı kimliği.']);
    exit;
}

try {
    // 1. Kullanıcının bu yazıyı zaten kaydedip kaydetmediğini kontrol et
    $stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing_save = $stmt->fetch();

    if ($existing_save) {
        // 2. Kayıt varsa, kaydı geri çek (UNSAVE)
        $delete_stmt = $pdo->prepare("DELETE FROM saved_posts WHERE id = ?");
        $delete_stmt->execute([$existing_save['id']]);

        // Yazının save_count'ını azalt
        $pdo->prepare("UPDATE posts SET save_count = GREATEST(0, save_count - 1) WHERE id = ?")->execute([$post_id]);
        $action = 'unsaved';

    } else {
        // 3. Kayıt yoksa, kaydı ekle (SAVE)
        $insert_stmt = $pdo->prepare("INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)");
        $insert_stmt->execute([$user_id, $post_id]);

        // Yazının save_count'ını artır
        $pdo->prepare("UPDATE posts SET save_count = save_count + 1 WHERE id = ?")->execute([$post_id]);
        $action = 'saved';
    }

    // 4. Yeni kaydetme sayısını al ve JSON olarak döndür
    $count_stmt = $pdo->prepare("SELECT save_count FROM posts WHERE id = ?");
    $count_stmt->execute([$post_id]);
    $new_save_count = $count_stmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'new_save_count' => (int)$new_save_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Save/Unsave Error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.']);
}