// lib/main.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

// Gerekli importlar
import 'services/api_service.dart';
import 'services/auth_service.dart';
import 'services/connectivity_service.dart';
import 'models/post_model.dart';
import 'screens/search_screen.dart';
import 'widgets/app_drawer.dart';
import 'widgets/post_list_item.dart';
import 'widgets/loading_animation.dart';
import 'firebase_options.dart';
import 'services/notification_service.dart';
import 'config/app_theme.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsBinding widgetsBinding = WidgetsFlutterBinding.ensureInitialized();
  // flutter_native_splash ile ilgili komutlar kaldırıldı.

  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  await NotificationService().initialize();
  NotificationService().handleInitialMessage();

  initializeDateFormatting('tr_TR', null).then((_) {
    runApp(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (ctx) => AuthService()),
          ChangeNotifierProvider(create: (ctx) => ConnectivityService()),
        ],
        child: const BlogiumApp(),
      ),
    );
  });
}

class BlogiumApp extends StatelessWidget {
  const BlogiumApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Blogium',
      navigatorKey: navigatorKey,
      theme: buildAppTheme(),
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('tr', 'TR'),
      ],
      locale: const Locale('tr', 'TR'),
      home: const HomeScreen(),
      debugShowCheckedModeBanner: false,
    );
  }
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  List<Post>? _posts;
  String _error = '';
  bool _isLoading = true; // Sayfa ilk açıldığında yükleme durumunu başlat

  late StreamSubscription<bool> _connectivitySubscription;
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  final GlobalKey<RefreshIndicatorState> _refreshIndicatorKey = GlobalKey<RefreshIndicatorState>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeApp();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.resumed) {
      // Uygulamaya geri dönüldüğünde sessizce yenile
      _refreshIndicatorKey.currentState?.show();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _connectivitySubscription.cancel();
    super.dispose();
  }

  void _initializeApp() {
    _loadData(); // Veri yüklemeyi başlat

    // Bağlantı dinleyicisini kur
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
      if (_error.isNotEmpty && isConnected) {
        _loadData();
      }
    });
  }

  Future<void> _loadData() async {
    // Yükleme denemesi başlamadan önce durumu ayarla
    if (!_isLoading) {
      setState(() => _isLoading = true);
    }

    const minSplashDuration = Duration(seconds: 2);
    final startTime = DateTime.now();

    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      await authService.tryAutoLogin();
      final newPosts = await ApiService.getPosts();

      final elapsedTime = DateTime.now().difference(startTime);
      if (elapsedTime < minSplashDuration) {
        await Future.delayed(minSplashDuration - elapsedTime);
      }

      if (mounted) {
        setState(() {
          _posts = newPosts;
          _error = '';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = "Lütfen internet bağlantınızı kontrol edin ve tekrar deneyin.";
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _handleRefresh() async {
    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      await authService.tryAutoLogin();
      final newPosts = await ApiService.getPosts();
      if (mounted) {
        setState(() {
          _posts = newPosts;
          _error = '';
        });
      }
    } catch (e) {
      // Hata durumunda snackbar ile bilgilendirilebilir.
    }
  }

  void _openEndDrawer() {
    _scaffoldKey.currentState?.openEndDrawer();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: _scaffoldKey,
      endDrawer: const AppDrawer(),
      body: SafeArea(
        child: Column(
          children: [
            _buildConnectivityBanner(context),
            Expanded(
              child: _buildBody(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildConnectivityBanner(BuildContext context) {
    return Consumer<ConnectivityService>(
      builder: (context, connectivity, child) {
        if (connectivity.isConnected) return const SizedBox.shrink();
        return Material(
          color: Colors.red.shade600,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            height: 40,
            child: const Center(
              child: Text('İnternet Bağlantısı Yok', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ),
        );
      },
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: LoadingAnimation());
    }
    if (_error.isNotEmpty) {
      return _buildErrorState();
    }
    return RefreshIndicator(
      key: _refreshIndicatorKey,
      onRefresh: _handleRefresh,
      color: Theme.of(context).primaryColor,
      child: CustomScrollView(
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        slivers: [
          _buildClassicSliverAppBar(context),
          if (_posts == null || _posts!.isEmpty)
            _buildEmptyState()
          else
            _buildContentSliver(_posts!),
        ],
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off_rounded, size: 72, color: Colors.grey.shade400),
            const SizedBox(height: 24),
            Text(_error, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyLarge),
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
              onPressed: _loadData,
            )
          ],
        ),
      ),
    );
  }

  Widget _buildClassicSliverAppBar(BuildContext context) {
    final theme = Theme.of(context);
    return SliverAppBar(
      backgroundColor: theme.cardColor,
      pinned: true,
      floating: true,
      elevation: 0,
      shape: Border(bottom: BorderSide(color: Colors.grey.shade200, width: 1.0)),
      title: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/logo.png', height: 32),
          const SizedBox(width: 12),
          const Text('Blogium'),
        ],
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.search),
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (ctx) => const SearchScreen())),
        ),
        IconButton(
          icon: const Icon(Icons.menu),
          onPressed: _openEndDrawer,
        ),
        const SizedBox(width: 8),
      ],
    );
  }

  Widget _buildContentSliver(List<Post> posts) {
    return SliverLayoutBuilder(
      builder: (context, constraints) {
        if (constraints.crossAxisExtent > 600) {
          final crossAxisCount = (constraints.crossAxisExtent / 380).floor().clamp(2, 4);
          return SliverPadding(
            padding: const EdgeInsets.all(24.0),
            sliver: SliverGrid(
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: crossAxisCount, crossAxisSpacing: 24, mainAxisSpacing: 24, childAspectRatio: 0.8),
              delegate: SliverChildBuilderDelegate((ctx, i) => PostListItem(post: posts[i]), childCount: posts.length),
            ),
          );
        } else {
          return SliverPadding(
            padding: const EdgeInsets.all(16.0),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate((ctx, i) => Padding(padding: const EdgeInsets.only(bottom: 16.0), child: PostListItem(post: posts[i])), childCount: posts.length),
            ),
          );
        }
      },
    );
  }

  Widget _buildEmptyState() {
    return SliverFillRemaining(
      hasScrollBody: false,
      child: Center(
        child: Text('Hiç gönderi bulunamadı.', style: Theme.of(context).textTheme.bodyLarge),
      ),
    );
  }
}