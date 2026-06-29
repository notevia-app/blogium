<?php
// api/send_contact_form.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

$document_root = $_SERVER['DOCUMENT_ROOT'];
require_once $document_root . '/api/db.php'; 
require_once $document_root . '/includes/mail.php'; // Web sitenizin çalışan mail.php'si

function getUserDataFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.username, u.email FROM auth_tokens t JOIN blog_users u ON t.user_id = u.id WHERE t.token = :token AND t.expires > NOW() LIMIT 1";
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

if (!isset($data->token) || !isset($data->subject) || !isset($data->message)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Eksik parametreler."]);
    exit();
}

$userData = getUserDataFromToken($db, $data->token);
if (!$userData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Bu işlemi yapmak için giriş yapmalısınız."]);
    exit();
}

$username = $userData['username'];
$email = $userData['email'];
$subject = trim($data->subject);
$message = trim($data->message);

if (empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Lütfen tüm alanları doldurun."]);
    exit();
}

// E-postayı gönder
$to_email = 'info@blogium.net';
$email_subject = 'Blogium İletişim Formu: ' . htmlspecialchars($subject);
$body = "<html><body>" .
        "<h2>Blogium İletişim Formu'ndan Yeni Mesaj</h2>" .
        "<p><strong>Gönderen Kullanıcı Adı:</strong> " . htmlspecialchars($username) . "</p>" .
        "<p><strong>Gönderen E-posta:</strong> " . htmlspecialchars($email) . "</p>" .
        "<p><strong>Konu:</strong> " . htmlspecialchars($subject) . "</p>" .
        "<hr>" .
        "<h3>Mesaj İçeriği:</h3>" .
        "<p>" . nl2br(htmlspecialchars($message)) . "</p>" . // nl2br, satır atlamalarını <br> etiketine çevirir
        "</body></html>";

if (send_email($to_email, $email_subject, $body, $email)) { // Yanıt adresi olarak kullanıcının e-postasını ekleyebiliriz
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Mesajınız başarıyla gönderildi. En kısa sürede size geri dönüş yapacağız."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Mesajınız gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin."]);
}
?>