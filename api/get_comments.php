<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

// Gönderi ID'si parametresini kontrol et
if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Geçerli bir gönderi ID'si belirtilmedi."], JSON_UNESCAPED_UNICODE);
    exit();
}

$postId = intval($_GET['post_id']);

try {
    // Sadece 'approved' (onaylanmış) durumdaki yorumları çekiyoruz.
    // 'author' alanı, giriş yapan kullanıcıların 'username' alanından geleceği için
    // veritabanı şemanızdaki `comments` tablosunun `author` sütununu kullanacağız.
    // Şemanızda 'name' ve 'author' olarak iki farklı isim alanı var, 'author'u temel alıyorum.
    $query = "SELECT 
                id, 
                content, 
                created_at,
                author
              FROM comments 
              WHERE post_id = :post_id AND status = 'approved'
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt->execute();
    
    $comments = $stmt->fetchAll();

    // Yorum olmasa bile boş bir dizi dönmek daha tutarlıdır, bu bir hata değildir.
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $comments], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Yorumlar çekilirken bir hata oluştu: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>