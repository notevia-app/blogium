// lib/models/comment_model.dart

class Comment {
  final int id;
  final String content;
  final String author;
  final String createdAt;

  Comment({
    required this.id,
    required this.content,
    required this.author,
    required this.createdAt,
  });

  factory Comment.fromJson(Map<String, dynamic> json) {
    return Comment(
      id: json['id'],
      content: json['content'],
      author: json['author'] ?? 'Bilinmiyor',
      createdAt: json['created_at'],
    );
  }
}