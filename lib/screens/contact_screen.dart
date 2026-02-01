// lib/screens/contact_screen.dart

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import '../services/connectivity_service.dart'; // YENİ IMPORT
import '../widgets/custom_app_bar.dart';
import 'login_screen.dart';

class ContactScreen extends StatefulWidget {
  const ContactScreen({super.key});

  @override
  State<ContactScreen> createState() => _ContactScreenState();
}

class _ContactScreenState extends State<ContactScreen> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
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

  Future<void> _submitForm() async {
    // İnternet yoksa formu göndermeyi en baştan engelle
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      _showSnackbar("Lütfen internet bağlantınızı kontrol edin.", isError: true);
      return;
    }

    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    final auth = Provider.of<AuthService>(context, listen: false);

    try {
      final message = await ApiService.sendContactForm(
        token: auth.token!,
        subject: _subjectController.text,
        message: _messageController.text,
      );
      _showSnackbar(message);
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      _showSnackbar(e.toString().replaceAll("Exception: ", ""), isError: true);
    }
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthService>(context, listen: true);

    return Scaffold(
      appBar: const CustomAppBar(title: "İletişim"),
      body: auth.isLoggedIn
          ? _buildFormView(context)
          : _buildLoginPrompt(context),
    );
  }

  Widget _buildLoginPrompt(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 400),
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Icon(
                Icons.mail_outline_rounded,
                size: 64,
                color: theme.colorScheme.onSurface.withOpacity(0.5),
              ),
              const SizedBox(height: 24),
              Text(
                "İletişim formunu kullanmak için giriş yapmalısınız.",
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge,
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: () {
                  Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen()));
                },
                child: const Text("Giriş Yap / Kayıt Ol"),
              )
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFormView(BuildContext context) {
    // --- YENİ: BAĞLANTI DURUMUNU DİNLEMEK İÇİN CONSUMER ---
    // Bu, internet durumu değiştiğinde butonun yeniden çizilmesini sağlar.
    return Consumer<ConnectivityService>(
      builder: (context, connectivity, child) {
        return Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 600),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildHeader(context),
                    const SizedBox(height: 32),
                    _buildUserInfoFields(context),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _subjectController,
                      decoration: const InputDecoration(
                        labelText: "Konu",
                        prefixIcon: Icon(Icons.subject_rounded),
                      ),
                      validator: (value) => (value == null || value.isEmpty) ? "Lütfen bir konu girin." : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _messageController,
                      decoration: const InputDecoration(
                        labelText: "Mesajınız",
                        prefixIcon: Icon(Icons.chat_bubble_outline_rounded),
                        alignLabelWithHint: true,
                      ),
                      maxLines: 5,
                      minLines: 3,
                      validator: (value) => (value == null || value.isEmpty) ? "Lütfen mesajınızı yazın." : null,
                    ),
                    const SizedBox(height: 24),
                    if (_isLoading)
                      const Center(child: CircularProgressIndicator())
                    else
                      ElevatedButton(
                        // --- DEĞİŞİKLİK: BUTONUN AKTİF OLUP OLMAMASI İNTERNETE BAĞLI ---
                        onPressed: connectivity.isConnected ? _submitForm : null,
                        child: Text(connectivity.isConnected ? "Mesajı Gönder" : "İnternet Bağlantısı Bekleniyor"),
                      ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildHeader(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Text(
          "Bize Ulaşın",
          style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        Text(
          "Görüş, öneri veya şikayetlerinizi bizimle paylaşabilirsiniz.",
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyLarge,
        ),
      ],
    );
  }

  Widget _buildUserInfoFields(BuildContext context) {
    final auth = Provider.of<AuthService>(context, listen: false);
    return Column(
      children: [
        TextFormField(
          initialValue: auth.username,
          readOnly: true,
          decoration: const InputDecoration(
            labelText: "Kullanıcı Adı",
            prefixIcon: Icon(Icons.person_outline_rounded),
            filled: true,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          initialValue: auth.email,
          readOnly: true,
          decoration: const InputDecoration(
            labelText: "E-posta Adresiniz",
            prefixIcon: Icon(Icons.alternate_email_rounded),
            filled: true,
          ),
        ),
      ],
    );
  }
}