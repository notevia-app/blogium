<?php 
/**
 * Wikiquote'tan Günün Sözünü Çekip JSON Dosyasına Kaydeden Script
 * En kararlı sürüm: Spesifik bir görseli referans alarak tabloyu bulur (Sürüm 5).
 */

// Hata raporlamayı açarak olası sorunları görebiliriz.
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Hedef URL
//$url = 'https://tr.wikiquote.org/wiki/Anasayfa';
// Kaydedilecek dosyanın yolu
/**$output_file = __DIR__ . '/quote_of_the_day.json';

// Bu scriptin doğrudan tarayıcıdan çalıştırılıp çalıştırılmadığını kontrol et
$is_direct_run = (php_sapi_name() === 'cli' || !defined('INDEX_INCLUDED'));
if ($is_direct_run) {
    echo "Günün sözü çekiliyor...\n<br>";
}

try {
    // Sayfa içeriğini al
    $html = @file_get_contents($url);
    if ($html === false) {
        throw new Exception("Wikiquote sayfasına ulaşılamadı. Sunucu ayarlarınızda 'allow_url_fopen' kapalı olabilir.");
    }

    // DOM'u parse etmek için yeni nesneler oluştur
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    // --- EN GÜVENİLİR YÖNTEM: GÖRSEL İSMİNDEN TABLOYU BULMA ---
    
    // 1. İçinde "George_Orwell_press_photo.jpg" geçen `img` etiketini bul.
    // Bu görselin bulunduğu en yakın `table` elementini bul. Bu bizim ana tablomuzdur.
    $table_query = '//img[contains(@src, "George_Orwell_press_photo.jpg")]/ancestor::table[1]';
    $table_node = $xpath->query($table_query)->item(0);

    if (!$table_node) {
        throw new Exception("Günün Sözü tablosu bulunamadı. Referans görsel (George Orwell) sayfadan kaldırılmış veya değiştirilmiş olabilir.");
    }

    // 2. Artık doğru tabloyu bulduğumuza emin olduğumuz için, içinden veriyi çekebiliriz.
    // XPath'te `.` (nokta), sorguya mevcut bulunan node'dan ($table_node) başla demektir.
    $quote_node = $xpath->query('.//tr[1]/td[3]', $table_node)->item(0);
    $author_node = $xpath->query('.//tr[2]//a[1]', $table_node)->item(0);

    // Hata kontrolü
    if (!$quote_node) {
        throw new Exception("Sözün kendisi (quote_node) bulunamadı. Tablo yapısı değişmiş.");
    }
    if (!$author_node) {
        throw new Exception("Sözün yazarı (author_node) bulunamadı. Tablo yapısı değişmiş.");
    }

    // İçerikleri temizle
    $quote = trim($quote_node->nodeValue);
    $author = trim($author_node->nodeValue);

    // Veriyi array olarak hazırla
    $data = [
        'quote' => $quote,
        'author' => $author,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // JSON formatına çevirip dosyaya yaz
    if (file_put_contents($output_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        if ($is_direct_run) {
            echo "Başarılı! Günün sözü '$output_file' dosyasına kaydedildi.\n<br>";
            echo "Söz: <strong>$quote</strong> — Yazar: <strong>$author</strong>\n";
        }
    } else {
        throw new Exception("Dosya yazma hatası. Klasör izinlerini (örn: 755) kontrol edin.");
    }

} catch (Exception $e) {
    if ($is_direct_run) {
        echo '<div style="border: 2px solid red; padding: 10px; font-family: sans-serif; background-color: #ffebeb;">';
        echo '<strong>HATA:</strong> ' . $e->getMessage();
        echo '</div>';
    }
    error_log("Günün Sözü Hatası: " . $e->getMessage());
}

if (!$is_direct_run) {
    define('INDEX_INCLUDED', true);
}*/