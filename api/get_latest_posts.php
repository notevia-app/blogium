<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

try {
    // SORGUNUN GÜNCELLENMESİ: Post modeli için gerekli tüm alanları seçiyoruz.
    $query = "SELECT 
                p.id, p.title, p.slug, p.meta_description, p.image_url, 
                p.created_at, p.view_count, p.like_count, p.comment_count,
                c.name as category_name
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              ORDER BY p.created_at DESC 
              LIMIT 5";
    
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