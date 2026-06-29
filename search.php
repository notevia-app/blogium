<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/adminpanel/includes/db.php';

// Aranan terimi al ve temizle
$search_query = trim($_GET['q'] ?? '');

// Eğer arama terimi boşsa, işlem yapma
if (empty($search_query)) {
    $page_title = "Arama";
    $posts = [];
    $total_results = 0;
} else {
    $page_title = '"' . htmlspecialchars($search_query) . '" için arama sonuçları';

    // Arama sorgusu için '%' karakterlerini ekle (LIKE için)
    // Arama terimini küçük harfe çevirerek büyük/küçük harf duyarsız arama yap
    $search_term_like = '%' . mb_strtolower($search_query, 'UTF-8') . '%';
    
    // Güvenli arama sorgusu: Hem başlıkta, hem içerikte, hem de etiketlerde ara
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM posts 
             WHERE 
                LOWER(title) LIKE :search_term_title OR 
                LOWER(content) LIKE :search_term_content OR 
                LOWER(tags) LIKE :search_term_tags 
             ORDER BY created_at DESC"
        );
        // Parametreleri bağla
        $stmt->execute([
            'search_term_title' => $search_term_like,
            'search_term_content' => $search_term_like,
            'search_term_tags' => $search_term_like // Etiketlerde de arama terimini kullan
        ]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_results = count($posts);

    } catch (PDOException $e) {
        error_log("Arama hatası: " . $e->getMessage());
        $posts = [];
        $total_results = 0;
        // Kullanıcıya bir hata mesajı da gösterebilirsiniz
    }
}

/** Aranan kelimeyi vurgulayan fonksiyon **/
function highlight_term($text, $term) {
    if (empty($term)) {
        return $text;
    }
    // Düzenli ifade ile büyük/küçük harf duyarsız değiştirme
    // preg_quote, özel karakterlerin sorun çıkarmasını engeller.
    // Unicode karakterler için /u bayrağı eklendi
    return preg_replace('/(' . preg_quote($term, '/') . ')/iu', '<mark class="highlight">$1</mark>', $text);
}

/** Tarihi Türkçe formata çeviren fonksiyon **/
function format_turkish_date($date_string) {
    if (empty($date_string)) return '';
    $timestamp = strtotime($date_string);
    $aylar = [1=>'Ocak', 2=>'Şubat', 3=>'Mart', 4=>'Nisan', 5=>'Mayıs', 6=>'Haziran', 7=>'Temmuz', 8=>'Ağustos', 9=>'Eylül', 10=>'Ekim', 11=>'Kasım', 12=>'Aralık'];
    return date('d', $timestamp) . ' ' . $aylar[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Header'ı dahil et
include __DIR__ . '/includes/header.php';
// Gereksiz include kaldırıldı: adminpanel/includes/head.php
// include __DIR__ . '/adminpanel/includes/head.php'; 
?>

<style>
    /* Arama sayfası için özel stiller */
    .search-page-container {
        max-width: 900px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .search-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .search-header h1 {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
    }
    .search-header h1 .query-term {
        color: #667eea;
    }
    .search-header p {
        font-size: 16px;
        color: #64748b;
    }
    .search-form-container {
        margin-bottom: 40px;
    }
    .search-form-container form {
        position: relative;
    }
    .search-form-container .search-box {
        width: 100%;
        height: 55px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0 60px 0 20px;
        font-size: 18px;
        background-color: #fff;
    }
    .search-form-container .search-btn {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 20px;
        color: #94a3b8;
        cursor: pointer;
    }

    /* Vurgulama stili */
    .highlight {
        background-color: #fefce8; /* Açık sarı */
        color: #a16207; /* Koyu sarı */
        padding: 2px 4px;
        border-radius: 4px;
        font-weight: 600;
    }

    /* Arama Sonuçları Kart Stilleri */
    .search-results-list {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }
    .search-result-card {
        background-color: #fff;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        text-decoration: none;
        color: inherit;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .search-result-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08);
    }
    .search-result-card h2 {
        font-size: 22px;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 10px 0;
    }
    .search-result-card .snippet {
        font-size: 15px;
        line-height: 1.6;
        color: #475569;
        margin-bottom: 15px;
    }
    .search-result-card .meta-info {
        font-size: 13px;
        color: #94a3b8;
    }

    /* Sonuç bulunamadı mesajı */
    .no-results-message {
        text-align: center;
        padding: 60px 20px;
        background-color: #fff;
        border-radius: 16px;
    }
    .no-results-message i {
        font-size: 40px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }
    .no-results-message h3 {
        font-size: 20px;
        color: #1e293b;
        margin-bottom: 10px;
    }
    .no-results-message p {
        color: #64748b;
    }

    @media (max-width: 768px) {
        .search-header h1 { font-size: 26px; }
    }
</style>

<main class="search-page-container">

    <div class="search-header">
        <?php if (!empty($search_query)): ?>
            <h1>
                "<span class="query-term"><?= htmlspecialchars($search_query) ?></span>" için arama sonuçları
            </h1>
            <p><?= $total_results ?> sonuç bulundu</p>
        <?php else: ?>
            <h1>Arama</h1>
            <p>Lütfen aramak istediğiniz kelimeyi yazın.</p>
        <?php endif; ?>
    </div>

    <div class="search-form-container">
        <form action="/search.php" method="GET">
            <input type="text" name="q" class="search-box" placeholder="Yeni bir arama yapın..." value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="search-results-list">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <a href="/yazi/<?= urlencode($post['slug']) ?>" class="search-result-card">
                    <h2><?= highlight_term(htmlspecialchars($post['title']), $search_query) ?></h2>
                    <p class="snippet">
                        <?= highlight_term(htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 250)), $search_query) ?>...
                    </p>
                    <div class="meta-info">
                        <span><?= format_turkish_date($post['created_at']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php elseif (!empty($search_query)): ?>
            <div class="no-results-message">
                <i class="fas fa-search"></i>
                <h3>Sonuç bulunamadı</h3>
                <p>Aradığınız kriterlere uygun sonuç bulunamadı. Lütfen farklı bir kelime ile tekrar deneyin.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php
include __DIR__ . '/includes/footer.php';
?>