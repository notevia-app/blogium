<?php
// api/get_popular_posts.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

try {
    // SORGUNUN GÜNCELLENMESİ:
    // 1. SELECT kısmında p.views'i view_count olarak adlandırıyoruz.
    // 2. ORDER BY kısmında sıralamayı p.views'e göre yapıyoruz.
    $query = "SELECT 
                p.id, p.title, p.slug, p.meta_description, p.image_url, 
                p.created_at, 
                p.views as view_count, -- DÜZELTME 1
                p.like_count, 
                p.comment_count,
                c.name as category_name
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              ORDER BY p.views DESC -- DÜZELTME 2
              LIMIT 10"; // Popüler listesini 5 yerine 10 yapalım, daha zengin dursun.
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $posts = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $posts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>