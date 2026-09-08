import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'config.dart';
import '../models/user.dart';
import 'api_client.dart';

class AuthService extends ChangeNotifier {
  static const _kToken = 'fn_token';
  static const _kUser = 'fn_user';
  final _store = const FlutterSecureStorage();

  String? _token;
  AppUser? _user;
  bool _booting = true;
  static Future<void>? _googleInitialization;
  StreamSubscription<GoogleSignInAuthenticationEvent>? _googleSubscription;
  String? _googleError;

  String? get token => _token;
  AppUser? get user => _user;
  bool get isBooting => _booting;
  bool get isLoggedIn => _token != null && _user != null;
  String? get googleError => _googleError;

  late final ApiClient api = ApiClient(() => _token, onUnauthorized: logout);

  Future<void> boot() async {
    _token = await _store.read(key: _kToken);
    final raw = await _store.read(key: _kUser);
    if (raw != null) {
      try {
        _user = AppUser.fromJson(jsonDecode(raw));
      } catch (_) {}
    }
    _booting = false;
    notifyListeners();
    if (_token != null) {
      // refresh profile silently
      unawaited(_refreshMe());
    }
  }

  Future<void> _refreshMe() async {
    try {
      final r = await api.get('/me');
      _user = AppUser.fromJson(r['user']);
      await _store.write(key: _kUser, value: jsonEncode(r['user']));
      notifyListeners();
    } catch (_) {}
  }

  Future<void> login(String email, String password) async {
    final r = await api.post('/auth/login', {
      'email': email.trim(),
      'password': password,
    });
    await _saveSession(r);
  }

  Future<void> loginWithGoogle() async {
    final google = GoogleSignIn.instance;
    await initializeGoogle();
    if (!google.supportsAuthenticate()) {
      throw ApiException('Use the Google sign-in button shown above.');
    }

    GoogleSignInAccount account;
    try {
      account = await google.authenticate();
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) {
        throw ApiException(
          'Google sign-in was cancelled or needs reauthentication. Choose your account and try again.',
        );
      }
      throw ApiException('Google sign-in could not be completed. Please try again.');
    }
    final idToken = account.authentication.idToken;
    if (idToken == null || idToken.isEmpty) {
      throw ApiException('Google did not return a sign-in token.');
    }
    await _loginWithGoogleToken(idToken);
  }

  Future<void> initializeGoogle() async {
    final google = GoogleSignIn.instance;
    await (_googleInitialization ??= google.initialize(
      clientId: kIsWeb ? AppConfig.googleServerClientId : null,
      serverClientId: kIsWeb ? null : AppConfig.googleServerClientId,
    ));
    if (kIsWeb && _googleSubscription == null) {
      _googleSubscription = google.authenticationEvents.listen(
        (event) {
          if (event is GoogleSignInAuthenticationEventSignIn) {
            final idToken = event.user.authentication.idToken;
            if (idToken != null && idToken.isNotEmpty) {
              unawaited(_handleWebGoogleSignIn(idToken));
            }
          }
        },
        onError: (Object error, StackTrace stackTrace) {
          _googleError = 'Google sign-in failed: $error';
          notifyListeners();
        },
      );
    }
  }

  Future<void> _handleWebGoogleSignIn(String idToken) async {
    try {
      await _loginWithGoogleToken(idToken);
    } catch (error) {
      _googleError = '$error';
      notifyListeners();
    }
  }

  Future<void> _loginWithGoogleToken(String idToken) async {
    _googleError = null;
    final r = await api.post('/auth/google', {'id_token': idToken});
    await _saveSession(r);
  }

  Future<void> _saveSession(dynamic response) async {
    _token = response['token'] as String;
    _user = AppUser.fromJson(response['user']);
    await _store.write(key: _kToken, value: _token);
    await _store.write(key: _kUser, value: jsonEncode(response['user']));
    notifyListeners();
  }

  Future<void> updateProfile(Map<String, dynamic> data) async {
    final r = await api.put('/me', data);
    _user = AppUser.fromJson(r['user']);
    await _store.write(key: _kUser, value: jsonEncode(r['user']));
    notifyListeners();
  }

  Future<void> logout() async {
    _token = null;
    _user = null;
    await _store.deleteAll();
    notifyListeners();
  }

  @override
  void dispose() {
    unawaited(_googleSubscription?.cancel());
    super.dispose();
  }
}
