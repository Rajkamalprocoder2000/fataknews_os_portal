import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/widgets.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  static const _labels = {
    'my_posts': 'My posts',
    'published': 'Published',
    'pending': 'Pending review',
    'drafts': 'Drafts',
    'total_views': 'Total views',
    'total_users': 'Users',
    'today_posts': 'Posts today',
    'breaking': 'Breaking live',
    'comments': 'Comments',
    'unread_notifications': 'Unread alerts',
  };

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final u = auth.user!;
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Hi, ${u.fullName.split(' ').first}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            Text(u.roleName, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w400, color: Color(0xFF64748B))),
          ],
        ),
      ),
      body: AsyncView<Map<String, dynamic>>(
        load: () async => (await auth.api.get('/dashboard')) as Map<String, dynamic>,
        builder: (context, data, reload) {
          final stats = (data['stats'] as Map).cast<String, dynamic>();
          final entries = stats.entries.where((e) => e.value is num).toList();
          return RefreshIndicator(
            onRefresh: () async => reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.7,
                  children: [
                    for (final e in entries)
                      StatCard(_labels[e.key] ?? e.key, '${e.value}'),
                  ],
                ),
                if (stats['hr'] is Map && (stats['hr'] as Map).isNotEmpty) ...[
                  const SizedBox(height: 20),
                  const Text('HR', style: TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        children: [
                          for (final e in (stats['hr'] as Map).entries)
                            ListTile(
                              dense: true,
                              contentPadding: EdgeInsets.zero,
                              title: Text('${e.key}'.replaceAll('_', ' ')),
                              trailing: Text('${e.value}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            ),
                        ],
                      ),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}
