// lib/widgets/search_result_list_item.dart

import 'package:flutter/material.dart';
import '../models/post_model.dart';
import '../screens/post_detail_screen.dart';
import '../utils/navigation_helper.dart'; // YENİ IMPORT

class SearchResultListItem extends StatelessWidget {
  final Post post;

  const SearchResultListItem({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      // --- İYİLEŞTİRME: KART STİLİ ARTIK TEMADAN GELİYOR ---
      // Manuel elevation ve shadowColor kaldırıldı. Artık stilini
      // app_theme.dart içindeki CardTheme'den alıyor (kenarlıklı, gölgesiz).
      margin: const EdgeInsets.only(bottom: 12.0),
      child: InkWell(
        onTap: () {
          // --- DEĞİŞİKLİK BURADA ---
          // Standart Navigator.push yerine, internet bağlantısını kontrol eden
          // yeni NavigationHelper'ımızı kullanıyoruz.
          NavigationHelper.navigateTo(context, PostDetailScreen(postId: post.id));
        },
        // Kartın şekliyle uyumlu olması için borderRadius ekliyoruz.
        borderRadius: BorderRadius.circular(16.0),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 20.0),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Sol taraftaki metinler
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      post.title,
                      style: theme.textTheme.titleMedium,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    if (post.metaDescription != null)
                      Text(
                        post.metaDescription!,
                        style: theme.textTheme.bodyMedium,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              // Sağ taraftaki "git" ikonu
              Icon(
                Icons.chevron_right,
                // --- İYİLEŞTİRME: İKON RENGİ TEMADAN ALINIYOR ---
                color: theme.iconTheme.color?.withOpacity(0.6),
                size: 28,
              ),
            ],
          ),
        ),
      ),
    );
  }
}