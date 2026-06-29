<?php
// api/get_post_by_slug.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

// 1. Slug parametresi gelmiş mi kontrol et
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Slug parametresi eksik."], JSON_UNESCAPED_UNICODE);
    exit();
}

$slug = $_GET['slug'];

try {
    // 2. Veritabanında bu slug'a sahip yazının ID'sini ara
    // Sadece ID çekmek yeterli çünkü Flutter uygulaması ID'yi alıp detay sayfasına gidecek.
    $query = "SELECT id FROM posts WHERE slug = :slug LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        // 3. Yazı bulundu, ID'yi döndür
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "data" => $post // Örnek çıktı: { "id": 65 }
        ]);
    } else {
        // 4. Yazı bulunamadı
        http_response_code(404); // Not Found
        echo json_encode([
            "status" => "error", 
            "message" => "Bu slug ile eşleşen yazı bulunamadı."
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // 5. Sunucu hatası
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Bir hata oluştu: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>