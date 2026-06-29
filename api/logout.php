<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Sadece POST metodu kabul edilir."], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token) || empty($data->token)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Token belirtilmedi."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Token'ı auth_tokens tablosundan sil
    $query = "DELETE FROM auth_tokens WHERE token = :token";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $data->token);
    
    if ($stmt->execute()) {
        // Token silinmiş olsa da olmasa da başarılı bir çıkış mesajı döndürmek daha iyidir.
        // Çünkü token zaten geçersizse, kullanıcı için bir sorun teşkil etmez.
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Başarıyla çıkış yapıldı."], JSON_UNESCAPED_UNICODE);
    } else {
        // Bu genellikle bir veritabanı hatası durumunda olur
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Çıkış yapılırken bir hata oluştu."], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>