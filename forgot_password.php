<?php
// forgot_password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gerekli dosyalar
require_once __DIR__ . '/adminpanel/includes/db.php';
// --- HATA BURADAYDI, BU SATIRI EKLİYORUZ ---
require_once __DIR__ . '/includes/mail.php'; // Mail fonksiyonlarımızı içeren dosyayı dahil et

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$is_success = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Geçersiz form gönderimi.";
    } elseif (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $message = "Lütfen geçerli bir e-posta adresi girin.";
    } else {
        $email = trim($_POST['email']);
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM blog_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiry_time = date('Y-m-d H:i:s', time() + 60 * 15); // 15 dakika geçerlilik

                $update_stmt = $pdo->prepare("UPDATE blog_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $update_stmt->execute([$token, $expiry_time, $user['id']]);

                // E-posta içeriğini hazırla
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/reset_password.php?token=" . urlencode($token);
                $subject = 'Şifre Sıfırlama Talebi';
                $body    = "Merhaba,<br><br>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın. Bu bağlantı 15 dakika boyunca geçerlidir.<br><br>"
                         . "<a href='{$reset_link}' style='padding: 10px 15px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Şifremi Sıfırla</a><br><br>"
                         . "Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.";
                
                // Genel mail fonksiyonu ile gönder
                send_email($email, $subject, $body);
            }
            
            $is_success = true;
            $message = "Eğer girdiğiniz e-posta adresi sistemimizde kayıtlı ise, şifre sıfırlama bağlantısı gönderilmiştir. Lütfen gelen kutunuzu kontrol edin.";

        } catch (PDOException $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $message = "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}

$page_title = 'Şifremi Unuttum';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<!-- HTML KODU DEĞİŞMEDİ, AYNI KALIYOR -->
<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Şifremi Unuttum</h1>
            <p class="auth-subtitle">Hesabınıza ait e-posta adresini girerek şifrenizi sıfırlayabilirsiniz.</p>

            <?php if ($message): ?>
                <div class="<?= $is_success ? 'success-box' : 'error-box' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_success): ?>
            <form action="/forgot_password.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="form-group">
                    <label for="email">E-posta Adresiniz</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <button type="submit" class="btn-submit">Sıfırlama Bağlantısı Gönder</button>
            </form>
            <?php endif; ?>

            <div class="switch-auth">
                <a href="/signin.php">Giriş Sayfasına Dön</a>
            </div>
        </div>
    </div>
</main>

<!-- STİL KODLARI DEĞİŞMEDİ, AYNI KALIYOR -->
<style>
    .auth-page { display: flex; align-items: center; justify-content: center; min-height: 80vh; background-color: #f8fafc; padding: 40px 20px; }
    .auth-container { width: 100%; max-width: 450px; }
    .auth-card { background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07); }
    .auth-title { text-align: center; font-size: 28px; font-weight: 700; color: #1e293b; margin: 0; }
    .auth-subtitle { text-align: center; font-size: 16px; color: #64748b; margin: 10px 0 30px 0; }
    .error-box, .success-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid transparent; }
    .error-box { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .success-box { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; }
    .form-group input[type="email"] { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
    .btn-submit { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .switch-auth { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 15px; }
    .switch-auth a { color: #667eea; text-decoration: none; font-weight: 600; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>