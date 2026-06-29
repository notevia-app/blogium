<?php
// Gerekli veritabanı bağlantısı
require_once __DIR__ . '/adminpanel/includes/db.php';

// Temel URL
$base_url = "https://www.blogium.net";

// XML başlığını gönder
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- ================== -->
    <!--  Statik Sayfalar   -->
    <!-- ================== -->
    <?php
    // Sitenizdeki ana ve statik sayfaları bir diziye ekleyin
    $static_pages = [
        ['loc' => '/',               'changefreq' => 'daily',   'priority' => '1.0'],
        ['loc' => '/iletisim',       'changefreq' => 'yearly',  'priority' => '0.5']
    ];

    foreach ($static_pages as $page) {
        echo "<url>\n";
        echo "  <loc>" . htmlspecialchars($base_url . $page['loc']) . "</loc>\n";
        echo "  <changefreq>" . $page['changefreq'] . "</changefreq>\n";
        echo "  <priority>" . $page['priority'] . "</priority>\n";
        echo "</url>\n";
    }
    ?>

    <!-- ==================================== -->
    <!--  Diğer Sayfalar (pages tablosundan)  -->
    <!-- ==================================== -->
    <?php
    try {
        $pageStmt = $pdo->query("SELECT slug FROM pages");
        while ($page = $pageStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<url>\n";
            echo "  <loc>" . htmlspecialchars($base_url . '/' . $page['slug']) . "</loc>\n";
            echo "  <changefreq>monthly</changefreq>\n";
            echo "  <priority>0.6</priority>\n";
            echo "</url>\n";
        }
    } catch (PDOException $e) { /* Hata olursa es geç */ }
    ?>

    <!-- ========================================= -->
    <!--  Kategoriler (Veritabanından, Temiz URL)  -->
    <!-- ========================================= -->
    <?php
    try {
        $catStmt = $pdo->query("SELECT slug FROM categories");
        while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<url>\n";
            // .htaccess ile ayarladığımız SEO dostu URL yapısını kullanıyoruz
            echo "  <loc>" . htmlspecialchars($base_url . '/kategori/' . $cat['slug']) . "</loc>\n";
            echo "  <changefreq>weekly</changefreq>\n";
            echo "  <priority>0.8</priority>\n";
            echo "</url>\n";
        }
    } catch (PDOException $e) { /* Hata olursa es geç */ }
    ?>

    <!-- ==================================== -->
    <!--  Yazılar (Veritabanından, Temiz URL)  -->
    <!-- ==================================== -->
    <?php
    try {
        // created_at yerine updated_at varsa onu kullanmak daha iyidir
        $postStmt = $pdo->query("SELECT slug, created_at FROM posts ORDER BY created_at DESC");
        while ($post = $postStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<url>\n";
            // .htaccess ile ayarladığımız SEO dostu URL yapısını kullanıyoruz
            echo "  <loc>" . htmlspecialchars($base_url . '/yazi/' . $post['slug']) . "</loc>\n";
            // lastmod etiketi, sayfanın son güncellenme tarihini belirtir ve çok önemlidir
            echo "  <lastmod>" . date('Y-m-d', strtotime($post['created_at'])) . "</lastmod>\n";
            echo "  <changefreq>monthly</changefreq>\n";
            echo "  <priority>0.9</priority>\n";
            echo "</url>\n";
        }
    } catch (PDOException $e) { /* Hata olursa es geç */ }
    ?>

</urlset>