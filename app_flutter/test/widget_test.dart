import 'package:flutter_test/flutter_test.dart';
import 'package:fataknews_staff/core/config.dart';

void main() {
  test('builds mobile API URI without duplicate separators', () {
    final uri = AppConfig.api('/posts', {'page': 2});

    expect(uri.toString(), 'https://fataknews.in/api/mobile/posts?page=2');
  });
}
