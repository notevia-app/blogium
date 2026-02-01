// lib/services/api_service.dart

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/post_model.dart';
import '../models/post_detail_model.dart';
import '../models/category_model.dart';
import '../models/comment_model.dart';
import '../models/simple_post_model.dart'; // Bu model kullanılmıyorsa kaldırılabilir

class ApiService {
  static const String _baseUrl = "https://blogium.net/api";
  static const String _postsCacheKey = "posts_cache";

  // --- MERKEZİ İSTEK FONKSİYONLARI (UTF-8 DÜZELTMESİ BURADA) ---

  /// Tüm GET isteklerini yöneten merkezi fonksiyon.
  /// Sunucudan gelen yanıtı her zaman UTF-8 olarak çözer.
  static Future<Map<String, dynamic>> _get(String endpoint) async {
    try {
      final response = await http.get(Uri.parse('$_baseUrl/$endpoint'));

      // Gelen yanıtı UTF-8 olarak çöz
      final decodedBody = utf8.decode(response.bodyBytes);
      final responseData = json.decode(decodedBody) as Map<String, dynamic>;

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return responseData;
      } else {
        throw Exception(responseData['message'] ?? 'Sunucu hatası: ${response.statusCode}');
      }
    } catch (e) {
      // Ağ hatası veya diğer istisnalar için
      throw Exception('İstek gönderilemedi: $e');
    }
  }

  /// Tüm POST isteklerini yöneten merkezi fonksiyon.
  /// Sunucuya veriyi UTF-8 olarak gönderir ve yanıtı UTF-8 olarak çözer.
  static Future<Map<String, dynamic>> _post(String endpoint, Map<String, dynamic> body) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/$endpoint'),
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
        },
        body: json.encode(body),
      );

      // Gelen yanıtı UTF-8 olarak çöz
      final decodedBody = utf8.decode(response.bodyBytes);
      final responseData = json.decode(decodedBody) as Map<String, dynamic>;

      if (response.statusCode >= 200 && response.statusCode < 300) {
        if (responseData.containsKey('status') && responseData['status'] != 'success') {
          throw Exception(responseData['message'] ?? 'Bir hata oluştu.');
        }
        return responseData;
      } else {
        throw Exception(responseData['message'] ?? 'Sunucu hatası: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('İstek gönderilemedi: $e');
    }
  }

  // --- API METOTLARI (ARTIK MERKEZİ FONKSİYONLARI KULLANIYOR) ---

  static Future<List<Post>> getPosts() async {
    try {
      // Önce ağdan almayı dene
      final response = await http.get(Uri.parse('$_baseUrl/get_posts.php'));
      if (response.statusCode == 200) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_postsCacheKey, utf8.decode(response.bodyBytes)); // Önbelleğe UTF-8 olarak kaydet

        final Map<String, dynamic> responseBody = json.decode(utf8.decode(response.bodyBytes));
        final List<dynamic> data = responseBody['data'];
        return data.map((json) => Post.fromJson(json)).toList();
      } else {
        return getCachedPosts();
      }
    } catch (e) {
      return getCachedPosts();
    }
  }

  static Future<List<Post>> getCachedPosts() async {
    final prefs = await SharedPreferences.getInstance();
    final cachedData = prefs.getString(_postsCacheKey);
    if (cachedData != null) {
      final Map<String, dynamic> responseBody = json.decode(cachedData);
      final List<dynamic> data = responseBody['data'];
      return data.map((json) => Post.fromJson(json)).toList();
    } else {
      throw Exception('İnternet bağlantısı yok ve önbellekte veri bulunamadı.');
    }
  }

  static Future<PostDetail> getPostDetails(int postId, {String? token}) async {
    String query = 'get_post_details.php?id=$postId';
    if (token != null) query += '&token=$token';
    final responseData = await _get(query);
    return PostDetail.fromJson(responseData['data']);
  }

  static Future<List<Category>> getCategories() async {
    final responseData = await _get('get_categories.php');
    return (responseData['data'] as List).map((json) => Category.fromJson(json)).toList();
  }

  static Future<List<Post>> getPostsByCategory(String categorySlug) async {
    final responseData = await _get('get_posts_by_category.php?slug=$categorySlug');
    return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
  }

  static Future<List<Post>> getPopularPosts() async {
    final responseData = await _get('get_popular_posts.php');
    return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
  }

  static Future<List<Post>> getLatestPosts() async {
    final responseData = await _get('get_latest_posts.php');
    return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
  }

  static Future<void> toggleLike(String token, int postId) async {
    await _post('toggle_like.php', {'token': token, 'post_id': postId});
  }

  static Future<void> toggleSave(String token, int postId) async {
    await _post('toggle_save.php', {'token': token, 'post_id': postId});
  }

  static Future<List<Comment>> getComments(int postId) async {
    final responseData = await _get('get_comments.php?post_id=$postId');
    return (responseData['data'] as List).map((json) => Comment.fromJson(json)).toList();
  }

  static Future<String> addComment(String token, int postId, String content) async {
    final responseData = await _post('add_comment.php', {'token': token, 'post_id': postId, 'content': content});
    return responseData['message'] ?? 'Yorum gönderildi.';
  }

  static Future<List<Post>> getLikedPosts(String token) async {
    final responseData = await _get('get_liked_posts.php?token=$token');
    return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
  }

  static Future<List<Post>> getSavedPosts(String token) async {
    final responseData = await _get('get_saved_posts.php?token=$token');
    return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
  }

  static Future<PostDetail> getRandomPost() async {
    final responseData = await _get('get_random_post.php');
    return PostDetail.fromJson(responseData['data']);
  }

  static Future<List<Post>> searchPosts(String query) async {
    final encodedQuery = Uri.encodeComponent(query);
    // --- DEĞİŞİKLİK BURADA ---
    // Artık merkezi _get fonksiyonunu kullanıyoruz ve bu, hata durumunda Exception fırlatacaktır.
    try {
      final responseData = await _get('search.php?query=$encodedQuery');
      // Gelen 'data' null ise veya liste değilse boş liste döndür, bu bir hata değil.
      if (responseData['data'] == null || responseData['data'] is! List) {
        return [];
      }
      return (responseData['data'] as List).map((json) => Post.fromJson(json)).toList();
    } catch (e) {
      // Eğer _get fonksiyonu bir Exception fırlatırsa (internet yok, sunucu hatası vb.),
      // bu hatayı doğrudan bir üst katmana (FutureBuilder'a) iletiyoruz.
      throw Exception('Arama yapılamadı: $e');
    }
  }


  static Future<Map<String, dynamic>> getAccountDetails(String token) async {
    final responseData = await _get('get_account_details.php?token=$token');
    return responseData['data'];
  }

  static Future<String> requestEmailChange({required String token, required String newEmail, required String password}) async {
    final responseData = await _post('request_email_change.php', {'token': token, 'new_email': newEmail, 'password': password});
    return responseData['message'];
  }

  static Future<String> verifyEmailChange({required String token, required String code}) async {
    final responseData = await _post('verify_email_change.php', {'token': token, 'verification_code': code});
    return responseData['message'];
  }

  static Future<String> changePassword({required String token, required String currentPassword, required String newPassword}) async {
    final responseData = await _post('change_password.php', {'token': token, 'current_password': currentPassword, 'new_password': newPassword});
    return responseData['message'];
  }

  static Future<String> deleteAccount({required String token, required String password}) async {
    final responseData = await _post('delete_account.php', {'token': token, 'password': password});
    return responseData['message'];
  }

  static Future<String> cancelEmailChange({required String token}) async {
    final responseData = await _post('cancel_email_change.php', {'token': token});
    return responseData['message'];
  }

  static Future<String> requestPasswordReset(String email) async {
    final responseData = await _post('request_password_reset.php', {'email': email});
    return responseData['message'];
  }

  static Future<String> resetPassword({required String email, required String token, required String newPassword}) async {
    final responseData = await _post('reset_password.php', {'email': email, 'token': token, 'new_password': newPassword});
    return responseData['message'];
  }

  static Future<String> sendContactForm({required String token, required String subject, required String message}) async {
    final responseData = await _post('send_contact_form.php', {'token': token, 'subject': subject, 'message': message});
    return responseData['message'];
  }
}