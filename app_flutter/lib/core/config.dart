class AppConfig {
  /// Base URL of the FatakNews backend. Override with:
  ///   flutter run --dart-define=API_BASE=https://staging.fataknews.in
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://fataknews.in',
  );

  /// OAuth web client ID used as the server audience for native Google sign-in.
  /// Override it for another environment with GOOGLE_SERVER_CLIENT_ID.
  static const String googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '669477689668-gct3jb464e6b9fd1f116g4nrcr76pnq1.apps.googleusercontent.com',
  );

  static Uri api(String path, [Map<String, dynamic>? query]) {
    final clean = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$apiBase/api/mobile$clean').replace(
      queryParameters: query?.map((k, v) => MapEntry(k, '$v')),
    );
  }
}
