// lib/models/simple_post_model.dart

class SimplePost {
  final int id;
  final String title;
  final String slug;
  final String? imageUrl; // Resim URL'si. Null olabilir ('?').

  SimplePost({
    required this.id,
    required this.title,
    required this.slug,
    this.imageUrl, // Constructor'a eklendi.
  });

  // Bu factory metodu, API'den gelen JSON verisini bir SimplePost nesnesine dönüştürür.
  factory SimplePost.fromJson(Map<String, dynamic> json) {
    return SimplePost(
      id: json['id'],
      title: json['title'],
      slug: json['slug'],
      // Gelen 'image_url' verisi boş değilse, tam URL'yi oluşturur.
      // Boşsa veya hiç yoksa, null olarak ayarlar.
      imageUrl: json['image_url'] != null
          ? 'https://blogium.net/' + json['image_url']
          : null,
    );
  }
}