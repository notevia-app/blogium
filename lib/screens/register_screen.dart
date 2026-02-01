// lib/screens/register_screen.dart

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart'; // YENİ IMPORT
import 'login_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final List<String> _allowedDomains = [
    'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'icloud.com',
    'yandex.com', 'protonmail.com', 'hotmail.com.tr', 'outlook.com.tr',
    'yahoo.com.tr', 'yandex.com.tr'
  ];

  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _agreedToTerms = false;
  bool _isPasswordVisible = false;

  @override
  void dispose() {
    _usernameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _launchURL(String urlString) async {
    final Uri url = Uri.parse(urlString);
    if (!await launchUrl(url, mode: LaunchMode.inAppWebView)) {
      _showErrorSnackbar('Bağlantı açılamadı: $urlString');
    }
  }

  void _showErrorSnackbar(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: Theme.of(context).colorScheme.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // --- GÜNCELLENMİŞ SUBMIT FONKSİYONU ---
  Future<void> _submit() async {
    // 1. İnternet bağlantısını kontrol et
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      _showErrorSnackbar('Lütfen internet bağlantınızı kontrol edin.');
      return; // İnternet yoksa işlemi sonlandır
    }

    // 2. Formun ve sözleşmenin geçerliliğini kontrol et
    final form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    if (!_agreedToTerms) {
      _showErrorSnackbar("Devam etmek için kullanıcı sözleşmesini kabul etmelisiniz.");
      return;
    }

    // 3. Kayıt işlemini başlat
    setState(() => _isLoading = true);

    try {
      final message = await Provider.of<AuthService>(context, listen: false).register(
        _usernameController.text,
        _emailController.text,
        _passwordController.text,
      );

      if (mounted) {
        await showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text("Kayıt Başarılı!"),
            content: Text(message),
            actions: [
              TextButton(
                child: const Text("Giriş Yap"),
                onPressed: () => Navigator.of(ctx).pop(),
              )
            ],
          ),
        );
        if (mounted) {
          Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
        }
      }
    } catch (error) {
      _showErrorSnackbar(error.toString().replaceAll('Exception: ', ''));
    }

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 20.0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _buildHeader(context),
                  const SizedBox(height: 40),
                  _buildForm(context),
                  const SizedBox(height: 24),
                  _buildLoginRedirect(context),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // Helper widget'larda değişiklik yok
  Widget _buildHeader(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Text(
          "Hesap Oluştur",
          style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 10),
        Text(
          "Yeni bir hesap oluşturarak aramıza katılın.",
          style: theme.textTheme.bodyLarge,
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildForm(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextFormField(
            controller: _usernameController,
            decoration: const InputDecoration(
              labelText: 'Kullanıcı Adı',
              hintText: 'a-z, 0-9, ., -, _',
              prefixIcon: Icon(Icons.person_outline_rounded),
            ),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp("[a-z0-9._-]"))],
            validator: (value) {
              if (value == null || value.isEmpty) return 'Kullanıcı adı boş olamaz.';
              if (value.length < 3) return 'Kullanıcı adı en az 3 karakter olmalıdır.';
              if (RegExp(r'[^a-z0-9._-]').hasMatch(value)) {
                return 'İzin verilmeyen karakter kullanıldı.';
              }
              return null;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _emailController,
            decoration: const InputDecoration(
              labelText: 'E-posta',
              prefixIcon: Icon(Icons.alternate_email_rounded),
            ),
            keyboardType: TextInputType.emailAddress,
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return 'E-posta alanı boş olamaz.';
              }
              final emailParts = value.split('@');
              if (emailParts.length != 2 || emailParts[1].isEmpty) {
                return 'Geçerli bir e-posta adresi girin.';
              }
              final domain = emailParts[1].toLowerCase();
              if (!_allowedDomains.contains(domain)) {
                return 'Lütfen geçerli bir e-posta sağlayıcısı kullanın (gmail, outlook vb.).';
              }
              return null;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _passwordController,
            decoration: InputDecoration(
              labelText: 'Şifre',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: IconButton(
                icon: Icon(_isPasswordVisible ? Icons.visibility_off : Icons.visibility),
                onPressed: () => setState(() => _isPasswordVisible = !_isPasswordVisible),
              ),
            ),
            obscureText: !_isPasswordVisible,
            validator: (value) {
              if (value == null || value.isEmpty) return 'Şifre boş olamaz.';
              if (value.length < 6) return 'Şifre en az 6 karakter olmalı.';
              return null;
            },
          ),
          const SizedBox(height: 24),
          _buildTermsAndConditions(context),
          const SizedBox(height: 24),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ElevatedButton(
            onPressed: _submit,
            child: const Text('Hesabımı Oluştur'),
          ),
        ],
      ),
    );
  }

  Widget _buildTermsAndConditions(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 24,
          height: 24,
          child: Checkbox(
            value: _agreedToTerms,
            onChanged: (value) => setState(() => _agreedToTerms = value ?? false),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: RichText(
            text: TextSpan(
              style: theme.textTheme.bodyMedium,
              children: [
                const TextSpan(text: "Okudum, anladım ve "),
                TextSpan(
                  text: "Kullanıcı Sözleşmesini",
                  style: TextStyle(
                    color: theme.primaryColor,
                    decoration: TextDecoration.underline,
                  ),
                  recognizer: TapGestureRecognizer()..onTap = () => _launchURL("https://blogium.net/kullanici_sozlesmesi"),
                ),
                const TextSpan(text: " kabul ediyorum."),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildLoginRedirect(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Text("Zaten bir hesabınız var mı?"),
        TextButton(
          onPressed: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen())),
          child: const Text("Giriş Yapın"),
        ),
      ],
    );
  }
}