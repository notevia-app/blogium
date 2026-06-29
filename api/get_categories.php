<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

try {
    $query = "SELECT id, name, slug FROM categories ORDER BY name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $categories = $stmt->fetchAll();

    if ($categories) {
        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $categories], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "not_found", "message" => "Hiç kategori bulunamadı."], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>