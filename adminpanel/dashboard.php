<?php
/**
 * Blogium - Modern Dashboard v3.1
 * Estetik Grid Tasarımı ve Canlı İstatistikler
 */
// --- OTURUM SÜRESİ AYARI (1 SAAT) ---
ini_set('session.gc_maxlifetime', 3600); // Sunucu tarafında oturumu 1 saat (3600 sn) tut
session_set_cookie_params(3600); // Tarayıcı çerezini 1 saat tut

session_start();

// Eğer son işlemden bu yana 1 saat (3600 saniye) geçtiyse oturumu kapat
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();     // Değişkenleri temizle
    session_destroy();   // Oturumu yok et
    header("Location: index.php?timeout=1"); // Giriş sayfasına yönlendir
    exit;
}

// Son işlem zamanını şu an olarak güncelle
$_SESSION['LAST_ACTIVITY'] = time();
// ------------------------------------
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit; }
require 'includes/db.php';

// --- YARDIMCI FONKSİYONLAR ---
function trDate($date){
    $aylar = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    return date("d", strtotime($date)) . ' ' . $aylar[date("n", strtotime($date))-1] . ' ' . date("Y", strtotime($date));
}

function getGreeting() {
    $hour = date('H');
    if ($hour >= 5 && $hour < 12) return "Günaydın";
    if ($hour >= 12 && $hour < 18) return "Tünaydın";
    if ($hour >= 18 && $hour < 22) return "İyi Akşamlar";
    return "İyi Geceler";
}

// --- VERİLERİ ÇEKME ---
try {
    // 1. Tarih Değişkenleri
    $today = date('Y-m-d');
    $this_month = date('m');
    $this_year = date('Y');

    // 2. Ziyaretçi İstatistikleri (visitor_analytics tablosu varsa)
    // Tablo yoksa hata vermemesi için try-catch içinde basit kontrol yapıyoruz veya 0 döndürüyoruz.
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE DATE(visit_date) = ?");
        $stmt->execute([$today]);
        $count_today = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ?");
        $stmt->execute([$this_month, $this_year]);
        $count_month = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE YEAR(visit_date) = ?");
        $stmt->execute([$this_year]);
        $count_year = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Tablo henüz yoksa 0 varsay
        $count_today = $count_month = $count_year = 0;
    }

    // 3. İçerik Sayaçları
    $total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $pending_comments = $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'")->fetchColumn();

    // 4. Popüler İçerikler
    $popular_posts = $pdo->query("SELECT title, views, slug, created_at FROM posts ORDER BY views DESC LIMIT 5")->fetchAll();

    // 5. Son Yorumlar
    $recent_comments = $pdo->query("
        SELECT c.author, c.content, c.status, c.created_at, p.title as post_title 
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id 
        ORDER BY c.created_at DESC LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $error = "Veri hatası: " . $e->getMessage();
}

$admin_name = $_SESSION['admin_username'] ?? 'Yönetici';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        /* Dashboard'a Özel Ek Stiller */
        .dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .dash-header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; color: var(--text-main); letter-spacing: -0.5px; }
        .dash-header p { margin: 5px 0 0; color: var(--secondary); font-size: 0.95rem; }
        .date-badge { 
            background: #fff; padding: 8px 16px; border-radius: 50px; 
            font-size: 0.9rem; font-weight: 500; color: var(--text-main); 
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); 
            display: flex; align-items: center; gap: 8px;
        }

        .stat-card {
            background: #fff; padding: 1.5rem; border-radius: 16px; 
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); 
            display: flex; align-items: center; gap: 1.2rem; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .stat-data { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }
        .stat-label { font-size: 0.85rem; color: var(--secondary); font-weight: 500; }

        .dash-content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 992px) { .dash-content-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="dash-header">
            <div class="welcome-text">
                <h1><?= getGreeting() ?>, <?= htmlspecialchars($admin_name) ?>! 👋</h1>
                <p>İşte blogunuzun bugünkü durumu.</p>
            </div>
            <div class="date-badge">
                <i class="far fa-calendar-alt text-primary"></i> <?= trDate(date('Y-m-d')) ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= number_format($count_today) ?></span>
                    <span class="stat-label">Bugünkü Ziyaretçi</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= number_format($count_month) ?></span>
                    <span class="stat-label">Bu Ay Ziyaretçi</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= number_format($total_posts) ?></span>
                    <span class="stat-label">Toplam İçerik</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= number_format($pending_comments) ?></span>
                    <span class="stat-label">Onay Bekleyen</span>
                </div>
            </div>
        </div>

        <div class="dash-content-grid">
            
            <div class="dash-card">
                <div class="card-header-clean">
                    <h3>🔥 En Çok Okunanlar</h3>
                    <a href="posts.php" class="link-sm">Tümünü Gör</a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Başlık</th>
                                <th>Tarih</th>
                                <th class="text-right">Görüntülenme</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($popular_posts)): ?>
                                <tr><td colspan="3" class="text-center text-muted p-4">Henüz veri yok.</td></tr>
                            <?php else: ?>
                                <?php foreach($popular_posts as $post): ?>
                                <tr>
                                    <td>
                                        <a href="../post.php?slug=<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="text-main" style="font-weight:500;">
                                            <?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?>
                                        </a>
                                    </td>
                                    <td class="text-muted text-sm"><?= date("d.m.Y", strtotime($post['created_at'])) ?></td>
                                    <td class="text-right">
                                        <span class="badge-pill badge-blue">
                                            <?= number_format($post['views']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-header-clean">
                    <h3>💬 Son Yorumlar</h3>
                    <a href="comments.php" class="link-sm">Yönet</a>
                </div>
                <div class="activity-feed">
                    <?php if(empty($recent_comments)): ?>
                        <div class="text-center text-muted p-4">Henüz yorum yok.</div>
                    <?php else: ?>
                        <?php foreach($recent_comments as $comment): ?>
                        <div class="feed-item">
                            <div class="feed-icon">
                                <?= strtoupper(mb_substr($comment['author'], 0, 1)) ?>
                            </div>
                            <div class="feed-content">
                                <div class="feed-header">
                                    <span class="feed-author"><?= htmlspecialchars($comment['author']) ?></span>
                                    <span class="feed-date"><?= date("d M", strtotime($comment['created_at'])) ?></span>
                                </div>
                                <div class="feed-text">
                                    "<?= htmlspecialchars(mb_strimwidth($comment['content'], 0, 60, '...')) ?>"
                                </div>
                                <div class="feed-meta">
                                    <?php if($comment['status'] == 'pending'): ?>
                                        <span class="badge badge-warning" style="font-size:0.7rem; padding:2px 6px;">Onay Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge badge-success" style="font-size:0.7rem; padding:2px 6px;">Yayında</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="quick-actions-bar">
            <a href="add_post.php" class="action-btn primary"><i class="fas fa-pen"></i> İçerik Ekle</a>
            <a href="comments.php?status=pending" class="action-btn light"><i class="fas fa-check-double"></i> Yorumları Onayla</a>
            <a href="settings.php" class="action-btn light"><i class="fas fa-cog"></i> Ayarlar</a>
            <a href="/" target="_blank" class="action-btn light ml-auto"><i class="fas fa-external-link-alt"></i> Siteyi Görüntüle</a>
        </div>

    </div>

</body>
</html>