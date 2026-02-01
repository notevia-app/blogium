import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/category_model.dart';
import '../screens/account_settings_screen.dart';
import '../screens/category_posts_screen.dart';
import '../screens/contact_screen.dart';
import '../screens/latest_posts_screen.dart';
import '../screens/liked_posts_screen.dart';
import '../screens/login_screen.dart';
import '../screens/popular_posts_screen.dart';
import '../screens/post_detail_screen.dart';
import '../screens/saved_posts_screen.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class AppDrawer extends StatefulWidget {
  const AppDrawer({super.key});
  @override
  State<AppDrawer> createState() => _AppDrawerState();
}

class _AppDrawerState extends State<AppDrawer> with TickerProviderStateMixin {
  late Future<List<Category>> _categoriesFuture;
  late AnimationController _animationController;

  @override
  void initState() {
    super.initState();
    _categoriesFuture = ApiService.getCategories();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  void _showLoadingDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => Center(child: CircularProgressIndicator(color: Theme.of(ctx).primaryColor)),
    );
  }

  Future<void> _navigateToRandomPost() async {
    if (!mounted) return;
    _showLoadingDialog();
    try {
      final randomPost = await ApiService.getRandomPost();
      if (!mounted) return;
      Navigator.pop(context); // Yükleniyor dialog'unu kapat
      Navigator.push(context, MaterialPageRoute(builder: (_) => PostDetailScreen(postId: randomPost.id)));
    } catch(e) {
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Yazı yüklenemedi.'), backgroundColor: Colors.red.shade400, behavior: SnackBarBehavior.floating));
    }
  }

  // --- REFAKTORE EDİLDİ ---
  // Fonksiyonlar artık `BuildContext` alıyor ve renkleri temadan çekiyor.
  Widget _buildModernMenuTile(BuildContext context, {required IconData icon, required String title, required VoidCallback onTap, Color? iconColor, String? subtitle, bool hasNotification = false}) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final textTheme = theme.textTheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: (iconColor ?? colorScheme.primary).withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
              child: Icon(icon, color: iconColor ?? colorScheme.primary, size: 20),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: textTheme.titleSmall?.copyWith(fontSize: 15)),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(subtitle, style: textTheme.bodySmall?.copyWith(color: colorScheme.onSurfaceVariant)),
                  ],
                ],
              ),
            ),
            if (hasNotification) Container(width: 8, height: 8, decoration: BoxDecoration(color: colorScheme.error, borderRadius: BorderRadius.circular(4))),
            const SizedBox(width: 8),
            Icon(Icons.chevron_right, color: colorScheme.onSurfaceVariant.withOpacity(0.4), size: 18),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryTile(BuildContext context, Category category) {
    final theme = Theme.of(context);
    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: () {
        Navigator.pop(context);
        Navigator.push(context, MaterialPageRoute(builder: (ctx) => CategoryPostsScreen(categoryName: category.name, categorySlug: category.slug)));
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            Container(width: 6, height: 6, decoration: BoxDecoration(color: theme.primaryColor.withOpacity(0.6), borderRadius: BorderRadius.circular(3))),
            const SizedBox(width: 12),
            Expanded(child: Text(category.name, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w500))),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionDivider(BuildContext context, {String? title}) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Row(
        children: [
          if (title != null) Text(title, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant, letterSpacing: 0.5)),
          if (title != null) const SizedBox(width: 8),
          Expanded(child: Divider(color: theme.dividerColor.withOpacity(0.5), height: 1)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // --- TEMADAN DEĞERLERİ ALIYORUZ ---
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final textTheme = theme.textTheme;

    return Consumer<AuthService>(
      builder: (ctx, authService, _) => Drawer(
        // Artık backgroundColor belirtmeye gerek yok, temadan alıyor.
        width: 280,
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            // --- HEADER BÖLÜMÜ YENİLENDİ ---
            _buildDrawerHeader(context, authService),

            const SizedBox(height: 16),

            if (!authService.isLoggedIn) ...[
              _buildModernMenuTile(context, icon: Icons.login_rounded, title: 'Giriş Yap', subtitle: 'Hesabınıza erişim sağlayın', onTap: () {Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));}),
              _buildSectionDivider(context),
            ],

            _buildModernMenuTile(context, icon: Icons.home_rounded, title: 'Anasayfa', subtitle: 'Tüm yazıları görüntüle', onTap: () => Navigator.pop(context)),

            if (authService.isLoggedIn) ...[
              _buildModernMenuTile(context, icon: Icons.favorite_rounded, iconColor: Colors.pink.shade400, title: 'Beğenilenler', subtitle: 'Favori yazılarınız', onTap: () {Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const LikedPostsScreen()));}),
              _buildModernMenuTile(context, icon: Icons.bookmark_rounded, iconColor: Colors.blue.shade400, title: 'Kaydedilenler', subtitle: 'Sonra okumak için', onTap: () {Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const SavedPostsScreen()));}),
            ],

            _buildModernMenuTile(
              context, icon: Icons.shuffle_rounded, iconColor: Colors.purple.shade400, title: 'Rastgele Yazı', subtitle: 'Sürpriz içerik keşfedin',
              onTap: () {
                Navigator.pop(context);
                _navigateToRandomPost();
              },
            ),

            _buildSectionDivider(context, title: "KEŞFET"),

            _buildModernMenuTile(context, icon: Icons.local_fire_department_rounded, iconColor: Colors.orange.shade600, title: 'Popüler Yazılar', subtitle: 'En çok okunanlar', hasNotification: true, onTap: () {Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const PopularPostsScreen()));}),
            _buildModernMenuTile(context, icon: Icons.schedule_rounded, iconColor: Colors.green.shade600, title: 'Son Yazılar', subtitle: 'En yeni içerikler', onTap: () {Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const LatestPostsScreen()));}),

            _buildSectionDivider(context),

            // --- KATEGORİLER BÖLÜMÜ YENİLENDİ ---
            _buildCategoriesExpansionTile(context),

            _buildSectionDivider(context),
            _buildModernMenuTile(
              context, icon: Icons.alternate_email_rounded, iconColor: Colors.teal.shade600, title: 'İletişim', subtitle: 'Bize ulaşın',
              onTap: () {
                Navigator.pop(context);
                Navigator.push(context, MaterialPageRoute(builder: (_) => const ContactScreen()));
              },
            ),

            if (authService.isLoggedIn) ...[
              _buildSectionDivider(context),
              _buildModernMenuTile(
                  context, icon: Icons.settings_rounded, iconColor: Colors.grey.shade600, title: 'Hesap Ayarları', subtitle: 'Profilinizi ve tercihlerinizi yönetin',
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const AccountSettingsScreen()));
                  }
              ),
              _buildModernMenuTile(
                  context, icon: Icons.logout_rounded, iconColor: colorScheme.error, title: 'Çıkış Yap', subtitle: 'Hesabınızdan çıkış yapın',
                  onTap: () {
                    Navigator.of(context).pop();
                    Provider.of<AuthService>(context, listen: false).logout();
                  }
              ),
            ],
            // --- FOOTER BÖLÜMÜ YENİLENDİ ---
            _buildDrawerFooter(context),
          ],
        ),
      ),
    );
  }

  // --- YENİ WIDGETLAR ---
  // Kod okunabilirliğini artırmak için build metodu parçalara ayrıldı.

  Widget _buildDrawerHeader(BuildContext context, AuthService authService) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      height: 200,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [colorScheme.primary, colorScheme.primary.withOpacity(0.8)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 60, height: 60,
                  decoration: BoxDecoration(color: colorScheme.surface, borderRadius: BorderRadius.circular(30), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 10)]),
                  child: authService.isLoggedIn
                      ? Center(child: Text(authService.username?.substring(0,1).toUpperCase() ?? "B", style: TextStyle(fontSize: 24, color: colorScheme.primary, fontWeight: FontWeight.bold)))
                      : Padding(padding: const EdgeInsets.all(12), child: Image.asset('assets/logo.png')),
                ),
                IconButton(onPressed: () => Navigator.pop(context), icon: Icon(Icons.close, color: colorScheme.onPrimary)),
              ],
            ),
            const Spacer(),
            Text(authService.isLoggedIn ? authService.username ?? "Kullanıcı" : "Blogium", style: TextStyle(color: colorScheme.onPrimary, fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text(authService.isLoggedIn ? "Hoş Geldiniz 👋" : "İçerik Burada Başlar", style: TextStyle(color: colorScheme.onPrimary.withOpacity(0.9), fontSize: 14)),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoriesExpansionTile(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        color: theme.colorScheme.surfaceVariant.withOpacity(0.3),
      ),
      child: Theme(
        data: theme.copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          leading: Icon(Icons.category_rounded, color: theme.primaryColor),
          title: Text('Tüm Kategoriler', style: theme.textTheme.titleSmall?.copyWith(fontSize: 15)),
          onExpansionChanged: (expanded) {
            if (expanded) { _animationController.forward(); } else { _animationController.reverse(); }
          },
          children: [
            FadeTransition(
              opacity: _animationController,
              child: FutureBuilder<List<Category>>(
                future: _categoriesFuture,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) return Container(padding: const EdgeInsets.all(20), child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: theme.primaryColor)));
                  if (!snapshot.hasData || snapshot.data!.isEmpty) return Padding(padding: const EdgeInsets.all(20), child: Text('Kategori bulunamadı.', textAlign: TextAlign.center, style: theme.textTheme.bodyMedium));
                  return Column(
                    children: [ ...snapshot.data!.map((category) => _buildCategoryTile(context, category)), const SizedBox(height: 8)],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDrawerFooter(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24.0),
      child: Text(
        'Blogium © 2025',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.bodySmall,
      ),
    );
  }
}