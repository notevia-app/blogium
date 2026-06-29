<?php
// verify_email.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/adminpanel/includes/db.php';

$message = '';
$is_success = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = "Geçersiz doğrulama bağlantısı.";
} else {
    try {
        // Token'a sahip, süresi dolmamış ve henüz doğrulanmamış kullanıcıyı bul
        $stmt = $pdo->prepare(
            "SELECT id FROM blog_users 
             WHERE verification_token = ? AND verification_token_expiry > NOW() AND email_verified = 0 
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Kullanıcıyı doğrulanmış olarak işaretle ve token'ı temizle
            $update_stmt = $pdo->prepare(
                "UPDATE blog_users 
                 SET email_verified = 1, verification_token = NULL, verification_token_expiry = NULL 
                 WHERE id = ?"
            );
            $update_stmt->execute([$user['id']]);

            $is_success = true;
            $message = "Hesabınız başarıyla doğrulandı! Artık giriş yapabilirsiniz.";
        } else {
            $message = "Bu doğrulama bağlantısı geçersiz veya süresi dolmuş. Lütfen tekrar kayıt olmayı deneyin.";
        }
    } catch (PDOException $e) {
        error_log("Email verification error: " . $e->getMessage());
        $message = "Doğrulama sırasında bir veritabanı hatası oluştu.";
    }
}

$page_title = 'Hesap Doğrulama';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/adminpanel/includes/head.php'; ?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Hesap Doğrulama</h1>
            <div class="<?= $is_success ? 'success-box' : 'error-box' ?>" style="margin-top: 20px;">
                <?= $message ?>
            </div>
            <?php if ($is_success): ?>
            <div class="switch-auth" style="border-top: none; padding-top: 20px;">
                <a href="/signin.php" class="btn-submit" style="text-decoration:none;">Giriş Yap</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Stiller signin.php sayfanızla aynı olmalı -->
<style>
    .auth-page { display: flex; align-items: center; justify-content: center; min-height: 80vh; background-color: #f8fafc; padding: 40px 20px; }
    .auth-container { width: 100%; max-width: 450px; }
    .auth-card { background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07); }
    .auth-title { text-align: center; font-size: 28px; font-weight: 700; color: #1e293b; margin: 0; }
    .error-box, .success-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid transparent; }
    .error-box { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .success-box { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .btn-submit { display: block; width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
    .switch-auth { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 15px; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>