<?php
// signup.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/adminpanel/includes/db.php';
require_once __DIR__ . '/includes/mail.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$username_value = '';
$email_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Geçersiz form gönderimi.";
    } else {
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm'];
        $terms = isset($_POST['terms']);

        $email_value = htmlspecialchars($email);
        $username_value = htmlspecialchars($username);

        // Form doğrulama kontrolleri (değişiklik yok)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Lütfen geçerli bir e-posta formatı girin.";
        } elseif (!preg_match('/^[a-z0-9._-]{3,20}$/', $username)) {
            $message = "Kullanıcı adı 3-20 karakter arası, sadece küçük harf, rakam ve `._-` içerebilir.";
        } elseif (strlen($password) < 8) {
            $message = "Şifreniz en az 8 karakter uzunluğunda olmalıdır.";
        } elseif ($password !== $confirm) {
            $message = "Girdiğiniz şifreler birbiriyle uyuşmuyor.";
        } elseif (!$terms) {
            $message = "Kayıt olmak için kullanıcı sözleşmesini kabul etmelisiniz.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM blog_users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    $message = "Bu e-posta adresi veya kullanıcı adı zaten kullanımda.";
                } else {
                    $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', time() + 60 * 30);

                    $insert_stmt = $pdo->prepare("INSERT INTO blog_users (username, email, password, email_verified, verification_token, verification_token_expiry) VALUES (?, ?, ?, 0, ?, ?)");
                    $insert_stmt->execute([$username, $email, $password_hashed, $token, $expiry]);

                    $verify_link = "http://{$_SERVER['HTTP_HOST']}/verify_email.php?token=" . urlencode($token);
                    $subject = 'Blogium Hesabınızı Doğrulayın';
                    $body    = "Merhaba " . htmlspecialchars($username) . ",<br><br>Blogium'a hoş geldiniz! Hesabınızı aktive etmek için lütfen aşağıdaki bağlantıya tıklayın.<br><br><a href='{$verify_link}' style='padding: 12px 20px; background-color: #667eea; color: white; text-decoration: none; border-radius: 8px;'>Hesabımı Doğrula</a>";
                    
                    if (send_email($email, subject: $subject, body: $body)) {
                        // --- EN ÖNEMLİ DEĞİŞİKLİK BURADA ---
                        // Başarılı kayıttan sonra kullanıcıyı "Giriş Yap" sayfasına yönlendir.
                        header("Location: /signin.php?status=verification_sent");
                        exit;
                    } else {
                        $message = "Kayıt oluşturuldu ancak doğrulama e-postası gönderilemedi.";
                    }
                }
            } catch (PDOException $e) {
                error_log('Signup Error: ' . $e->getMessage());
                $message = "Kayıt sırasında bir veritabanı hatası oluştu.";
            }
        }
    }
}

$page_title = 'Kayıt Ol';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Hesap Oluştur</h1>
            <p class="auth-subtitle">Topluluğumuza katılmak için formu doldurun.</p>
            
            <?php if ($message): ?>
                <div class="error-box"><?= $message ?></div>
            <?php endif; ?>

            <form action="/signup.php" method="POST" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <!-- Form alanlarında değişiklik yok -->
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" value="<?= $username_value ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-posta Adresi</label>
                    <input type="email" id="email" name="email" value="<?= $email_value ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm">Şifre (Tekrar)</label>
                    <input type="password" id="confirm" name="confirm" required>
                </div>
                <div class="options-row" style="margin-bottom: 20px;">
                    <div class="remember-me">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms"><a href="/kullanici-sozlesmesi.php" target="_blank">Kullanıcı Sözleşmesini</a> kabul ediyorum.</label>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Hesap Oluştur</button>
            </form>
            
            <div class="switch-auth">
                Zaten bir hesabın var mı? <a href="/signin.php">Giriş Yap</a>
            </div>
        </div>
    </div>
</main>

<!-- Stillerde değişiklik yok -->
<style>
    .auth-page { display: flex; align-items: center; justify-content: center; min-height: 80vh; background-color: #f8fafc; padding: 40px 20px; }
    .auth-container { width: 100%; max-width: 450px; }
    .auth-card { background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07); }
    .auth-title { text-align: center; font-size: 28px; font-weight: 700; color: #1e293b; margin: 0; }
    .auth-subtitle { text-align: center; font-size: 16px; color: #64748b; margin: 10px 0 30px 0; }
    .error-box, .success-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid transparent; }
    .error-box { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
    .options-row { display: flex; align-items: center; }
    .remember-me { display: flex; align-items: center; gap: 8px; }
    .remember-me label { margin-bottom: 0; color: #475569; }
    .remember-me label a { color: #667eea; text-decoration: none; font-weight: 500; }
    .btn-submit { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
    .switch-auth { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 15px; color: #475569; }
    .switch-auth a { color: #667eea; text-decoration: none; font-weight: 600; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>