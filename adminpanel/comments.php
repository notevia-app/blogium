<?php
/**
 * Blogium - Yorum Yönetimi v3.0
 * Modern Feed Tasarımı ve Hızlı Filtreleme
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

// MESAJ İŞLEMLERİ
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// İŞLEMLER (Onayla / Sil)
if (isset($_GET['action'], $_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $from_status = $_GET['status'] ?? 'all';

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['message'] = 'Yorum onaylandı.';
            $_SESSION['message_type'] = 'alert-success';
        }
    } elseif ($action === 'delete') {
        // Önce yorumun hangi yazıya ait olduğunu bulalım (sayacı düşmek için)
        $stmt = $pdo->prepare("SELECT post_id FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch();
        
        if ($comment) {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
            // Yazıdaki yorum sayısını güncelle
            $pdo->prepare("UPDATE posts SET comment_count = GREATEST(0, comment_count - 1) WHERE id = ?")->execute([$comment['post_id']]);
            
            $_SESSION['message'] = 'Yorum silindi.';
            $_SESSION['message_type'] = 'alert-success';
        }
    }
    header("Location: comments.php?status=$from_status");
    exit;
}

// FİLTRELEME VE VERİ ÇEKME
$status_filter = $_GET['status'] ?? 'all';
$sql = "SELECT c.*, p.title AS post_title, p.slug AS post_slug
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id";

$params = [];
if ($status_filter === 'approved') {
    $sql .= " WHERE c.status = 'approved'";
} elseif ($status_filter === 'pending') {
    $sql .= " WHERE c.status = 'pending'";
}

$sql .= " ORDER BY c.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Veri hatası: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        /* Yorum Sayfasına Özel Stiller */
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-btn { 
            padding: 8px 16px; border-radius: 50px; font-size: 0.9rem; font-weight: 500; 
            border: 1px solid var(--border-color); background: #fff; color: var(--text-muted); cursor: pointer; text-decoration: none; transition: 0.2s;
        }
        .filter-btn:hover { background: #f8fafc; color: var(--text-main); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 2px 5px rgba(79, 70, 229, 0.3); }
        
        .comment-item { display: flex; gap: 15px; padding: 20px; border-bottom: 1px solid var(--border-color); transition: background 0.2s; }
        .comment-item:last-child { border-bottom: none; }
        .comment-item:hover { background-color: #fcfcfc; }
        
        .comment-avatar { 
            width: 48px; height: 48px; border-radius: 12px; background: #e0e7ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0;
        }
        
        .comment-content { flex: 1; }
        .comment-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 5px; }
        .comment-author { font-weight: 600; color: var(--text-main); font-size: 1rem; }
        .comment-meta { font-size: 0.85rem; color: var(--text-muted); }
        .comment-text { font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 12px; }
        .comment-post-link { font-size: 0.8rem; color: var(--primary); font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; padding: 4px 10px; border-radius: 6px; }
        .comment-post-link:hover { background: #e2e8f0; }

        .comment-actions { display: flex; gap: 8px; margin-top: 10px; }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Yorumlar</h1>
                <p class="text-muted">Kullanıcı geri bildirimlerini yönetin.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type === 'alert-error' ? 'alert-error' : 'badge-success' ?>" style="padding:15px; margin-bottom:20px; border-radius:8px; background-color: #dcfce7; color: #166534;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <a href="comments.php?status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">Tümü</a>
            <a href="comments.php?status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">Onay Bekleyenler</a>
            <a href="comments.php?status=approved" class="filter-btn <?= $status_filter === 'approved' ? 'active' : '' ?>">Onaylananlar</a>
        </div>

        <div class="card">
            <?php if (empty($comments)): ?>
                <div class="empty-state">
                    <i class="far fa-comments"></i>
                    <p>Bu filtreye uygun yorum bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="comments-feed">
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-avatar">
                            <?= mb_strtoupper(mb_substr($comment['author'], 0, 1)) ?>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <div>
                                    <div class="comment-author"><?= htmlspecialchars($comment['author']) ?></div>
                                    <div class="comment-meta"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></div>
                                </div>
                                <div>
                                    <?php if($comment['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Onay Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Onaylandı</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="comment-text">
                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <a href="../post.php?slug=<?= htmlspecialchars($comment['post_slug']) ?>#comments" target="_blank" class="comment-post-link">
                                    <i class="fas fa-link"></i> <?= htmlspecialchars(mb_strimwidth($comment['post_title'], 0, 40, '...')) ?>
                                </a>

                                <div class="comment-actions">
                                    <?php if ($comment['status'] === 'pending'): ?>
                                        <a href="?action=approve&id=<?= $comment['id'] ?>&status=<?= $status_filter ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> Onayla
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $comment['id'] ?>&status=<?= $status_filter ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yorumu silmek istediğinize emin misiniz?');">
                                        <i class="fas fa-trash"></i> Sil
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>