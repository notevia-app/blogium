<?php
// account_settings.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /signin.php');
    exit;
}

require_once __DIR__ . '/adminpanel/includes/db.php';
require_once __DIR__ . '/includes/mail.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
// E-posta değiştirme adımlarını takip etmek için
$email_change_step = 'form'; 

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password, new_email FROM blog_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: /signin.php');
        exit;
    }
    // Eğer veritabanında onay bekleyen bir e-posta varsa, doğrulama adımını göster
    if (!empty($user['new_email'])) {
        $email_change_step = 'verify';
    }
} catch (PDOException $e) {
    error_log("Account settings fetch error: " . $e->getMessage());
    die("Kullanıcı bilgileri alınırken bir hata oluştu.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Geçersiz form gönderimi.";
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // --- E-POSTA DEĞİŞİKLİĞİ ADIM 1: KOD GÖNDER ---
        if ($action === 'request_email_change') {
            $new_email = trim($_POST['new_email']);
            $password = $_POST['password'];

            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $message = "Lütfen geçerli bir e-posta adresi girin.";
                $message_type = 'error';
            } elseif ($new_email === $user['email']) {
                $message = "Yeni e-posta adresi mevcut adresinizle aynı olamaz.";
                $message_type = 'error';
            } elseif (!password_verify($password, $user['password'])) {
                $message = "Şifreniz yanlış.";
                $message_type = 'error';
            } else {
                // Bu e-postanın başka bir kullanıcı tarafından kullanılıp kullanılmadığını kontrol et
                $stmt = $pdo->prepare("SELECT id FROM blog_users WHERE email = ?");
                $stmt->execute([$new_email]);
                if ($stmt->fetch()) {
                    $message = "Bu e-posta adresi zaten başka bir hesap tarafından kullanılıyor.";
                    $message_type = 'error';
                } else {
                    $token = rand(100000, 999999); // 6 haneli doğrulama kodu
                    $expiry = date('Y-m-d H:i:s', time() + 60 * 15); // 15 dakika

                    $update_stmt = $pdo->prepare("UPDATE blog_users SET new_email = ?, email_change_token = ?, email_change_expiry = ? WHERE id = ?");
                    $update_stmt->execute([$new_email, $token, $expiry, $user_id]);
                    
                    $subject = 'E-posta Değişikliği Doğrulama Kodu';
                    $body = "Merhaba,<br><br>Hesabınız için e-posta adresi değişikliği talebinde bulundunuz. Doğrulama kodunuz aşağıdadır:<br><br><h2><b>$token</b></h2>";
                    
                    if (send_email($new_email, $subject, $body)) {
                        $message = "Doğrulama kodu <strong>" . htmlspecialchars($new_email) . "</strong> adresine gönderildi.";
                        $message_type = 'success';
                        $email_change_step = 'verify';
                    } else {
                        $message = "Doğrulama e-postası gönderilemedi.";
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // --- E-POSTA DEĞİŞİKLİĞİ ADIM 2: KODU DOĞRULA ---
        if ($action === 'verify_email_change') {
            $code = $_POST['verification_code'];
            
            $stmt = $pdo->prepare("SELECT new_email FROM blog_users WHERE id = ? AND email_change_token = ? AND email_change_expiry > NOW()");
            $stmt->execute([$user_id, $code]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($request) {
                // E-postayı güncelle ve geçici verileri temizle
                $final_stmt = $pdo->prepare("UPDATE blog_users SET email = ?, new_email = NULL, email_change_token = NULL, email_change_expiry = NULL WHERE id = ?");
                $final_stmt->execute([$request['new_email'], $user_id]);
                
                $_SESSION['email'] = $request['new_email']; // Oturumdaki e-postayı da güncelle
                $message = "E-posta adresiniz başarıyla güncellendi.";
                $message_type = 'success';
                $email_change_step = 'form'; // İşlem bitti, formu normale döndür
                // Sayfanın güncel e-posta ile yeniden yüklenmesi için
                header("Location: /account_settings.php?success=email_updated");
                exit;
            } else {
                $message = "Girdiğiniz kod yanlış veya süresi dolmuş.";
                $message_type = 'error';
                $email_change_step = 'verify';
            }
        }


        // --- ŞİFRE GÜNCELLEME ---
        if ($action === 'update_password') {
            // (Bu kod bloğu aynı kalıyor, değişiklik yok)
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!password_verify($current_password, $user['password'])) {
                $message = "Mevcut şifreniz doğru değil.";
                $message_type = 'error';
            } elseif (strlen($new_password) < 8) {
                $message = "Yeni şifre en az 8 karakter olmalıdır.";
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = "Yeni şifreler uyuşmuyor.";
                $message_type = 'error';
            } else {
                $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE blog_users SET password = ? WHERE id = ?");
                $stmt->execute([$new_password_hashed, $user_id]);
                $message = "Şifreniz başarıyla güncellendi.";
                $message_type = 'success';
            }
        }

        // --- HESAP SİLME ---
        if ($action === 'delete_account') {
            // (Bu kod bloğu aynı kalıyor, değişiklik yok)
            $delete_password = $_POST['delete_password'];
            if (!password_verify($delete_password, $user['password'])) {
                $message = "Hesabınızı silmek için girdiğiniz şifre yanlış.";
                $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM blog_users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                session_destroy();
                header("Location: /index.php?account_deleted=1");
                exit;
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'email_updated') {
    $message = "E-posta adresiniz başarıyla güncellendi.";
    $message_type = 'success';
}


$page_title = 'Hesap Ayarları';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<main class="auth-page">
    <div class="auth-container" style="max-width: 600px;">
        
        <h1 class="auth-title" style="font-size: 28px; margin-bottom: 30px;">Hesap Ayarları</h1>

        <?php if ($message): ?>
            <div class="<?= $message_type === 'success' ? 'success-box' : 'error-box' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Profil Bilgileri Kartı -->
        <div class="auth-card">
            <h2 class="card-title">Profil Bilgileri</h2>
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
            </div>
            <div class="form-group">
                <label>Mevcut E-posta Adresi</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
            </div>
        </div>
        
        <!-- E-posta Değiştirme Kartı -->
        <div class="auth-card">
             <?php if ($email_change_step === 'form'): ?>
                <h2 class="card-title">E-posta Değiştir</h2>
                <form action="/account_settings.php" method="POST" class="auth-form">
                    <input type="hidden" name="action" value="request_email_change">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="new_email">Yeni E-posta Adresi</label>
                        <input type="email" id="new_email" name="new_email" required>
                    </div>
                    <div class="form-group">
                        <label for="password_for_email">Onay için Mevcut Şifreniz</label>
                        <input type="password" id="password_for_email" name="password" required>
                    </div>
                    <button type="submit" class="btn-submit">Doğrulama Kodu Gönder</button>
                </form>
            <?php else: // $email_change_step === 'verify' ?>
                <h2 class="card-title">Yeni E-postayı Doğrula</h2>
                <p style="font-size: 14px; color: #475569; margin-bottom: 20px;">Lütfen <strong><?= htmlspecialchars($user['new_email']) ?></strong> adresine gönderilen 6 haneli kodu girin.</p>
                <form action="/account_settings.php" method="POST" class="auth-form">
                    <input type="hidden" name="action" value="verify_email_change">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="verification_code">Doğrulama Kodu</label>
                        <input type="text" inputmode="numeric" id="verification_code" name="verification_code" required autofocus>
                    </div>
                    <button type="submit" class="btn-submit">E-postayı Onayla ve Değiştir</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Şifre Değiştirme Kartı -->
        <div class="auth-card">
            <h2 class="card-title">Şifre Değiştir</h2>
            <form action="/account_settings.php" method="POST" class="auth-form">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="form-group">
                    <label for="current_password">Mevcut Şifre</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Yeni Şifre</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-submit">Şifreyi Güncelle</button>
            </form>
        </div>

        <!-- Hesap Silme Kartı -->
        <div class="auth-card danger-zone">
            <h2 class="card-title">Tehlikeli Bölge</h2>
            <p>Bu işlem geri alınamaz. Hesabınızı sildiğinizde tüm verileriniz kalıcı olarak yok olacaktır.</p>
            <form action="/account_settings.php" method="POST" class="auth-form" onsubmit="return confirm('Hesabınızı kalıcı olarak silmek istediğinizden emin misiniz?');">
                <input type="hidden" name="action" value="delete_account">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="form-group">
                    <label for="delete_password">Onaylamak için şifrenizi girin</label>
                    <input type="password" id="delete_password" name="delete_password" required>
                </div>
                <button type="submit" class="btn-submit btn-danger">Hesabımı Kalıcı Olarak Sil</button>
            </form>
        </div>

    </div>
</main>

<!-- Stil kodları aynı kalıyor -->
<style>
    .auth-page { display: flex; justify-content: center; min-height: 80vh; background-color: #f8fafc; padding: 40px 20px; }
    .auth-container { width: 100%; max-width: 600px; }
    .auth-card { background-color: #ffffff; padding: 30px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
    .auth-title { text-align: center; font-weight: 700; color: #1e293b; }
    .card-title { font-size: 20px; font-weight: 600; color: #334155; margin: 0 0 25px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .error-box, .success-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid transparent; }
    .error-box { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .success-box { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #475569; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
    .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
    .form-group input[readonly] { background-color: #f8fafc; color: #64748b; cursor: not-allowed; }
    .btn-submit { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .danger-zone { border: 2px solid #fecaca; }
    .danger-zone .card-title { color: #b91c1c; }
    .danger-zone p { font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 20px; }
    .btn-danger { background: #dc2626; }
    .btn-danger:hover { background: #b91c1c; transform: translateY(0); box-shadow: none; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>