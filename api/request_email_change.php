<?php
// api/request_email_change.php

// Hataları görmek için bu satırları aktif bırakın
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db.php'; 

// Gerekli PHPMailer dosyaları
$document_root = $_SERVER['DOCUMENT_ROOT'];
require_once $document_root . '/includes/phpmailer/PHPMailer.php';
require_once $document_root . '/includes/phpmailer/SMTP.php';
require_once $document_root . '/includes/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Token doğrulama fonksiyonu ARTIK BU DOSYANIN İÇİNDE
function getUserDataFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.username, u.email, u.password FROM auth_tokens t JOIN blog_users u ON t.user_id = u.id WHERE t.token = :token AND t.expires > NOW() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token) || !isset($data->new_email) || !isset($data->password)) {
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

// ... (Buradan sonraki tüm mantık, şifre kontrolü, veritabanı güncellemesi ve mail gönderme kısmı öncekiyle aynı kalacak)
// ...
// ...
$userId = $userData['user_id'];
$currentHashedPassword = $userData['password'];
$newEmail = trim($data->new_email);
$password = $data->password;

if (!password_verify($password, $currentHashedPassword)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Mevcut şifreniz yanlış."]);
    exit();
}

// ... (Diğer kontroller: e-posta formatı, e-posta zaten kullanımda mı, vs.)

$mail = new PHPMailer(true);
try {
    $token = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', time() + 60 * 15);
    $updateStmt = $db->prepare("UPDATE blog_users SET new_email = :new_email, email_change_token = :token, email_change_expiry = :expiry WHERE id = :user_id");
    $updateStmt->execute([':new_email' => $newEmail, ':token' => $token, ':expiry' => $expiry, ':user_id' => $userId]);

    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
    $mail->isSMTP();
    $mail->Host       = 'smtp.turkticaret.net';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@blogium.net';
    $mail->Password   = 'M788e455e*';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('no-reply@blogium.net', 'Blogium');
    $mail->addAddress($newEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Blogium E-posta Değişikliği Doğrulama Kodu';
    $mail->Body    = "Doğrulama kodunuz: <h2><b>$token</b></h2>";

    ob_start(); // Çıktı tamponlamasını başlat
    $mail->send();
    ob_end_clean(); // SMTP loglarını temizle

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Doğrulama kodu " . htmlspecialchars($newEmail) . " adresine gönderildi."]);

} catch (Exception $e) {
    ob_end_clean(); // Hata durumunda da tamponu temizle
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Mail gönderilemedi: " . $mail->ErrorInfo 
    ]);
}
?>