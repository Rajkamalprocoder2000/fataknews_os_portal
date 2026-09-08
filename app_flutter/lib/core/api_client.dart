import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'config.dart';

class ApiException implements Exception {
  final String message;
  final int status;
  ApiException(this.message, [this.status = 0]);
  @override
  String toString() => message;
}

/// Thin JSON client for /api/mobile. Injects the bearer token and
/// surfaces `{success:false,error:...}` payloads as [ApiException].
class ApiClient {
  ApiClient(this._tokenProvider, {this.onUnauthorized});

  final String? Function() _tokenProvider;
  final VoidCallback? onUnauthorized;
  final _http = http.Client();

  Map<String, String> _headers([bool json = true]) {
    final t = _tokenProvider();
    return {
      if (json) 'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (t != null && t.isNotEmpty) 'Authorization': 'Bearer $t',
    };
  }

  Future<dynamic> get(String path, [Map<String, dynamic>? query]) =>
      _send('GET', path, query: query);

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) =>
      _send('POST', path, body: body);

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) =>
      _send('PUT', path, body: body);

  Future<dynamic> delete(String path) => _send('DELETE', path);

  Future<dynamic> _send(String method, String path,
      {Map<String, dynamic>? query, Map<String, dynamic>? body}) async {
    final uri = AppConfig.api(path, query);
    late http.Response res;
    try {
      final req = http.Request(method, uri)..headers.addAll(_headers());
      if (body != null) req.body = jsonEncode(body);
      final streamed = await _http.send(req).timeout(const Duration(seconds: 30));
      res = await http.Response.fromStream(streamed);
    } on SocketException {
      throw ApiException('No internet connection');
    } catch (e) {
      throw ApiException('Network error: $e');
    }
    return _parse(res);
  }

  /// Multipart upload. [field] is the form field name, defaults to `file`.
  Future<dynamic> upload(String path, File file,
      {String field = 'file', Map<String, String>? fields}) async {
    final uri = AppConfig.api(path);
    final req = http.MultipartRequest('POST', uri)
      ..headers.addAll(_headers(false))
      ..fields.addAll(fields ?? const {})
      ..files.add(await http.MultipartFile.fromPath(field, file.path));
    final streamed = await req.send().timeout(const Duration(minutes: 3));
    final res = await http.Response.fromStream(streamed);
    return _parse(res);
  }

  dynamic _parse(http.Response res) {
    if (res.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException('Session expired. Please sign in again.', 401);
    }
    dynamic data;
    try {
      data = res.body.isEmpty ? null : jsonDecode(res.body);
    } catch (_) {
      throw ApiException('Bad response from server (${res.statusCode})', res.statusCode);
    }
    if (data is Map && data['success'] == false) {
      throw ApiException(data['error']?.toString() ?? 'Request failed', res.statusCode);
    }
    if (res.statusCode >= 400) {
      throw ApiException('Request failed (${res.statusCode})', res.statusCode);
    }
    return data;
  }
}
