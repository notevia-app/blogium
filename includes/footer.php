<?php
// Footer için veritabanından sayfaları çek
$footer_pages = [];
// $pdo değişkeni genellikle header veya index'ten gelir ama kontrol edelim
if (isset($pdo)) {
    try {
        // Sayfaları başlığa göre alfabetik sıralayalım
        $stmt = $pdo->query("SELECT title, slug FROM pages ORDER BY title ASC");
        $footer_pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Hata olursa footer boş kalmasın diye sessizce geç
    }
}
?>
<style>
* {
  -webkit-tap-highlight-color: transparent;
}
/* Footer Stilleri */
.site-footer {
    background-color: #1e293b;
    color: #94a3b8;
    padding: 60px 20px;
    margin-top: 60px;
    text-align: center;
}
.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
}
.footer-socials {
    display: flex;
    gap: 20px;
}
.footer-socials a {
    color: #94a3b8;
    font-size: 22px;
    transition: color 0.2s, transform 0.2s;
}
.footer-socials a:hover {
    color: #fff;
    transform: translateY(-3px);
}
.footer-nav {
    display: flex;
    gap: 30px;
    list-style: none;
    padding: 0;
    margin: 0;
    flex-wrap: wrap; /* Linkler çok olursa alt satıra geçsin */
    justify-content: center;
}
.footer-nav a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s;
}
.footer-nav a:hover {
    color: #fff;
}
.footer-copyright {
    font-size: 13px;
}

/* Footer için Mobil Uyumluluk */
@media (max-width: 768px) {
    .footer-nav {
        flex-direction: column;
        gap: 15px;
    }
    .site-footer {
        padding: 40px 15px;
    }
}
</style>

<footer class="site-footer">
    <div class="footer-container">
        
        <nav>
            <ul class="footer-nav">
                <li><a href="/">Anasayfa</a></li>
                
                <?php if (!empty($footer_pages)): ?>
                    <?php foreach ($footer_pages as $page): ?>
                        <li>
                            <a href="/<?= htmlspecialchars($page['slug']) ?>">
                                <?= htmlspecialchars($page['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <li><a href="/iletisim">İletişim</a></li>
            </ul>
        </nav>
        
        <div class="footer-copyright">
            <?php 
            // Eğer settings tablosundan gelen site_desc varsa onu kullan, yoksa standart yazıyı
            $footer_desc = isset($settings['site_desc']) && !empty($settings['site_desc']) ? $settings['site_desc'] : "Blogium. Tüm hakları saklıdır.";
            ?>
            <div style="margin-bottom: 10px; opacity: 0.7; font-size: 0.9em; max-width: 600px; margin-left: auto; margin-right: auto;">
                <?= htmlspecialchars($footer_desc) ?>
            </div>
            © <?= date('Y') ?> Blogium.
        </div>
    </div>
</footer>

<script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js"></script>

<script>
// --- FIREBASE AYARLARI ---
// Firebase Console -> Proje Ayarları -> Genel -> Web Uygulaması -> Config
const firebaseConfig = {
  apiKey: "AIzaSyDIhB3_I0-3lRJs-R58_Xk_-6pgNlwuKoc",
  authDomain: "blogiumapp-cd5d9.firebaseapp.com",
  projectId: "blogiumapp-cd5d9",
  storageBucket: "blogiumapp-cd5d9.firebasestorage.app",
  messagingSenderId: "82323424969",
  appId: "1:82323424969:web:79c6487689015e0a3a6f18"
};

// Firebase Başlat
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Bildirim İzni İste ve Token Al
function requestNotificationPermission() {
    Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
            console.log('Bildirim izni verildi.');
            
            // Token Al (VAPID Key Gerekli)
            // Firebase Console -> Cloud Messaging -> Web Push certificates -> Key Pair
            messaging.getToken({ vapidKey: 'BHZpYiW_OY3VP6CKbTS8Glxl8Ib9q2I_rrmrBUc-bHWelc0TCQgD10Odl18muUwnydj60dS5ILjKdhd8GmO-Jkg' }).then((currentToken) => {
                if (currentToken) {
                    console.log('Token alındı:', currentToken);
                    saveTokenToDatabase(currentToken);
                } else {
                    console.log('Token oluşturulamadı.');
                }
            }).catch((err) => {
                console.log('Token alma hatası:', err);
            });
        } else {
            console.log('Bildirim izni reddedildi.');
        }
    });
}

// Token'ı PHP'ye gönderip kaydet
function saveTokenToDatabase(token) {
    fetch('/save_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
    })
    .then(response => response.json())
    .then(data => console.log('Sunucu yanıtı:', data))
    .catch(err => console.error('Token kaydedilemedi:', err));
}

// Sayfa yüklendiğinde otomatik izin iste
document.addEventListener('DOMContentLoaded', function() {
    requestNotificationPermission();
});

// Sayfa açıkken bildirim gelirse konsola yaz
messaging.onMessage((payload) => {
    console.log('Ön plan bildirimi:', payload);
});
</script>