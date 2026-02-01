// lib/screens/account_settings_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/custom_app_bar.dart';

class AccountSettingsScreen extends StatefulWidget {
  const AccountSettingsScreen({super.key});

  @override
  State<AccountSettingsScreen> createState() => _AccountSettingsScreenState();
}

class _AccountSettingsScreenState extends State<AccountSettingsScreen> {
  Future<Map<String, dynamic>>? _detailsFuture;
  late StreamSubscription<bool> _connectivitySubscription;
  AsyncSnapshot<Map<String, dynamic>>? _lastSnapshot;

  bool _isVerifyingEmail = false;
  bool _isLoading = false;

  final _emailFormKey = GlobalKey<FormState>();
  final _verifyEmailFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();
  final _deleteFormKey = GlobalKey<FormState>();

  final _newEmailController = TextEditingController();
  final _currentPasswordForEmailController = TextEditingController();
  final _verificationCodeController = TextEditingController();
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _deleteConfirmPasswordController = TextEditingController();

  bool _isCurrentPasswordVisible = false;
  bool _isNewPasswordVisible = false;
  bool _isConfirmPasswordVisible = false;
  bool _isDeletePasswordVisible = false;
  bool _isEmailPasswordVisible = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_detailsFuture == null) {
      _loadDetails();

      final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
      _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
        // Bu dinleyici, sayfa İLK AÇILIRKEN hata alırsa ve sonra internet gelirse diye hala gerekli.
        if (_lastSnapshot?.hasError == true && isConnected) {
          _loadDetails();
        }
      });
    }
  }

  @override
  void dispose() {
    _connectivitySubscription.cancel();
    _newEmailController.dispose();
    _currentPasswordForEmailController.dispose();
    _verificationCodeController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    _deleteConfirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _loadDetails() async {
    final auth = Provider.of<AuthService>(context, listen: false);
    if (auth.isLoggedIn) {
      setState(() {
        _detailsFuture = ApiService.getAccountDetails(auth.token!);
      });
    }
  }

  void _showSnackbar(String message, {bool isError = false}) {
    if (!mounted) return;
    final theme = Theme.of(context);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: isError ? theme.colorScheme.error : Colors.green.shade600,
      behavior: SnackBarBehavior.floating,
    ));
  }

  bool _checkConnection() {
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      _showSnackbar("Lütfen internet bağlantınızı kontrol edin.", isError: true);
      return false;
    }
    return true;
  }

  // --- FORM İŞLEME FONKSİYONLARI (değişiklik yok) ---
  Future<void> _handleRequestEmailChange() async {
    if (!_checkConnection() || !_emailFormKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);
    try {
      final message = await ApiService.requestEmailChange(token: auth.token!, newEmail: _newEmailController.text, password: _currentPasswordForEmailController.text);
      _showSnackbar(message);
      await _loadDetails();
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _handleVerifyEmailChange() async {
    if (!_checkConnection() || !_verifyEmailFormKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);
    try {
      final message = await ApiService.verifyEmailChange(token: auth.token!, code: _verificationCodeController.text);
      _showSnackbar(message);
      _newEmailController.clear();
      _currentPasswordForEmailController.clear();
      _verificationCodeController.clear();
      await _loadDetails();
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _handleCancelEmailChange() async {
    if (!_checkConnection()) return;
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);
    try {
      final message = await ApiService.cancelEmailChange(token: auth.token!);
      _showSnackbar(message);
      await _loadDetails();
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _handleChangePassword() async {
    if (!_checkConnection() || !_passwordFormKey.currentState!.validate()) return;
    if (_newPasswordController.text != _confirmPasswordController.text) {
      _showSnackbar("Yeni şifreler uyuşmuyor.", isError: true);
      return;
    }
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);
    try {
      final message = await ApiService.changePassword(token: auth.token!, currentPassword: _currentPasswordController.text, newPassword: _newPasswordController.text);
      _showSnackbar(message);
      _currentPasswordController.clear();
      _newPasswordController.clear();
      _confirmPasswordController.clear();
      FocusScope.of(context).unfocus();
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _handleDeleteAccount() async {
    if (!_checkConnection() || !_deleteFormKey.currentState!.validate()) return;
    final confirmed = await showDialog<bool>(context: context, builder: (ctx) => _buildDeleteConfirmationDialog(ctx));
    if (confirmed != true) return;
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);
    try {
      final message = await ApiService.deleteAccount(token: auth.token!, password: _deleteConfirmPasswordController.text);
      _showSnackbar(message);
      await auth.logout();
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppBar(title: "Hesap Ayarları"),
      // --- DEĞİŞİKLİK: BODY ARTIK CONSUMER İLE SARILI ---
      body: Consumer<ConnectivityService>(
        builder: (context, connectivity, child) {
          // 1. ÖNCELİK: İnternet var mı?
          // Eğer internet herhangi bir anda yoksa, FutureBuilder'ı hiç çalıştırma, doğrudan hata ekranını göster.
          if (!connectivity.isConnected) {
            return _buildErrorState(context, 'İnternet bağlantısı kesildi.');
          }

          // 2. ÖNCELİK: İnternet varsa, FutureBuilder'ı çalıştır.
          return FutureBuilder<Map<String, dynamic>>(
            future: _detailsFuture,
            builder: (context, snapshot) {
              _lastSnapshot = snapshot;

              if (snapshot.connectionState == ConnectionState.waiting) {
                return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
              }

              if (snapshot.hasError) {
                return _buildErrorState(context);
              }

              if (!snapshot.hasData) {
                return _buildErrorState(context, "Hesap bilgileri alınamadı.");
              }

              final userDetails = snapshot.data!;
              _isVerifyingEmail = userDetails['is_verifying_new_email'] ?? false;

              // Veri başarıyla yüklendi, ana içeriği göster.
              return _buildMainContent(context, userDetails);
            },
          );
        },
      ),
    );
  }

  /// Veri başarıyla yüklendiğinde gösterilecek ana içerik.
  Widget _buildMainContent(BuildContext context, Map<String, dynamic> userDetails) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 700),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              _buildProfileInfoSection(context, userDetails),
              _buildEmailSection(context, userDetails),
              _buildPasswordSection(context),
              _buildDeleteSection(context),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildErrorState(BuildContext context, [String? message]) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.wifi_off_rounded, size: 72, color: Colors.grey),
            const SizedBox(height: 24),
            Text(
              message ?? 'Bilgiler yüklenemedi.\nLütfen internet bağlantınızı kontrol edin.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            Text(
              'Bağlantı kurulduğunda otomatik olarak yenilenecektir.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              icon: const Icon(Icons.refresh_rounded),
              label: const Text("Tekrar Dene"),
              onPressed: _loadDetails,
            )
          ],
        ),
      ),
    );
  }

  // Geri kalan tüm _build... fonksiyonları aynı, sadece parametre alıyorlar.
  Widget _buildSettingsGroup({required BuildContext context, required String title, required Widget child}) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const Divider(height: 32),
            child,
          ],
        ),
      ),
    );
  }

  Widget _buildProfileInfoSection(BuildContext context, Map<String, dynamic> userDetails) {
    return _buildSettingsGroup(
      context: context,
      title: "Profil Bilgileri",
      child: Column(
        children: [
          TextFormField(initialValue: userDetails['username'], readOnly: true, decoration: const InputDecoration(labelText: "Kullanıcı Adı", prefixIcon: Icon(Icons.person_outline_rounded), filled: true)),
          const SizedBox(height: 16),
          TextFormField(initialValue: userDetails['email'], readOnly: true, decoration: const InputDecoration(labelText: "Mevcut E-posta Adresi", prefixIcon: Icon(Icons.alternate_email_rounded), filled: true)),
        ],
      ),
    );
  }

  Widget _buildEmailSection(BuildContext context, Map<String, dynamic> userDetails) {
    return _buildSettingsGroup(
      context: context,
      title: _isVerifyingEmail ? "Yeni E-postayı Doğrula" : "E-posta Değiştir",
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        child: _isVerifyingEmail
            ? Form(key: _verifyEmailFormKey, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [Text("Lütfen ${userDetails['new_email']} adresine gönderilen 6 haneli kodu girin."), const SizedBox(height: 16), TextFormField(controller: _verificationCodeController, decoration: const InputDecoration(labelText: "Doğrulama Kodu", prefixIcon: Icon(Icons.pin_outlined)), validator: (v) => v!.isEmpty ? 'Kod boş olamaz.' : null), const SizedBox(height: 16), _isLoading ? const Center(child: CircularProgressIndicator()) : ElevatedButton(onPressed: _handleVerifyEmailChange, child: const Text("Kodu Onayla")), SizedBox(width: double.infinity, child: TextButton(onPressed: _isLoading ? null : _handleCancelEmailChange, child: const Text("Vazgeç")))]))
            : Form(key: _emailFormKey, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [TextFormField(controller: _newEmailController, decoration: const InputDecoration(labelText: "Yeni E-posta Adresi", prefixIcon: Icon(Icons.alternate_email_rounded)), validator: (v) => v!.isEmpty || !v.contains('@') ? 'Geçerli bir e-posta girin.' : null), const SizedBox(height: 16), TextFormField(controller: _currentPasswordForEmailController, obscureText: !_isEmailPasswordVisible, decoration: InputDecoration(labelText: "Onay İçin Mevcut Şifreniz", prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(icon: Icon(_isEmailPasswordVisible ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _isEmailPasswordVisible = !_isEmailPasswordVisible))), validator: (v) => v!.isEmpty ? 'Şifre boş olamaz.' : null), const SizedBox(height: 16), _isLoading ? const Center(child: CircularProgressIndicator()) : ElevatedButton(onPressed: _handleRequestEmailChange, child: const Text("Doğrulama Kodu Gönder"))])),
      ),
    );
  }

  Widget _buildPasswordSection(BuildContext context) {
    return Form(
      key: _passwordFormKey,
      child: _buildSettingsGroup(
        context: context,
        title: "Şifre Değiştir",
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [TextFormField(controller: _currentPasswordController, obscureText: !_isCurrentPasswordVisible, decoration: InputDecoration(labelText: "Mevcut Şifre", prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(icon: Icon(_isCurrentPasswordVisible ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _isCurrentPasswordVisible = !_isCurrentPasswordVisible))), validator: (v) => v!.isEmpty ? 'Şifre boş olamaz.' : null), const SizedBox(height: 16), TextFormField(controller: _newPasswordController, obscureText: !_isNewPasswordVisible, decoration: InputDecoration(labelText: "Yeni Şifre", prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(icon: Icon(_isNewPasswordVisible ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _isNewPasswordVisible = !_isNewPasswordVisible))), validator: (v) => v!.length < 6 ? 'Şifre en az 6 karakter olmalı.' : null), const SizedBox(height: 16), TextFormField(controller: _confirmPasswordController, obscureText: !_isConfirmPasswordVisible, decoration: InputDecoration(labelText: "Yeni Şifre (Tekrar)", prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(icon: Icon(_isConfirmPasswordVisible ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _isConfirmPasswordVisible = !_isConfirmPasswordVisible))), validator: (v) => v!.isEmpty ? 'Şifre boş olamaz.' : null), const SizedBox(height: 16), _isLoading ? const Center(child: CircularProgressIndicator()) : ElevatedButton(onPressed: _handleChangePassword, child: const Text("Şifreyi Güncelle"))]),
      ),
    );
  }

  Widget _buildDeleteSection(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      color: theme.colorScheme.errorContainer,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16.0), side: BorderSide(color: theme.colorScheme.error)),
      child: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Form(
          key: _deleteFormKey,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [Text("Tehlikeli Bölge", style: theme.textTheme.titleMedium?.copyWith(color: theme.colorScheme.error)), Divider(height: 32, color: theme.colorScheme.error), Text("Bu işlem geri alınamaz. Hesabınızı sildiğinizde tüm verileriniz kalıcı olarak yok olacaktır.", style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onErrorContainer)), const SizedBox(height: 16), TextFormField(controller: _deleteConfirmPasswordController, obscureText: !_isDeletePasswordVisible, decoration: InputDecoration(labelText: "Onaylamak için şifrenizi girin", prefixIcon: Icon(Icons.shield_outlined, color: theme.colorScheme.error), suffixIcon: IconButton(icon: Icon(_isDeletePasswordVisible ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _isDeletePasswordVisible = !_isDeletePasswordVisible))), validator: (v) => v!.isEmpty ? 'Şifre boş olamaz.' : null), const SizedBox(height: 16), _isLoading ? const Center(child: CircularProgressIndicator()) : ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: theme.colorScheme.error, foregroundColor: theme.colorScheme.onError), onPressed: _handleDeleteAccount, child: const Text("Hesabımı Kalıcı Olarak Sil"))]),
        ),
      ),
    );
  }

  Widget _buildDeleteConfirmationDialog(BuildContext context) {
    final theme = Theme.of(context);
    return AlertDialog(
      title: const Text("Emin misiniz?"),
      content: const Text("Bu işlem geri alınamaz. Hesabınız ve tüm verileriniz kalıcı olarak silinecektir."),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text("İptal")),
        TextButton(
          onPressed: () => Navigator.of(context).pop(true),
          child: Text("Evet, Sil", style: TextStyle(color: theme.colorScheme.error)),
        ),
      ],
    );
  }
}