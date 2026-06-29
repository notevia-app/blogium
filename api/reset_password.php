<?php
// api/reset_password.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->token) || !isset($data->new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Eksik parametreler."]);
    exit();
}

$email = $data->email;
$token = $data->token;
$newPassword = $data->new_password;

try {
    $currentTime = gmdate("Y-m-d H:i:s");
    $stmt = $db->prepare("SELECT id FROM blog_users WHERE email = :email AND reset_token = :token AND reset_token_expiry > :current_time");
    $stmt->execute([':email' => $email, ':token' => $token, ':current_time' => $currentTime]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $db->prepare("UPDATE blog_users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :user_id");
        $updateStmt->execute([':password' => $newHashedPassword, ':user_id' => $user['id']]);

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Şifreniz başarıyla güncellendi. Şimdi giriş yapabilirsiniz."]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Doğrulama kodu yanlış veya süresi dolmuş."]);
    }

} catch (PDOException $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir hata oluştu."]);
}
?>