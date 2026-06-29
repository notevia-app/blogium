<?php
/**
 * Blogium - Site Ayarları v3.1
 * Günün Sözü Yönetimi Eklendi
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

// Ayarları Çek
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Admin Bilgilerini Çek
$admin_stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
$admin_stmt->execute([$_SESSION['admin_username']]);
$current_admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Günün Sözü JSON Dosyasını Oku
$quote_file = __DIR__ . '/../quote_of_the_day.json';
$current_quote = ['quote' => '', 'author' => ''];
if (file_exists($quote_file)) {
    $json_content = file_get_contents($quote_file);
    $current_quote = json_decode($json_content, true) ?: $current_quote;
}

// Dosya Yükleme Fonksiyonu
function uploadFileToRoot($fileField, $saveAsFilename) {
    if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileField]['tmp_name'];
        $targetPath = realpath(__DIR__ . '/..') . '/' . $saveAsFilename;
        if (move_uploaded_file($tmpName, $targetPath)) {
            return '/' . $saveAsFilename . '?v=' . time();
        }
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // --- 1. SİTE AYARLARI GÜNCELLEME ---
    if (isset($_POST['update_site_settings'])) {
        $site_title = trim($_POST['site_title']);
        $site_slogan = trim($_POST['site_slogan']);
        $site_desc = trim($_POST['site_desc']);
        $seo_description = trim($_POST['seo_description']);
        $meta_tags = trim($_POST['meta_tags']);

        $favicon_url = $settings['favicon_url'] ?? '';
        $logo_url = $settings['logo_url'] ?? '';

        if ($new_fav = uploadFileToRoot('favicon', 'favicon.ico')) $favicon_url = $new_fav;
        if ($new_logo = uploadFileToRoot('logo', 'logo.png')) $logo_url = $new_logo;

        if ($settings) {
            $sql = "UPDATE settings SET site_title=?, site_slogan=?, site_desc=?, seo_description=?, meta_tags=?, favicon_url=?, logo_url=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$site_title, $site_slogan, $site_desc, $seo_description, $meta_tags, $favicon_url, $logo_url, $settings['id']]);
        } else {
            $sql = "INSERT INTO settings (site_title, site_slogan, site_desc, seo_description, meta_tags, favicon_url, logo_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$site_title, $site_slogan, $site_desc, $seo_description, $meta_tags, $favicon_url, $logo_url]);
        }
        
        // --- GÜNÜN SÖZÜNÜ KAYDET (JSON) ---
        $new_quote_text = trim($_POST['daily_quote']);
        $new_quote_author = trim($_POST['daily_quote_author']);
        
        $quote_data = [
            'quote' => $new_quote_text,
            'author' => $new_quote_author,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Dosyaya yaz
        if (file_put_contents($quote_file, json_encode($quote_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $current_quote = $quote_data; // Ekranda güncel hali görünsün
        }

        if ($success) {
            $message = "Ayarlar ve Günün Sözü başarıyla güncellendi.";
            $message_type = 'badge-success';
            $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Veritabanı hatası oluştu.";
            $message_type = 'alert-error';
        }
    }

    // --- 2. ADMIN HESAP GÜNCELLEME ---
    if (isset($_POST['update_admin_settings'])) {
        $new_username = trim($_POST['new_username']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_username) || empty($current_password)) {
            $message = "Kullanıcı adı ve mevcut şifre zorunludur.";
            $message_type = 'alert-error';
        } elseif (!password_verify($current_password, $current_admin['password'])) {
            $message = "Mevcut şifrenizi yanlış girdiniz.";
            $message_type = 'alert-error';
        } else {
            $password_to_update = $current_admin['password'];
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $message = "Yeni şifreler birbiriyle uyuşmuyor.";
                    $message_type = 'alert-error';
                } elseif (strlen($new_password) < 6) {
                    $message = "Yeni şifre en az 6 karakter olmalıdır.";
                    $message_type = 'alert-error';
                } else {
                    $password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }

            if (empty($message) || $message_type === 'badge-success') {
                $upd = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
                if ($upd->execute([$new_username, $password_to_update, $current_admin['id']])) {
                    $_SESSION['admin_username'] = $new_username;
                    $current_admin['username'] = $new_username;
                    $message = "Hesap bilgileri başarıyla güncellendi.";
                    $message_type = 'badge-success';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .dash-content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        @media (max-width: 992px) { .dash-content-grid { grid-template-columns: 1fr; } }
        .preview-img { width: 48px; height: 48px; object-fit: contain; border: 1px solid var(--border-color); border-radius: 8px; padding: 2px; background: #f8fafc; }
        .file-input-wrapper { display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Site Ayarları</h1>
                <p class="text-muted">Web sitenizin genel yapılandırmasını yönetin.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message_type, 'success') !== false ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:20px; padding:15px; border-radius:8px; background-color: #dcfce7; color: #166534; border:1px solid #bbf7d0;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="dash-content-grid">
            
            <div class="left-column">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_site_settings" value="1">
                    
                    <div class="card" style="border-left: 4px solid var(--primary);">
                        <div class="card-header">
                            <h3><i class="fas fa-quote-left"></i> Günün Sözü</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Söz</label>
                                <textarea name="daily_quote" class="form-control" rows="2" placeholder="Günün ilham verici sözünü buraya yazın..."><?= htmlspecialchars($current_quote['quote'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yazar / Kaynak</label>
                                <input type="text" name="daily_quote_author" class="form-control" value="<?= htmlspecialchars($current_quote['author'] ?? '') ?>" placeholder="Örn: Albert Einstein">
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-globe"></i> Genel Bilgiler</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Site Başlığı</label>
                                <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Site Sloganı</label>
                                <input type="text" name="site_slogan" class="form-control" value="<?= htmlspecialchars($settings['site_slogan'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-search"></i> SEO Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">SEO Açıklaması</label>
                                <textarea name="seo_description" class="form-control" rows="3"><?= htmlspecialchars($settings['seo_description'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Anahtar Kelimeler</label>
                                <input type="text" name="meta_tags" class="form-control" value="<?= htmlspecialchars($settings['meta_tags'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Footer Açıklaması</label>
                                <textarea name="site_desc" class="form-control" rows="2"><?= htmlspecialchars($settings['site_desc'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-images"></i> Görseller</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Site Logosu</label>
                                <div class="file-input-wrapper">
                                    <?php if(!empty($settings['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($settings['logo_url']) ?>" class="preview-img">
                                    <?php endif; ?>
                                    <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.svg">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Favicon</label>
                                <div class="file-input-wrapper">
                                    <?php if(!empty($settings['favicon_url'])): ?>
                                        <img src="<?= htmlspecialchars($settings['favicon_url']) ?>" class="preview-img">
                                    <?php endif; ?>
                                    <input type="file" name="favicon" class="form-control" accept=".ico,.png">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="right-column">
                <form method="POST">
                    <input type="hidden" name="update_admin_settings" value="1">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-shield"></i> Yönetici Hesabı</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Kullanıcı Adı</label>
                                <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($current_admin['username']) ?>" required>
                            </div>
                            <hr style="border:0; border-top:1px solid var(--border-color); margin: 20px 0;">
                            <div class="form-group">
                                <label class="form-label">Yeni Şifre</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>
                            <div class="alert alert-warning" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5; padding:10px; font-size:0.85rem; margin-bottom:15px;">
                                <i class="fas fa-exclamation-circle"></i> Değişiklikleri kaydetmek için mevcut şifrenizi girmelisiniz.
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mevcut Şifre <span style="color:red">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-secondary" style="width:100%;">
                                <i class="fas fa-user-check"></i> Hesabı Güncelle
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>

    </div>

</body>
</html>