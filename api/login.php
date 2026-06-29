<?php
// api/login.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Geçersiz istek metodu."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "E-posta ve şifre alanları zorunludur."], JSON_UNESCAPED_UNICODE);
    exit();
}

$email = trim($data->email);
$password = trim($data->password);

try {
    $query = "SELECT id, username, password, email, email_verified FROM blog_users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "E-posta veya şifre hatalı."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "E-posta veya şifre hatalı."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($user['email_verified'] == 0) {
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "Giriş yapmadan önce hesabınızı doğrulamanız gerekmektedir. Lütfen e-postanızı kontrol edin."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires) VALUES (:user_id, :token, :expires)";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindParam(':user_id', $user['id']);
    $tokenStmt->bindParam(':token', $token);
    $tokenStmt->bindParam(':expires', $expires);
    $tokenStmt->execute();

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Giriş başarılı.",
        "data" => [
            "token" => $token,
            "user" => [
                "id" => (int)$user['id'], 
                "username" => $user['username'],
                "email" => $user['email']
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>