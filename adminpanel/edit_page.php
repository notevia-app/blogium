<?php
/**
 * Blogium - Sayfa Güncelle v4.0 (SEO ve Slug Yönetimi)
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

// --- SLUG OLUŞTURUCU FONKSİYON ---
function generateSlug($str) {
    $str = trim($str);
    $char_map = ['Ş'=>'s','ş'=>'s','İ'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c',' '=>'-'];
    $str = strtr($str, $char_map);
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: pages.php"); exit; }
$id = (int)$_GET['id'];

// Mevcut veriyi çek
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) { header("Location: pages.php"); exit; }

$message = ""; $message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title      = trim($_POST['title']);
    $content    = $_POST['content'];
    $slug_input = trim($_POST['slug'] ?? '');
    
    // SEO Verileri
    $meta_title = trim($_POST['meta_title']);
    $meta_desc  = trim($_POST['meta_desc']);

    // Slug Oluşturma
    $slug = empty($slug_input) ? generateSlug($title) : generateSlug($slug_input);
    if(empty($slug)) $slug = 'page-' . time();

    // Slug Benzersizlik Kontrolü (Kendisi hariç)
    $check = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
    $check->execute([$slug, $id]);
    if ($check->fetch()) {
        $slug .= '-' . time();
    }

    if (empty($title)) {
        $message = "Sayfa başlığı boş olamaz.";
        $message_type = "alert-error";
    } else {
        // Güncelleme Sorgusu
        $sql = "UPDATE pages SET title=?, slug=?, content=?, meta_title=?, meta_description=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$title, $slug, $content, $meta_title, $meta_desc, $id])) {
            $message = "Sayfa başarıyla güncellendi.";
            $message_type = "alert-success";
            // Veriyi tazele
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
            $stmt->execute([$id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Veritabanı hatası oluştu.";
            $message_type = "alert-error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .permalink-box { display: flex; align-items: center; gap: 5px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 12px; border-radius: 8px; font-size: 0.9rem; color: #64748b; }
        .permalink-input { border: none; background: transparent; color: #1e293b; font-weight: 600; outline: none; width: 100%; font-family: inherit; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid transparent; }
        .alert-success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .tox-tinymce { border-radius: 8px !important; border: 1px solid #e2e8f0 !important; }
        /* Pages genellikle sidebar gerektirmez ama layout uyumu için grid kullanabiliriz */
        .form-grid { display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem; }
        @media (max-width: 992px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div><h1 class="page-title">Sayfayı Düzenle</h1></div>
            <div>
                <a href="../<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> Sitede Gör</a>
                <a href="pages.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Listeye Dön</a>
            </div>
        </div>

        <?php if ($message): ?> <div class="alert <?= $message_type ?>"><?= $message ?></div> <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="form-main">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Sayfa Başlığı</label>
                                <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($page['title']) ?>" style="font-size: 1.1rem; font-weight: 600;" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Kalıcı Bağlantı (Slug)</label>
                                <div class="permalink-box">
                                    <span>site.com/</span>
                                    <input type="text" id="slug" name="slug" class="permalink-input" value="<?= htmlspecialchars($page['slug']) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">İçerik</label>
                                <textarea id="content" name="content"><?= htmlspecialchars($page['content']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <div class="card-header">
                            <h3><i class="fas fa-search"></i> SEO Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">SEO Başlığı (Meta Title)</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="Google'da görünecek başlık" value="<?= htmlspecialchars($page['meta_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SEO Açıklaması (Meta Description)</label>
                                <textarea name="meta_desc" class="form-control" rows="3" placeholder="Sayfa hakkında kısa özet."><?= htmlspecialchars($page['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-sidebar">
                    <div class="card">
                        <div class="card-header"><h3>İşlemler</h3></div>
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-save"></i> Güncelle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        tinymce.init({
            selector: '#content', height: 500, menubar: true,
            entity_encoding: 'raw',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            language: 'tr', content_style: 'body { font-family:Inter,sans-serif; font-size:16px; color:#333; }'
        });

        // Slug Otomatik Oluşturma (Post düzenlemedeki mantıkla aynı)
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        let isSlugEdited = false;

        function trToEn(text) { 
            return text.replace(/Ğ/g, 'G').replace(/Ü/g, 'U').replace(/Ş/g, 'S').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ç/g, 'C').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c'); 
        }

        titleInput.addEventListener('keyup', function() { 
            if (!isSlugEdited) {
                let slug = trToEn(this.value).toLowerCase().trim(); 
                slug = slug.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-'); 
                slugInput.value = slug; 
            }
        });

        slugInput.addEventListener('input', function() {
            isSlugEdited = true;
        });
    });
    </script>
</body>
</html>