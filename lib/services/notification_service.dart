// lib/services/notification_service.dart

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../screens/post_detail_screen.dart';
import '../main.dart'; // ANA NAVIGATORKEY'E ERİŞMEK İÇİN

class NotificationService {
  final FirebaseMessaging _fcm = FirebaseMessaging.instance;

  Future<void> initialize() async {
    // 1. Bildirim İzni İste
    await _fcm.requestPermission(
      alert: true, announcement: false, badge: true, carPlay: false,
      criticalAlert: false, provisional: false, sound: true,
    );

    // 2. Cihaz Token'ını Al ve Sunucuya Kaydet (Hata Yönetimiyle)
    // --- DEĞİŞİKLİK BURADA BAŞLIYOR ---
    try {
      String? token = await _fcm.getToken();
      if (token != null) {
        _sendTokenToServer(token);
      }
      // Token yenilendiğinde de sunucuya gönder
      _fcm.onTokenRefresh.listen(_sendTokenToServer);
    } catch (e) {
      // HATA YÖNETİMİ: Eğer token alınamazsa (internet yok vb.),
      // hatayı sadece konsola yazdır ve uygulamanın devam etmesine izin ver.
      print("FCM Token alınamadı: $e");
      // Burada bir Exception fırlatmıyoruz ki uygulama main() fonksiyonunda çökmesin.
    }
    // --- DEĞİŞİKLİK SONU ---


    // 3. Uygulama Açıkken Gelen Bildirimleri Dinle
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Uygulama açıkken bildirim geldi: ${message.notification?.title}');
    });

    // 4. Bildirime Tıklanma Durumunu Dinle
    FirebaseMessaging.onMessageOpenedApp.listen(_handleMessage);
  }

  // Uygulama kapalıyken bildirime tıklanarak açıldıysa
  void handleInitialMessage() async {
    RemoteMessage? initialMessage = await _fcm.getInitialMessage();
    if (initialMessage != null) {
      // Uygulama açıldıktan sonra yönlendirme yapmak için kısa bir gecikme ekliyoruz
      Future.delayed(const Duration(milliseconds: 500), () {
        _handleMessage(initialMessage);
      });
    }
  }

  void _handleMessage(RemoteMessage message) {
    final postId = message.data['post_id'];
    if (postId != null) {
      // --- DEĞİŞİKLİK: ANA NAVIGATORKEY KULLANILIYOR ---
      // main.dart'ta tanımlanan ana navigatorKey'i kullanarak yönlendirme yapıyoruz.
      // Bu, uygulamanın neresinde olursanız olun doğru çalışmasını sağlar.
      navigatorKey.currentState?.push(
        MaterialPageRoute(builder: (_) => PostDetailScreen(postId: int.parse(postId))),
      );
    }
  }

  Future<void> _sendTokenToServer(String token) async {
    try {
      final response = await http.post(
        Uri.parse('https://blogium.net/api/register_fcm_token.php'),
        headers: {'Content-Type': 'application/json; charset=UTF-8'}, // UTF-8 eklendi
        body: json.encode({'token': token}),
      );
      if(response.statusCode == 200) {
        print("FCM Token sunucuya gönderildi: $token");
      } else {
        print("FCM Token sunucuya gönderilemedi. Status Code: ${response.statusCode}");
      }
    } catch (e) {
      print("FCM Token gönderilemedi: $e");
    }
  }
}