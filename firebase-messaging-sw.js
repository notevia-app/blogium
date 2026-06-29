// firebase-messaging-sw.js
importScripts('https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js');

// --- BURAYI KENDİ FIREBASE BİLGİLERİNLE DOLDUR ---
const firebaseConfig = {
  apiKey: "AIzaSyDIhB3_I0-3lRJs-R58_Xk_-6pgNlwuKoc",
  authDomain: "blogiumapp-cd5d9.firebaseapp.com",
  projectId: "blogiumapp-cd5d9",
  storageBucket: "blogiumapp-cd5d9.firebasestorage.app",
  messagingSenderId: "82323424969",
  appId: "1:82323424969:web:79c6487689015e0a3a6f18"
};
// -------------------------------------------------

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Arka planda bildirim geldiğinde (Tarayıcı kapalıyken)
messaging.onBackgroundMessage(function(payload) {
  console.log('Arka plan bildirimi:', payload);

  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: payload.notification.image || '/logo.png', // Varsayılan ikon
    image: payload.notification.image, // Büyük resim
    data: {
        url: 'https://www.blogium.net/yazi/' + payload.data.slug // Tıklanınca gideceği adres
    }
  };

  return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Bildirime tıklanma olayı (URL'ye gitmesi için)
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  
  // Tıklanınca ilgili yazıya git, sekme açıksa odaklan
  event.waitUntil(
    clients.matchAll({type: 'window'}).then( windowClients => {
        // Zaten açık bir sekme var mı?
        for (var i = 0; i < windowClients.length; i++) {
            var client = windowClients[i];
            if (client.url === event.notification.data.url && 'focus' in client) {
                return client.focus();
            }
        }
        // Yoksa yeni sekme aç
        if (clients.openWindow) {
            return clients.openWindow(event.notification.data.url);
        }
    })
  );
});