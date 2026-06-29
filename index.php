<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Veritabanı bağlantısını garantiye alalım
if (!isset($pdo)) {
    require_once __DIR__ . '/adminpanel/includes/db.php';
}

include 'includes/header.php'; 

// --- AYARLARI VERİTABANINDAN ÇEKME ---
$site_settings = [];
try {
    // En son eklenen/güncellenen ayarı çek (ID'ye göre sondan başa)
    $settings_stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
    $site_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hata olursa varsayılan boş dizi kalsın
    error_log("Ayarlar çekilemedi: " . $e->getMessage());
}

// --- DEĞİŞKENLERİ VERİTABANI VERİLERİYLE DOLDURMA ---

// Başlık
if (!empty($site_settings['site_title'])) {
    $page_title = $site_settings['site_title'];
    if (!empty($site_settings['site_slogan'])) {
        $page_title .= ' ' . $site_settings['site_slogan'];
    }
} else {
    $page_title = 'Blogium - İçerik Burada Başlar'; // Yedek (Fallback)
}

// Açıklama (Description)
$page_description = !empty($site_settings['seo_description']) 
    ? $site_settings['seo_description'] 
    : 'Blogium, gündemden kültüre, teknolojiden girişimciliğe kadar her alanda özgün içerikler sunar.';

// Anahtar Kelimeler (Keywords)
$page_keywords = !empty($site_settings['meta_tags']) 
    ? $site_settings['meta_tags'] 
    : 'blog, teknoloji, kültür, girişimcilik, dijital, güncel haberler';

// Diğer Sabitler
$page_url = 'https://www.blogium.net/';

// Logo URL
$page_image = !empty($site_settings['logo_url']) 
    ? 'https://www.blogium.net' . $site_settings['logo_url'] 
    : 'https://www.blogium.net/logo.png';


// --- SAYFALAMA AYARLARI ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- VERİ ÇEKME İŞLEMLERİ ---

// 1. Günün Sözü (Veritabanından öncelikli, yoksa JSON'dan)
if (!empty($site_settings['daily_quote'])) {
    $quote_data = [
        'quote' => $site_settings['daily_quote'],
        'author' => $site_settings['daily_quote_author'] ?? 'Blogium'
    ];
} else {
    // Yedek olarak JSON dosyası
    $quote_file = __DIR__ . '/quote_of_the_day.json';
    $quote_data = ['quote' => 'Sitemize hoş geldiniz!', 'author' => 'Blogium'];
    if (file_exists($quote_file)) {
        $quote_data = json_decode(file_get_contents($quote_file), true) ?: $quote_data;
    }
}

// 2. Kullanıcının Kaydettiği Yazıları Çek
$saved_post_ids = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt_saved = $pdo->prepare("SELECT post_id FROM saved_posts WHERE user_id = ?");
        $stmt_saved->execute([$_SESSION['user_id']]);
        $saved_post_ids = $stmt_saved->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Hata olursa boş dizi kalsın
    }
}

// 3. Son Yazılar (Sayfalama Mantığı ile)
try {
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM posts");
    $total_posts = $total_stmt->fetchColumn();
    $total_pages = ceil($total_posts / $limit);

    $stmt_recent = $pdo->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt_recent->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_recent->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_recent->execute();
    $recent_posts = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $recent_posts = [];
    $total_pages = 0;
    error_log("Anasayfa son yazılar çekilirken DB hatası: " . $e->getMessage());
}

// 4. Popüler Yazılar (Sidebar için - En çok okunanlar)
try {
    $stmt_popular = $pdo->query("SELECT title, slug FROM posts ORDER BY views DESC LIMIT 5");
    $popular_posts = $stmt_popular->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $popular_posts = [];
}

// 5. Rastgele Yazılar (Limit: 5)
try {
    $stmt_random = $pdo->query("SELECT title, slug FROM posts ORDER BY RAND() LIMIT 5");
    $random_posts = $stmt_random->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $random_posts = [];
    error_log("Rastgele yazılar çekilirken DB hatası: " . $e->getMessage());
}

