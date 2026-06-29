<?php
/**
 * Post Görüntüleme Sayfası - v11 (Nihai Sürüm)
 * Tüm özellikler (AJAX yorum, dinamik mesaj vb.) entegre edilmiştir.
 * Bu sürüm, istikrarlı ve tam işlevseldir.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bu fonksiyon hem sayfa içinde hem de AJAX yanıtında kullanılacağı için en başta tanımlanmıştır.
function format_turkish_date($date_string) {
    if (empty($date_string)) return 'Bilinmiyor';
    try {
        $date = new DateTime($date_string);
        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        return $date->format('d') . ' ' . $aylar[$date->format('n') - 1] . ' ' . $date->format('Y');
    } catch (Exception $e) { return 'Geçersiz Tarih'; }
}

require_once __DIR__ . '/adminpanel/includes/db.php';
if (!isset($pdo)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Kritik: Veritabanı bağlantısı kurulamadı.']);
        exit;
    }
    die("Kritik Sistem Hatası: Veritabanı bağlantısı kurulamadı.");
}


// --- AJAX YORUM İŞLEME BLOĞU ---
// Bu blok, sadece JavaScript'ten gelen özel bir POST isteği olduğunda çalışır ve JSON yanıtı döndürüp işlemi sonlandırır.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_comment'])) {
    
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Geçersiz yorum isteği.'];

    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
        $content = trim($_POST['content'] ?? '');

        $post_stmt = $pdo->prepare("SELECT allow_comments FROM posts WHERE id = ?");
        $post_stmt->execute([$post_id]);
        $post_settings = $post_stmt->fetch(PDO::FETCH_ASSOC);
        $allow_comments = $post_settings['allow_comments'] ?? 'no';

        if ($post_id && !empty($content) && $allow_comments !== 'no') {
            try {
                $current_time_for_db = date('Y-m-d H:i:s');
                $comment_status = ($allow_comments === 'moderated') ? 'pending' : 'approved';
                
                $comment_stmt = $pdo->prepare("INSERT INTO comments (post_id, author, content, status, created_at) VALUES (?, ?, ?, ?, ?)");
                $comment_stmt->execute([$post_id, $_SESSION['username'], $content, $comment_status, $current_time_for_db]);
                
                if ($comment_status === 'approved') {
                    $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?")->execute([$post_id]);
                    $author = $_SESSION['username'];
                    $response = [
                        'status' => 'success',
                        'message' => 'Yorumunuz başarıyla yayınlandı. Teşekkür ederiz!',
                        'comment' => [
                            'author' => htmlspecialchars($author),
                            'content' => nl2br(htmlspecialchars($content)),
                            'created_at' => format_turkish_date($current_time_for_db),
                            'author_initial' => htmlspecialchars(strtoupper(substr($author, 0, 1)))
                        ]
                    ];
                } else {
                    $response = [
                        'status' => 'info', 
                        'message' => 'Yorumunuz editör onayı için gönderildi. Teşekkürler!'
                    ];
                }
            } catch (PDOException $e) {
                error_log("AJAX Comment Error: " . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'Veritabanı hatası nedeniyle yorum eklenemedi.'];
            }
        } elseif (empty($content)) {
            $response = ['status' => 'error', 'message' => 'Yorum alanı boş bırakılamaz.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Bu yazıya yorum yapılamaz.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Yorum yapmak için giriş yapmalısınız.'];
    }

    echo json_encode($response);
    exit;
}


// --- NORMAL SAYFA YÜKLENME AKIŞI ---

$slug = $_GET['slug'] ?? null;
if (empty($slug)) {
    http_response_code(400); $page_title = "Hatalı İstek";
    include __DIR__ . '/includes/header.php';
    echo "<main class='post-page-container'><div class='container-center'><p>Geçersiz istek.</p></div></main>";
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name as category_name, c.slug as category_slug
         FROM posts p LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.slug = ? LIMIT 1"
    );
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $update_view_stmt = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
        $update_view_stmt->execute([$post['id']]);
    }
} catch (PDOException $e) {
    http_response_code(500); error_log("DB Sorgu Hatası: " . $e->getMessage()); $page_title = "Sunucu Hatası";
    include __DIR__ . '/includes/header.php';
    echo "<main class='post-page-container'><div class='container-center'><p>Veritabanı sorunu.</p></div></main>";
    exit;
}

if ($post === false) {
    http_response_code(404); $page_title = "Yazı Bulunamadı";
    include __DIR__ . '/includes/header.php';
    echo "<main class='post-page-container'><div class='container-center'><p>Aradığınız yazı bulunamadı.</p></div></main>";
    exit;
}

$post_id = (int)($post['id'] ?? 0);
$post_title = $post['title'] ?? 'Başlık Bulunamadı';
$post_content = $post['content'] ?? '<p>İçerik bulunamadı.</p>';
$post_image = $post['image_url'] ?? '';
$post_created_at = $post['created_at'] ?? null;
$allow_comments = $post['allow_comments'] ?? 'yes';
$like_count = (int)($post['like_count'] ?? 0);
$save_count = (int)($post['save_count'] ?? 0);
$comment_count = (int)($post['comment_count'] ?? 0);
$category_id = (int)($post['category_id'] ?? 0);
$category_name = $post['category_name'] ?? 'Kategorisiz';
$category_slug = $post['category_slug'] ?? '#';
$tags = !empty($post['tags']) ? explode(',', $post['tags']) : [];
$is_logged_in = isset($_SESSION['user_id']);

$user_has_liked = false; $user_has_saved = false;
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    try {
        $like_stmt = $pdo->prepare("SELECT id FROM user_likes WHERE user_id = ? AND post_id = ?");
        $like_stmt->execute([$user_id, $post_id]);
        if ($like_stmt->fetch()) { $user_has_liked = true; }

        $save_stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
        $save_stmt->execute([$user_id, $post_id]);
        if ($save_stmt->fetch()) { $user_has_saved = true; }
    } catch (PDOException $e) { error_log("Etkileşim hatası: " . $e->getMessage()); }
}

$comments = []; $related_posts = [];
try {
    $commentStmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at DESC");
    $commentStmt->execute([$post_id]);
    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($category_id > 0) {
        $relatedStmt = $pdo->prepare("SELECT title, slug, image_url FROM posts WHERE category_id = ? AND id != ? ORDER BY RAND() LIMIT 3");
        $relatedStmt->execute([$category_id, $post_id]);
        $related_posts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { error_log("Ek veri çekme hatası: " . $e->getMessage()); }

$page_title = $post['title'] ;
$page_description = mb_substr(strip_tags($post['content']), 0, 160);
$page_keywords = $post['tags'] ?? 'blog, teknoloji, kültür, girişimcilik, dijital, güncel haberler';
$page_url = "https://www.blogium.net/yazi/" . $post['slug']; 
$page_image = !empty($post['image_url']) ? "https://www.blogium.net/" . ltrim($post['image_url'], '/') : "https://www.blogium.net/logo.png";

include __DIR__ . '/includes/header.php'; 
include __DIR__ . '/includes/head.php';
?>
<meta name="google-adsense-account" content="ca-pub-9387325939432547">
<style>
    :root { --primary-color: #667eea; --primary-hover: #5a67d8; }
    .main-wrapper { display: grid; grid-template-columns: 1fr minmax(auto, 840px) 1fr; gap: 30px; max-width: 1400px; margin: 0 auto; padding: 0 20px; align-items: flex-start; }
    .left-ad-column, .right-ad-column { padding-top: 40px; }
    .sticky-ad-placeholder { position: -webkit-sticky; position: sticky; top: 20px; width: 100%; min-height: 250px; display: flex; align-items: center; justify-content: center; background-color: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; font-size: 14px; color: #94a3b8; text-align: center; padding: 20px; }
    .sticky-ad-placeholder small { display: block; margin-top: 5px; }
    .post-page-container { margin: 40px 0; padding: 0; width: 100%; }
    .post-content-area { background-color: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); overflow: hidden; }
    .post-header { padding: 40px 40px 20px 40px; }
    .category-badge { display: inline-block; background-color: #eef2ff; color: #4338ca; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 15px; }
    .post-main-title { font-size: 38px; font-weight: 800; color: #1e293b; line-height: 1.2; margin: 0 0 20px 0; }
    .post-meta-info { display: flex; align-items: center; gap: 15px; font-size: 14px; color: #64748b; }
    .featured-image-wrapper { width: 100%; max-height: 450px; overflow: hidden; }
    .featured-image { width: 100%; height: 100%; object-fit: cover; }
    .post-body { padding: 40px; font-size: 18px; line-height: 1.75; color: #334155; }
    .post-body p, .post-body ul, .post-body ol, .post-body blockquote { margin-bottom: 1.5em; }
    .post-body h2, .post-body h3 { font-weight: 700; color: #1e293b; margin: 2em 0 1em 0; }
    .post-body a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
    .post-body blockquote { border-left: 4px solid var(--primary-color); padding-left: 20px; font-style: italic; color: #475569; }
    .post-footer { padding: 0 40px 40px 40px; }
    .post-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
    .tag-link { background-color: #f1f5f9; color: #475569; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px; }
    .post-actions { display: flex; gap: 15px; padding: 20px 0; border-top: 1px solid #f1f5f9; }
    .action-btn { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 25px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .action-btn:hover { background: #f1f5f9; transform: translateY(-2px); }
    .action-btn.liked { background-color: #fdf2f8; color: #be185d; border-color: #fbcfe8; }
    .action-btn.saved { background-color: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
    .related-posts-section, .comments-section, .ad-placeholder-wrapper { margin-top: 60px; }
    .section-title { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 25px; text-align: center; }
    .post-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
    .related-post-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); overflow: hidden; transition: all 0.3s; text-decoration: none; }
    .related-post-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
    .related-post-card .card-image { width: 100%; height: 160px; object-fit: cover; }
    .related-post-card .card-content { padding: 20px; }
    .related-post-card .card-title { font-size: 16px; font-weight: 600; color: #1e293b; margin: 0; line-height: 1.4; }
    .comment-form-wrapper, .comments-list-container { background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); }
    .comments-list-container { margin-top: 30px; padding-top: 10px; padding-bottom: 10px; }
    .comment-form .form-header { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
    .comment-form textarea { width: 100%; height: 120px; padding: 15px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 16px; resize: vertical; }
    .comment-form .form-footer { display: flex; justify-content: flex-end; align-items: center; margin-top: 10px; }
    .comment-form .action-btn { background: var(--primary-color); color: #fff; border: none; }
    .comment-card { display: flex; gap: 15px; padding: 20px 0; border-bottom: 1px solid #f1f5f9; }
    .comment-card:last-child { border-bottom: none; }
    .comment-avatar { width: 40px; height: 40px; background: #eef2ff; color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
    .comment-content .comment-header { display: flex; align-items: baseline; gap: 10px; margin-bottom: 5px; }
    .comment-header strong { font-weight: 600; }
    .comment-date { font-size: 13px; color: #94a3b8; }
    .comments-closed-prompt { text-align: center; padding: 40px; color: #64748b; }
    .login-prompt { text-align: center; padding: 30px; background-color: #f8fafc; border-radius: 12px; }
    .login-prompt p { font-size: 16px; margin: 0 0 15px 0; }
    .login-prompt .action-btn { background: var(--primary-color); color: #fff; border: none; }
    .no-comments-prompt { text-align: center; padding: 50px 20px; background-color: #fff; border-radius: 12px; margin-top: 30px; color: #475569; }
    .no-comments-prompt i { font-size: 28px; color: var(--primary-color); margin-bottom: 15px; display: block; }
    .no-comments-prompt p { font-size: 16px; font-weight: 500; margin: 0; }
    .mobile-action-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 10px 15px; box-shadow: 0 -5px 20px rgba(0,0,0,0.08); display: none; align-items: center; gap: 10px; z-index: 999; transform: translateY(0); transition: transform 0.3s ease-in-out; }
    .mobile-action-bar.is-hidden { transform: translateY(100%); }
    .mobile-action-bar .action-btn { padding: 10px; width: 45px; height: 45px; justify-content: center; font-size: 18px; }
    .mobile-action-bar .action-btn span { display: none; }
    .mobile-action-bar .comment-cta-btn { flex-grow: 1; height: 45px; text-decoration: none; background: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 600; border-radius: 12px; }
    .ad-placeholder { width: 100%; min-height: 100px; display: flex; align-items: center; justify-content: center; background-color: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; font-size: 14px; color: #94a3b8; text-align: center; padding: 20px; }
    .ad-placeholder small { display: block; margin-top: 5px; }
    
    #comment-message-box { margin-bottom: 20px; border-radius: 12px; padding: 15px 20px; display: none; opacity: 0; transition: opacity 0.4s ease-in-out; border: 1px solid transparent; font-weight: 500; }
    #comment-message-box.show { display: block; opacity: 1; }
    #comment-message-box.success { background-color: #dcfce7; border-color: #bbf7d0; color: #166534; }
    #comment-message-box.info { background-color: #e0f2fe; border-color: #bae6fd; color: #0369a1; }
    #comment-message-box.error { background-color: #fee2e2; border-color: #fecaca; color: #b91c1c; }

    @media (max-width: 1200px) { .main-wrapper { grid-template-columns: 1fr; padding: 0; } .left-ad-column, .right-ad-column { display: none; } .post-page-container { margin: 0 0 100px 0; } }
    @media (max-width: 768px) { .post-content-area { border-radius: 0; } .post-header, .post-body, .post-footer { padding-left: 25px; padding-right: 25px; } .post-header { padding-top: 25px; padding-bottom: 20px; } .post-body { padding-top: 30px; padding-bottom: 30px; font-size: 17px; } .post-main-title { font-size: 28px; } .post-grid { grid-template-columns: 1fr; gap: 15px; padding: 0 15px; } .comments-section, .related-posts-section, .ad-placeholder-wrapper { margin-top: 40px; } .post-actions.desktop-actions { display: none; } .mobile-action-bar { display: flex; } .post-page-container { margin-bottom: 0; } }
</style>

<div class="main-wrapper">
    <aside class="left-ad-column">
        <div class="sticky-ad-placeholder"><span>SOL REKLAM ALANI <br><small>(Örn: 160x600)</small></span></div>
    </aside>
    
    <main class="post-page-container">
        <article class="post-content-area">
            <div class="post-header">
                <a href="/kategori/<?= htmlspecialchars($category_slug) ?>" class="category-badge"><?= htmlspecialchars($category_name) ?></a>
                <h1 class="post-main-title"><?= htmlspecialchars($post_title) ?></h1>
                <div class="post-meta-info">
                    <span>Blogium Ekibi</span> •
                    <time datetime="<?= date('Y-m-d', strtotime($post_created_at ?? 'now')) ?>"><?= format_turkish_date($post_created_at) ?></time>
                </div>
            </div>
            <?php if ($post_image): ?>
                <div class="featured-image-wrapper"><img src="/<?= htmlspecialchars(ltrim($post_image, '/')) ?>" alt="<?= htmlspecialchars($post_title) ?>" class="featured-image"></div>
            <?php endif; ?>
            
            <div class="post-body"><?= $post_content ?></div>

            <div class="post-footer">
                <?php if (!empty($tags)): ?>
                    <div class="post-tags">
                        <?php foreach ($tags as $tag): ?><a href="/search.php?q=<?= urlencode(trim($tag)) ?>" class="tag-link">#<?= htmlspecialchars(trim($tag)) ?></a><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="post-actions desktop-actions">
                    <button class="action-btn like-btn <?= $user_has_liked ? 'liked' : '' ?>" data-post-id="<?= $post_id ?>" data-action="like"><i class="fas fa-heart"></i><span><?= $user_has_liked ? 'Beğenildi' : 'Beğen' ?></span>(<span class="like-count"><?= $like_count ?></span>)</button>
                    <button class="action-btn save-btn <?= $user_has_saved ? 'saved' : '' ?>" data-post-id="<?= $post_id ?>" data-action="save"><i class="fas fa-bookmark"></i><span><?= $user_has_saved ? 'Kaydedildi' : 'Kaydet' ?></span>(<span class="save-count"><?= $save_count ?></span>)</button>
                </div>
            </div>
        </article>

        <section class="ad-placeholder-wrapper">
            <div class="ad-placeholder"><span>REKLAM ALANI <br><small>(Örn: 728x90)</small></span></div>
        </section>

        <?php if (!empty($related_posts)): ?>
        <section class="related-posts-section">
            <h2 class="section-title">Bunlar da Hoşunuza Gidebilir</h2>
            <div class="post-grid">
                <?php foreach ($related_posts as $related): ?>
                <a href="/yazi/<?= urlencode($related['slug']) ?>" class="related-post-card">
                    <img src="/<?= htmlspecialchars(ltrim($related['image_url'], '/') ?? 'assets/images/default.png') ?>" alt="<?= htmlspecialchars($related['title']) ?>" class="card-image">
                    <div class="card-content"><h3 class="card-title"><?= htmlspecialchars($related['title']) ?></h3></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <section class="ad-placeholder-wrapper">
            <div class="ad-placeholder"><span>REKLAM ALANI <br><small>(Örn: 300x250)</small></span></div>
        </section>

        <section id="comments-section" class="comments-section">
            <h2 id="comments-count-title" class="section-title">Yorumlar (<?= $comment_count ?>)</h2>
            
            <?php if ($allow_comments !== 'no'): ?>
                <div class="comment-form-wrapper">
                    <?php if ($is_logged_in): ?>
                        <div id="comment-message-box"></div>
                        <form id="comment-form" action="/yazi/<?= urlencode($slug) ?>" method="POST" class="comment-form">
                            <input type="hidden" name="post_id" value="<?= $post_id ?>">
                            <div class="form-header">
                                <div class="comment-avatar"><i class="fas fa-user"></i></div><span><strong><?= htmlspecialchars($_SESSION['username']) ?></strong> olarak yorum yap</span>
                            </div>
                            <textarea name="content" placeholder="Düşüncelerinizi bizimle paylaşın..." required></textarea>
                            <div class="form-footer"><button type="submit" name="submit_comment" class="action-btn">Yorumu Gönder</button></div>
                        </form>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p>Sohbete katılmak için giriş yapın.</p>
                            <a href="/signin.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="action-btn">Giriş Yap veya Kayıt Ol</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="comments-list-container">
                    <div id="comments-list">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-card">
                            <div class="comment-avatar"><?= htmlspecialchars(strtoupper(substr($comment['author'], 0, 1))) ?></div>
                            <div class="comment-content">
                                <div class="comment-header"><strong><?= htmlspecialchars($comment['author']) ?></strong><span class="comment-date"><?= format_turkish_date($comment['created_at']) ?></span></div>
                                <div class="comment-body"><p><?= nl2br(htmlspecialchars($comment['content'])) ?></p></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="no-comments-prompt" class="no-comments-prompt" style="<?= !empty($comments) ? 'display: none;' : '' ?>">
                        <i class="fas fa-comment-dots"></i>
                        <p>Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
                    </div>
                </div>

            <?php else: ?>
                <div class="comments-closed-prompt"><p><i class="fas fa-lock"></i> Bu yazıya yorumlar kapatılmıştır.</p></div>
            <?php endif; ?>
        </section>
    </main>

    <aside class="right-ad-column">
        <div class="sticky-ad-placeholder"><span>SAĞ REKLAM ALANI <br><small>(Örn: 160x600)</small></span></div>
    </aside>
</div>

<div class="mobile-action-bar">
    <button class="action-btn like-btn <?= $user_has_liked ? 'liked' : '' ?>" data-post-id="<?= $post_id ?>" data-action="like"><i class="fas fa-heart"></i><span class="like-count"><?= $like_count ?></span></button>
    <button class="action-btn save-btn <?= $user_has_saved ? 'saved' : '' ?>" data-post-id="<?= $post_id ?>" data-action="save"><i class="fas fa-bookmark"></i><span class="save-count"><?= $save_count ?></span></button>
    <?php if ($is_logged_in): ?><a href="#comments-section" class="comment-cta-btn">Yorum Yap</a><?php else: ?><a href="/signin.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="comment-cta-btn">Giriş Yap ve Yorum Yap</a><?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isUserLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;

    document.querySelectorAll('.action-btn[data-post-id][data-action]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (!isUserLoggedIn) { 
                window.location.href = '/signin.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return; 
            }
            const postId = this.dataset.postId;
            const actionType = this.dataset.action;
            const handlerUrl = actionType === 'like' ? '/handle_like.php' : '/handle_save.php';
            const allButtons = document.querySelectorAll(`.action-btn[data-post-id="${postId}"][data-action="${actionType}"]`);
            
            fetch(handlerUrl, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `post_id=${postId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    allButtons.forEach(btn => {
                        const countSpan = btn.querySelector(`.${actionType}-count`);
                        const textSpan = btn.querySelector('span:not([class])');
                        let newCount = actionType === 'like' ? data.new_like_count : data.new_save_count;
                        if (countSpan) { countSpan.textContent = newCount; }
                        btn.classList.toggle(actionType === 'like' ? 'liked' : 'saved', data.action === actionType + 'd');
                        if(textSpan) {
                             textSpan.textContent = data.action === actionType + 'd' ? (actionType === 'like' ? 'Beğenildi' : 'Kaydedildi') : (actionType === 'like' ? 'Beğen' : 'Kaydet');
                        }
                    });
                } else { 
                    alert(data.message || 'Bir hata oluştu.'); 
                }
            })
            .catch(error => console.error('Hata:', error));
        });
    });

    const mobileActionBar = document.querySelector('.mobile-action-bar');
    if (mobileActionBar) {
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                mobileActionBar.classList.add('is-hidden');
            } else {
                mobileActionBar.classList.remove('is-hidden');
            }
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }, { passive: true });
    }

    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        const messageBox = document.getElementById('comment-message-box');
        const commentTextarea = commentForm.querySelector('textarea');

        commentForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const submitButton = commentForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = 'Gönderiliyor...';
            messageBox.classList.remove('show', 'success', 'info', 'error');
            const formData = new FormData(commentForm);
            formData.append('ajax_comment', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const showMessage = (type, text) => {
                    messageBox.textContent = text;
                    messageBox.className = '';
                    messageBox.classList.add(type, 'show');
                    setTimeout(() => {
                        messageBox.classList.remove('show');
                    }, 5000);
                };

                if (data.status === 'success') {
                    showMessage('success', data.message);
                    commentTextarea.value = ''; 
                    const newCommentHtml = `<div class="comment-card" style="opacity:0; transform: translateY(-20px); transition: all 0.4s ease-out;"><div class="comment-avatar">${data.comment.author_initial}</div><div class="comment-content"><div class="comment-header"><strong>${data.comment.author}</strong><span class="comment-date">${data.comment.created_at}</span></div><div class="comment-body"><p>${data.comment.content}</p></div></div></div>`;
                    const commentsList = document.getElementById('comments-list');
                    commentsList.insertAdjacentHTML('afterbegin', newCommentHtml);
                    const newCommentElement = commentsList.firstElementChild;
                    setTimeout(() => { newCommentElement.style.opacity = '1'; newCommentElement.style.transform = 'translateY(0)'; }, 50);
                    const noCommentsPrompt = document.getElementById('no-comments-prompt');
                    if (noCommentsPrompt) { noCommentsPrompt.style.display = 'none'; }
                    const commentsTitle = document.getElementById('comments-count-title');
                    const currentCountText = commentsTitle.innerText.match(/\((\d+)\)/);
                    const currentCount = currentCountText ? parseInt(currentCountText[1]) : 0;
                    commentsTitle.innerText = `Yorumlar (${currentCount + 1})`;
                } else if (data.status === 'info') {
                    showMessage('info', data.message);
                    commentTextarea.value = ''; 
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(error => {
                console.error('Yorum gönderme hatası:', error);
                showMessage('error', 'Ağ hatası: Yorumunuz gönderilemedi.');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }
});
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>