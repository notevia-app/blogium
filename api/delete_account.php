<?php
// api/delete_account.php

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

if (!isset($data->token) || !isset($data->password)) {
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
$deletePassword = $data->password;

// Girilen şifrenin doğruluğunu kontrol et
if (!password_verify($deletePassword, $currentHashedPassword)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Hesabınızı silmek için girdiğiniz şifre yanlış."]);
    exit();
}

try {
    $db->beginTransaction();

    // İlişkili tüm verileri temizlemek önemlidir.
    // Örnek: Kullanıcının token'larını, beğenilerini, kaydettiklerini ve yorumlarını sil.
    // Bu sorguları kendi veritabanı yapınıza göre genişletebilirsiniz.
    $db->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM user_likes WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM saved_posts WHERE user_id = ?")->execute([$userId]);
    // Yorumları anonimleştirmek veya silmek bir tercih meselesidir. Burada siliyoruz.
    $db->prepare("DELETE FROM comments WHERE author = (SELECT username FROM blog_users WHERE id = ?)")->execute([$userId]);

    // Son olarak kullanıcının kendisini sil
    $deleteStmt = $db->prepare("DELETE FROM blog_users WHERE id = ?");
    $deleteStmt->execute([$userId]);
    
    $db->commit();

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Hesabınız başarıyla kalıcı olarak silindi."]);

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Delete Account Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>