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

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: categories.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    echo "Kategori bulunamadı.";
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
    if ($stmt->execute([$name, $slug, $id])) {
        header("Location: categories.php");
        exit;
    } else {
        $message = "❌ Güncelleme başarısız.";
    }
}
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<style>
    .form-container {
        max-width: 600px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        font-family: "Segoe UI", sans-serif;
    }

    .form-container h2 {
        margin-bottom: 20px;
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
        padding: 10px 20px;
        background: #000000;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s;
    }

    .form-container button:hover {
        background: #0056b3;
        transition:0.3s ease;
    }

    .message {
        color: red;
        margin-bottom: 15px;
    }
</style>

<div class="form-container">
    <h2>✏️ Kategori Düzenle</h2>
    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
        <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']) ?>" required>
        <button type="submit">Güncelle</button>
    </form>
</div>
