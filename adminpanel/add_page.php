<?php
/**
 * Blogium - Yeni Sayfa Ekle v9.1 (UTF-8 Karakter Düzeltmesi)
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

$message = ""; $message_type = "";

// GARANTİ SLUG FONKSİYONU
function generateSlug($str) {
    $str = trim($str);
    $char_map = [
        'Ş' => 's', 'ş' => 's',
        'İ' => 'i', 'ı' => 'i',
        'Ğ' => 'g', 'ğ' => 'g',
        'Ü' => 'u', 'ü' => 'u',
        'Ö' => 'o', 'ö' => 'o',
        'Ç' => 'c', 'ç' => 'c',
        ' ' => '-',
    ];
    $str = strtr($str, $char_map);
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    
    // --- DÜZELTME BURADA ---
    // TinyMCE'den gelen HTML kodlarını (örn: &uuml;) gerçek karakterlere (ü) çeviriyoruz.
    $raw_content = $_POST['content'] ?? '';
    $content = html_entity_decode($raw_content, ENT_QUOTES, 'UTF-8');
    // -----------------------

    $slug_raw = trim($_POST['slug'] ?? '');
    
    // SEO Verileri
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_desc = trim($_POST['meta_desc'] ?? '');

    // Slug Belirleme
    if (!empty($slug_raw)) {
        $slug = generateSlug($slug_raw);
    } else {
        $slug = generateSlug($title);
    }

    if (empty($slug)) { $slug = 'sayfa-' . time(); }

    // Çakışma Kontrolü
    $check = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
    $check->execute([$slug]);
    
    if ($check->rowCount() > 0) {
        $message = "Bu URL (<strong>$slug</strong>) zaten kullanılıyor.";
        $message_type = "alert-error";
    } else {
        try {
            $insert = $pdo->prepare("INSERT INTO pages (title, slug, content, meta_title, meta_description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($insert->execute([$title, $slug, $content, $meta_title, $meta_desc])) {
                header("Location: pages.php?success=created");
                exit;
            } else {
                $message = "Veritabanı hatası oluştu.";
                $message_type = "alert-error";
            }
        } catch (PDOException $e) {
            $message = "Hata: " . $e->getMessage();
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
        @media(max-width:992px) { .form-grid { grid-template-columns: 1fr !important; } .form-sidebar { order: -1; margin-bottom: 20px; } }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div><h1 class="page-title">Yeni Sayfa Ekle</h1></div>
            <a href="pages.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> İptal</a>
        </div>

        <?php if ($message): ?> <div class="alert <?= $message_type ?>"><?= $message ?></div> <?php endif; ?>

        <form method="POST">
            <div class="form-grid" style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">
                
                <div class="form-main">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Sayfa Başlığı</label>
                                <input type="text" id="title" name="title" class="form-control" style="font-size: 1.1rem; font-weight: 600;" value="<?= htmlspecialchars($title ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Kalıcı Bağlantı (URL)</label>
                                <div class="permalink-box">
                                    <span>site.com/</span>
                                    <input type="text" id="slug" name="slug" class="permalink-input" placeholder="otomatik-olusturulur" value="<?= htmlspecialchars($slug_raw ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">İçerik</label>
                                <textarea id="editor" name="content"><?= htmlspecialchars($content ?? '') ?></textarea>
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
                                <input type="text" name="meta_title" class="form-control" placeholder="Google'da görünecek başlık" value="<?= htmlspecialchars($meta_title ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SEO Açıklaması (Meta Description)</label>
                                <textarea name="meta_desc" class="form-control" rows="3" placeholder="Sayfa hakkında kısa özet."><?= htmlspecialchars($meta_desc ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-sidebar">
                    <div class="card">
                        <div class="card-header"><h3>Yayımla</h3></div>
                        <div class="card-body"><button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Oluştur</button></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script>
        tinymce.init({
            selector: '#editor', height: 500, menubar: true,
            // Bu ayar önemli: TinyMCE'nin de karakterleri çevirmesini engeller
            entity_encoding: 'raw', 
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            language: 'tr', content_style: 'body { font-family:Inter,sans-serif; font-size:16px; color:#333; }'
        });
        
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        
        function trToEn(text) { 
            return text.replace(/Ğ/g, 'G').replace(/Ü/g, 'U').replace(/Ş/g, 'S').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ç/g, 'C')
                       .replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c'); 
        }
        
        titleInput.addEventListener('input', function() { 
            if (slugInput.value.length === 0) { 
                let slug = trToEn(this.value).toLowerCase().trim(); 
                slug = slug.replace(/[^a-z0-9\s-_]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-'); 
                slugInput.value = slug; 
            }
        });
    </script>
</body>
</html>