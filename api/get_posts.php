<?php
// api/get_posts.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$token = isset($_GET['token']) ? $_GET['token'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$categorySlug = isset($_GET['category']) ? $_GET['category'] : 'all'; // Kategori filtresi

$limit = 6;
$offset = ($page - 1) * $limit;
$user_id = 0;

// 1. Token Çözümleme
if ($token) {
    try {
        $stmt = $db->prepare("SELECT user_id FROM auth_tokens WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $user_id = $user['user_id'];
    } catch (Exception $e) {}
}

try {
    // 2. Kategori Filtresi için WHERE koşulu hazırlama
    $whereClause = "";
    $params = [];

    if ($categorySlug !== 'all') {
        // Eğer kategori seçiliyse, o kategoriye ait yazıları filtrele
        $whereClause = "WHERE c.slug = :categorySlug";
        $params[':categorySlug'] = $categorySlug;
    }

    // 3. Toplam Kayıt Sayısını Bul (Filtreye göre)
    $countQuery = "SELECT COUNT(*) as total FROM posts p LEFT JOIN categories c ON p.category_id = c.id $whereClause";
    $stmtCount = $db->prepare($countQuery);
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages == 0) $totalPages = 1;

    // 4. Verileri Çek
    $query = "SELECT 
                p.id, p.title, p.slug, p.meta_description, p.image_url, 
                p.created_at, p.views as view_count, p.like_count, p.comment_count,
                (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
                (SELECT COUNT(*) FROM user_likes WHERE post_id = p.id AND user_id = :uid1) as is_liked,
                (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = :uid2) as is_saved,
                c.name as category_name, c.slug as category_slug
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              $whereClause
              ORDER BY p.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Parametreleri bağla
    if ($categorySlug !== 'all') {
        $stmt->bindValue(':categorySlug', $categorySlug);
    }
    $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Veri işleme
    if ($posts) {
        foreach ($posts as &$post) {
            $post['is_liked'] = ($post['is_liked'] > 0);
            $post['is_saved'] = ($post['is_saved'] > 0);
            $post['save_count'] = (int)$post['save_count'];
            $post['like_count'] = (int)$post['like_count'];
            $post['view_count'] = (int)$post['view_count'];
            $post['comment_count'] = (int)$post['comment_count'];
        }
    }

    echo json_encode([
        "status" => "success", 
        "data" => $posts,
        "pagination" => [
            "current_page" => $page,
            "total_pages" => $totalPages,
            "total_records" => $totalRecords
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>