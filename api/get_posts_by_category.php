<?php
// api/get_posts_by_category.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    http_response_code(400); 
    echo json_encode(["status" => "error", "message" => "Slug eksik."], JSON_UNESCAPED_UNICODE);
    exit();
}

$categorySlug = $_GET['slug'];
$token = isset($_GET['token']) ? $_GET['token'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

$user_id = 0;

// 1. Token varsa User ID'yi bul
if ($token) {
    try {
        $stmt = $db->prepare("SELECT user_id FROM auth_tokens WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $user_id = $user['user_id'];
    } catch (Exception $e) {}
}

try {
    // 2. Kategori ID'sini bul
    $catQuery = "SELECT id FROM categories WHERE slug = :slug";
    $catStmt = $db->prepare($catQuery);
    $catStmt->bindParam(':slug', $categorySlug);
    $catStmt->execute();
    $category = $catStmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        http_response_code(404);
        echo json_encode(["status" => "not_found", "message" => "Kategori bulunamadı."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $categoryId = $category['id'];

    // 3. Toplam Kayıt Sayısını Bul (Bu kategori için)
    $countQuery = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE category_id = :catId");
    $countQuery->execute([':catId' => $categoryId]);
    $totalRecords = $countQuery->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages == 0) $totalPages = 1;

    // 4. Gönderileri Çek (Sayfalı)
    $query = "SELECT 
                p.id, p.title, p.slug, p.meta_description, p.image_url, 
                p.created_at, p.views as view_count, p.like_count, p.comment_count,
                
                (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
                (SELECT COUNT(*) FROM user_likes WHERE post_id = p.id AND user_id = :uid1) as is_liked,
                (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = :uid2) as is_saved,
                
                -- Kategori tablosunu joinlemeye gerek yok, ID elimizde ama isim lazımsa joinleyebiliriz.
                -- Frontend zaten kategori adını biliyor ama standart olsun diye ekleyelim.
                (SELECT name FROM categories WHERE id = p.category_id) as category_name

              FROM posts p
              WHERE p.category_id = :category_id
              ORDER BY p.created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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