<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

function getUserIdAndUsernameFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT t.user_id, u.username 
              FROM auth_tokens t 
              JOIN blog_users u ON t.user_id = u.id 
              WHERE t.token = :token AND t.expires > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Sadece POST metodu kabul edilir."], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token) || !isset($data->post_id) || !is_numeric($data->post_id) || !isset($data->content) || empty(trim($data->content))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Eksik veya geçersiz parametre."], JSON_UNESCAPED_UNICODE);
    exit();
}

$userData = getUserIdAndUsernameFromToken($db, $data->token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Geçersiz veya süresi dolmuş token."], JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $userData['user_id'];
$username = $userData['username'];
$postId = intval($data->post_id);
$content = trim($data->content);

try {
    // 1. ADIM: Yorum yapılacak gönderinin yorum ayarını ('allow_comments') öğren.
    $postCheckQuery = "SELECT allow_comments FROM posts WHERE id = :post_id";
    $postStmt = $db->prepare($postCheckQuery);
    $postStmt->bindParam(':post_id', $postId);
    $postStmt->execute();
    $postSettings = $postStmt->fetch();

    if (!$postSettings) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Yorum yapılacak gönderi bulunamadı."], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $allowComments = $postSettings['allow_comments'];
    
    // 2. ADIM: Yorum ayarına göre durumu ve mesajı belirle.
    if ($allowComments == 'no') {
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "Bu gönderi yorumlara kapalıdır."], JSON_UNESCAPED_UNICODE);
        exit();
    } elseif ($allowComments == 'yes') {
        $commentStatus = 'approved';
        $successMessage = "Yorumunuz başarıyla eklendi.";
    } else { // 'moderated' durumu
        $commentStatus = 'pending';
        $successMessage = "Yorumunuz alındı. Onaylandıktan sonra yayınlanacaktır.";
    }

    $db->beginTransaction();

    // 3. ADIM: Yorumu, belirlenen status ile veritabanına ekle.
    $query = "INSERT INTO comments (post_id, content, status, author) VALUES (:post_id, :content, :status, :author)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':post_id', $postId);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':status', $commentStatus);
    $stmt->bindParam(':author', $username);
    $stmt->execute();

    // Sadece yorum onaylandıysa yorum sayısını artır. 
    // Onay bekleyenler için admin panelden artırılabilir veya burada da artırılabilir, bu bir tasarım kararıdır.
    // Şimdilik her durumda artıralım.
    $updateQuery = "UPDATE posts SET comment_count = comment_count + 1 WHERE id = :post_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':post_id', $postId);
    $updateStmt->execute();

    $db->commit();

    http_response_code(201);
    echo json_encode(["status" => "success", "message" => $successMessage], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir hata oluştu."], JSON_UNESCAPED_UNICODE);
}
?>