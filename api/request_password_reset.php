<?php
// api/request_password_reset.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

$document_root = $_SERVER['DOCUMENT_ROOT'];
require_once $document_root . '/api/db.php'; 
require_once $document_root . '/includes/mail.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Lütfen geçerli bir e-posta adresi girin."]);
    exit();
}

$email = $data->email;

try {
    $stmt = $db->prepare("SELECT id FROM blog_users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // GÜVENLİK NOTU: Kullanıcı bulunamasa bile, e-postanın sistemde olup olmadığını
    // belli etmemek için her zaman başarılı bir mesaj döndürüyoruz.
    if ($user) {
        $token = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', time() + 60 * 15); // 15 dakika geçerli

        $updateStmt = $db->prepare("UPDATE blog_users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :user_id");
        $updateStmt->execute([
            ':token' => $token,
            ':expiry' => $expiry,
            ':user_id' => $user['id']
        ]);

        $subject = 'Blogium Şifre Sıfırlama Kodu';
        $body = "Şifre sıfırlama talebiniz alındı. Doğrulama kodunuz: <h2><b>$token</b></h2>";
        
        send_email($email, $subject, $body);
    }

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Eğer e-posta adresiniz sistemimizde kayıtlıysa, şifre sıfırlama kodu gönderilmiştir."]);

} catch (PDOException $e) {
    error_log("Request Password Reset Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir hata oluştu."]);
}
?>