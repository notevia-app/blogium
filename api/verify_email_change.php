<?php
// api/verify_email_change.php

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

if (!isset($data->token) || !isset($data->verification_code)) {
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
$code = $data->verification_code;

try {
    $currentTime = gmdate("Y-m-d H:i:s");

    $stmt = $db->prepare(
        "SELECT new_email, email_change_token FROM blog_users 
         WHERE id = :user_id AND email_change_expiry > :current_time"
    );
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':current_time', $currentTime);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request && $request['email_change_token'] == $code) {
        $final_stmt = $db->prepare(
            "UPDATE blog_users 
             SET email = :new_email, new_email = NULL, email_change_token = NULL, email_change_expiry = NULL 
             WHERE id = :user_id"
        );
        $final_stmt->execute([
            ':new_email' => $request['new_email'],
            ':user_id' => $userId
        ]);
        
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "E-posta adresiniz başarıyla güncellendi."]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Girdiğiniz kod yanlış veya süresi dolmuş."]);
    }

} catch (PDOException $e) {
    error_log("Verify Email Change Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>