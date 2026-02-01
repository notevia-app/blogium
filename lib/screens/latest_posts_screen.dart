// lib/screens/latest_posts_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/post_model.dart';
import '../services/api_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/post_list_item.dart';
import '../widgets/custom_app_bar.dart';

class LatestPostsScreen extends StatefulWidget {
  const LatestPostsScreen({super.key});

  @override
  State<LatestPostsScreen> createState() => _LatestPostsScreenState();
}

class _LatestPostsScreenState extends State<LatestPostsScreen> {
  // --- STATE DEĞİŞKENLERİ ---
  Future<List<Post>>? _latestPostsFuture;
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
      _latestPostsFuture = ApiService.getLatestPosts();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppBar(title: "Son Yazılar"),
      body: FutureBuilder<List<Post>>(
        future: _latestPostsFuture,
        builder: (context, snapshot) {
          // Son durumu her build'de güncelle
          _lastSnapshot = snapshot;

          // Yükleniyor durumunda
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
          }

          // Hata durumunda (İnternet yok veya başka bir hata)
          if (snapshot.hasError) {
            return _buildErrorState(context);
          }

          // Veri var ama boş
          if (!snapshot.hasData || snapshot.data!.isEmpty) {
            return _buildEmptyState(context);
          }

          // Veri başarıyla yüklendi
          final posts = snapshot.data!;
          return _buildContent(context, posts);
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
              onPressed: _loadPosts, // Butona basıldığında API çağrısını tekrar yap
            )
          ],
        ),
      ),
    );
  }

  /// İçeriği oluşturan ana widget
  Widget _buildContent(BuildContext context, List<Post> posts) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth > 600) {
          return _buildGridView(context, constraints, posts);
        } else {
          return _buildListView(context, posts);
        }
      },
    );
  }

  // Geri kalan helper widget'ları
  Widget _buildEmptyState(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.schedule_outlined,
            size: 64,
            color: theme.colorScheme.onSurface.withOpacity(0.5),
          ),
          const SizedBox(height: 16),
          Text(
            'Son gönderi bulunamadı.',
            style: theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }

  Widget _buildGridView(BuildContext context, BoxConstraints constraints, List<Post> posts) {
    final crossAxisCount = (constraints.maxWidth / 350).floor().clamp(2, 4);
    return GridView.builder(
      padding: const EdgeInsets.all(24.0),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount,
        crossAxisSpacing: 24,
        mainAxisSpacing: 24,
        childAspectRatio: 0.8,
      ),
      itemCount: posts.length,
      itemBuilder: (ctx, i) {
        return PostListItem(post: posts[i]);
      },
    );
  }

  Widget _buildListView(BuildContext context, List<Post> posts) {
    return ListView.builder(
      padding: const EdgeInsets.all(16.0),
      itemCount: posts.length,
      itemBuilder: (ctx, i) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 16.0),
          child: PostListItem(post: posts[i]),
        );
      },
    );
  }
}