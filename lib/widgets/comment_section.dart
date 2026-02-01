// lib/widgets/comment_section.dart

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/comment_model.dart';
import '../screens/login_screen.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/connectivity_service.dart'; // YENİ IMPORT
import 'package:intl/intl.dart';

class CommentSection extends StatefulWidget {
  final int postId;
  final String allowComments;

  const CommentSection({super.key, required this.postId, required this.allowComments});

  @override
  State<CommentSection> createState() => _CommentSectionState();
}

class _CommentSectionState extends State<CommentSection> {
  late Future<List<Comment>> _commentsFuture;
  final _commentController = TextEditingController();
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _loadComments();
  }

  void _loadComments() {
    setState(() {
      _commentsFuture = ApiService.getComments(widget.postId);
    });
  }

  Future<void> _submitComment(AuthService auth) async {
    // İnternet kontrolü
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);
    if (!connectivityService.isConnected) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('Yorum göndermek için internet bağlantısı gerekli.'),
        backgroundColor: Theme.of(context).colorScheme.error,
      ));
      return;
    }

    if (_commentController.text.trim().isEmpty || !auth.isLoggedIn) return;
    setState(() => _isSending = true);
    try {
      final responseMessage = await ApiService.addComment(auth.token!, widget.postId, _commentController.text.trim());
      _commentController.clear();
      FocusScope.of(context).unfocus();
      _loadComments();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(responseMessage),
        backgroundColor: Colors.green.shade600,
      ));
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString().replaceAll("Exception: ", "")), backgroundColor: Colors.red.shade600));
    }
    if (mounted) {
      setState(() => _isSending = false);
    }
  }

  // _formatDate ve diğer build metodları aynı kalacak...
  String _formatDate(String dateString) {
    try {
      final dateTime = DateTime.parse(dateString);
      return DateFormat('dd MMMM yyyy', 'tr_TR').format(dateTime);
    } catch (e) {
      return dateString.split(' ')[0];
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FutureBuilder<List<Comment>>(
          future: _commentsFuture,
          builder: (context, snapshot) {
            final count = snapshot.hasData ? snapshot.data!.length : 0;
            return Text('Yorumlar ($count)', style: theme.textTheme.titleLarge);
          },
        ),
        const SizedBox(height: 20),
        if (widget.allowComments == 'no')
          _buildDisabledCommentsMessage(context)
        else
          _buildCommentInputSection(context),
        const SizedBox(height: 24),
        FutureBuilder<List<Comment>>(
          future: _commentsFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return Center(child: CircularProgressIndicator(color: theme.primaryColor));
            }
            if (snapshot.hasError) {
              return Center(child: Text('Yorumlar yüklenemedi.', style: theme.textTheme.bodyMedium));
            }
            if (snapshot.hasData && snapshot.data!.isNotEmpty) {
              return _buildCommentsList(context, snapshot.data!);
            } else {
              return widget.allowComments != 'no' ? _buildNoCommentsMessage(context) : const SizedBox.shrink();
            }
          },
        ),
      ],
    );
  }

  // --- YORUM GÖNDER BUTONU GÜNCELLENDİ ---
  Widget _buildCommentInputSection(BuildContext context) {
    final auth = Provider.of<AuthService>(context);
    final theme = Theme.of(context);
    final connectivity = Provider.of<ConnectivityService>(context); // Bağlantıyı dinle

    if (!auth.isLoggedIn) {
      return Center(
        child: ElevatedButton.icon(
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen())),
          icon: const Icon(Icons.login, size: 18),
          label: const Text('Yorum Yapmak İçin Giriş Yapın'),
        ),
      );
    }

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(backgroundColor: theme.primaryColor.withOpacity(0.1), child: Icon(Icons.person, color: theme.primaryColor)),
                const SizedBox(width: 12),
                Text('${auth.username} olarak yorum yap', style: theme.textTheme.titleSmall),
              ],
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _commentController,
              decoration: InputDecoration(
                hintText: 'Düşüncelerinizi paylaşın...',
              ),
              maxLines: 4,
              minLines: 2,
            ),
            const SizedBox(height: 16),
            Align(
              alignment: Alignment.centerRight,
              child: _isSending
                  ? CircularProgressIndicator(color: theme.primaryColor)
                  : ElevatedButton(
                // İnternet yoksa butonu devre dışı bırak
                onPressed: connectivity.isConnected ? () => _submitComment(auth) : null,
                child: Text(connectivity.isConnected ? 'Yorumu Gönder' : 'İnternet Yok'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Geri kalan tüm _build... metodları aynı
  Widget _buildDisabledCommentsMessage(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(24.0),
      decoration: BoxDecoration(color: theme.colorScheme.surfaceVariant.withOpacity(0.5), borderRadius: BorderRadius.circular(12.0)),
      child: Center(child: Text("Bu gönderi yorumlara kapalıdır.", style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant))),
    );
  }

  Widget _buildCommentsList(BuildContext context, List<Comment> comments) {
    final theme = Theme.of(context);
    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: comments.length,
      separatorBuilder: (ctx, i) => Divider(color: theme.dividerColor.withOpacity(0.5), height: 32),
      itemBuilder: (ctx, i) {
        final comment = comments[i];
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(backgroundColor: theme.primaryColor, child: Text(comment.author.substring(0, 1).toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: Text(comment.author, style: theme.textTheme.titleSmall)), const SizedBox(width: 8), Text(_formatDate(comment.createdAt), style: theme.textTheme.bodySmall)]), const SizedBox(height: 6), Text(comment.content, style: theme.textTheme.bodyMedium)])),
          ],
        );
      },
    );
  }

  Widget _buildNoCommentsMessage(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 24.0),
        child: Column(
          children: [
            Icon(Icons.forum_outlined, size: 50, color: theme.colorScheme.onSurface.withOpacity(0.5)),
            const SizedBox(height: 16),
            Text('Henüz yorum yapılmamış.\nİlk yorumu siz yapın!', textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
          ],
        ),
      ),
    );
  }
}