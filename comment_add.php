<?php
require 'adminpanel/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($post_id && $name && $content) {
        // 1. Yorumu veritabanına ekle
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, name, content, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$post_id, $name, $content]);

        // 2. post tablosundaki comment_count değerini 1 artır
        $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?")->execute([$post_id]);

        // 3. Başarılı yönlendirme
        header("Location: /" . slugifyPost($pdo, $post_id) . "?success=1#yorumlar");
        exit;
    } else {
        die("Eksik bilgi gönderildi.");
    }
} else {
    die("Geçersiz istek.");
}

// Slug'ı bulmak için yardımcı fonksiyon
function slugifyPost($pdo, $id) {
    $stmt = $pdo->prepare("SELECT slug FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? $row['slug'] : '404';
}
