// lib/services/connectivity_service.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

class ConnectivityService with ChangeNotifier {
  final Connectivity _connectivity = Connectivity();
  late StreamSubscription<ConnectivityResult> _subscription;

  // --- YENİ BÖLÜM: DIŞARIYA YAYIN YAPMAK İÇİN STREAMCONTROLLER ---
  // Bu StreamController, bağlantı durumu değiştiğinde bir "event" yayınlayacak.
  final StreamController<bool> _connectionChangeController = StreamController.broadcast();

  // Dışarıdan bu stream'e abone olunabilecek.
  Stream<bool> get onConnectivityChanged => _connectionChangeController.stream;
  // --- YENİ BÖLÜM SONU ---

  bool _isConnected = true;
  bool get isConnected => _isConnected;

  ConnectivityService() {
    _checkInitialConnectivity();
    _subscription = _connectivity.onConnectivityChanged.listen(_updateConnectionStatus);
  }

  Future<void> _checkInitialConnectivity() async {
    ConnectivityResult result = await _connectivity.checkConnectivity();
    _updateConnectionStatus(result);
  }

  void _updateConnectionStatus(ConnectivityResult result) {
    bool currentlyConnected = result != ConnectivityResult.none;
    if (_isConnected != currentlyConnected) {
      _isConnected = currentlyConnected;

      // Hem Provider dinleyicilerini bilgilendir...
      notifyListeners();

      // --- YENİ: HEM DE STREAM'E YENİ DURUMU GÖNDER ---
      _connectionChangeController.add(_isConnected);
    }
  }

  @override
  void dispose() {
    _subscription.cancel();
    _connectionChangeController.close(); // Controller'ı temizlemeyi unutma
    super.dispose();
  }
}