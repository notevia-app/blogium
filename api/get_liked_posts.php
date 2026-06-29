<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php';
ini_set('display_errors', 0);
error_reporting(E_ALL);

$token = $_GET['token'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

if (!$token) { echo json_encode(["status" => "error", "message" => "Token eksik."]); exit(); }

$stmt = $db->prepare("SELECT user_id FROM auth_tokens WHERE token = :token LIMIT 1");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { echo json_encode(["status" => "error", "message" => "Geçersiz oturum."]); exit(); }
$userId = $user['user_id'];

try {
    $countQuery = $db->prepare("SELECT COUNT(*) as total FROM user_likes WHERE user_id = :uid");
    $countQuery->execute([':uid' => $userId]);
    $totalRecords = $countQuery->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages == 0) $totalPages = 1;

    $query = "SELECT p.id, p.title, p.slug, p.meta_description, p.image_url, p.created_at, 
              p.views as view_count, p.like_count, p.comment_count,
              (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
              1 as is_liked,
              (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = :uid2) as is_saved,
              c.name as category_name
              FROM user_likes ul
              JOIN posts p ON ul.post_id = p.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE ul.user_id = :uid1
              ORDER BY ul.created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':uid1', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($posts) {
        foreach ($posts as &$post) {
            $post['is_liked'] = true;
            $post['is_saved'] = ($post['is_saved'] > 0);
            $post['save_count'] = (int)$post['save_count'];
            $post['like_count'] = (int)$post['like_count'];
            $post['view_count'] = (int)$post['view_count'];
            $post['comment_count'] = (int)$post['comment_count'];
        }
    }

    echo json_encode([
        "status" => "success", 
        "data" => $posts ?: [],
        "pagination" => ["current_page" => $page, "total_pages" => $totalPages]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
?>