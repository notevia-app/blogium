<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

// Yardımcı fonksiyon
function getUserIdFromToken($db, $token) {
    if (empty($token)) return null;
    $query = "SELECT user_id FROM auth_tokens WHERE token = :token AND expires > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['user_id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Sadece POST metodu kabul edilir."], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token) || !isset($data->post_id) || !is_numeric($data->post_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Eksik veya geçersiz parametre."], JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = getUserIdFromToken($db, $data->token);

if (!$userId) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Geçersiz veya süresi dolmuş token."], JSON_UNESCAPED_UNICODE);
    exit();
}

$postId = intval($data->post_id);

try {
    $db->beginTransaction();

    $checkQuery = "SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->bindParam(':post_id', $postId);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        // Kaydı sil
        $deleteQuery = "DELETE FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':user_id', $userId);
        $deleteStmt->bindParam(':post_id', $postId);
        $deleteStmt->execute();
        
        $updateQuery = "UPDATE posts SET save_count = save_count - 1 WHERE id = :post_id";
        $message = "Kayıt kaldırıldı.";
    } else {
        // Kaydet
        $insertQuery = "INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $userId);
        $insertStmt->bindParam(':post_id', $postId);
        $insertStmt->execute();

        $updateQuery = "UPDATE posts SET save_count = save_count + 1 WHERE id = :post_id";
        $message = "Gönderi kaydedildi.";
    }
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':post_id', $postId);
    $updateStmt->execute();

    $db->commit();

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => $message], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>