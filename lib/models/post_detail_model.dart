// lib/models/post_detail_model.dart

class PostDetail {
  final int id;
  final String title;
  final String content;
  // Bu alan artık 'assets/post_images/resim.jpg' gibi sadece dosya yolunu tutacak
  final String? imageUrl;
  final String createdAt;
  final int viewCount;
  final int likeCount;
  final int commentCount;
  final String? categoryName;
  final List<String> tags;
  final bool isLikedByUser;
  final bool isSavedByUser;
  final String allowComments;

  PostDetail({
    required this.id,
    required this.title,
    required this.content,
    this.imageUrl,
    required this.createdAt,
    required this.viewCount,
    required this.likeCount,
    required this.commentCount,
    this.categoryName,
    required this.tags,
    required this.isLikedByUser,
    required this.isSavedByUser,
    required this.allowComments,
  });

  /// İstenen genişlikte, optimize edilmiş bir resim URL'si oluşturur.
  /// Detay sayfası için daha yüksek kalite isteyebiliriz.
  /// Kullanım: postDetail.getImageUrl(width: 800)
  String? getImageUrl({int width = 800, int quality = 80}) {
    if (imageUrl == null || imageUrl!.isEmpty) {
      return null;
    }
    // PHP API'mize istek atacak olan tam URL'yi burada oluşturuyoruz
    return 'https://blogium.net/api/image.php?src=$imageUrl&w=$width&q=$quality';
  }

  factory PostDetail.fromJson(Map<String, dynamic> json) {
    List<String> tagsList = [];
    if (json['tags'] is List) {
      tagsList = List<String>.from(json['tags']);
    }

    int parseInt(dynamic value) {
      if (value == null) return 0;
      return int.tryParse(value.toString()) ?? 0;
    }

    return PostDetail(
      id: parseInt(json['id']),
      title: json['title'] ?? '',
      content: json['content'] ?? '',
      // GÜNCELLEME: Artık URL birleştirme yapmıyoruz, sadece ham yolu alıyoruz.
      imageUrl: json['image_url'],
      createdAt: json['created_at'] ?? '',
      viewCount: parseInt(json['view_count']),
      likeCount: parseInt(json['like_count']),
      commentCount: parseInt(json['comment_count']),
      categoryName: json['category_name'],
      tags: tagsList,
      isLikedByUser: json['is_liked_by_user'] ?? false,
      isSavedByUser: json['is_saved_by_user'] ?? false,
      allowComments: json['allow_comments'] ?? 'no',
    );
  }
}