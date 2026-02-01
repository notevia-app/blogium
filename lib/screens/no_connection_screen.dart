// lib/screens/no_connection_screen.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/connectivity_service.dart';

class NoConnectionScreen extends StatefulWidget {
  final VoidCallback onConnectionRestored;

  const NoConnectionScreen({
    super.key,
    required this.onConnectionRestored,
  });

  @override
  State<NoConnectionScreen> createState() => _NoConnectionScreenState();
}

class _NoConnectionScreenState extends State<NoConnectionScreen> {
  // late StreamSubscription _connectivitySubscription; // Bu satır artık StreamSubscription<bool> olacak
  late StreamSubscription<bool> _connectivitySubscription;

  @override
  void initState() {
    super.initState();
    final connectivityService = Provider.of<ConnectivityService>(context, listen: false);

    // Artık 'onConnectivityChanged' stream'i mevcut olduğu için bu kod doğru çalışacak.
    _connectivitySubscription = connectivityService.onConnectivityChanged.listen((isConnected) {
      if (isConnected) {
        // Bu ekranın hala widget ağacında olup olmadığını kontrol et
        if (mounted) {
          Navigator.of(context).pop();
          widget.onConnectionRestored();
        }
      }
    });
  }

  @override
  void dispose() {
    _connectivitySubscription.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.wifi_off_rounded,
                size: 80,
                color: Colors.grey.shade400,
              ),
              const SizedBox(height: 24),
              Text(
                'İnternet Bağlantısı Yok',
                style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              Text(
                'Lütfen internet bağlantınızı kontrol edin. Bağlantı kurulduğunda otomatik olarak devam edeceksiniz.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge,
              ),
            ],
          ),
        ),
      ),
    );
  }
}