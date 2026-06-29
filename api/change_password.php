<?php
// api/change_password.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db.php';

// Token doğrulama fonksiyonunu bu dosyanın içine alıyoruz
function getUserDataFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.password FROM auth_tokens t JOIN blog_users u ON t.user_id = u.id WHERE t.token = :token AND t.expires > NOW() LIMIT 1";
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

if (!isset($data->token) || !isset($data->current_password) || !isset($data->new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Eksik parametreler."]);
    exit();
}

$userData = getUserDataFromToken($db, $data->token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Kimlik doğrulanamadı."]);
    exit();
}

$userId = $userData['user_id'];
$currentHashedPassword = $userData['password'];
$currentPassword = $data->current_password;
$newPassword = $data->new_password;

// Girdi doğrulamaları
if (!password_verify($currentPassword, $currentHashedPassword)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Mevcut şifreniz doğru değil."]);
    exit();
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Yeni şifre en az 6 karakter olmalıdır."]);
    exit();
}

try {
    $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $updateStmt = $db->prepare("UPDATE blog_users SET password = :password WHERE id = :user_id");
    $updateStmt->execute([
        ':password' => $newHashedPassword,
        ':user_id' => $userId
    ]);

    if ($updateStmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Şifreniz başarıyla güncellendi."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Şifre güncellenirken bir hata oluştu."]);
    }

} catch (PDOException $e) {
    error_log("Change Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>