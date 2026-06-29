<?php
// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SEO ve Sayfa Değişkenleri ---
$page_title = "İletişim";
$page_description = "Blogium ile iletişime geçin. Künye bilgileri, sorularınız, önerileriniz veya iş birliği teklifleriniz için bize ulaşın.";
$page_keywords = "iletişim, bize ulaşın, künye, imprint, destek, e-posta, form, blogium iletişim";
// Temiz URL yapısını kullanarak canonical URL'yi oluştur.
$page_url = "https://www.blogium.net/iletisim";
$page_image = "https://www.blogium.net/logo.png"; 

// --- CLOUDFLARE AYARLARI ---
$cf_secret_key = '0x4AAAAAACWLRFNP8mlrsyMuZHfLaKjodms'; 

$flash_message = null;

// PHPMailer entegrasyonu
require_once __DIR__ . '/includes/mail.php';

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADIM: CLOUDFLARE CAPTCHA KONTROLÜ
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    $captcha_success = false;

    if (!empty($turnstile_response)) {
        // Cloudflare'e doğrulama isteği gönder
        $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $cf_secret_key,
            'response' => $turnstile_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $verify_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        
        $response_data = json_decode($response);
        if ($response_data->success) {
            $captcha_success = true;
        }
    }

    if (!$captcha_success) {
        $flash_message = ['type' => 'error', 'text' => 'Güvenlik doğrulaması başarısız. Lütfen robot olmadığınızı doğrulayın.'];
    } else {
        // 2. ADIM: CAPTCHA BAŞARILIYSA FORM VERİLERİNİ İŞLE
        
        // Form verilerini temizle
        $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH));
        $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH));

        // Basit doğrulama
        if (empty($name) || empty($email) || empty($subject) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_message = ['type' => 'error', 'text' => 'Lütfen tüm alanları doğru bir şekilde doldurun.'];
        } else {
            $to_email = 'info@blogium.net';
            $email_subject = "Blog İletişim Formu: " . $subject;
            $email_body_html = "
            <p>Yeni bir iletişim formu mesajı aldınız.</p>
            <p><strong>Gönderen:</strong> " . htmlspecialchars($name) . "</p>
            <p><strong>E-posta:</strong> <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>
            <p><strong>Konu:</strong> " . htmlspecialchars($subject) . "</p>
            <p><strong>Mesaj:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
            <hr>
            <p>Bu mesaj Blogium web sitesi iletişim formu aracılığıyla gönderilmiştir.</p>
            ";
            
            if (send_email($to_email, $email_subject, $email_body_html)) {
                $flash_message = ['type' => 'success', 'text' => 'Teşekkürler! Mesajınız başarıyla gönderildi. En kısa sürede size geri döneceğiz.'];
                $_POST = array(); 
            } else {
                $flash_message = ['type' => 'error', 'text' => 'Üzgünüz, mesajınız gönderilirken bir hata oluştu. Lütfen mail.php ayarlarını veya sunucu loglarını kontrol edin.'];
            }
        }
    }
}

