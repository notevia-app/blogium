<?php
// api/image.php

// Hata ayıklamayı kapatalım ki resim verisi bozulmasın
error_reporting(0);
ini_set('display_errors', 0);

// Gerekli parametreleri al
$source_path = $_GET['src'] ?? '';
$width = isset($_GET['w']) ? (int)$_GET['w'] : null;
$height = isset($_GET['h']) ? (int)$_GET['h'] : null;
$quality = isset($_GET['q']) ? (int)$_GET['q'] : 80; // Varsayılan kalite %80

if (empty($source_path) || (!$width && !$height)) {
    http_response_code(400);
    die('Kaynak dosya veya boyut belirtilmedi.');
}

// Güvenlik: Sadece belirli bir klasörden dosya okunmasına izin ver
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/';
$full_path = realpath($base_path . $source_path);

if ($full_path === false || strpos($full_path, realpath($base_path . 'assets/post_images/')) !== 0) {
    http_response_code(403);
    die('Geçersiz dosya yolu.');
}

if (!file_exists($full_path)) {
    http_response_code(404);
    die('Dosya bulunamadı.');
}

// Önbellek (cache) klasörü oluşturalım
$cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/api/cache/images/';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Önbelleklenmiş dosyanın adını ve yolunu oluştur
$cache_filename = md5($source_path . $width . $height . $quality) . '.webp';
$cache_filepath = $cache_dir . $cache_filename;

// Eğer önbelleklenmiş dosya zaten varsa, onu gönder ve işlemi bitir
if (file_exists($cache_filepath)) {
    header('Content-Type: image/webp');
    readfile($cache_filepath);
    exit;
}

// Orijinal resmi yükle
list($original_width, $original_height, $type) = getimagesize($full_path);
switch ($type) {
    case IMAGETYPE_JPEG:
        $image = imagecreatefromjpeg($full_path);
        break;
    case IMAGETYPE_PNG:
        $image = imagecreatefrompng($full_path);
        break;
    case IMAGETYPE_WEBP:
        $image = imagecreatefromwebp($full_path);
        break;
    default:
        http_response_code(415);
        die('Desteklenmeyen resim formatı.');
}

// Yeni boyutları hesapla (oranları koruyarak)
if ($width && !$height) {
    $new_width = $width;
    $new_height = floor($original_height * ($width / $original_width));
} elseif (!$width && $height) {
    $new_height = $height;
    $new_width = floor($original_width * ($height / $original_height));
} else {
    $new_width = $width;
    $new_height = $height;
}

// Yeniden boyutlandırılmış boş bir tuval oluştur
$new_image = imagecreatetruecolor($new_width, $new_height);
// PNG şeffaflığını korumak için
imagealphablending($new_image, false);
imagesavealpha($new_image, true);
$transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);

// Orijinal resmi yeni tuvale kopyala ve yeniden boyutlandır
imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

// Tarayıcıya WebP olarak gönder
header('Content-Type: image/webp');
imagewebp($new_image, null, $quality);

// Önbelleğe kaydet
imagewebp($new_image, $cache_filepath, $quality);

// Belleği temizle
imagedestroy($image);
imagedestroy($new_image);
?>