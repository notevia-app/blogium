<?php
/**
 * Blogium - Modern Medya Galerisi v3.0
 * Görsel Yönetimi, Hızlı Kopyalama ve Önizleme
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

$message = '';
$message_type = '';

// Resim Yolu
$imageDir = realpath(__DIR__ . '/../assets/post_images') . '/';
$webPath = '../assets/post_images/'; // Frontend'de görünecek yol

// Güvenlik Fonksiyonu
function sanitize_filename($filename) {
    return basename($filename);
}

// Dosya Boyutu Formatlama
function formatSizeUnits($bytes) {
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; }
    else { return $bytes . ' bytes'; }
}

// SİLME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_to_delete = sanitize_filename($_POST['delete_image']);
    $file_path = $imageDir . $image_to_delete;

    if (file_exists($file_path) && is_writable($file_path)) {
        if (unlink($file_path)) {
            $message = "Resim başarıyla silindi.";
            $message_type = 'badge-success';
        } else {
            $message = "Silme işlemi başarısız.";
            $message_type = 'alert-error';
        }
    } else {
        $message = "Dosya bulunamadı veya yetki yok.";
        $message_type = 'alert-error';
    }
}

// RESİMLERİ LİSTELEME
$images = [];
if (is_dir($imageDir)) {
    $files = glob($imageDir . '*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE);
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); }); // En yeni en üstte

    foreach ($files as $file) {
        $filename = basename($file);
        // Yazı içinde kullanılacak tam URL (Domain dinamik alınırsa daha iyi olur ama şimdilik path yeterli)
        // Kullanıcı kopyalarken "assets/post_images/resim.jpg" formatında kopyalayacak.
        $full_web_url = 'assets/post_images/' . $filename;
        
        $images[] = [
            'name' => $filename,
            'view_url' => $webPath . $filename, // Admin panelde görüntüleme
            'copy_url' => $full_web_url,        // Kopyalanacak metin
            'size' => formatSizeUnits(filesize($file)),
            'date' => date("d.m.Y", filemtime($file))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        /* GALERİYE ÖZEL STİLLER */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .media-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .media-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .media-img-wrapper {
            height: 150px;
            width: 100%;
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 10px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .media-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        /* Hover Overlay */
        .media-overlay {
            position: absolute; inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
        }
        .media-card:hover .media-overlay { opacity: 1; }
        .media-card:hover .media-img { transform: scale(1.05); }

        .btn-view {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #fff; padding: 8px 16px; border-radius: 20px;
            font-size: 0.9rem; pointer-events: none; /* Tıklamayı wrapper'a bırak */
        }

        .media-body {
            padding: 1rem;
            display: flex; flex-direction: column; gap: 5px;
            border-top: 1px solid var(--border-color);
            flex-grow: 1;
        }

        .media-name {
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .media-meta {
            font-size: 0.75rem; color: var(--secondary);
            display: flex; justify-content: space-between;
        }

        .media-actions {
            padding: 0.8rem 1rem;
            background-color: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex; gap: 8px;
        }

        .btn-media {
            flex: 1; padding: 6px; border-radius: 6px; border: 1px solid var(--border-color);
            background: #fff; color: var(--text-main); cursor: pointer; font-size: 0.8rem;
            display: flex; align-items: center; justify-content: center; gap: 5px;
            transition: 0.2s;
        }
        .btn-media:hover { background: #e2e8f0; }
        .btn-media.delete:hover { background: #fee2e2; color: #ef4444; border-color: #fca5a5; }

        /* Lightbox (Modal) */
        .lightbox {
            position: fixed; inset: 0; z-index: 2000;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(5px);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        .lightbox.active { display: flex; animation: fadeIn 0.2s; }
        .lightbox-img { max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .lightbox-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2rem; cursor: pointer; }

        .empty-state { text-align: center; padding: 4rem; color: var(--secondary); }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Medya Galerisi</h1>
                <p class="text-muted">Toplam <?= count($images) ?> görsel bulunuyor.</p>
            </div>
            </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message_type, 'success') !== false ? 'alert-success' : 'alert-error' ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (empty($images)): ?>
            <div class="card empty-state">
                <i class="far fa-images empty-icon"></i>
                <p>Henüz hiç görsel yüklenmemiş.</p>
                <p class="text-muted text-sm">Yazı eklerken yüklediğiniz görseller burada listelenir.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($images as $img): ?>
                <div class="media-card">
                    <div class="media-img-wrapper" onclick="openLightbox('<?= $img['view_url'] ?>')">
                        <img src="<?= $img['view_url'] ?>" class="media-img" loading="lazy" alt="Görsel">
                        <div class="media-overlay">
                            <span class="btn-view"><i class="fas fa-search-plus"></i> Büyüt</span>
                        </div>
                    </div>
                    
                    <div class="media-body">
                        <div class="media-name" title="<?= htmlspecialchars($img['name']) ?>">
                            <?= htmlspecialchars($img['name']) ?>
                        </div>
                        <div class="media-meta">
                            <span><?= $img['size'] ?></span>
                            <span><?= $img['date'] ?></span>
                        </div>
                    </div>

                    <div class="media-actions">
                        <button class="btn-media" onclick="copyUrl('<?= $img['copy_url'] ?>', this)">
                            <i class="far fa-copy"></i> URL
                        </button>
                        
                        <form method="post" onsubmit="return confirm('Bu görseli silmek istediğinize emin misiniz? Kullanıldığı yazılarda resim kırık görünecektir.');" style="flex:1;">
                            <input type="hidden" name="delete_image" value="<?= htmlspecialchars($img['name']) ?>">
                            <button type="submit" class="btn-media delete" style="width:100%;">
                                <i class="far fa-trash-alt"></i> Sil
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
        <span class="lightbox-close">&times;</span>
        <img src="" class="lightbox-img" id="lightboxImg">
    </div>

    <script>
        // URL Kopyalama
        function copyUrl(url, btn) {
            navigator.clipboard.writeText(url).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı';
                btn.style.color = 'var(--success)';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.style.color = '';
                }, 2000);
            });
        }

        // Lightbox Açma
        function openLightbox(url) {
            const lb = document.getElementById('lightbox');
            const img = document.getElementById('lightboxImg');
            img.src = url;
            lb.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Lightbox Kapatma
        function closeLightbox(e) {
            if (e.target !== document.getElementById('lightboxImg')) {
                document.getElementById('lightbox').classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    </script>

</body>
</html>