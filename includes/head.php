<?php
// Veritabanı bağlantısı yoksa dahil et (Garanti olsun diye)
if (!isset($pdo)) {
    // includes/head.php'nin çağrıldığı konuma göre yol değişebilir, 
    // ancak genellikle ana dizinden çağrıldığı için api/db.php veya includes/db.php kullanılır.
    // Mevcut yapınıza göre en güvenli yol api/db.php gibi görünüyor dosya listesinden.
    // Eğer ana dizinde init.php varsa orada db çağrılmış olabilir.
    // Biz burada hata vermemesi için güvenli bir kontrol yapıyoruz.
    if (file_exists(__DIR__ . '/../api/db.php')) {
        require_once __DIR__ . '/../api/db.php';
    } elseif (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    }
}

// Ayarları veritabanından çek
$settings_stmt = $pdo->prepare("SELECT * FROM settings LIMIT 1");
$settings_stmt->execute();
$site_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

// Veritabanı boşsa varsayılan değerler (Yedek)
$default_title = $site_settings['site_title'] ?? 'Blogium';
$default_slogan = $site_settings['site_slogan'] ?? ' - İçerik Burada Başlar';
$default_desc  = $site_settings['seo_description'] ?? ($site_settings['site_desc'] ?? 'Blogium modern blog platformu.');
$default_keywords = $site_settings['meta_tags'] ?? 'blog, teknoloji, bilim';

// Sayfa özelinde başlık belirlenmemişse (Anasayfa gibi)
if (!isset($page_title)) {
    // Anasayfadaysak: Site Adı + Slogan
    $final_title = $default_title . $default_slogan;
} else {
    // Alt sayfadaysak: Yazı Başlığı - Site Adı
    $final_title = $page_title . ' - ' . $default_title;
}

// Sayfa özelinde açıklama yoksa genel SEO açıklamasını kullan
$final_desc = $page_description ?? $default_desc;

// Anahtar kelimeler
$final_keywords = $page_keywords ?? $default_keywords;

// URL ve Resim
$final_url = $page_url ?? 'https://www.blogium.net' . $_SERVER['REQUEST_URI'];
$final_image = $page_image ?? 'https://www.blogium.net/logo.png';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LTMEQQ1J5Y"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-LTMEQQ1J5Y');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= htmlspecialchars($default_title) ?>">
    <meta name="theme-color" content="#1d4ed8">

    <title><?= htmlspecialchars($final_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($final_desc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($final_keywords) ?>">

    <link rel="canonical" href="<?= htmlspecialchars($final_url) ?>" />
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "<?= htmlspecialchars($default_title) ?>",
      "url": "https://www.blogium.net/",
      "logo": "https://www.blogium.net/logo.png"
    }
    </script>

    <meta property="og:title" content="<?= htmlspecialchars($final_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($final_desc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($final_image) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($final_url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($default_title) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($final_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($final_desc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($final_image) ?>">

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($default_title) ?>" />
    <link rel="manifest" href="/site.webmanifest" />
</head>