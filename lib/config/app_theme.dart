// lib/config/app_theme.dart

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

// --- RENK PALETİ (MAKSİMUM KONTRAST) ---
const kPrimaryColor = Color(0xFF5E5CE6);
const kBackgroundColor = Color(0xFFF5F5F7);
const kCardColor = Colors.white;

// --- DEĞİŞİKLİK: TÜM METİNLER İÇİN NET SİYAH ---
const kTextColor = Color(0xFF000000); // Net Siyah
const kSecondaryTextColor = Color(0xFF000000); // İkincil metin de artık Net Siyah

// --- TEMA OLUŞTURMA FONKSİYONU ---
ThemeData buildAppTheme() {
  final baseTheme = ThemeData.light();
  return baseTheme.copyWith(
    // Genel Renkler
    primaryColor: kPrimaryColor,
    scaffoldBackgroundColor: kBackgroundColor,
    colorScheme: ColorScheme.fromSeed(
      seedColor: kPrimaryColor,
      background: kBackgroundColor,
      surface: kCardColor,
      onSurface: kTextColor,
      onSurfaceVariant: kSecondaryTextColor,
    ),

    // Tipografi
    textTheme: GoogleFonts.interTextTheme(baseTheme.textTheme).copyWith(
      // --- DEĞİŞİKLİK: TÜM BAŞLIKLAR KESİNLİKLE KALIN ---
      titleLarge: const TextStyle(color: kTextColor, fontWeight: FontWeight.bold, fontSize: 24),
      titleMedium: const TextStyle(color: kTextColor, fontWeight: FontWeight.bold, fontSize: 20),
      titleSmall: const TextStyle(color: kTextColor, fontWeight: FontWeight.bold, fontSize: 16),
      // Gövde metinleri artık siyah
      bodyLarge: const TextStyle(color: kSecondaryTextColor, fontSize: 16, height: 1.6),
      bodyMedium: const TextStyle(color: kSecondaryTextColor, fontSize: 14, height: 1.5),
      labelLarge: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
    ).apply(
      bodyColor: kSecondaryTextColor,
      displayColor: kTextColor,
    ),

    // Widget Temaları
    appBarTheme: const AppBarTheme(
      backgroundColor: kCardColor,
      foregroundColor: kTextColor,
      elevation: 0,
      systemOverlayStyle: SystemUiOverlayStyle.dark,
      iconTheme: IconThemeData(color: kTextColor),
      titleTextStyle: TextStyle(
        fontFamily: 'Inter',
        color: kTextColor,
        fontSize: 22,
        fontWeight: FontWeight.bold, // AppBar başlığı da her zaman kalın
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: kCardColor,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16.0),
        side: BorderSide(color: Colors.grey.shade300, width: 1),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: kPrimaryColor,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontWeight: FontWeight.bold, // Buton metinleri de her zaman kalın
          ),
        )),
    chipTheme: ChipThemeData(
      backgroundColor: kPrimaryColor.withOpacity(0.1),
      labelStyle: const TextStyle(color: kPrimaryColor, fontWeight: FontWeight.w600),
      padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 6.0),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10.0)),
    ),
    iconTheme: const IconThemeData(color: kTextColor, size: 24), // Varsayılan ikon rengi de siyah
    textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: kPrimaryColor)),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12.0),
        borderSide: BorderSide(color: Colors.grey.shade300, width: 1),
      ),
      prefixIconColor: kTextColor, // Form ikonları da siyah
    ),
  );
}