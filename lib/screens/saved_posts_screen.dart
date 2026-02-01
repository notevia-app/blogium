// lib/screens/saved_posts_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/post_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/post_list_item.dart';
import '../widgets/custom_app_bar.dart';
import 'login_screen.dart';

class SavedPostsScreen extends StatefulWidget {
  const SavedPostsScreen({super.key});

  @override
  State<SavedPostsScreen> createState() => _SavedPostsScreenState();
}

class _SavedPostsScreenState extends State<SavedPostsScreen> {
  // --- STATE DEĞİŞKENLERİ ---
  Future<List<Post>>? _savedPostsFuture;
  late StreamSubscription<bool> _connectivitySubscription;
  AsyncSnapshot<List<Post>>? _lastSnapshot; // Son durumu saklamak için

  @override
  void initState() {
    super.initState();
    // initState'te Provider.of çağırmak yerine, didChangeDependencies kullanmak daha güvenlidir.
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Sadece bir kez, widget ağaca eklendikten sonra çalışır.
    if (_savedPostsFuture == null) {
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
  }

  @override
  void dispose() {
    _connectivitySubscription.cancel();
    super.dispose();
  }

  // API çağrısını tetikleyen fonksiyon
  void _loadPosts() {
    final auth = Provider.of<AuthService>(context, listen: false);
    if (auth.isLoggedIn) {
      setState(() {
        _savedPostsFuture = ApiService.getSavedPosts(auth.token!);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthService>(context, listen: true);

    return Scaffold(
      appBar: const CustomAppBar(title: 'Kaydedilen Yazılar'),
      body: auth.isLoggedIn
          ? _buildLoggedInView()
          : _buildLoginPrompt(context),
    );
  }

  Widget _buildLoginPrompt(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(Icons.collections_bookmark_outlined, size: 64, color: theme.colorScheme.onSurface.withOpacity(0.5)),
            const SizedBox(height: 24),
            Text(
              'Kaydettiğiniz yazıları görmek için lütfen giriş yapın.',
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
    );
  }

  Widget _buildLoggedInView() {
    if (_savedPostsFuture == null) {
      return _buildLoginPrompt(context);
    }

    return FutureBuilder<List<Post>>(
      future: _savedPostsFuture,
      builder: (context, snapshot) {
        _lastSnapshot = snapshot;

        if (snapshot.connectionState == ConnectionState.waiting) {
          return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
        }

        if (snapshot.hasError) {
          return _buildErrorState(context);
        }

        if (!snapshot.hasData || snapshot.data!.isEmpty) {
          return _buildEmptyState(context);
        }

        final posts = snapshot.data!;
        return _buildContent(context, posts);
      },
    );
  }

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

  Widget _buildContent(BuildContext context, List<Post> posts) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return CustomScrollView(
          slivers: [
            SliverToBoxAdapter(child: _buildHeader(context)),
            _buildContentSliver(context, constraints, posts),
          ],
        );
      },
    );
  }

  Widget _buildHeader(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(24.0, 24.0, 24.0, 16.0),
      child: Column(
        children: [
          Icon(Icons.bookmark_rounded, color: theme.primaryColor, size: 40),
          const SizedBox(height: 10),
          Text('Kaydedilen Yazılar', style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text(
            'Daha sonra okumak için listenize eklediğiniz yazılar.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Column(
      children: [
        _buildHeader(context),
        Expanded(
          child: Center(
            child: Text(
              'Henüz hiç yazı kaydetmemişsiniz.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildContentSliver(BuildContext context, BoxConstraints constraints, List<Post> posts) {
    if (constraints.maxWidth > 600) {
      final crossAxisCount = (constraints.maxWidth / 350).floor().clamp(2, 4);
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
      return SliverList(
        delegate: SliverChildBuilderDelegate((context, index) {
          return Padding(
            padding: const EdgeInsets.fromLTRB(16.0, 0, 16.0, 16.0),
            child: PostListItem(post: posts[index]),
          );
        }, childCount: posts.length),
      );
    }
  }
}