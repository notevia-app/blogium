<?php
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
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /adminpanel");
    exit;
}

require 'includes/db.php';
require 'includes/menu.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);

    // Aynı slug varsa engelle
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetchColumn() > 0) {
        $message = "⚠️ Bu slug zaten mevcut!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        if ($stmt->execute([$name, $slug])) {
            header("Location: categories.php");
            exit;
        } else {
            $message = "❌ Kategori eklenirken hata oluştu.";
        }
    }
}
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    body {
        margin: 0;
        padding: 0;
        font-family: "Segoe UI", sans-serif;
        background: #f5f5f5;
    }

    .form-container {
        max-width: 600px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }

    .form-container h2 {
        margin-bottom: 20px;
        font-size: 24px;
        text-align: center;
    }

    .form-container input {
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 16px;
    }

    .form-container button {
        width: 100%;
        padding: 12px;
        background: #007BFF;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.2s;
    }

    .form-container button:hover {
        background: #0056b3;
    }

    .message {
        color: red;
        margin-bottom: 15px;
        text-align: center;
    }

    @media (max-width: 600px) {
        .form-container {
            margin: 75px 10px;
            padding: 20px;
        }

        .form-container h2 {
            font-size: 20px;
        }

        .form-container input,
        .form-container button {
            font-size: 15px;
            padding: 10px;
        }
    }
</style>

<div class="form-container">
    <h2>➕ Yeni Kategori Ekle</h2>
    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="name" placeholder="Kategori adı" required>
        <input type="text" name="slug" placeholder="Slug (URL için, örn: bilim)" required>
        <button type="submit">Kaydet</button>
    </form>
</div>
