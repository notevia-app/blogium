<?php
/**
 * Beğenilen Yazılar Sayfası - v2.0 (Sayfalama Eklendi)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /signin.php');
    exit;
}

require_once __DIR__ . '/adminpanel/includes/db.php';
if (!isset($pdo)) {
    die("Kritik Sistem Hatası: Veritabanı bağlantısı kurulamadı.");
}

// --- SAYFALAMA AYARLARI ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$liked_posts = [];
$total_pages = 0;

try {
    // 1. Toplam beğeni sayısını bul
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM user_likes WHERE user_id = ?");
    $stmt_count->execute([$_SESSION['user_id']]);
    $total_liked = $stmt_count->fetchColumn();
    $total_pages = ceil($total_liked / $limit);

    // 2. Verileri Çek (LIMIT ve OFFSET ile)
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.slug, p.content, p.image_url, p.created_at, p.like_count, p.comment_count, p.save_count
         FROM posts p
         INNER JOIN user_likes ul ON p.id = ul.post_id
         WHERE ul.user_id = :user_id
         ORDER BY ul.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $liked_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $liked_posts = [];
    error_log("Beğenilenleri çekme hatası: " . $e->getMessage());
}

$page_title = 'Beğenilen Yazılar';
include __DIR__ . '/includes/header.php';

function format_turkish_date($date_string) {
    if (empty($date_string)) return 'Tarih Yok';
    try {
        $date = new DateTime($date_string);
        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        return $date->format('d') . ' ' . $aylar[$date->format('n') - 1] . ' ' . $date->format('Y');
    } catch (Exception $e) {
        return 'Geçersiz Tarih';
    }
}

function format_number_short($number) {
    if (!is_numeric($number)) return 0;
    if ($number >= 1000000) return round($number / 1000000, 1) . ' Mn';
    elseif ($number >= 1000) return round($number / 1000, 1) . ' Bin';
    return $number;
}
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<main class="main-content">
    <div class="container">
        
        <div class="page-header">
            <div class="page-header-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h1 class="page-title">Beğenilen Yazılar</h1>
            <p class="page-subtitle">Daha sonra tekrar göz atmak için favorilerinize eklediğiniz yazılar (Toplam: <?= $total_liked ?>).</p>
        </div>

        <div class="post-grid-container">
            <?php if (empty($liked_posts)): ?>
                <div class="no-posts-message">
                    <div class="no-posts-icon"><i class="fas fa-search-plus"></i></div>
                    <h2>Henüz bir yazıyı beğenmediniz.</h2>
                    <p>Beğendiğiniz yazılar, daha kolay erişim için burada listelenecektir.</p>
                    <a href="/" class="btn-primary">Yazıları Keşfet</a>
                </div>
            <?php else: ?>
                <?php foreach ($liked_posts as $post): 
                    $post_link = "/yazi/" . urlencode($post['slug']);
                ?>
                    <a href="<?= $post_link ?>" class="post-card">
                        <div class="post-image-wrapper">
                            <img src="/<?= htmlspecialchars($post['image_url'] ?? 'assets/img/no-image.png') ?>" alt="<?= htmlspecialchars($post['title'] ?? '') ?>" class="post-image" loading="lazy">
                        </div>
                        <div class="post-content">
                            <h2 class="post-title"><?= htmlspecialchars($post['title'] ?? 'Başlık Yok') ?></h2>
                            <p class="post-snippet">
                                <?= htmlspecialchars(mb_substr(strip_tags($post['content'] ?? ''), 0, 110)) . '...' ?>
                            </p>
                            <div class="post-footer">
                                <span class="post-date"><?= format_turkish_date($post['created_at'] ?? null) ?></span>
                                <div class="post-stats">
                                    <span><i class="fas fa-heart"></i> <?= format_number_short($post['like_count'] ?? 0) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= format_number_short($post['comment_count'] ?? 0) ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <?php if ($page > 1): ?>
                <a href="?page=1" class="page-link" title="İlk Sayfa">&laquo;</a>
                <a href="?page=<?= $page - 1 ?>" class="page-link" title="Önceki Sayfa">&lsaquo;</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>" class="page-link" title="Sonraki Sayfa">&rsaquo;</a>
                <a href="?page=<?= $total_pages ?>" class="page-link" title="Son Sayfa">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
/* Ana Yapı */
.main-content { padding: 50px 20px; background-color: #f8fafc; min-height: 80vh; }
.container { max-width: 1200px; margin: 0 auto; }

/* Header */
.page-header { text-align: center; margin-bottom: 50px; }
.page-header-icon { font-size: 32px; color: #ef4444; margin-bottom: 15px; }
.page-title { font-size: 36px; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
.page-subtitle { font-size: 18px; color: #64748b; max-width: 600px; margin: 0 auto; }

/* Grid */
.post-grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }

/* Post Card */
.post-card { background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); text-decoration: none; color: inherit; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.post-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); }

.post-image-wrapper { width: 100%; aspect-ratio: 16 / 9; overflow: hidden; background-color: #f1f5f9; }
.post-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
.post-card:hover .post-image { transform: scale(1.05); }

.post-content { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
.post-title { font-size: 20px; font-weight: 600; color: #1e293b; margin: 0 0 10px 0; line-height: 1.4; }
.post-snippet { font-size: 14px; color: #475569; line-height: 1.6; margin: 0; flex-grow: 1; margin-bottom: 20px; }
.post-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; }
.post-date { font-size: 13px; font-weight: 500; color: #64748b; }
.post-stats { display: flex; align-items: center; gap: 15px; font-size: 13px; color: #64748b; }
.post-stats span { display: flex; align-items: center; gap: 5px; }

/* Pagination */
.pagination-wrapper { margin-top: 60px; display: flex; justify-content: center; align-items: center; gap: 8px; }
.page-link {
    display: flex; align-items: center; justify-content: center;
    min-width: 40px; height: 40px; padding: 0 10px;
    background-color: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px;
    transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.03);
}
.page-link:hover { border-color: #667eea; color: #667eea; transform: translateY(-2px); }
.page-link.active { background-color: #667eea; border-color: #667eea; color: #fff; }

/* Boş Mesaj */
.no-posts-message {
    grid-column: 1 / -1; text-align: center; background-color: #ffffff;
    padding: 60px 40px; border-radius: 16px; border: 1px solid #e2e8f0;
}
.no-posts-icon { font-size: 40px; color: #667eea; margin-bottom: 20px; }
.no-posts-message h2 { font-size: 24px; color: #1e293b; margin-bottom: 15px; }
.no-posts-message p { font-size: 16px; color: #64748b; margin-bottom: 30px; }
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white; border: none; padding: 12px 25px; border-radius: 25px;
    font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block;
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }

@media (max-width: 768px) {
    .page-title { font-size: 28px; }
    .page-subtitle { font-size: 16px; }
    .main-content { padding: 40px 15px; }
    .post-grid-container { grid-template-columns: 1fr; }
}
</style>

<?php
// include __DIR__ . '/includes/footer.php';
?>