// Helper Fonksiyonlar
function format_turkish_date($date_string) {
    if (empty($date_string)) return '';
    $timestamp = strtotime($date_string);
    $aylar = [1=>'Ocak', 2=>'Şubat', 3=>'Mart', 4=>'Nisan', 5=>'Mayıs', 6=>'Haziran', 7=>'Temmuz', 8=>'Ağustos', 9=>'Eylül', 10=>'Ekim', 11=>'Kasım', 12=>'Aralık'];
    return date('d', $timestamp) . ' ' . $aylar[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

function format_number_short($number) {
    if (!is_numeric($number)) return 0;
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . ' Mn';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . ' Bin';
    }
    return $number;
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($page_keywords) ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:image" content="<?= $page_image ?>">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= $page_url ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="twitter:image" content="<?= $page_image ?>">
    
    <?php if(!empty($site_settings['favicon_url'])): ?>
    <link rel="icon" href="<?= htmlspecialchars($site_settings['favicon_url']) ?>" type="image/x-icon">
    <?php endif; ?>

    <meta name="google-adsense-account" content="ca-pub-9387325939432547">
    
    <style>
    body { background-color: #f8fafc; color: #334155; font-family: 'Inter', sans-serif; }
    
    /* Quote Ticker */
    .quote-ticker-wrapper { background-color: #1e293b; color: #e2e8f0; padding: 12px 0; width: 100%; overflow: hidden; white-space: nowrap; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .quote-ticker p { display: inline-block; padding-left: 100%; animation: marquee 35s linear infinite; margin: 0; font-size: 14px; }
    .quote-ticker p strong { color: #fff; font-weight: 600; }
    @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
    
    /* Layout */
    .main-content-wrapper { display: grid; grid-template-columns: 300px 1fr 300px; gap: 30px; max-width: 1440px; margin: 40px auto; padding: 0 20px; align-items: flex-start; }
    
    /* Sidebar Styles */
    .sidebar { display: flex; flex-direction: column; gap: 30px; position: sticky; top: 100px; } /* Top değeri header yüksekliğine göre ayarlanmalı */
    
    .sidebar-widget { background-color: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
    .sidebar-widget-title { font-size: 18px; font-weight: 600; color: #1e293b; margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .sidebar-post-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 15px; }
    .sidebar-post-list li a { color: #475569; text-decoration: none; font-size: 15px; line-height: 1.5; transition: color 0.2s; }
    .sidebar-post-list li a:hover { color: #667eea; }
    
    .popular-posts-list { counter-reset: popular-counter; }
    .popular-posts-list li { opacity: 0; animation: fadeInSlideUp 0.5s ease-out forwards; display: flex; align-items: flex-start; }
    .popular-posts-list li::before { counter-increment: popular-counter; content: counter(popular-counter); font-size: 14px; font-weight: 700; color: #cbd5e1; margin-right: 15px; min-width: 20px; text-align: right; }
    <?php for ($i = 1; $i <= 5; $i++): ?>
    .popular-posts-list li:nth-child(<?= $i ?>) { animation-delay: <?= $i * 0.1 ?>s; }
    <?php endfor; ?>
    @keyframes fadeInSlideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    /* Main Content */
    .main-content { padding: 0; }
    .post-grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 30px; }
    .no-posts-message { grid-column: 1 / -1; text-align: center; font-size: 18px; color: #64748b; padding: 50px; background-color: #fff; border-radius:16px; }
    
    /* Post Card Styles */
    .post-card { background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; position: relative; }
    .post-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); }
    
    .post-image-wrapper { width: 100%; aspect-ratio: 16 / 9; overflow: hidden; position: relative; }
    .post-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; display: block; }
    .post-card:hover .post-image { transform: scale(1.05); }
    
    /* Save Button */
    .save-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 36px;
        height: 36px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1e293b;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        cursor: pointer;
        z-index: 10;
        transition: all 0.2s ease;
        text-decoration: none;
        backdrop-filter: blur(4px);
    }
    .save-btn:hover {
        background-color: #000;
        color: #fff;
        transform: scale(1.1);
    }

    .post-content { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
    
    .post-title { margin: 0 0 10px 0; line-height: 1.4; }
    .post-title a { font-size: 20px; font-weight: 600; color: #1e293b; text-decoration: none; transition: color 0.2s; }
    .post-title a:hover { color: #667eea; }
    
    .post-snippet { font-size: 14px; color: #475569; line-height: 1.6; margin: 0; flex-grow: 1; margin-bottom: 20px; }
    .post-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 15px; }
    .post-date { font-size: 13px; font-weight: 500; color: #64748b; }
    .post-stats { display: flex; align-items: center; gap: 15px; font-size: 13px; color: #64748b; }
    .post-stats span { display: flex; align-items: center; gap: 5px; }

    /* Pagination */
    .pagination-wrapper {
        margin-top: 50px;
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
    .page-link.disabled {
        opacity: 0.5;
        pointer-events: none;
        background-color: #f1f5f9;
    }

    /* Ads */
    .ad-placeholder { width: 100%; min-height: 100px; display: flex; align-items: center; justify-content: center; background-color: #e2e8f0; border: 2px dashed #cbd5e1; border-radius: 12px; font-size: 14px; font-weight: 500; color: #64748b; text-align: center; }
    .ad-placeholder small { display: block; margin-top: 5px; color: #475569; }
    .top-banner-ad-container { max-width: 1440px; margin: 0 auto 30px auto; padding: 0 20px; }
    .ad-card-in-grid { grid-column: 1 / -1; padding: 20px; background-color: #fff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
    
    @media (max-width: 1200px) {
        .main-content-wrapper { grid-template-columns: 1fr; }
        .sidebar { display: none; } 
    }
    
    @media (max-width: 768px) {
        .main-content-wrapper { margin-top: 20px; padding: 0 15px; }
        .post-grid-container { grid-template-columns: 1fr; gap: 20px; }
        .post-title a { font-size: 18px; }
        .top-banner-ad-container { margin-bottom: 20px; padding: 0; }
    }
    </style>
</head>
<body>

<div class="quote-ticker-wrapper">
    <div class="quote-ticker">
        <p><?= htmlspecialchars($quote_data['quote']) ?> — <strong><?= htmlspecialchars($quote_data['author']) ?></strong></p>
    </div>
</div>

<div class="top-banner-ad-container">
    <div class="ad-placeholder">
        <span>ÜST BANNER REKLAM ALANI <br><small>(Örn: 970x90 veya 728x90)</small></span>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9387325939432547" crossorigin="anonymous"></script>
            <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9387325939432547" data-ad-slot="1531240293" data-ad-format="auto" data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
</div>


<div class="main-content-wrapper">
    <aside class="left-sidebar sidebar">
        <?php if (!empty($popular_posts)): ?>
        <div class="sidebar-widget">
            <h3 class="sidebar-widget-title">Popüler Yazılar</h3>
            <ol class="sidebar-post-list popular-posts-list">
                <?php foreach ($popular_posts as $post): ?>
                    <li><a href="/yazi/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
    </aside>

    <main class="main-content">
        <div class="post-grid-container">
            <?php if (empty($recent_posts)): ?>
                <p class="no-posts-message">Henüz hiç gönderi yayınlanmamış.</p>
            <?php else: ?>
                <?php 
                $post_counter = 0;
                foreach ($recent_posts as $post): 
                    $post_counter++;
                    // Kullanıcı bu yazıyı daha önce kaydetmiş mi?
                    $is_saved = in_array($post['id'], $saved_post_ids);
                    $icon_class = $is_saved ? 'fas fa-bookmark' : 'far fa-bookmark';
                    $post_link = "/yazi/" . urlencode($post['slug']);
                ?>
                    <div class="post-card">
                        <div class="post-image-wrapper">
                            <a href="javascript:void(0)" 
                               class="save-btn" 
                               title="Kaydet"
                               onclick="toggleSave(this, <?= $post['id'] ?>)">
                                <i class="<?= $icon_class ?>"></i>
                            </a>
                            
                            <a href="<?= $post_link ?>">
                                <img src="<?= !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : 'assets/img/no-image.png' ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-image" fetchpriority="high">
                            </a>
                        </div>
                        <div class="post-content">
                            <h2 class="post-title">
                                <a href="<?= $post_link ?>"><?= htmlspecialchars($post['title']) ?></a>
                            </h2>
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
                    </div>

                <?php 
                // 6. yazıdan sonra reklam kartını göster
                if ($post_counter == 6): ?>
                    <div class="ad-card-in-grid">
                        <div class="ad-placeholder">
                            <span>YAZI ARASI REKLAM ALANI<br><small>(Örn: 300x250 veya Responsive)</small></span>
                            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9387325939432547" crossorigin="anonymous"></script>
                            <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9387325939432547" data-ad-slot="6971269235" data-ad-format="auto" data-full-width-responsive="true"></ins>
                            <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
                        </div>
                    </div>
                <?php endif; ?>
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
        </main>

    <aside class="right-sidebar sidebar">
        <?php if (!empty($random_posts)): ?>
        <div class="sidebar-widget">
            <h3 class="sidebar-widget-title">Rastgele Yazılar</h3>
            <ul class="sidebar-post-list">
                <?php foreach ($random_posts as $post): ?>
                    <li><a href="/yazi/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="sidebar-widget">
             <div class="ad-placeholder" style="min-height: 250px;">
                <span>SAĞ DİKEY REKLAM<br><small>(Örn: 300x600)</small></span>
             </div>
        </div>
    </aside>
</div>

<?php
include __DIR__ . '/includes/footer.php'; 
?>
<script>
function toggleSave(btn, postId) {
    const icon = btn.querySelector('i');

    fetch('api_save_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.action === 'saved') {
                icon.classList.remove('far'); 
                icon.classList.add('fas');    
            } else if (data.action === 'removed') {
                icon.classList.remove('fas'); 
                icon.classList.add('far');    
            }
        } else if (data.status === 'not_logged_in') {
            window.location.href = '/signin.php?redirect=' + encodeURIComponent(window.location.pathname);
        } else {
            console.error('İşlem başarısız:', data.message);
        }
    })
    .catch(error => {
        console.error('Hata:', error);
    });
}
</script>
</body>
</html>