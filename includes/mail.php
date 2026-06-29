<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

/**
 * Sitedeki tüm e-postaları göndermek için genel bir fonksiyon.
 * @param string $to_email Alıcının e-posta adresi.
 * @param string $subject E-postanın konusu.
 * @param string $body E-postanın HTML içeriği.
 * @return bool Gönderim başarılıysa true, değilse false döner.
 */
function send_email($to_email, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // --- HATA AYIKLAMAYI GEÇİCİ OLARAK BURAYA EKLEYEBİLİRSİNİZ ---
        //$mail->SMTPDebug = PHPMailer::DEBUG_SERVER; 

        // Sunucu ayarları
        $mail->isSMTP();
        $mail->Host       = 'smtp.turkticaret.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'no-reply@blogium.net';
        $mail->Password   = 'M788e455e*';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 'tls' yerine bu sabiti kullanmak daha iyidir.
        $mail->Port       = 587;
        
        $mail->CharSet = 'UTF-8';

        // Gönderen ve Alıcı
        $mail->setFrom('no-reply@blogium.net', 'Blogium');
        $mail->addAddress($to_email);

        // İçerik
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mail gönderilemedi: {$mail->ErrorInfo}");
        return false;
    }
}