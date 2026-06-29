<?php
// reset_password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/adminpanel/includes/db.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error_message = '';
$token = $_GET['token'] ?? '';
$is_token_valid = false;

if (empty($token)) {
    $error_message = "Geçersiz sıfırlama bağlantısı. Lütfen tekrar deneyin.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT id FROM blog_users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $is_token_valid = true;
        } else {
            $error_message = "Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen yeni bir talepte bulunun.";
        }
    } catch (PDOException $e) {
        error_log("Token Check Error: " . $e->getMessage());
        $error_message = "Bir veritabanı hatası oluştu.";
    }
}

// Yeni şifre formu gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_token_valid) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = "Geçersiz form gönderimi.";
    } elseif (empty($_POST['password']) || empty($_POST['confirm_password'])) {
        $error_message = "Lütfen her iki şifre alanını da doldurun.";
    } elseif (strlen($_POST['password']) < 8) {
        $error_message = "Yeni şifreniz en az 8 karakter uzunluğunda olmalıdır.";
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error_message = "Girdiğiniz şifreler uyuşmuyor.";
    } else {
        try {
            $new_password_hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Şifreyi güncelle ve token'ı temizle
            $update_stmt = $pdo->prepare("UPDATE blog_users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $update_stmt->execute([$new_password_hashed, $token]);

            // Başarılı olursa giriş sayfasına yönlendir
            header("Location: /signin.php?reset=success");
            exit;
            
        } catch (PDOException $e) {
            error_log("Password Update Error: " . $e->getMessage());
            $error_message = "Şifre güncellenirken bir hata oluştu.";
        }
    }
}

$page_title = 'Yeni Şifre Belirle';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Yeni Şifre Belirle</h1>

            <?php if ($error_message): ?>
                <div class="error-box"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($is_token_valid): ?>
                <p class="auth-subtitle">Lütfen yeni şifrenizi oluşturun.</p>
                <form action="/reset_password.php?token=<?= htmlspecialchars($token) ?>" method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="password">Yeni Şifre</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-submit">Şifreyi Güncelle</button>
                </form>
            <?php else: ?>
                <div class="switch-auth" style="border-top:none; padding-top:0;">
                    <a href="/forgot_password.php">Yeni bir sıfırlama talebi gönder</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Stil kodları signin.php sayfanızla aynı olmalı -->
<style>
    /* ... signin.php'den kopyalanan tüm stil kodları buraya gelecek ... */
    .auth-page { display: flex; align-items: center; justify-content: center; min-height: 80vh; background-color: #f8fafc; padding: 40px 20px; }
    .auth-container { width: 100%; max-width: 450px; }
    .auth-card { background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07); }
    .auth-title { text-align: center; font-size: 28px; font-weight: 700; color: #1e293b; margin: 0; }
    .auth-subtitle { text-align: center; font-size: 16px; color: #64748b; margin: 10px 0 30px 0; }
    .error-box { background: #fee2e2; color: #b91c1c; border-color: #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; }
    .form-group input[type="password"] { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
    .btn-submit { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .switch-auth { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 15px; }
    .switch-auth a { color: #667eea; text-decoration: none; font-weight: 600; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>