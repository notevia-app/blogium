<?php
require_once __DIR__ . '/init.php';

// EĞER KULLANICI ZATEN GİRİŞ YAPMIŞSA, ONU DİREKT ANA SAYFAYA YOLLA.
// Buradaki karmaşık yönlendirme mantığına gerek yok.
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$error_message = null;
$success_message = null;

// --- YÖNLENDİRME MESAJLARINI YAKALA ---
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'verification_sent') {
        $success_message = "Kayıt başarılı! Lütfen hesabınızı aktive etmek için e-postanıza gönderilen bağlantıya tıklayın.";
    }
}
if (isset($_GET['verified']) && $_GET['verified'] === 'true') {
    $success_message = "Hesabınız başarıyla doğrulandı! Şimdi giriş yapabilirsiniz.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username_or_email'], $_POST['password'])) {
        $username_or_email = trim($_POST['username_or_email']);
        $password = $_POST['password'];

        try {
            $stmt = $pdo->prepare(
                "SELECT id, username, email, password, email_verified FROM blog_users WHERE username = ? OR email = ? LIMIT 1"
            );
            $stmt->execute([$username_or_email, $username_or_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                
                if ($user['email_verified'] == 0) {
                    $error_message = "Giriş yapmadan önce hesabınızı doğrulamanız gerekmektedir. E-postanıza gönderilen bağlantıyı kontrol edin.";
                } else {
                    // Doğrulama başarılı, oturumu başlat.
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    
                    // "Beni Hatırla" işlemi (Bu kod doğru yerde)
                    if (isset($_POST['remember_me'])) {
                        // ... (Beni hatırla kodunuz burada aynen kalıyor)
                        $token = bin2hex(random_bytes(32));
                        $expires = new DateTime('+30 days');
                        $token_stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                        $token_stmt->execute([$user['id'], $token, $expires->format('Y-m-d H:i:s')]);
                        setcookie('remember_me_token', $token, ['expires' => $expires->getTimestamp(), 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
                    }

                    // --- YENİ VE DOĞRU YÖNLENDİRME MANTIĞI BURADA ---
                    // Önceki header('Location: /'); satırını silip bunu ekliyoruz.

                    // 1. Varsayılan olarak ana sayfaya yönlendir.
                    $redirect_url = '/'; // Ana sayfa için / veya /index.php kullanabilirsiniz.

                    // 2. Formdan gelen bir redirect adresi var mı diye kontrol et.
                    if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
                        $potential_url = $_POST['redirect'];
                        
                        // 3. Güvenlik Kontrolü: Yönlendirmenin sadece kendi sitenize ait olduğundan emin ol.
                        if (substr($potential_url, 0, 1) === '/') {
                            $redirect_url = $potential_url;
                        }
                    }

                    // 4. Belirlenen adrese yönlendir.
                    header('Location: ' . $redirect_url);
                    exit;
                }

            } else {
                $error_message = "Kullanıcı adı/e-posta veya şifre hatalı.";
            }

        } catch (PDOException $e) {
            error_log("Giriş hatası: " . $e->getMessage());
            $error_message = "Giriş sırasında bir veritabanı hatası oluştu.";
        }
    } else {
        $error_message = "Lütfen tüm alanları doldurun.";
    }
}

$page_title = 'Giriş Yap';
// Header'ı en sona bırakmak, yukarıdaki header() yönlendirmelerinin sorunsuz çalışmasını sağlar.
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Giriş Yap</h1>
            <p class="auth-subtitle">Hesabınıza erişmek için bilgilerinizi girin.</p>

            <?php if ($error_message): ?>
                <div class="error-box"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-box"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <form action="/signin.php" method="POST" class="auth-form">
                <input type="hidden" name="redirect" value="<?= isset($_GET['redirect']) ? htmlspecialchars($_GET['redirect']) : '' ?>">
                <!-- Form içeriği aynı -->
                <div class="form-group">
                    <label for="username_or_email">Kullanıcı Adı veya E-posta</label>
                    <input type="text" id="username_or_email" name="username_or_email" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="options-row">
                    <div class="remember-me">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Beni Hatırla</label>
                    </div>
                    <a href="/forgot_password.php" class="forgot-link">Şifremi Unuttum</a>
                </div>
                <button type="submit" class="btn-submit">Giriş Yap</button>
            </form>

            <div class="switch-auth">
                Hesabınız yok mu? <a href="/signup.php">Hemen Kayıt Olun</a>
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
    .success-box { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
    .options-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 14px; }
    .remember-me { display: flex; align-items: center; gap: 8px; }
    .remember-me label { margin-bottom: 0; color: #475569; }
    .forgot-link { color: #667eea; text-decoration: none; font-weight: 500; }
    .btn-submit { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
    .switch-auth { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 15px; color: #475569; }
    .switch-auth a { color: #667eea; text-decoration: none; font-weight: 600; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>