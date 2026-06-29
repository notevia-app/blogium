<?php
// handle_comment.php

// Her zaman JSON içeriği döndüreceğimizi belirtelim
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/adminpanel/includes/db.php';
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

// Tarih formatlama fonksiyonunu buraya da alalım
function format_turkish_date($date_string) {
    if (empty($date_string)) return 'Şimdi';
    try {
        $date = new DateTime($date_string);
        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        return $date->format('d') . ' ' . $aylar[$date->format('n') - 1] . ' ' . $date->format('Y');
    } catch (Exception $e) { return 'Geçersiz Tarih'; }
}


$response = ['status' => 'error', 'message' => 'Geçersiz istek.'];

// Sadece POST isteklerini ve giriş yapmış kullanıcıları kabul et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $content = trim($_POST['content'] ?? '');
    
    // Gerekli ayarları post üzerinden çekelim (allow_comments)
    $stmt_post = $pdo->prepare("SELECT allow_comments FROM posts WHERE id = ?");
    $stmt_post->execute([$post_id]);
    $post_settings = $stmt_post->fetch(PDO::FETCH_ASSOC);
    $allow_comments = $post_settings['allow_comments'] ?? 'no';

    if ($post_id && !empty($content) && $allow_comments !== 'no') {
        try {
            $comment_status = ($allow_comments === 'moderated') ? 'pending' : 'approved';
            
            $comment_stmt = $pdo->prepare("INSERT INTO comments (post_id, author, content, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            $comment_stmt->execute([$post_id, $_SESSION['username'], $content, $comment_status]);
            
            $new_comment_id = $pdo->lastInsertId();

            if ($comment_status === 'approved') {
                $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?")->execute([$post_id]);
                
                // Yeni eklenen yorumun bilgilerini JavaScript'e göndermek için çekelim
                $stmt_new_comment = $pdo->prepare("SELECT * FROM comments WHERE id = ?");
                $stmt_new_comment->execute([$new_comment_id]);
                $new_comment_data = $stmt_new_comment->fetch(PDO::FETCH_ASSOC);
                
                $response = [
                    'status' => 'success',
                    'message' => 'Yorumunuz başarıyla yayınlandı.',
                    'comment' => [
                        'author' => htmlspecialchars($new_comment_data['author']),
                        'content' => nl2br(htmlspecialchars($new_comment_data['content'])),
                        'created_at' => format_turkish_date($new_comment_data['created_at']),
                        'author_initial' => htmlspecialchars(strtoupper(substr($new_comment_data['author'], 0, 1)))
                    ]
                ];
            } else {
                $response = [
                    'status' => 'info',
                    'message' => 'Yorumunuz editörlerimiz tarafından incelendikten sonra yayınlanacaktır.'
                ];
            }
        } catch (PDOException $e) {
            error_log("AJAX Comment Error: " . $e->getMessage());
            $response = ['status' => 'error', 'message' => 'Yorum gönderilirken bir sunucu hatası oluştu.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Lütfen yorum alanını boş bırakmayın.'];
    }
} else {
    $response = ['status' => 'error', 'message' => 'Bu işlemi yapmak için giriş yapmalısınız.'];
}

echo json_encode($response);
exit;