// lib/widgets/post_list_item.dart

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/post_model.dart';
import '../screens/post_detail_screen.dart';
import '../utils/navigation_helper.dart'; // YENİ IMPORT

class PostListItem extends StatelessWidget {
  final Post post;

  const PostListItem({
    super.key,
    required this.post,
  });

  String _formatDate(String dateString) {
    try {
      final dateTime = DateTime.parse(dateString);
      return DateFormat('dd MMMM yyyy', 'tr_TR').format(dateTime);
    } catch (e) {
      return dateString.split(' ')[0];
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          // --- DEĞİŞİKLİK BURADA ---
          // Standart Navigator.push yerine, internet bağlantısını kontrol eden
          // yeni NavigationHelper'ımızı kullanıyoruz.
          NavigationHelper.navigateTo(context, PostDetailScreen(postId: post.id));
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (post.imageUrl != null)
              _buildImage(context),

            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    post.title,
                    style: theme.textTheme.titleMedium,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  if (post.metaDescription != null)
                    Text(
                      post.metaDescription!,
                      style: theme.textTheme.bodyMedium,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                    ),
                  const SizedBox(height: 16),
                  _buildStatsRow(context),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildImage(BuildContext context) {
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: CachedNetworkImage(
        imageUrl: post.getImageUrl(width: 400) ?? '',
        fit: BoxFit.cover,
        placeholder: (context, url) => Container(
          color: Theme.of(context).colorScheme.surfaceVariant.withOpacity(0.5),
          child: Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor)),
        ),
        errorWidget: (context, url, error) => Container(
          color: Theme.of(context).colorScheme.surfaceVariant.withOpacity(0.5),
          child: Icon(
            Icons.image_not_supported_outlined,
            size: 50,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
      ),
    );
  }

  Widget _buildStatsRow(BuildContext context) {
    final theme = Theme.of(context);
    final secondaryColor = theme.colorScheme.onSurfaceVariant;

    return Row(
      children: [
        Text(
          _formatDate(post.createdAt),
          style: theme.textTheme.bodySmall?.copyWith(color: secondaryColor),
        ),
        const Spacer(),
        _buildStatIcon(Icons.visibility_outlined, post.viewCount.toString(), context),
        const SizedBox(width: 16),
        _buildStatIcon(Icons.favorite_outline, post.likeCount.toString(), context),
        const SizedBox(width: 16),
        _buildStatIcon(Icons.chat_bubble_outline, post.commentCount.toString(), context),
      ],
    );
  }

  Widget _buildStatIcon(IconData icon, String text, BuildContext context) {
    final theme = Theme.of(context);
    final secondaryColor = theme.colorScheme.onSurfaceVariant;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Icon(icon, size: 16, color: secondaryColor),
        const SizedBox(width: 6),
        Text(
          text,
          style: theme.textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: theme.colorScheme.onSurface,
          ),
        ),
      ],
    );
  }
}