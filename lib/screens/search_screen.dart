// lib/screens/search_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/post_model.dart';
import '../services/api_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/search_result_list_item.dart';

class SearchScreen extends StatefulWidget {
  final String? initialQuery;

  const SearchScreen({super.key, this.initialQuery});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _searchController = TextEditingController();
  Future<List<Post>>? _searchFuture;
  String _currentQuery = "";

  // --- DEĞİŞİKLİK: _isLoading KALDIRILDI ---
  // bool _isLoading = false;

  late StreamSubscription<bool> _connectivitySubscription;
  AsyncSnapshot<List<Post>>? _lastSnapshot;

  @override
  void initState() {
    super.initState();
    if (widget.initialQuery != null && widget.initialQuery!.isNotEmpty) {
      _searchController.text = widget.initialQuery!;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _performSearch(widget.initialQuery!);
      });
    }

    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
      if (_searchFuture != null && _lastSnapshot?.hasError == true && isConnected) {
        _performSearch(_currentQuery);
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _connectivitySubscription.cancel();
    super.dispose();
  }

  void _performSearch(String query) {
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      setState(() {
        _currentQuery = query.trim();
        _searchFuture = Future.error('Lütfen internet bağlantınızı kontrol edin.');
      });
      return;
    }

    final trimmedQuery = query.trim();
    if (trimmedQuery.isEmpty) return;

    // --- DEĞİŞİKLİK: whenComplete bloğu kaldırıldı ---
    // Manuel kontrolü engellemek için sadece aynı sorguyu tekrar aratma kontrolü kalıyor.
    if (trimmedQuery == _currentQuery && _lastSnapshot?.connectionState == ConnectionState.waiting) return;

    FocusScope.of(context).unfocus();

    setState(() {
      _currentQuery = trimmedQuery;
      _searchFuture = ApiService.searchPosts(_currentQuery);
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _searchController,
          autofocus: widget.initialQuery == null,
          decoration: InputDecoration(
            hintText: 'Yazı, yazar veya etiket arayın...',
            border: InputBorder.none,
            hintStyle: theme.textTheme.titleMedium?.copyWith(
              color: theme.colorScheme.onSurface.withOpacity(0.6),
              fontWeight: FontWeight.normal,
            ),
          ),
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.normal),
          onSubmitted: _performSearch,
        ),
        actions: [
          if (_searchController.text.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.clear_rounded),
              onPressed: () {
                _searchController.clear();
                setState(() {
                  _searchFuture = null;
                  _currentQuery = "";
                });
              },
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_searchFuture == null) {
      return _buildInitialPrompt(context);
    }
    return FutureBuilder<List<Post>>(
      future: _searchFuture,
      builder: (context, snapshot) {
        _lastSnapshot = snapshot;

        // --- DEĞİŞİKLİK: SADECE connectionState KONTROL EDİLİYOR ---
        if (snapshot.connectionState == ConnectionState.waiting) {
          return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
        }

        if (snapshot.hasError) {
          return _buildErrorState(context);
        }

        final posts = snapshot.data;
        if (posts == null || posts.isEmpty) {
          return _buildNoResults(context);
        }

        return _buildResultsList(context, posts);
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
            const Icon(Icons.wifi_off_rounded, size: 72, color: Colors.grey),
            const SizedBox(height: 24),
            Text(
              'Arama yapılamadı.\nLütfen internet bağlantınızı kontrol edin.',
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
              onPressed: () => _performSearch(_currentQuery),
            )
          ],
        ),
      ),
    );
  }

  // Geri kalan helper widget'ları aynı
  Widget _buildInitialPrompt(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.search, size: 64, color: theme.colorScheme.onSurface.withOpacity(0.5)),
          const SizedBox(height: 16),
          Text('Blog içerisindeki yazıları arayın.', style: theme.textTheme.bodyLarge),
        ],
      ),
    );
  }
  Widget _buildNoResults(BuildContext context) {
    if (_currentQuery.isEmpty) {
      return _buildInitialPrompt(context);
    }

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            children: [
              _buildResultsHeader(context, 0),
              const SizedBox(height: 48),
              const Text('Aramanızla eşleşen sonuç bulunamadı.'),
            ],
          ),
        ),
      ),
    );
  }
  Widget _buildResultsList(BuildContext context, List<Post> posts) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: ListView.builder(
          padding: const EdgeInsets.symmetric(vertical: 8.0),
          itemCount: posts.length + 1,
          itemBuilder: (context, index) {
            if (index == 0) {
              return _buildResultsHeader(context, posts.length);
            }
            final post = posts[index - 1];
            return SearchResultListItem(post: post);
          },
        ),
      ),
    );
  }
  Widget _buildResultsHeader(BuildContext context, int resultCount) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16.0, 24.0, 16.0, 16.0),
      child: Column(
        children: [
          Text.rich(
            TextSpan(
              style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.normal),
              children: [
                const TextSpan(text: '"'),
                TextSpan(
                  text: _currentQuery,
                  style: TextStyle(color: theme.primaryColor, fontWeight: FontWeight.bold),
                ),
                const TextSpan(text: '" için arama sonuçları'),
              ],
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            '$resultCount sonuç bulundu',
            style: theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }
}