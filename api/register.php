<?php
// api/register.php

// Hataları görmek için bu satırları geçici olarak aktif bırakabilirsiniz.
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// DİKKAT: Bu yolların sunucunuzdaki yapıya uygun olduğundan emin olun.
$document_root = $_SERVER['DOCUMENT_ROOT'];
require_once $document_root . '/api/db.php'; 
require_once $document_root . '/includes/mail.php'; // Çalışan mail.php dosyanız

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Geçersiz istek metodu."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->username) || !isset($data->email) || !isset($data->password) || empty(trim($data->username)) || empty(trim($data->email)) || empty(trim($data->password))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Lütfen tüm alanları doldurun."]);
    exit();
}

$username = trim($data->username);
$email = trim($data->email);
$password = trim($data->password);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Geçersiz e-posta formatı."]);
    exit();
}

try {
    $stmt = $db->prepare("SELECT id FROM blog_users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $username, ':email' => $email]);

    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Bu kullanıcı adı veya e-posta zaten kullanılıyor."]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // GÜNCELLEME: Doğrulama için token ve son kullanma tarihi oluşturuyoruz.
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', time() + 60 * 60 * 24); // 24 saat geçerli

    // GÜNCELLEME: Kullanıcıyı email_verified = 0 olarak ve token ile kaydediyoruz.
    $insertQuery = "INSERT INTO blog_users (username, email, password, email_verified, verification_token, verification_token_expiry) 
                    VALUES (:username, :email, :password, 0, :token, :expiry)";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':username', $username);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':password', $hashedPassword);
    $insertStmt->bindParam(':token', $verificationToken);
    $insertStmt->bindParam(':expiry', $tokenExpiry);

    if ($insertStmt->execute()) {
        // Kullanıcı başarıyla oluşturuldu, şimdi doğrulama e-postası gönder.
        $verification_link = "https://blogium.net/verify_email.php?token=" . $verificationToken;
        
        $subject = 'Blogium Hesap Doğrulama';
        $body = "<html><body>" .
                "<h2>Blogium'a Hoş Geldiniz!</h2>" .
                "<p>Hesabınızı doğrulamak ve kullanmaya başlamak için lütfen aşağıdaki butona tıklayın:</p>" .
                "<a href='$verification_link' style='background-color:#667eea; color:white; padding:15px 25px; text-decoration:none; border-radius:8px; display:inline-block;'>Hesabımı Doğrula</a>" .
                "<br><br><p>Eğer buton çalışmazsa, aşağıdaki linki tarayıcınıza yapıştırabilirsiniz:</p>" .
                "<p><a href='$verification_link'>$verification_link</a></p>" .
                "</body></html>";

        if (send_email($email, $subject, $body)) {
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Kayıt başarılı! Lütfen hesabınızı doğrulamak için e-posta adresinizi kontrol edin."], JSON_UNESCAPED_UNICODE);
        } else {
            // E-posta gönderilemese bile kullanıcı oluşturuldu. Ama bir hata mesajı dönmek daha iyi.
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Kullanıcı oluşturuldu ancak doğrulama e-postası gönderilemedi."], JSON_UNESCAPED_UNICODE);
        }
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Kayıt oluşturulurken bir veritabanı hatası oluştu."], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>