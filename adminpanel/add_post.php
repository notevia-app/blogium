<?php
/**
 * Blogium - Yeni Yazı Ekle v9.3 (FCM HTTP v1 API - JSON Key Entegrasyonu)
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

// --- HELPER: Google OAuth2 Token Üretici (Kütüphanesiz) ---
function getGoogleAccessToken($keyFile) {
    if (!file_exists($keyFile)) { 
        error_log("FCM Hatası: Anahtar dosyası bulunamadı: " . $keyFile);
        return false; 
    }
    
    $data = json_decode(file_get_contents($keyFile), true);
    if (!$data) {
        error_log("FCM Hatası: JSON dosyası okunamadı.");
        return false; 
    }

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $data['client_email'],
        'sub' => $data['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = '';
    // Private key extraction
    if (!openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $data['private_key'], 'sha256')) {
        error_log("FCM Hatası: OpenSSL imzalama başarısız.");
        return false;
    }
    
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("FCM Token CURL Hatası: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $json = json_decode($response, true);
    return $json['access_token'] ?? false;
}

function generateSlug($str) {
    $str = trim($str);
    $char_map = ['Ş'=>'s','ş'=>'s','İ'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c',' '=>'-'];
    $str = strtr($str, $char_map);
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { $categories = []; }

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug_input = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $tags = trim($_POST['tags'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_desc = trim($_POST['meta_desc'] ?? '');
    $allow_comments = $_POST['allow_comments'] ?? 'yes';

    if (empty($title) || empty($content) || empty($category_id)) {
        $message = "Lütfen başlık, içerik ve kategori alanlarını doldurun."; $message_type = 'alert-error';
    }

    if (empty($message) && empty($image_url) && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = realpath(__DIR__ . '/../assets/post_images') . '/';
        $relativePath = 'assets/post_images/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        $filename = basename($_FILES['image_file']['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (in_array($_FILES['image_file']['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $newFileName)) {
                $image_url = $relativePath . $newFileName;
            }
        }
    }

    if (empty($message)) {
        $slug = empty($slug_input) ? generateSlug($title) : generateSlug($slug_input);
        if(empty($slug)) $slug = 'post-' . time();
        
        $check = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) { $slug .= '-' . time(); }

        try {
            // 1. Yazıyı Kaydet
            $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, category_id, tags, image_url, meta_title, meta_description, allow_comments, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $slug, $content, $category_id, $tags, $image_url, $meta_title, $meta_desc, $allow_comments]);
            $last_id = $pdo->lastInsertId();

            // ---------------------------------------------------------
            // 2. FCM HTTP V1 BİLDİRİM GÖNDERİMİ
            // ---------------------------------------------------------
            
            // JSON Dosya Yolu: Bu dosya add_post.php ile AYNI klasörde olmalı.
            $keyFile = __DIR__ . '/service-account-key.json'; 
            
            // Eğer dosya home klasöründeyse yolu şöyle değiştirin:
            // $keyFile = '/home/username/service-account-key.json'; 

            if (file_exists($keyFile)) {
                $accessToken = getGoogleAccessToken($keyFile);
                
                if ($accessToken) {
                    // JSON'dan Proje ID'sini oku
                    $keyData = json_decode(file_get_contents($keyFile), true);
                    $projectId = $keyData['project_id']; // blogiumapp-cd5d9 olmalı

                    // Tokenları Çek
                    $tokensStmt = $pdo->query("SELECT token FROM fcm_tokens");
                    $tokens = $tokensStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($tokens)) {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $domain = $_SERVER['HTTP_HOST'];
                        $full_image_url = !empty($image_url) ? "$protocol://$domain/$image_url" : "$protocol://$domain/assets/img/logo.png";
                        $body_text = mb_substr(strip_tags($content), 0, 100) . '...';

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json'
                        ]);

                        foreach ($tokens as $token) {
                            $messagePayload = [
                                'message' => [
                                    'token' => $token,
                                    'notification' => [
                                        'title' => $title,
                                        'body'  => $body_text,
                                        'image' => $full_image_url
                                    ],
                                    'data' => [
                                        'post_id' => (string)$last_id,
                                        'slug'    => $slug,
                                        'title'   => $title,
                                        'image'   => $full_image_url,
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                    ]
                                ]
                            ];

                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messagePayload));
                            curl_exec($ch); 
                        }
                        curl_close($ch);
                    }
                } else {
                    error_log("FCM Hatası: Access Token alınamadı.");
                }
            } else {
                error_log("FCM Hatası: JSON anahtar dosyası bulunamadı: " . $keyFile);
            }
            // ---------------------------------------------------------
            // FCM BİTİŞ
            // ---------------------------------------------------------

            $message = "Yazı yayımlandı ve bildirimler gönderildi! <a href='edit_post.php?id={$last_id}'>Düzenle</a>"; 
            $message_type = 'alert-success';
            $title = $slug_input = $content = $tags = $image_url = $meta_title = $meta_desc = '';

        } catch (PDOException $e) {
            $message = "Hata: " . $e->getMessage(); $message_type = 'alert-error';
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
        .preview-box img { width: 100%; height: 100%; object-fit: cover; display: none; }
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
            <h1 class="page-title">Yeni Yazı Ekle</h1>
            <a href="posts.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Listeye Dön</a>
        </div>
        <?php if ($message): ?> <div class="alert <?= $message_type ?>"><?= $message ?></div> <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-grid" style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">
                
                <div class="form-main">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group"><label class="form-label">Yazı Başlığı</label><input type="text" id="title" name="title" class="form-control" style="font-size: 1.1rem; font-weight: 600;" value="<?= htmlspecialchars($title ?? '') ?>" required></div>
                            <div class="form-group"><label class="form-label">Kalıcı Bağlantı</label><div class="permalink-box"><span>site.com/yazi/</span><input type="text" id="slug" name="slug" class="permalink-input" placeholder="otomatik-olusturulur" value="<?= htmlspecialchars($slug_input ?? '') ?>"></div></div>
                            <div class="form-group"><label class="form-label">İçerik</label><textarea id="content" name="content"><?= htmlspecialchars($content ?? '') ?></textarea></div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <div class="card-header">
                            <h3><i class="fas fa-search"></i> SEO Ayarları (Google Görünümü)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">SEO Başlığı (Meta Title)</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="Google'da görünmesini istediğiniz başlık" value="<?= htmlspecialchars($meta_title ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SEO Açıklaması (Meta Description)</label>
                                <textarea name="meta_desc" class="form-control" rows="3" placeholder="Yazı hakkında kısa özet (160 karakter önerilir)."><?= htmlspecialchars($meta_desc ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-sidebar">
                    <div class="card">
                        <div class="card-header"><h3>Yayımla</h3></div>
                        <div class="card-body">
                            <div class="form-group"><label class="form-label">Yorum Durumu</label><select name="allow_comments" class="form-control"><option value="yes">Açık</option><option value="moderated">Onaylı</option><option value="no">Kapalı</option></select></div>
                            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Yayımla</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Kategori</h3></div>
                        <div class="card-body">
                            <select name="category_id" class="form-control" required>
                                <option value="">Seçiniz...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($category_id) && $category_id == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Görsel</h3></div>
                        <div class="card-body">
                            <input type="text" name="image_url" id="imgUrlInput" class="form-control" placeholder="URL veya dosya..." value="<?= htmlspecialchars($image_url ?? '') ?>">
                            <input type="file" name="image_file" id="imgFileInput" class="form-control" style="margin-top:5px;" accept="image/*">
                            <div class="preview-box" id="imgPreviewBox"><span>Önizleme Yok</span><img id="imgPreview" src=""></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Etiketler</h3></div>
                        <div class="card-body"><input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($tags ?? '') ?>"></div>
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
        const titleInput = document.getElementById('title'); const slugInput = document.getElementById('slug');
        function trToEn(text) { return text.replace(/Ğ/g, 'G').replace(/Ü/g, 'U').replace(/Ş/g, 'S').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ç/g, 'C').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c'); }
        titleInput.addEventListener('keyup', function() { let slug = trToEn(this.value).toLowerCase().trim(); slug = slug.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-'); slugInput.value = slug; });
        
        const imgUrlInput = document.getElementById('imgUrlInput'); const imgFileInput = document.getElementById('imgFileInput'); const imgPreview = document.getElementById('imgPreview'); const placeholderText = document.querySelector('#imgPreviewBox span');
        function showImage(src) { imgPreview.src = src; imgPreview.style.display = 'block'; placeholderText.style.display = 'none'; }
        imgUrlInput.addEventListener('input', function() { if (this.value.length > 5) showImage(this.value); });
        imgFileInput.addEventListener('change', function(e) { const file = e.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = function(e) { showImage(e.target.result); }; reader.readAsDataURL(file); imgUrlInput.value = ''; } });
    });
    </script>
</body>
</html>