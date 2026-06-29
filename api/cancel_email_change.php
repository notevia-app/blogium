<?php
// api/cancel_email_change.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db.php'; 

// DÜZELTME: auth_helper.php'yi çağırmak yerine, fonksiyonu doğrudan bu dosyaya ekliyoruz.
function getUserDataFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.username, u.email, u.password FROM auth_tokens t JOIN blog_users u ON t.user_id = u.id WHERE t.token = :token AND t.expires > NOW() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Geçersiz istek metodu."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Token eksik."]);
    exit();
}

$userData = getUserDataFromToken($db, $data->token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Kimlik doğrulanamadı."]);
    exit();
}

$userId = $userData['user_id'];

try {
    $updateStmt = $db->prepare(
        "UPDATE blog_users 
         SET new_email = NULL, email_change_token = NULL, email_change_expiry = NULL 
         WHERE id = :user_id"
    );
    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "E-posta değişikliği talebi iptal edildi."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "İşlem sırasında bir hata oluştu."]);
    }

} catch (PDOException $e) {
    error_log("Cancel email change error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>