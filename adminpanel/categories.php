<?php
/**
 * Blogium - Kategori Yönetimi v3.0
 * Modern Split-View Tasarım (Liste + Hızlı Ekle)
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

$message = '';
$message_type = '';

// --- YENİ KATEGORİ EKLEME İŞLEMİ (Aynı Sayfada) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);

    if ($name && $slug) {
        // Slug Kontrolü
        $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->rowCount() > 0) {
            $message = "Bu URL (slug) zaten kullanılıyor.";
            $message_type = "alert-error";
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            if ($stmt->execute([$name, $slug])) {
                $message = "Kategori başarıyla eklendi.";
                $message_type = "alert-success";
            } else {
                $message = "Bir hata oluştu.";
                $message_type = "alert-error";
            }
        }
    } else {
        $message = "Lütfen isim ve URL alanlarını doldurun.";
        $message_type = "alert-warning";
    }
}

// --- KATEGORİLERİ VE YAZI SAYILARINI ÇEK ---
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.slug, COUNT(p.id) as post_count
        FROM categories c
        LEFT JOIN posts p ON c.id = p.category_id
        GROUP BY c.id, c.name, c.slug
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .dash-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Sol 2 birim, Sağ 1 birim */
            gap: 1.5rem;
        }
        @media (max-width: 992px) {
            .dash-content-grid { grid-template-columns: 1fr; }
            .form-card-container { order: -1; } /* Mobilde form üste gelsin */
        }
        
        /* Inline Düzenleme Formu Stili */
        .edit-row { display: none; background-color: #f8fafc; }
        .edit-form-inline { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .slug-text { font-family: monospace; color: var(--secondary); font-size: 0.85rem; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Kategoriler</h1>
                <p class="text-muted">İçeriklerinizi gruplandırın ve yönetin.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="dash-content-grid">
            
            <div class="list-container">
                <div class="card">
                    <div class="card-header">
                        <h3>Mevcut Kategoriler (<?= count($categories) ?>)</h3>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kategori Adı</th>
                                        <th>URL (Slug)</th>
                                        <th style="text-align:center;">Yazı</th>
                                        <th style="text-align:right;">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr><td colspan="4" class="text-center p-4 text-muted">Kategori bulunamadı.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <tr id="row-<?= $cat['id'] ?>">
                                                <td style="font-weight:600; color:var(--text-main);">
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </td>
                                                <td>
                                                    <span class="slug-text">/<?= htmlspecialchars($cat['slug']) ?></span>
                                                </td>
                                                <td style="text-align:center;">
                                                    <span class="badge badge-blue"><?= $cat['post_count'] ?></span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <button class="btn btn-sm btn-secondary edit-btn" data-id="<?= $cat['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="delete_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz? İçindeki yazılar silinmez, ancak kategorisiz kalır.');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>

                                            <tr id="edit-row-<?= $cat['id'] ?>" class="edit-row">
                                                <td colspan="4">
                                                    <form action="update_category.php" method="POST" class="edit-form-inline">
                                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                        <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($cat['name']) ?>" required style="width: auto; flex:1;">
                                                        <input type="text" name="slug" class="form-control form-control-sm" value="<?= htmlspecialchars($cat['slug']) ?>" required style="width: auto; flex:1;">
                                                        
                                                        <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                                                        <button type="button" class="btn btn-sm btn-secondary cancel-btn" data-id="<?= $cat['id'] ?>">İptal</button>
                                                    </form>
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

            <div class="form-card-container">
                <div class="card sticky-top" style="top: 20px;"> <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Yeni Ekle</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="form-group">
                                <label class="form-label">Kategori Adı</label>
                                <input type="text" id="catName" name="name" class="form-control" placeholder="Örn: Yapay Zeka" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">URL (Slug)</label>
                                <input type="text" id="catSlug" name="slug" class="form-control" placeholder="yapay-zeka" required>
                                <small class="text-muted">Otomatik oluşturulur.</small>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%;">Ekle</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- SLUG OLUŞTURUCU ---
        const nameInput = document.getElementById('catName');
        const slugInput = document.getElementById('catSlug');

        function trToEn(text) {
            return text.replace(/Ğ/g, 'G').replace(/Ü/g, 'U').replace(/Ş/g, 'S').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ç/g, 'C')
                       .replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c');
        }

        nameInput.addEventListener('keyup', function() {
            let slug = trToEn(this.value).toLowerCase().trim();
            slug = slug.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            slugInput.value = slug;
        });

        // --- INLINE DÜZENLEME MANTIĞI ---
        const editButtons = document.querySelectorAll('.edit-btn');
        const cancelButtons = document.querySelectorAll('.cancel-btn');

        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                document.getElementById('row-' + id).style.display = 'none';
                document.getElementById('edit-row-' + id).style.display = 'table-row';
            });
        });

        cancelButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                document.getElementById('edit-row-' + id).style.display = 'none';
                document.getElementById('row-' + id).style.display = 'table-row';
            });
        });
    });
    </script>

</body>
</html>