<?php
/**
 * Blogium - Yönetici Girişi v3.0
 * Güvenli ve Modern Tasarım
 */

// Güvenli oturum ayarları
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.cookie_samesite', 'Strict');

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

// 1. OTURUM KONTROLÜ
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

require 'includes/db.php';

$message = '';

// 2. BRUTE FORCE KORUMASI (Saldırı Önleme)
if (!isset($_SESSION['login_attempts'])) { $_SESSION['login_attempts'] = 0; }
if (!isset($_SESSION['lockout_time'])) { $_SESSION['lockout_time'] = 0; }

// Kilitlenme süresi doldu mu?
if (time() < $_SESSION['lockout_time']) {
    $remaining = $_SESSION['lockout_time'] - time();
    $message = "Çok fazla hatalı deneme. Lütfen " . ceil($remaining / 60) . " dakika sonra tekrar deneyin.";
} else {
    // 3. CSRF TOKEN OLUŞTURMA
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    // 4. FORM İŞLEME
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Token Kontrolü
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $message = "Güvenlik uyarısı: Geçersiz istek.";
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $message = "Lütfen tüm alanları doldurun.";
            } else {
                // Admin Sorgulama
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    // BAŞARILI GİRİŞ
                    session_regenerate_id(true); // Session Fixation Koruması

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    // Sayaçları sıfırla
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['lockout_time']);

                    header("Location: dashboard.php");
                    exit;
                } else {
                    // BAŞARISIZ GİRİŞ
                    $_SESSION['login_attempts']++;
                    $message = "Kullanıcı adı veya şifre hatalı.";

                    // 5. Hata Limiti (5 Deneme)
                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['lockout_time'] = time() + (15 * 60); // 15 dakika kilitle
                        $message = "Çok fazla hatalı giriş. Hesabınız geçici olarak kilitlendi.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap - Blogium Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(#e0e7ff 1px, transparent 1px);
            background-size: 24px 24px;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255,255,255,0.5);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: #fff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-group { margin-bottom: 1.25rem; text-align: left; }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .input-wrapper { position: relative; }
        
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem; /* İkon için soldan boşluk */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: #fcfcfc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            background-color: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            padding: 0.85rem;
            border-radius: 8px;
            border: 1px solid #fecaca;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-section">
            <div class="logo-icon"><i class="fas fa-bolt"></i></div>
            <h1 class="brand-title">Hoş Geldiniz</h1>
            <p class="brand-subtitle">Blogium Yönetim Paneli</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> 
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="form-group">
                <label class="form-label">Kullanıcı Adı</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="admin" required autocomplete="username" <?= (time() < $_SESSION['lockout_time']) ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Şifre</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password" <?= (time() < $_SESSION['lockout_time']) ? 'disabled' : '' ?>>
                </div>
            </div>

            <button type="submit" class="btn-submit" <?= (time() < $_SESSION['lockout_time']) ? 'disabled' : '' ?>>
                Giriş Yap <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="footer-text">
            &copy; <?= date('Y') ?> Blogium. Tüm hakları saklıdır.
        </div>
    </div>

</body>
</html>