<?php
/**
 * Blogium - Dinamik Sayfa Şablonu
 * Tüm statik sayfalar (Gizlilik, Sözleşme vb.) bu tasarımı kullanır.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/adminpanel/includes/db.php';

// URL'den gelen slug'ı al (örn: gizlilik-politikasi)
$page_slug = $_GET['slug'] ?? '';

// Güvenlik: Slug boşsa anasayfaya at
if (empty($page_slug)) {
    header("Location: /");
    exit;
}

$page = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? LIMIT 1");
    $stmt->execute([$page_slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        $page_title = $page['title'];
        // Eğer panelden meta girilmediyse içeriğin ilk 150 karakterini al
        $page_description = $page['meta_description'] ?? mb_substr(strip_tags($page['content']), 0, 160) . '...';
        $page_keywords = $page['meta_keywords'] ?? 'blogium, ' . str_replace('-', ' ', $page_slug);
        $page_url = "https://www.blogium.net/" . htmlspecialchars($page['slug']);
        // Kapak resmi yoksa varsayılan logo
        $page_image = !empty($page['cover_image']) ? $page['cover_image'] : 'https://www.blogium.net/logo.png';
    } else {
        // Sayfa bulunamadıysa 404
        http_response_code(404);
        $page_title = "Sayfa Bulunamadı";
        $page_description = "Aradığınız sayfa bulunamadı.";
    }

} catch (PDOException $e) {
    http_response_code(500);
    $page_title = "Hata";
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/head.php'; 
?>

<style>
    /* GENEL DÜZEN - Beğendiğin Tasarım */
    .page-wrapper { font-family: 'Inter', system-ui, sans-serif; color: #334155; padding-bottom: 60px; }
    
    /* 1. HERO ALANI (Hakkımızda ile Aynı Stil) */
    .page-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff;
        padding: 80px 20px 100px 20px; /* Alttan boşluk, içerik kutusu binsin diye */
        text-align: center;
        border-radius: 0 0 50% 50% / 40px;
        margin-bottom: 0;
    }
    .page-hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 15px; letter-spacing: -1px; }
    .page-hero p { font-size: 1.1rem; opacity: 0.8; max-width: 600px; margin: 0 auto; }

    /* 2. İÇERİK KUTUSU */
    .page-content-container {
        max-width: 900px;
        margin: -80px auto 40px auto; /* Negatif margin ile yukarı bindirme */
        padding: 60px;
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        position: relative;
        z-index: 10;
        font-size: 1.1rem;
        line-height: 1.8;
    }

    /* İçerik Tipografisi */
    .page-content-container h2 { color: #1e293b; font-size: 1.8rem; margin-top: 40px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .page-content-container h3 { color: #1e293b; font-size: 1.4rem; margin-top: 30px; margin-bottom: 15px; }
    .page-content-container ul, .page-content-container ol { margin-bottom: 20px; padding-left: 25px; }
    .page-content-container li { margin-bottom: 10px; }
    .page-content-container a { color: #4f46e5; text-decoration: underline; }
    .page-content-container img { max-width: 100%; height: auto; border-radius: 12px; margin: 20px 0; }

    /* MOBİL UYUMLULUK */
    @media (max-width: 768px) {
        .page-hero h1 { font-size: 2rem; }
        .page-content-container { padding: 30px 20px; margin-top: -50px; border-radius: 16px; width: 90%; }
    }
</style>

<div class="page-wrapper">

    <?php if ($page): ?>
        
        <header class="page-hero">
            <h1><?= htmlspecialchars($page['title']) ?></h1>
            <p>
                <?php if($page_slug == 'hakkimizda'): ?>
                    Sınırların ötesini okuyanların buluşma noktası.
                <?php else: ?>
                    Blogium Kurumsal
                <?php endif; ?>
            </p>
        </header>

        <article class="page-content-container">
            <?= $page['content'] ?>
        </article>

        <?php if ($page_slug == 'hakkimizda'): ?>
            <style>
                .features-section { padding: 40px 20px; background: #f8fafc; margin-top: 40px; }
                .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; max-width: 1100px; margin: 0 auto; }
                .feature-card { background: #fff; padding: 40px 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); text-align: center; }
                .card-icon { width: 70px; height: 70px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 25px; }
                .stats-grid { display: flex; justify-content: center; gap: 60px; flex-wrap: wrap; margin-top: 60px; padding-bottom: 40px; border-top: 1px solid #e2e8f0; paddingTop: 40px; max-width: 800px; margin-left: auto; margin-right: auto; }
                .stat-number { font-size: 2.5rem; font-weight: 800; color: #4f46e5; display: block; }
                .stat-label { font-size: 1rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
            </style>

            <section class="features-section">
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="card-icon"><i class="fas fa-rocket"></i></div>
                        <h3>Vizyonumuz</h3>
                        <p>Teknoloji ve bilim dünyasını herkes için anlaşılır kılmak.</p>
                    </div>
                    <div class="feature-card">
                        <div class="card-icon"><i class="fas fa-feather-alt"></i></div>
                        <h3>Özgünlük</h3>
                        <p>Kopyala-yapıştır değil, araştırılmış ve doğrulanmış içerik.</p>
                    </div>
                    <div class="feature-card">
                        <div class="card-icon"><i class="fas fa-users"></i></div>
                        <h3>Topluluk</h3>
                        <p>Merak eden zihinlerin buluşma noktası.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div style="text-align:center;">
                        <span class="stat-number">10+</span>
                        <span class="stat-label">İçerik</span>
                    </div>
                    <div style="text-align:center;">
                        <span class="stat-number">10K+</span>
                        <span class="stat-label">Okuyucu</span>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align: center; padding: 100px 20px;">
            <i class="fas fa-ghost" style="font-size: 50px; color: #cbd5e1; margin-bottom: 20px;"></i>
            <h2 style="color: #1e293b;">Sayfa Bulunamadı</h2>
            <p style="color: #64748b;">Aradığınız sayfa mevcut değil veya silinmiş.</p>
            <a href="/" style="display:inline-block; margin-top:20px; text-decoration:none; color:#4f46e5; font-weight:600;">Anasayfaya Dön &rarr;</a>
        </div>
    <?php endif; ?>

</div>

<?php
include __DIR__ . '/includes/footer.php';
?>