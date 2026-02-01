// lib/screens/post_detail_screen.dart

import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../models/post_detail_model.dart';
import '../screens/login_screen.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart';
import '../widgets/comment_section.dart';

class PostDetailScreen extends StatefulWidget {
  final int postId;
  const PostDetailScreen({super.key, required this.postId});

  @override
  State<PostDetailScreen> createState() => _PostDetailScreenState();
}

class _PostDetailScreenState extends State<PostDetailScreen> with TickerProviderStateMixin {
  Future<PostDetail>? _postDetailFuture;
  late StreamSubscription<bool> _connectivitySubscription;
  AsyncSnapshot<PostDetail>? _lastSnapshot;

  bool _isLiked = false;
  bool _isSaved = false;
  final _commentSectionKey = GlobalKey();

  late AnimationController _bottomSheetController;
  late Animation<Offset> _offsetAnimation;
  bool _isBottomSheetVisible = true;

  @override
  void initState() {
    super.initState();
    _bottomSheetController = AnimationController(duration: const Duration(milliseconds: 300), vsync: this);
    _offsetAnimation = Tween<Offset>(begin: Offset.zero, end: const Offset(0.0, 1.2)).animate(CurvedAnimation(parent: _bottomSheetController, curve: Curves.easeInOut));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_postDetailFuture == null) {
      _loadData();
      final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
      _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
        if (_lastSnapshot?.hasError == true && isConnected) {
          _loadData();
        }
      });
    }
  }

  @override
  void dispose() {
    _bottomSheetController.dispose();
    _connectivitySubscription.cancel();
    super.dispose();
  }

  void _toggleBottomSheet() {
    setState(() {
      _isBottomSheetVisible = !_isBottomSheetVisible;
      if (_isBottomSheetVisible) {
        _bottomSheetController.reverse();
      } else {
        _bottomSheetController.forward();
      }
    });
  }

  void _loadData() {
    final auth = Provider.of<AuthService>(context, listen: false);
    _postDetailFuture = ApiService.getPostDetails(widget.postId, token: auth.token);
    _postDetailFuture!.then((postDetail) {
      if (mounted) {
        setState(() {
          _isLiked = postDetail.isLikedByUser;
          _isSaved = postDetail.isSavedByUser;
        });
      }
    });
    setState(() {});
  }

  String _formatDate(String dateString) {
    try {
      final dateTime = DateTime.parse(dateString);
      return DateFormat('dd MMMM yyyy', 'tr_TR').format(dateTime);
    } catch (e) {
      return dateString.split(' ')[0];
    }
  }

  void _showSnackbar(String message, {bool isError = false}) {
    if (!mounted) return;
    final theme = Theme.of(context);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: isError ? theme.colorScheme.error : Colors.green.shade600,
      behavior: SnackBarBehavior.floating,
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: FutureBuilder<PostDetail>(
        future: _postDetailFuture,
        builder: (context, snapshot) {
          _lastSnapshot = snapshot;

          if (snapshot.hasError) {
            return _buildErrorState(context);
          }

          if (snapshot.connectionState == ConnectionState.waiting || snapshot.hasData) {
            if (!snapshot.hasData) {
              return Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor));
            }

            final postDetail = snapshot.data!;
            return _buildMainContent(context, postDetail);
          }

          return _buildNotFound(context);
        },
      ),
    );
  }

  Widget _buildMainContent(BuildContext context, PostDetail postDetail) {
    return Stack(
      children: [
        GestureDetector(
          onTap: _toggleBottomSheet,
          child: CustomScrollView(
            slivers: [
              _buildSliverAppBar(context, postDetail),
              SliverToBoxAdapter(
                child: Column(
                  children: [
                    _buildPostHeader(context, postDetail),
                    _buildPostContent(context, postDetail),
                    Divider(height: 32, thickness: 8, color: Theme.of(context).scaffoldBackgroundColor),
                    _buildCommentSection(postDetail),
                    const SizedBox(height: 120),
                  ],
                ),
              ),
            ],
          ),
        ),
        _buildInteractionModal(context),
      ],
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
              'Yazı yüklenemedi.\nLütfen internet bağlantınızı kontrol edin.',
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
              onPressed: _loadData,
            )
          ],
        ),
      ),
    );
  }

  SliverAppBar _buildSliverAppBar(BuildContext context, PostDetail postDetail) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    return SliverAppBar(
      expandedHeight: 320.0,
      pinned: true,
      stretch: true,
      backgroundColor: colorScheme.surface,
      foregroundColor: colorScheme.onSurface,
      elevation: 0,
      leading: Container(
        margin: const EdgeInsets.all(8),
        decoration: BoxDecoration(color: colorScheme.scrim.withOpacity(0.5), shape: BoxShape.circle),
        child: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Navigator.pop(context), color: Colors.white),
      ),
      flexibleSpace: FlexibleSpaceBar(
        stretchModes: const [StretchMode.zoomBackground],
        background: Stack(
          fit: StackFit.expand,
          children: [
            if (postDetail.imageUrl != null)
              CachedNetworkImage(
                imageUrl: postDetail.getImageUrl(width: 800) ?? '',
                fit: BoxFit.cover,
                placeholder: (ctx, url) => Container(color: colorScheme.surfaceVariant),
                errorWidget: (ctx, url, err) => Container(decoration: BoxDecoration(gradient: LinearGradient(colors: [colorScheme.primary.withOpacity(0.3), colorScheme.primary.withOpacity(0.8)]))),
              ),
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, colorScheme.scrim],
                  stops: const [0.5, 1.0],
                ),
              ),
            ),
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 40, 20, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (postDetail.categoryName != null)
                      Chip(
                        label: Text(postDetail.categoryName!),
                        backgroundColor: colorScheme.surface.withOpacity(0.9),
                        labelStyle: theme.textTheme.labelMedium?.copyWith(color: theme.primaryColor, fontWeight: FontWeight.bold),
                      ),
                    const SizedBox(height: 12),
                    Text(
                      postDetail.title,
                      style: theme.textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.bold, height: 1.2),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPostHeader(BuildContext context, PostDetail postDetail) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: theme.primaryColor,
            child: Text("B", style: TextStyle(color: theme.colorScheme.onPrimary, fontWeight: FontWeight.bold, fontSize: 18)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text("Blogium Ekibi", style: theme.textTheme.titleMedium),
                const SizedBox(height: 2),
                Text(_formatDate(postDetail.createdAt), style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPostContent(BuildContext context, PostDetail postDetail) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Html(
        data: postDetail.content,
        style: {
          "p": Style.fromTextStyle(theme.textTheme.bodyLarge!),
          "h1,h2,h3,h4,h5,h6": Style.fromTextStyle(theme.textTheme.titleLarge!.copyWith(fontWeight: FontWeight.bold)),
          "a": Style(color: theme.primaryColor, textDecoration: TextDecoration.none, fontWeight: FontWeight.bold),
          "li": Style.fromTextStyle(theme.textTheme.bodyLarge!),
        },
      ),
    );
  }

  Widget _buildCommentSection(PostDetail postDetail) {
    return Container(
      key: _commentSectionKey,
      padding: const EdgeInsets.all(20),
      child: CommentSection(
        postId: widget.postId,
        allowComments: postDetail.allowComments,
      ),
    );
  }

  Widget _buildInteractionModal(BuildContext context) {
    final auth = Provider.of<AuthService>(context, listen: false);
    final connectivity = Provider.of<ConnectivityService>(context);
    final theme = Theme.of(context);

    return Positioned(
      bottom: 0,
      left: 0,
      right: 0,
      child: SlideTransition(
        position: _offsetAnimation,
        child: Container(
          margin: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          decoration: BoxDecoration(
            color: theme.scaffoldBackgroundColor,
            borderRadius: BorderRadius.circular(24.0),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 20, offset: const Offset(0, -5))],
            border: Border.all(color: Colors.grey.shade300, width: 1),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  IconButton(
                    iconSize: 28,
                    onPressed: () async {
                      if (!connectivity.isConnected) {
                        _showSnackbar("Bu işlem için internet bağlantısı gerekli.", isError: true);
                        return;
                      }
                      if (!auth.isLoggedIn) {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
                        return;
                      }
                      setState(() => _isLiked = !_isLiked);
                      try {
                        await ApiService.toggleLike(auth.token!, widget.postId);
                      } catch (e) {
                        setState(() => _isLiked = !_isLiked);
                        _showSnackbar("İşlem yapılamadı, lütfen tekrar deneyin.", isError: true);
                      }
                    },
                    icon: Icon(_isLiked ? Icons.favorite : Icons.favorite_border),
                    color: _isLiked ? Colors.pink : theme.colorScheme.onSurface,
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    iconSize: 28,
                    onPressed: () async {
                      if (!connectivity.isConnected) {
                        _showSnackbar("Bu işlem için internet bağlantısı gerekli.", isError: true);
                        return;
                      }
                      if (!auth.isLoggedIn) {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
                        return;
                      }
                      setState(() => _isSaved = !_isSaved);
                      try {
                        await ApiService.toggleSave(auth.token!, widget.postId);
                      } catch (e) {
                        setState(() => _isSaved = !_isSaved);
                        _showSnackbar("İşlem yapılamadı, lütfen tekrar deneyin.", isError: true);
                      }
                    },
                    icon: Icon(_isSaved ? Icons.bookmark : Icons.bookmark_border),
                    color: _isSaved ? theme.primaryColor : theme.colorScheme.onSurface,
                  ),
                ],
              ),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16.0)),
                ),
                icon: const Icon(Icons.comment_outlined, size: 18),
                label: const Text('Yorumlar'),
                onPressed: () {
                  final currentContext = _commentSectionKey.currentContext;
                  if (currentContext != null) {
                    Scrollable.ensureVisible(currentContext, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNotFound(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.article_outlined, size: 64, color: theme.colorScheme.onSurface.withOpacity(0.5)),
          const SizedBox(height: 16),
          Text('Gönderi bulunamadı.', style: theme.textTheme.titleMedium),
        ],
      ),
    );
  }
}