import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://360.natex.com.tr/api/v1/mobile',
);

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.fields = const {}});

  final String message;
  final int? statusCode;
  final Map<String, String> fields;

  @override
  String toString() => message;
}

class SecureSessionStore {
  static const _tokenKey = 'mobile_access_token';
  static const _userKey = 'mobile_user';
  static const _idleKey = 'mobile_idle_timeout';

  const SecureSessionStore();

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  Future<MobileSession?> read() async {
    final token = await _storage.read(key: _tokenKey);
    final user = await _storage.read(key: _userKey);
    if (token == null || user == null) return null;
    try {
      return MobileSession(
        token: token,
        user: Map<String, dynamic>.from(jsonDecode(user) as Map),
        idleMinutes:
            int.tryParse(await _storage.read(key: _idleKey) ?? '') ?? 15,
      );
    } catch (_) {
      await clear();
      return null;
    }
  }

  Future<void> write(MobileSession session) async {
    await _storage.write(key: _tokenKey, value: session.token);
    await _storage.write(key: _userKey, value: jsonEncode(session.user));
    await _storage.write(key: _idleKey, value: session.idleMinutes.toString());
  }

  Future<void> clear() => _storage.deleteAll();
}

class MobileSession {
  const MobileSession({
    required this.token,
    required this.user,
    required this.idleMinutes,
  });

  final String token;
  final Map<String, dynamic> user;
  final int idleMinutes;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;
  String? token;

  Future<Map<String, dynamic>> get(String path, {bool authenticated = true}) =>
      _request('GET', path, authenticated: authenticated);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) => _request('POST', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) => _request('PUT', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> patch(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) => _request('PATCH', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) => _request('DELETE', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) async {
    final uri = Uri.parse('$apiBaseUrl$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json; charset=utf-8',
    };
    if (authenticated && token != null) {
      headers['Authorization'] = 'Bearer $token';
    }
    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) request.body = jsonEncode(body);
      final streamed = await _client
          .send(request)
          .timeout(const Duration(seconds: 25));
      final response = await http.Response.fromStream(streamed);
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body);
      final payload = decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : <String, dynamic>{};
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final rawErrors = payload['errors'];
        final fields = <String, String>{};
        if (rawErrors is Map) {
          rawErrors.forEach((key, value) {
            if (value is List && value.isNotEmpty) {
              fields['$key'] = '${value.first}';
            }
          });
        }
        throw ApiException(
          fields.values.firstOrNull ??
              payload['message']?.toString() ??
              'İşlem tamamlanamadı.',
          statusCode: response.statusCode,
          fields: fields,
        );
      }
      return payload;
    } on ApiException {
      rethrow;
    } catch (_) {
      throw const ApiException(
        'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.',
      );
    }
  }

  void close() => _client.close();
}

extension FirstOrNullExtension<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
