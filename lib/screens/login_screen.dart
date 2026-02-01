// lib/screens/login_screen.dart

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart'; // YENİ IMPORT
import 'register_screen.dart';
import 'forgot_password_screen.dart';
import '../main.dart'; // HomeScreen'e erişmek için

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _isPasswordVisible = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  // --- GÜNCELLENMİŞ SUBMIT FONKSİYONU ---
  Future<void> _submit() async {
    // 1. İnternet bağlantısını kontrol et
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Lütfen internet bağlantınızı kontrol edin.'),
          backgroundColor: Theme.of(context).colorScheme.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return; // İnternet yoksa işlemi sonlandır
    }

    // 2. Formun geçerliliğini kontrol et
    if (!_formKey.currentState!.validate()) {
      return;
    }

    // 3. Giriş işlemini başlat
    setState(() => _isLoading = true);

    try {
      await Provider.of<AuthService>(context, listen: false).login(
        _emailController.text.trim(), // E-postayı trim'le
        _passwordController.text,
      );
      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const HomeScreen()),
              (route) => false,
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.toString().replaceAll('Exception: ', '')),
            backgroundColor: Theme.of(context).colorScheme.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
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
                  _buildRegisterRedirect(context),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Text(
          "Tekrar Hoş Geldin!",
          style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 10),
        Text(
          "Hesabınıza erişmek için bilgilerinizi girin.",
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
            controller: _emailController,
            decoration: const InputDecoration(
              labelText: 'E-posta',
              prefixIcon: Icon(Icons.alternate_email_rounded),
            ),
            keyboardType: TextInputType.emailAddress,
            validator: (value) => (value == null || !value.contains('@')) ? 'Geçerli bir e-posta adresi girin.' : null,
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
            validator: (value) => (value == null || value.length < 6) ? 'Şifre en az 6 karakter olmalıdır.' : null,
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(
              onPressed: () {
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ForgotPasswordScreen()));
              },
              child: const Text("Şifremi Unuttum"),
            ),
          ),
          const SizedBox(height: 24),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ElevatedButton(
            onPressed: _submit,
            child: const Text('Giriş Yap'),
          ),
        ],
      ),
    );
  }

  Widget _buildRegisterRedirect(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Text("Hesabınız yok mu?"),
        TextButton(
          onPressed: () {
            Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const RegisterScreen()));
          },
          child: const Text("Hemen Kayıt Olun"),
        ),
      ],
    );
  }
}