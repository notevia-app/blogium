<?php
/**
 * Blogium - Gelişmiş Yazı Listesi
 * Modern ve Görsel Odaklı Tasarım
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

// Arama Mantığı (Basit)
$search = $_GET['q'] ?? '';
$params = [];
$sql = "SELECT p.*, c.name as category_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id";

if ($search) {
    $sql .= " WHERE p.title LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Veri hatası: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        /* Bu sayfaya özel stiller */
        .post-thumb {
            width: 60px; height: 40px; 
            object-fit: cover; border-radius: 6px;
            background-color: #e2e8f0;
            border: 1px solid #cbd5e1;
        }
        .search-container {
            display: flex; gap: 10px; max-width: 400px;
        }
        .search-input {
            padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); width: 100%;
            font-size: 0.9rem; outline: none; transition: 0.2s;
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .post-meta { display: flex; flex-direction: column; }
        .post-title-text { font-weight: 600; color: var(--text-main); font-size: 0.95rem; }
        .post-slug { color: var(--secondary); font-size: 0.8rem; }
        .empty-icon { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Yazılar</h1>
                <p class="text-muted">Toplam <?= count($posts) ?> içerik listeleniyor.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <form action="" method="GET" class="search-container">
                    <input type="text" name="q" class="search-input" placeholder="Yazı ara..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                </form>
                <a href="add_post.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> <span class="d-none-mobile">Yeni Ekle</span>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Görsel</th>
                                <th>Başlık / URL</th>
                                <th>Kategori</th>
                                <th style="text-align:center;">Okunma</th>
                                <th>Tarih</th>
                                <th style="text-align:right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($posts)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 3rem;">
                                        <i class="fas fa-folder-open empty-icon"></i>
                                        <p class="text-muted">Aradığınız kriterlere uygun yazı bulunamadı.</p>
                                        <?php if($search): ?>
                                            <a href="posts.php" class="btn btn-sm btn-secondary">Aramayı Temizle</a>
                                        <?php else: ?>
                                            <a href="add_post.php" class="btn btn-sm btn-primary">İlk Yazıyı Ekle</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($posts as $post): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($post['image_url'])): ?>
                                                <img src="../<?= htmlspecialchars($post['image_url']) ?>" class="post-thumb" alt="Kapak">
                                            <?php else: ?>
                                                <div class="post-thumb" style="display:flex; align-items:center; justify-content:center;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="post-meta">
                                                <span class="post-title-text"><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?></span>
                                                <span class="post-slug">/<?= htmlspecialchars($post['slug']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($post['category_name']): ?>
                                                <span class="badge badge-warning" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5;">
                                                    <?= htmlspecialchars($post['category_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted text-sm">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span style="font-weight:600; color:var(--text-main); font-size:0.9rem;">
                                                <?= number_format($post['views']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.85rem; color:var(--secondary);">
                                            <?= date('d.m.Y', strtotime($post['created_at'])) ?>
                                            <div style="font-size:0.75rem; opacity:0.7;"><?= date('H:i', strtotime($post['created_at'])) ?></div>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="../post.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Görüntüle">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <a href="edit_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-secondary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu yazıyı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

    </div>

</body>
</html>