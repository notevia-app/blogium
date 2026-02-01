// lib/services/auth_service.dart

import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class AuthService with ChangeNotifier {
  static const String _baseUrl = "https://blogium.net/api";

  String? _token;
  String? _username;
  int? _userId;
  String? _email;

  bool get isLoggedIn => _token != null;
  String? get token => _token;
  String? get username => _username;
  int? get userId => _userId;
  String? get email => _email;

  Future<void> tryAutoLogin() async {
    final prefs = await SharedPreferences.getInstance();
    if (!prefs.containsKey('userData')) {
      return;
    }

    final extractedUserData = json.decode(prefs.getString('userData')!) as Map<String, dynamic>;
    _token = extractedUserData['token'];
    _username = extractedUserData['username'];
    _userId = extractedUserData['userId'];
    _email = extractedUserData['email'];

    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final url = Uri.parse('$_baseUrl/login.php');
    try {
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'email': email, 'password': password}),
      );

      final responseData = json.decode(response.body);

      if (response.statusCode != 200 || responseData['status'] != 'success') {
        throw Exception(responseData['message'] ?? 'Giriş yapılamadı.');
      }

      _token = responseData['data']['token'];
      _username = responseData['data']['user']['username'];
      _userId = int.tryParse(responseData['data']['user']['id'].toString());
      _email = responseData['data']['user']['email'];

      notifyListeners();

      final prefs = await SharedPreferences.getInstance();
      final userData = json.encode({
        'token': _token,
        'username': _username,
        'userId': _userId,
        'email': _email,
      });
      prefs.setString('userData', userData);

    } catch (error) {
      rethrow;
    }
  }

  Future<String> register(String username, String email, String password) async {
    final url = Uri.parse('$_baseUrl/register.php');
    final response = await http.post(
      url,
      headers: {'Content-Type': 'application/json'},
      body: json.encode({'username': username, 'email': email, 'password': password}),
    );
    final responseData = json.decode(response.body);

    if (response.statusCode > 201 || responseData['status'] != 'success') {
      throw Exception(responseData['message'] ?? 'Kayıt başarısız.');
    }

    return responseData['message'];
  }

  Future<void> logout() async {
    if(_token != null){
      await http.post(
        Uri.parse('$_baseUrl/logout.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'token': _token}),
      );
    }

    _token = null;
    _username = null;
    _userId = null;
    _email = null;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    prefs.remove('userData');
  }
}