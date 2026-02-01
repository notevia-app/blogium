import 'package:flutter/material.dart';
import '../screens/search_screen.dart';

/// Projenin ikincil sayfalarında (detay, ayarlar vb.) tutarlı bir AppBar sağlamak için kullanılır.
/// Stilini ve görünümünü doğrudan ana temadaki `AppBarTheme`'den alır.
class CustomAppBar extends StatelessWidget implements PreferredSizeWidget {
  /// AppBar'da gösterilecek olan başlık.
  final String title;

  /// AppBar'ın sağ tarafında gösterilecek ek action (buton vb.) widget'ları.
  final List<Widget>? actions;

  const CustomAppBar({
    super.key,
    required this.title,
    this.actions,
  });

  @override
  Widget build(BuildContext context) {
    // AppBar artık tüm stil özelliklerini (renk, yazı tipi, ikon rengi)
    // main.dart -> app_theme.dart içinde tanımlanan AppBarTheme'den otomatik olarak alır.
    return AppBar(
      // --- DEĞİŞİKLİK ---
      // Başlık artık her zaman dışarıdan alınır, logo gösterme mantığı kaldırıldı
      // çünkü bu AppBar artık sadece başlık gerektiren ikincil sayfalarda kullanılacak.
      title: Text(title),
      centerTitle: false, // Modern tasarım için başlığı sola yaslıyoruz.

      // --- DEĞİŞİKLİK ---
      // Varsayılan arama butonu ve dışarıdan gelen ek action'ları birleştiriyoruz.
      actions: [
        // Varsayılan arama butonu
        IconButton(
          icon: const Icon(Icons.search),
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SearchScreen()),
          ),
        ),
        // Eğer bu AppBar'a özel ek action'lar gönderildiyse, onları da ekle.
        if (actions != null) ...actions!,
        const SizedBox(width: 8), // Sağdan küçük bir boşluk bırakmak için.
      ],
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}