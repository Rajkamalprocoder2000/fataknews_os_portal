import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              await auth.api.post('/notifications/read-all');
              key.currentState?.reload();
            },
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/notifications'))['notifications'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) {
          if (rows.isEmpty) return const EmptyState(text: 'No notifications', icon: Icons.notifications_none);
          return RefreshIndicator(
            onRefresh: () async => reload(),
            child: ListView.separated(
              itemCount: rows.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, i) {
                final n = rows[i];
                final unread = n['is_read'] == 0 || n['is_read'] == false;
                return ListTile(
                  leading: Icon(Icons.circle, size: 10, color: unread ? FnColors.red : Colors.transparent),
                  title: Text('${n['message']}'),
                  subtitle: Text('${n['type']} · ${n['created_at']}', style: const TextStyle(fontSize: 11)),
                  onTap: () async {
                    await auth.api.post('/notifications/read', {'id': n['id']});
                    reload();
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}
