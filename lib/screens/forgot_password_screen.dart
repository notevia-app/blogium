// lib/screens/forgot_password_screen.dart

import 'package:flutter/material.dart';
import 'package:provider/provider.dart'; // YENİ IMPORT
import '../services/api_service.dart';
import '../services/connectivity_service.dart'; // YENİ IMPORT
import '../widgets/custom_app_bar.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  bool _isVerifyingCode = false;
  bool _isLoading = false;
  bool _isNewPasswordVisible = false;
  bool _isConfirmPasswordVisible = false;

  final _emailFormKey = GlobalKey<FormState>();
  final _resetFormKey = GlobalKey<FormState>();

  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  @override
  void dispose() {
    _emailController.dispose();
    _codeController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  void _showSnackbar(String message, {bool isError = false}) {
    if (!mounted) return;
    final theme = Theme.of(context);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? theme.colorScheme.error : Colors.green.shade600,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  // --- GÜNCELLENMİŞ FONKSİYONLAR ---

  Future<void> _handleRequestCode() async {
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      _showSnackbar("Lütfen internet bağlantınızı kontrol edin.", isError: true);
      return;
    }

    if (!_emailFormKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      final message = await ApiService.requestPasswordReset(_emailController.text.trim());
      _showSnackbar(message);
      setState(() => _isVerifyingCode = true);
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _handleResetPassword() async {
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      _showSnackbar("Lütfen internet bağlantınızı kontrol edin.", isError: true);
      return;
    }

    if (!_resetFormKey.currentState!.validate()) return;
    if (_newPasswordController.text != _confirmPasswordController.text) {
      _showSnackbar("Yeni şifreler uyuşmuyor.", isError: true);
      return;
    }
    setState(() => _isLoading = true);
    try {
      final message = await ApiService.resetPassword(
        email: _emailController.text.trim(),
        token: _codeController.text.trim(),
        newPassword: _newPasswordController.text,
      );
      _showSnackbar(message);
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppBar(title: "Şifremi Unuttum"),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 300),
                transitionBuilder: (Widget child, Animation<double> animation) {
                  return FadeTransition(opacity: animation, child: child);
                },
                child: _isVerifyingCode
                    ? _buildResetForm(context)
                    : _buildRequestForm(context),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context, {required String title, required String subtitle}) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Text(title, style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 10),
        Text(subtitle, style: theme.textTheme.bodyLarge, textAlign: TextAlign.center),
      ],
    );
  }

  Widget _buildRequestForm(BuildContext context) {
    return Form(
      key: _emailFormKey,
      child: Column(
        key: const ValueKey('requestForm'),
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _buildHeader(
            context,
            title: "Şifre Sıfırlama",
            subtitle: "Hesabınıza kayıtlı e-posta adresini girerek sıfırlama kodu talep edebilirsiniz.",
          ),
          const SizedBox(height: 32),
          TextFormField(
            controller: _emailController,
            decoration: const InputDecoration(
              labelText: "E-posta Adresi",
              prefixIcon: Icon(Icons.alternate_email_rounded),
            ),
            keyboardType: TextInputType.emailAddress,
            validator: (value) => (value == null || !value.contains('@')) ? 'Geçerli bir e-posta girin.' : null,
          ),
          const SizedBox(height: 24),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ElevatedButton(
            onPressed: _handleRequestCode,
            child: const Text("Sıfırlama Kodu Gönder"),
          ),
        ],
      ),
    );
  }

  Widget _buildResetForm(BuildContext context) {
    return Form(
      key: _resetFormKey,
      child: Column(
        key: const ValueKey('resetForm'),
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _buildHeader(
            context,
            title: "Yeni Şifre Belirle",
            subtitle: "${_emailController.text} adresine gönderilen kodu ve yeni şifrenizi girin.",
          ),
          const SizedBox(height: 32),
          TextFormField(
            controller: _codeController,
            decoration: const InputDecoration(
              labelText: "Doğrulama Kodu",
              prefixIcon: Icon(Icons.pin_outlined),
            ),
            keyboardType: TextInputType.number,
            validator: (value) => (value == null || value.isEmpty) ? "Lütfen kodu girin." : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _newPasswordController,
            decoration: InputDecoration(
              labelText: "Yeni Şifre",
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: IconButton(
                icon: Icon(_isNewPasswordVisible ? Icons.visibility_off : Icons.visibility),
                onPressed: () => setState(() => _isNewPasswordVisible = !_isNewPasswordVisible),
              ),
            ),
            obscureText: !_isNewPasswordVisible,
            validator: (value) => (value == null || value.length < 6) ? "Şifre en az 6 karakter olmalı." : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _confirmPasswordController,
            decoration: InputDecoration(
              labelText: "Yeni Şifre (Tekrar)",
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: IconButton(
                icon: Icon(_isConfirmPasswordVisible ? Icons.visibility_off : Icons.visibility),
                onPressed: () => setState(() => _isConfirmPasswordVisible = !_isConfirmPasswordVisible),
              ),
            ),
            obscureText: !_isConfirmPasswordVisible,
            validator: (value) => (value == null || value.isEmpty) ? "Lütfen şifreyi tekrar girin." : null,
          ),
          const SizedBox(height: 24),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ElevatedButton(
            onPressed: _handleResetPassword,
            child: const Text("Şifreyi Güncelle"),
          ),
          TextButton(
            onPressed: () => setState(() => _isVerifyingCode = false),
            child: const Text("Geri Dön"),
          ),
        ],
      ),
    );
  }
}