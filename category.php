<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/adminpanel/includes/db.php';

// URL'den kategori slug'ını al
$category_slug = trim($_GET['slug'] ?? '');

// Slug boşsa anasayfaya yönlendir
if (empty($category_slug)) {
    header("Location: /"); 
    exit;
}

// --- SAYFALAMA AYARLARI ---
$limit = 10; // Sayfa başına gösterilecek yazı sayısı
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$category = null;
$posts = [];
$total_posts = 0;
$total_pages = 0;

try {
    // 1. Kategoriyi Bul
    $stmt_category = $pdo->prepare("SELECT id, name, slug FROM categories WHERE slug = ? LIMIT 1");
    $stmt_category->execute([$category_slug]);
    $category = $stmt_category->fetch(PDO::FETCH_ASSOC);

    if ($category) {
        // 2. Toplam Yazı Sayısını Bul (Sayfalama Hesabı İçin)
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category_id = ?");
        $stmt_count->execute([$category['id']]);
        $total_posts = $stmt_count->fetchColumn();
        $total_pages = ceil($total_posts / $limit);

        // 3. O sayfaya ait yazıları çek (LIMIT ve OFFSET ile)
        $stmt_posts = $pdo->prepare("
            SELECT * FROM posts 
            WHERE category_id = :cat_id 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        
        // PDO bindValue kullanarak integer olarak gönderiyoruz (LIMIT için önemli)
        $stmt_posts->bindValue(':cat_id', $category['id'], PDO::PARAM_INT);
        $stmt_posts->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_posts->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_posts->execute();
        
        $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

        // SEO Ayarları
        $page_title = $category['name'] . " Kategorisi";
        $page_description = "Blogium'da " . $category['name'] . " kategorisindeki özgün içerikleri keşfedin.";
        $page_keywords = "blog, kategori, {$category['name']}, yazılar";
        $page_url = "https://www.blogium.net/kategori/" . $category['slug'];
        $page_image = "https://www.blogium.net/logo.png";

    } else {
        // Kategori Yoksa 404
        http_response_code(404);
        $page_title = "Kategori Bulunamadı - Blogium";
        $page_description = "Aradığınız kategori bulunamadı.";
        $page_url = "https://www.blogium.net/";
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Kategori sayfası DB hatası: " . $e->getMessage());
}

/** Helper Fonksiyonlar **/
function format_turkish_date($date_string) {
    if (empty($date_string)) return '';
    $timestamp = strtotime($date_string);
    if ($timestamp === false) return '';
    $aylar = [1=>'Ocak', 2=>'Şubat', 3=>'Mart', 4=>'Nisan', 5=>'Mayıs', 6=>'Haziran', 7=>'Temmuz', 8=>'Ağustos', 9=>'Eylül', 10=>'Ekim', 11=>'Kasım', 12=>'Aralık'];
    return date('d', $timestamp) . ' ' . $aylar[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Anasayfadaki sayı formatlama fonksiyonu (View count için gerekli)
function format_number_short($number) {
    if (!is_numeric($number)) return 0;
    if ($number >= 1000000) return round($number / 1000000, 1) . ' Mn';
    elseif ($number >= 1000) return round($number / 1000, 1) . ' Bin';
    return $number;
}

include __DIR__ . '/includes/header.php';
include 'includes/head.php'; 
?>

<style>
    /* Kategori Sayfası Genel Stilleri */
    .category-page-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .category-header {
        padding: 30px;
        background-color: #fff;
        border-radius: 16px;
        margin-bottom: 40px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #f1f5f9;
    }
    .category-header h1 {
        font-size: 32px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 10px;
    }
    .category-header p {
        font-size: 16px;
        color: #64748b;
    }

    /* Post Grid Stilleri (Anasayfa ile uyumlu) */
    .post-grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 30px;
    }
    
    .post-card {
        background-color: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        color: inherit;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .post-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
    }
    .post-image-wrapper {
        width: 100%;
        aspect-ratio: 16 / 9;
        overflow: hidden;
    }
    .post-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .post-card:hover .post-image {
        transform: scale(1.05);
    }
    .post-content {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .post-title {
        font-size: 20px;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 10px 0;
        line-height: 1.4;
    }
    .post-snippet {
        font-size: 14px;
        color: #475569;
        line-height: 1.6;
        margin: 0;
        flex-grow: 1;
        margin-bottom: 20px;
    }
    .post-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        padding-top: 15px;
    }
    .post-date { font-size: 13px; font-weight: 500; color: #64748b; }
    .post-stats { display: flex; align-items: center; gap: 15px; font-size: 13px; color: #64748b; }
    .post-stats span { display: flex; align-items: center; gap: 5px; }

    /* PAGINATION (SAYFALAMA) STİLLERİ */
    .pagination-wrapper {
        margin-top: 60px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
    .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 10px;
        background-color: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    }
    .page-link:hover {
        border-color: #667eea;
        color: #667eea;
        transform: translateY(-2px);
    }
    .page-link.active {
        background-color: #667eea;
        border-color: #667eea;
        color: #fff;
    }

    /* Hata/Boş Mesaj Stili */
    .no-posts-message {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        background-color: #fff;
        border-radius: 16px;
    }
    .no-posts-message i { font-size: 40px; color: #cbd5e1; margin-bottom: 20px; }
    .no-posts-message h3 { font-size: 20px; color: #1e293b; margin-bottom: 10px; }
    .no-posts-message p { color: #64748b; }

    @media (max-width: 768px) {
        .category-header h1 { font-size: 26px; }
        .post-grid-container { grid-template-columns: 1fr; }
    }
</style>

<main class="category-page-container">

    <?php if ($category): ?>
    
        <div class="category-header">
            <h1><?= htmlspecialchars($category['name']) ?></h1>
            <p>Toplam <?= $total_posts ?> içerik</p>
        </div>

        <div class="post-grid-container">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <a href="/yazi/<?= htmlspecialchars($post['slug']) ?>" class="post-card">
                        <div class="post-image-wrapper">
                            <img src="/<?= !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : 'assets/img/no-image.png' ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-image" loading="lazy">
                        </div>
                        <div class="post-content">
                            <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
                            <p class="post-snippet"><?= htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 110)) ?>...</p>
                            <div class="post-footer">
                                <span class="post-date"><?= format_turkish_date($post['created_at']) ?></span>
                                <div class="post-stats">
                                    <span><i class="fas fa-eye"></i> <?= format_number_short($post['views'] ?? 0) ?></span>
                                    <span><i class="fas fa-heart"></i> <?= format_number_short($post['like_count'] ?? 0) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= format_number_short($post['comment_count'] ?? 0) ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-posts-message">
                    <i class="fas fa-folder-open"></i>
                    <h3>Henüz Yazı Eklenmemiş</h3>
                    <p>Bu kategoride henüz bir yazı bulunmuyor.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <?php if ($page > 1): ?>
                <a href="?page=1" class="page-link" title="İlk Sayfa">&laquo;&laquo;</a>
                <a href="?page=<?= $page - 1 ?>" class="page-link" title="Önceki Sayfa">&laquo;</a>
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
                <a href="?page=<?= $page + 1 ?>" class="page-link" title="Sonraki Sayfa">&raquo;</a>
                <a href="?page=<?= $total_pages ?>" class="page-link" title="Son Sayfa">&raquo;&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="no-posts-message">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Kategori Bulunamadı</h3>
            <p>Aradığınız kategori mevcut değil.</p>
        </div>
    <?php endif; ?>

</main>

<?php
include __DIR__ . '/includes/footer.php';
?>