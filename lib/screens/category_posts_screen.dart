// lib/screens/category_posts_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/post_model.dart';
import '../services/api_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/post_list_item.dart';
import '../widgets/custom_app_bar.dart';

class CategoryPostsScreen extends StatefulWidget {
  final String categoryName;
  final String categorySlug;

  const CategoryPostsScreen({
    super.key,
    required this.categoryName,
    required this.categorySlug,
  });

  @override
  State<CategoryPostsScreen> createState() => _CategoryPostsScreenState();
}

class _CategoryPostsScreenState extends State<CategoryPostsScreen> {
  // --- STATE DEĞİŞKENLERİ ---
  Future<List<Post>>? _postsFuture;
  late StreamSubscription<bool> _connectivitySubscription;
  AsyncSnapshot<List<Post>>? _lastSnapshot; // Son durumu saklamak için

  @override
  void initState() {
    super.initState();
    _loadPosts();

    // Bağlantı durumunu dinle
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
      // Eğer bir hata durumu varsa ve internet geri geldiyse, veriyi yeniden yükle
      if (_lastSnapshot?.hasError == true && isConnected) {
        _loadPosts();
      }
    });
  }

  @override
  void dispose() {
    _connectivitySubscription.cancel();
    super.dispose();
  }

  // API çağrısını tetikleyen fonksiyon
  void _loadPosts() {
    setState(() {
      _postsFuture = ApiService.getPostsByCategory(widget.categorySlug);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(title: widget.categoryName),
      body: FutureBuilder<List<Post>>(
        future: _postsFuture,
        builder: (context, snapshot) {
          _lastSnapshot = snapshot;

          // Yükleniyor durumunda
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
          }

          // Hata durumunda
          if (snapshot.hasError) {
            return _buildErrorState(context);
          }

          final posts = snapshot.data ?? [];

          // LayoutBuilder artık gereksiz, CustomScrollView responsive yapıyı sağlıyor.
          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(child: _buildHeader(context, posts.length)),
              if (posts.isEmpty)
                SliverFillRemaining(child: _buildEmptyState(context))
              else
                _buildContentSliver(context, posts),
            ],
          );
        },
      ),
    );
  }

  /// --- ANA SAYFA İLE AYNI HATA EKRANI ---
  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off_rounded, size: 72, color: Colors.grey.shade400),
            const SizedBox(height: 24),
            Text(
              'Yazılar yüklenemedi.\nLütfen internet bağlantınızı kontrol edin.',
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
              onPressed: _loadPosts,
            )
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Text(
        'Bu kategoride hiç gönderi bulunamadı.',
        style: Theme.of(context).textTheme.bodyMedium,
      ),
    );
  }

  Widget _buildHeader(BuildContext context, int postCount) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16.0, 24.0, 16.0, 16.0),
      child: Column(
        children: [
          Text(
            widget.categoryName,
            style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            '$postCount yazı bulundu',
            style: theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }

  /// Ekran genişliğine göre SliverList veya SliverGrid oluşturan widget.
  Widget _buildContentSliver(BuildContext context, List<Post> posts) {
    // SliverLayoutBuilder kullanarak responsive yapıyı sliver'lar içinde yönetiyoruz.
    return SliverLayoutBuilder(
      builder: (context, constraints) {
        if (constraints.crossAxisExtent > 600) {
          final crossAxisCount = (constraints.crossAxisExtent / 350).floor().clamp(2, 4);
          return SliverPadding(
            padding: const EdgeInsets.all(24.0),
            sliver: SliverGrid(
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                crossAxisSpacing: 24,
                mainAxisSpacing: 24,
                childAspectRatio: 0.8,
              ),
              delegate: SliverChildBuilderDelegate((context, index) => PostListItem(post: posts[index]), childCount: posts.length),
            ),
          );
        } else {
          return SliverPadding(
            padding: const EdgeInsets.fromLTRB(16.0, 0, 16.0, 16.0),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate((context, index) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 16.0),
                  child: PostListItem(post: posts[index]),
                );
              }, childCount: posts.length),
            ),
          );
        }
      },
    );
  }
}