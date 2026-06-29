<?php
/**
 * Blogium - Yazı Güncelle v9.1 (Slug Düzenleme + SEO)
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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: posts.php"); exit; }
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) { header("Location: posts.php"); exit; }

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$message = ""; $message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title           = trim($_POST['title']);
    // Slug İşlemleri
    $slug_input      = trim($_POST['slug'] ?? '');
    $content         = $_POST['content']; 
    $category_id     = $_POST['category_id'];
    $tags            = trim($_POST['tags']);
    
    // SEO Verileri
    $meta_title      = trim($_POST['meta_title']);
    $meta_desc       = trim($_POST['meta_desc']);
    
    $allow_comments  = $_POST['allow_comments'];
    $image_url       = $post['image_url']; 

    // Slug Oluşturma ve Kontrol (YENİ EKLENDİ)
    $slug = empty($slug_input) ? generateSlug($title) : generateSlug($slug_input);
    if(empty($slug)) $slug = 'post-' . time();

    // Slug benzersiz mi? (Kendisi hariç diğer yazıları kontrol et)
    $check = $pdo->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
    $check->execute([$slug, $id]);
    if ($check->fetch()) { 
        $slug .= '-' . time(); // Çakışma varsa sonuna zaman damgası ekle
    }

    // Görsel Yükleme
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = realpath(__DIR__ . '/../assets/post_images') . '/';
        $relativePath = 'assets/post_images/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        
        $filename = basename($_FILES['image_file']['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $newFileName)) {
                $image_url = $relativePath . $newFileName;
            }
        }
    }

    if (empty($message)) {
        // SQL Sorgusuna slug eklendi
        $sql = "UPDATE posts SET title=?, slug=?, content=?, category_id=?, tags=?, image_url=?, meta_title=?, meta_description=?, allow_comments=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$title, $slug, $content, $category_id, $tags, $image_url, $meta_title, $meta_desc, $allow_comments, $id])) {
            $message = "Yazı başarıyla güncellendi."; $message_type = "alert-success";
            // Güncel veriyi tekrar çek
            $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]); $post = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Veritabanı hatası oluştu."; $message_type = "alert-error";
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
        .preview-box { width: 100%; height: 180px; background-color: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-top: 10px; position: relative; }
        .preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid transparent; }
        .alert-success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .tox-tinymce { border-radius: 8px !important; border: 1px solid #e2e8f0 !important; }
        @media (max-width: 992px) { .form-grid { grid-template-columns: 1fr !important; } .form-sidebar { order: -1; margin-bottom: 20px; } }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div><h1 class="page-title">Yazıyı Düzenle</h1></div>
            <div>
                <a href="../yazi/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> Sitede Gör</a>
                <a href="posts.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Listeye Dön</a>
            </div>
        </div>

        <?php if ($message): ?> <div class="alert <?= $message_type ?>"><?= $message ?></div> <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid" style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">
                <div class="form-main">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Başlık</label>
                                <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($post['title']) ?>" style="font-size: 1.1rem; font-weight: 600;" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Kalıcı Bağlantı (Slug)</label>
                                <div class="permalink-box">
                                    <span>site.com/yazi/</span>
                                    <input type="text" id="slug" name="slug" class="permalink-input" value="<?= htmlspecialchars($post['slug']) ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.8rem;">Dikkat: Bunu değiştirirseniz eski linkler kırılabilir.</small>
                            </div>

                            <div class="form-group"><label class="form-label">İçerik</label><textarea id="content" name="content"><?= htmlspecialchars($post['content']) ?></textarea></div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <div class="card-header">
                            <h3><i class="fas fa-search"></i> SEO Ayarları (Google Görünümü)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">SEO Başlığı (Meta Title)</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="Google'da görünmesini istediğiniz başlık" value="<?= htmlspecialchars($post['meta_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SEO Açıklaması (Meta Description)</label>
                                <textarea name="meta_desc" class="form-control" rows="3" placeholder="Yazı hakkında kısa özet."><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-sidebar">
                    <div class="card">
                        <div class="card-header"><h3>Yayınla</h3></div>
                        <div class="card-body"><button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Güncelle</button></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Kategori</h3></div>
                        <div class="card-body">
                            <select name="category_id" class="form-control" required>
                                <option value="">Seçiniz...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $post['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Görsel</h3></div>
                        <div class="card-body">
                            <input type="text" name="image_url" id="imgUrlInput" class="form-control" placeholder="URL veya dosya..." value="<?= htmlspecialchars($post['image_url'] ?? '') ?>">
                            <input type="file" name="image_file" id="imgFileInput" class="form-control" style="margin-top:5px;" accept="image/*">
                            
                            <div class="preview-box" id="imgPreviewBox">
                                <?php if(!empty($post['image_url'])): ?> 
                                    <img id="imgPreview" src="../<?= htmlspecialchars($post['image_url']) ?>"> 
                                <?php else: ?>
                                    <span id="placeholderText">Önizleme Yok</span><img id="imgPreview" src="" style="display:none">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Etiketler</h3></div>
                        <div class="card-body"><input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($post['tags'] ?? '') ?>"></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Tartışma</h3></div>
                        <div class="card-body">
                            <select name="allow_comments" class="form-control">
                                <option value="yes" <?= $post['allow_comments'] === 'yes' ? 'selected' : '' ?>>Açık</option>
                                <option value="moderated" <?= $post['allow_comments'] === 'moderated' ? 'selected' : '' ?>>Onaylı</option>
                                <option value="no" <?= $post['allow_comments'] === 'no' ? 'selected' : '' ?>>Kapalı</option>
                            </select>
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

        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        let isSlugEdited = false; // Kullanıcı slug'ı elle değiştirdi mi?

        function trToEn(text) { 
            return text.replace(/Ğ/g, 'G').replace(/Ü/g, 'U').replace(/Ş/g, 'S').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ç/g, 'C').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c'); 
        }

        // Başlık değiştiğinde slug'ı güncelle (Eğer kullanıcı slug'a dokunmadıysa)
        titleInput.addEventListener('keyup', function() { 
            if (!isSlugEdited) {
                let slug = trToEn(this.value).toLowerCase().trim(); 
                slug = slug.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-'); 
                slugInput.value = slug; 
            }
        });

        // Kullanıcı slug alanına elle müdahale ederse otomatik güncellemeyi durdur
        slugInput.addEventListener('input', function() {
            isSlugEdited = true;
        });

        // Görsel Önizleme
        const imgUrlInput = document.getElementById('imgUrlInput'); 
        const imgFileInput = document.getElementById('imgFileInput'); 
        const imgPreview = document.getElementById('imgPreview'); 
        const placeholderText = document.getElementById('placeholderText');

        function showImage(src) { 
            imgPreview.src = src; 
            imgPreview.style.display = 'block'; 
            if(placeholderText) placeholderText.style.display = 'none'; 
        }

        imgUrlInput.addEventListener('input', function() { 
            if (this.value.length > 5) showImage('../' + this.value.replace('../', '')); // Düzeltme: URL önizlemesi
        });
        
        imgFileInput.addEventListener('change', function(e) { 
            const file = e.target.files[0]; 
            if (file) { 
                const reader = new FileReader(); 
                reader.onload = function(e) { showImage(e.target.result); }; 
                reader.readAsDataURL(file); 
                imgUrlInput.value = ''; 
            } 
        });
    });
    </script>
</body>
</html>