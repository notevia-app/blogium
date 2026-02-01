// lib/widgets/loading_animation.dart

import 'package:flutter/material.dart';

class LoadingAnimation extends StatefulWidget {
  const LoadingAnimation({super.key});

  @override
  State<LoadingAnimation> createState() => _LoadingAnimationState();
}

class _LoadingAnimationState extends State<LoadingAnimation> with TickerProviderStateMixin {
  // --- YENİ: ANİMASYON KONTROLCÜLERİ ---
  // Bu controller, widget'ın ekrana ilk giriş animasyonunu yönetir (sadece bir kez çalışır).
  late final AnimationController _entryController;
  // Bu controller, noktaların sürekli yanıp sönme animasyonunu yönetir (sürekli döngüde çalışır).
  late final AnimationController _loopingDotController;

  // --- YENİ: GİRİŞ ANİMASYONLARI ---
  late final Animation<double> _fadeAnimation;
  late final Animation<Offset> _slideAnimation;

  // Noktaların döngüsel animasyonları
  late final List<Animation<double>> _dotLoopingAnimations;

  @override
  void initState() {
    super.initState();

    // --- GİRİŞ ANİMASYONUNU AYARLAMA ---
    _entryController = AnimationController(
      duration: const Duration(milliseconds: 800), // Giriş animasyonunun süresi
      vsync: this,
    );

    // Fade (belirginleşme) animasyonu: 0 (görünmez) -> 1 (görünür)
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _entryController, curve: Curves.easeOut),
    );

    // Slide (kayma) animasyonu: Biraz aşağıdan (0.1) -> Orijinal pozisyonuna (0.0)
    _slideAnimation = Tween<Offset>(begin: const Offset(0, 0.1), end: Offset.zero).animate(
      CurvedAnimation(parent: _entryController, curve: Curves.easeOutCubic),
    );

    // --- NOKTALARIN DÖNGÜSEL ANİMASYONUNU AYARLAMA ---
    _loopingDotController = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    );

    _dotLoopingAnimations = List.generate(3, (index) {
      final startTime = index * 0.2;
      final endTime = startTime + 0.6;
      return Tween<double>(begin: 0.3, end: 1.0).animate(
        CurvedAnimation(
          parent: _loopingDotController,
          curve: Interval(startTime, endTime.clamp(0.0, 1.0), curve: Curves.easeInOut),
        ),
      );
    });

    // --- ANİMASYONLARI BAŞLATMA ---
    // Önce giriş animasyonunu başlat
    _entryController.forward();

    // Giriş animasyonu bittikten sonra, noktaların döngüsel animasyonunu başlat
    _entryController.addStatusListener((status) {
      if (status == AnimationStatus.completed) {
        _loopingDotController.repeat();
      }
    });
  }

  @override
  void dispose() {
    _entryController.dispose();
    _loopingDotController.dispose();
    super.dispose();
  }

  // Tek bir nokta widget'ı oluşturan yardımcı fonksiyon
  Widget _buildDot(int index) {
    return FadeTransition(
      opacity: _dotLoopingAnimations[index], // Döngüsel animasyonu kullan
      child: Container(
        width: 8,
        height: 8,
        margin: const EdgeInsets.symmetric(horizontal: 4.0),
        decoration: BoxDecoration(
          color: Colors.grey.shade600,
          shape: BoxShape.circle,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Tüm widget'ı giriş animasyonlarıyla sarmalıyoruz
    return FadeTransition(
      opacity: _fadeAnimation,
      child: SlideTransition(
        position: _slideAnimation,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Sabit Duran Logo
            Image.asset(
              'assets/logo.png',
              height: 64,
            ),
            const SizedBox(height: 24),

            // Sırayla Beliren Noktalar
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(3, (index) => _buildDot(index)),
            ),
          ],
        ),
      ),
    );
  }
}