// Gerekli dosyaları dahil et
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/head.php'; 
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style>
    .contact-page-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .page-header { text-align: center; margin-bottom: 50px; }
    .page-header h1 { font-size: 36px; font-weight: 700; color: #1e293b; }
    .page-header p { font-size: 18px; color: #64748b; max-width: 600px; margin: 10px auto 0 auto; }
    .contact-main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 50px; background-color: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); }
    .contact-form h2 { font-size: 24px; font-weight: 600; margin-bottom: 25px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; }
    .form-control { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 16px; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-control:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); }
    textarea.form-control { height: 150px; resize: vertical; }
    .submit-btn { background-color: #667eea; color: #fff; border: none; padding: 15px 30px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background-color 0.2s, transform 0.2s; width: 100%; margin-top: 10px; }
    .submit-btn:hover { background-color: #5a67d8; transform: translateY(-2px); }
    
    /* Sağ Sütun Ayarları */
    .contact-info { padding-left: 30px; border-left: 1px solid #f1f5f9; }
    .contact-info h2 { font-size: 24px; font-weight: 600; margin-bottom: 25px; }
    .info-list { list-style: none; padding: 0; }
    .info-item { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 25px; }
    .info-item .icon { font-size: 20px; color: #667eea; margin-top: 5px; }
    .info-item h4 { font-size: 16px; font-weight: 600; margin-bottom: 5px; }
    .info-item p, .info-item a { font-size: 15px; color: #475569; text-decoration: none; }
    .info-item a:hover { text-decoration: underline; }

    /* Yeni Künye Bölümü Stili */
    .kunye-box { margin-top: 40px; padding-top: 30px; border-top: 1px solid #e2e8f0; }
    .kunye-box h3 { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b; }
    .kunye-item { margin-bottom: 15px; }
    .kunye-label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 2px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    .kunye-value { font-size: 16px; color: #334155; font-weight: 500; }
    .kunye-disclaimer { font-size: 13px; color: #94a3b8; margin-top: 25px; line-height: 1.5; font-style: italic; }

    .flash-message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid; }
    .flash-message.success { background-color: #dcfce7; border-color: #bbf7d0; color: #166534; }
    .flash-message.error { background-color: #fee2e2; border-color: #fecaca; color: #b91c1c; }
    .captcha-wrapper { margin-bottom: 15px; display: flex; justify-content: center; }

    @media (max-width: 960px) {
        .contact-main-grid { grid-template-columns: 1fr; }
        .contact-info { padding-left: 0; border-left: none; margin-top: 40px; padding-top: 40px; border-top: 1px solid #f1f5f9; }
    }
    @media (max-width: 768px) {
        .contact-main-grid { padding: 25px; }
    }
</style>

<main class="contact-page-container">
    <header class="page-header">
        <h1>İletişime Geçin</h1>
        <p>Sorularınız, önerileriniz veya iş birliği teklifleriniz için bize her zaman ulaşabilirsiniz.</p>
    </header>

    <div class="contact-main-grid">
        <div class="contact-form">
            <h2>Bize Mesaj Gönderin</h2>
            
            <?php if ($flash_message): ?>
                <div class="flash-message <?= htmlspecialchars($flash_message['type']) ?>">
                    <?= htmlspecialchars($flash_message['text']) ?>
                </div>
            <?php endif; ?>

            <form action="/iletisim" method="POST"> 
                <div class="form-group">
                    <label for="name">Adınız Soyadınız</label>
                    <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email">E-posta Adresiniz</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="subject">Konu</label>
                    <input type="text" id="subject" name="subject" class="form-control" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="message">Mesajınız</label>
                    <textarea id="message" name="message" class="form-control" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <div class="captcha-wrapper">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACWLRH2GHEGNB7k_"></div>
                </div>

                <button type="submit" class="submit-btn">Mesajı Gönder</button>
            </form>
        </div>

        <div class="contact-info">
            <h2>İletişim Bilgileri</h2>
            <ul class="info-list">
                <li class="info-item">
                    <i class="fas fa-envelope icon"></i>
                    <div>
                        <h4>E-posta</h4>
                        <p><a href="mailto:info@blogium.net">info@blogium.net</a></p>
                    </div>
                </li>
            </ul>

            <div class="kunye-box">
                <h3>Künye / Imprint</h3>
                
                <div class="kunye-item">
                    <span class="kunye-label">Yayıncı (Publisher)</span>
                    <span class="kunye-value">PrimeTech Inc.</span>
                </div>

                <div class="kunye-item">
                    <span class="kunye-label">İletişim (Contact)</span>
                    <span class="kunye-value"><a href="mailto:info@blogium.net" style="text-decoration:none; color:inherit;">info@blogium.net</a></span>
                </div>

                <div class="kunye-item">
                    <span class="kunye-label">Adres (Address)</span>
                    <span class="kunye-value">İstanbul, Türkiye</span>
                </div>

                <p class="kunye-disclaimer">
                    Blogium uygulamasındaki ve web sitesindeki tüm içerikler Blogium editör ekibi tarafından hazırlanmaktadır.
                </p>
            </div>
            </div>
    </div>
</main>

<?php
include __DIR__ . '/includes/footer.php';
?>