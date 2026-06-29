<?php
// api/send_notification.php

require_once dirname(__DIR__, 2) . '/google-libs/vendor/autoload.php'; 
require_once __DIR__ . '/db.php';

use Google\Client as GoogleClient;
use Google\Exception as GoogleException;

if (!function_exists('sendNotificationToAll')) {
    function sendNotificationToAll($title, $body, $post_id) {
        global $db;

        $serviceAccountKeyPath = '/home/blo217mnet/service-account-key.json';
        $projectId = 'blogiumapp-cd5d9';

        if (!file_exists($serviceAccountKeyPath)) {
            error_log("FCM Hatası: Servis hesabı anahtar dosyası bulunamadı.");
            return "Hata: Servis hesabı anahtar dosyası bulunamadı.";
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($serviceAccountKeyPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

            $stmt = $db->query("SELECT token FROM fcm_tokens");
            $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($tokens)) {
                return "Hiç kayıtlı cihaz bulunamadı.";
            }

            $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
            
            $success_count = 0;
            $failure_count = 0;

            foreach ($tokens as $token) {
                // MESAJ İÇERİĞİNİ OLUŞTURUYORUZ
                $message = [
                    'message' => [
                        'token' => $token,
                        'notification' => [ 
                            'title' => $title, 
                            'body' => $body 
                        ],
                        'data' => [ 
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK', 
                            'post_id' => (string)$post_id 
                        ],
                        // =======================================================
                        // YENİ EKLENEN ANDROID'E ÖZEL AYARLAR
                        // =======================================================
                        'android' => [
                            'notification' => [
                                // Android'e standart uygulama ikonunu kullanmasını söylüyoruz.
                                // Bu ikon, mipmap klasörlerindeki ic_launcher'dır.
                                'icon' => 'ic_launcher',
                                
                                // İkonun ve başlığın arka plan rengini HEX kodu olarak belirtiyoruz.
                                // #FFFFFF, beyaz demektir. Bu, mavi daireyi kaldırır.
                                'color' => '#FFFFFF'
                            ]
                        ]
                        // =======================================================
                        // YENİ BLOK SONU
                        // =======================================================
                    ]
                ];

                $headers = [ 
                    'Authorization: Bearer ' . $accessToken, 
                    'Content-Type: application/json' 
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
                
                $result = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code == 200) {
                    $success_count++;
                } else {
                    $failure_count++;
                    error_log("FCM Send Error for token $token: " . $result);
                }
                curl_close($ch);
            }

            return "Bildirim gönderme tamamlandı. Başarılı: $success_count, Başarısız: $failure_count";

        } catch (Exception $e) {
            error_log("Google API Hatası: " . $e->getMessage());
            return "Hata: Google API istemcisi yapılandırılamadı. Anahtar dosyası yolunu veya kütüphane dosyalarını kontrol edin.";
        }
    }
}
?>