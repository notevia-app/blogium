<?php
// api/get_post_details.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Geçerli bir gönderi ID'si belirtilmedi."], JSON_UNESCAPED_UNICODE);
    exit();
}

$postId = intval($_GET['id']);
$userId = null;

// Token varsa, kullanıcı ID'sini al (auth_helper olmadan)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    try {
        $tokenQuery = "SELECT user_id FROM auth_tokens WHERE token = :token AND expires > NOW() LIMIT 1";
        $tokenStmt = $db->prepare($tokenQuery);
        $tokenStmt->bindParam(':token', $token);
        $tokenStmt->execute();
        $result = $tokenStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $userId = $result['user_id'];
        }
    } catch (PDOException $e) {
        // Token sorgusunda hata olursa logla ama işleme devam et
        error_log("Token validation error in get_post_details: " . $e->getMessage());
    }
}

try {
    $db->beginTransaction();

    $updateQuery = "UPDATE posts SET views = views + 1 WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':id', $postId, PDO::PARAM_INT);
    $updateStmt->execute();

    $query = "SELECT p.*, c.name as category_name, c.slug as category_slug, p.views as view_count 
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $postId, PDO::PARAM_INT);
    $stmt->execute();
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        $post['tags'] = !empty($post['tags']) ? array_map('trim', explode(',', $post['tags'])) : [];

        $post['is_liked_by_user'] = false;
        $post['is_saved_by_user'] = false;

        if ($userId) {
            $likeCheckQuery = "SELECT id FROM user_likes WHERE user_id = :user_id AND post_id = :post_id";
            $likeStmt = $db->prepare($likeCheckQuery);
            $likeStmt->execute([':user_id' => $userId, ':post_id' => $postId]);
            if ($likeStmt->rowCount() > 0) {
                $post['is_liked_by_user'] = true;
            }

            $saveCheckQuery = "SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id";
            $saveStmt = $db->prepare($saveCheckQuery);
            $saveStmt->execute([':user_id' => $userId, ':post_id' => $postId]);
            if ($saveStmt->rowCount() > 0) {
                $post['is_saved_by_user'] = true;
            }
        }

        $db->commit();
        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $post], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(["status" => "not_found", "message" => "Gönderi bulunamadı."], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Get Post Details Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Sunucuda bir veritabanı hatası oluştu."]);
}
?>