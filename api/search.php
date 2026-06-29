<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

// Aranan kelimeyi ('query') kontrol et
if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Lütfen bir arama terimi girin."], JSON_UNESCAPED_UNICODE);
    exit();
}

$searchQuery = trim($_GET['query']);
// SQL LIKE sorgusu için arama terimini % işaretleri arasına alıyoruz
$searchTerm = "%" . $searchQuery . "%";

try {
    // Arama sorgusu: Başlık (title), içerik (content) veya etiketler (tags) içinde eşleşme arar.
    // CONCAT_WS, etiketleri virgülle ayırarak arama yapılmasını kolaylaştırır.
    $query = "SELECT 
                id, 
                title, 
                slug,
                meta_description,
                image_url, 
                created_at, 
                view_count, 
                like_count,
                comment_count
              FROM posts 
              WHERE title LIKE :searchTerm 
                 OR content LIKE :searchTerm 
                 OR tags LIKE :searchTerm
              ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    
    $results = $stmt->fetchAll();

    if ($results) {
        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "not_found", "message" => "Aramanızla eşleşen sonuç bulunamadı."], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Arama sırasında bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>