<?php
// api/get_account_details.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

// Token doğrulama fonksiyonu ARTIK BU DOSYANIN İÇİNDE
function getUserDataFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.username, u.email, u.password FROM auth_tokens t JOIN blog_users u ON t.user_id = u.id WHERE t.token = :token AND t.expires > NOW() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Token eksik."]);
    exit();
}

$userData = getUserDataFromToken($db, $token); 
if (!$userData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Geçersiz veya süresi dolmuş token."]);
    exit();
}

$userId = $userData['user_id'];
try {
    $query = "SELECT username, email, new_email FROM blog_users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "data" => [
                "username" => $user['username'],
                "email" => $user['email'],
                "is_verifying_new_email" => !empty($user['new_email']),
                "new_email" => $user['new_email']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Kullanıcı bulunamadı."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>