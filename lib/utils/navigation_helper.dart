// lib/utils/navigation_helper.dart

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/connectivity_service.dart';
import '../screens/no_connection_screen.dart';

/// Uygulama içi navigasyonları yöneten ve internet bağlantısını kontrol eden yardımcı sınıf.
class NavigationHelper {
  /// Belirtilen hedefe gitmeden önce internet bağlantısını kontrol eder.
  /// Bağlantı yoksa, NoConnectionScreen'e yönlendirir.
  static void navigateTo(BuildContext context, Widget destinationPage) {
    // `listen: false` ile anlık bağlantı durumunu alıyoruz.
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);

    if (connectivityService.isConnected) {
      // Bağlantı varsa: Normal şekilde hedefe git.
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => destinationPage),
      );
    } else {
      // Bağlantı yoksa: NoConnectionScreen'e git ve bağlantı geri geldiğinde ne yapacağını söyle.
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (noConnectionContext) => NoConnectionScreen( // Context'in karışmaması için yeni isim
            onConnectionRestored: () {
              // Bağlantı geri geldiğinde, bu fonksiyon çalışacak ve kullanıcıyı ASIL HEDEFİNE götürecek.
              // --- HATA DÜZELTİLDİ ---
              // pushReplacement yerine PUSH kullanıyoruz ki Ana Sayfa yığından atılmasın.
              // Orijinal context'i (bu sayfaya gelirken kullandığımız context) kullanıyoruz.
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => destinationPage),
              );
            },
          ),
        ),
      );
    }
  }
}