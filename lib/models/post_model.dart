// lib/models/post_model.dart

class Post {
  final int id;
  final String title;
  final String? metaDescription;
  // Bu alan artık 'assets/post_images/resim.jpg' gibi sadece dosya yolunu tutacak
  final String? imageUrl;
  final String createdAt;
  final int viewCount;
  final int likeCount;
  final int commentCount;
  final String? categoryName;

  Post({
    required this.id,
    required this.title,
    this.metaDescription,
    this.imageUrl,
    required this.createdAt,
    required this.viewCount,
    required this.likeCount,
    required this.commentCount,
    this.categoryName,
  });

  /// İstenen genişlikte, optimize edilmiş bir resim URL'si oluşturur.
  /// Kullanım: post.getImageUrl(width: 400)
  String? getImageUrl({int width = 400, int quality = 75}) {
    if (imageUrl == null || imageUrl!.isEmpty) {
      return null;
    }
    // PHP API'mize istek atacak olan tam URL'yi burada oluşturuyoruz
    return 'https://blogium.net/api/image.php?src=$imageUrl&w=$width&q=$quality';
  }

  factory Post.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value == null) return 0;
      return int.tryParse(value.toString()) ?? 0;
    }

    return Post(
      id: parseInt(json['id']),
      title: json['title'] ?? '',
      metaDescription: json['meta_description'],
      // GÜNCELLEME: Artık URL birleştirme yapmıyoruz, sadece ham yolu alıyoruz.
      imageUrl: json['image_url'],
      createdAt: json['created_at'] ?? '',
      viewCount: parseInt(json['view_count']),
      likeCount: parseInt(json['like_count']),
      commentCount: parseInt(json['comment_count']),
      categoryName: json['category_name'],
    );
  }
}