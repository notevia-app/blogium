<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

try {
    // MySQL'de rastgele satır seçmek için ORDER BY RAND() kullanılır.
    // Not: Bu yöntem çok büyük tablolarda (yüz binlerce satır) yavaş olabilir.
    // Ancak sizin mevcut tablonuz için gayet uygundur.
    $query = "SELECT 
                p.*, 
                c.name as category_name,
                c.slug as category_slug
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              ORDER BY RAND() 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $post = $stmt->fetch();

    if ($post) {
         // Okunma sayısını artırmayı burada da yapabiliriz, isteğe bağlı.
        $updateQuery = "UPDATE posts SET view_count = view_count + 1 WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':id', $post['id'], PDO::PARAM_INT);
        $updateStmt->execute();

        // Etiketleri diziye dönüştür
        $post['tags'] = !empty($post['tags']) ? array_map('trim', explode(',', $post['tags'])) : [];
        
        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $post], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "not_found", "message" => "Gönderi bulunamadı."], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>