<?php
/**
 * Blogium - Sabit Sayfa Yönetimi v3.1
 * Ekleme ve Silme Özellikleri Eklendi
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

// SİLME İŞLEMİ
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Silmeden önce kontrol et
    $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        header("Location: pages.php?msg=deleted");
        exit;
    }
}

// MESAJLARI GÖSTER
$msg = "";
if (isset($_GET['success'])) $msg = '<div class="alert badge-success" style="padding:15px; margin-bottom:20px; border-radius:8px; background:#dcfce7; color:#166534;">Sayfa başarıyla oluşturuldu.</div>';
if (isset($_GET['msg']) && $_GET['msg']=='deleted') $msg = '<div class="alert badge-success" style="padding:15px; margin-bottom:20px; border-radius:8px; background:#dcfce7; color:#166534;">Sayfa başarıyla silindi.</div>';

// SAYFALARI ÇEK
try {
    $stmt = $pdo->query("SELECT * FROM pages ORDER BY title ASC");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Veri hatası: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .slug-badge {
            font-family: 'Monaco', 'Courier New', monospace;
            background-color: #f1f5f9; color: var(--secondary);
            padding: 4px 8px; border-radius: 6px;
            font-size: 0.8rem; border: 1px solid var(--border-color);
        }
        .page-icon {
            font-size: 1.2rem; color: var(--primary);
            background: var(--primary-light);
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Sabit Sayfalar</h1>
                <p class="text-muted">Footer alanında görünecek kurumsal sayfaları yönetin.</p>
            </div>
            <a href="add_page.php" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Sayfa</a>
        </div>

        <?= $msg ?>

        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 60px;"></th>
                                <th>Sayfa Başlığı</th>
                                <th>URL (Slug)</th>
                                <th>Son Güncelleme</th>
                                <th style="text-align:right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pages)): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-5 text-muted">
                                        <i class="far fa-file-alt" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                                        <p>Henüz hiç sabit sayfa bulunmuyor.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td>
                                            <div class="page-icon">
                                                <i class="far fa-file-alt"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main);">
                                                <?= htmlspecialchars($page['title']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="slug-badge">/<?= htmlspecialchars($page['slug']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted" style="font-size: 0.9rem;">
                                                <?= isset($page['created_at']) ? date('d.m.Y', strtotime($page['created_at'])) : '-' ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="../<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="btn btn-sm btn-secondary" title="Sitede Gör">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <a href="edit_page.php?id=<?= $page['id'] ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <a href="pages.php?delete=<?= $page['id'] ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu sayfayı silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash"></i>
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