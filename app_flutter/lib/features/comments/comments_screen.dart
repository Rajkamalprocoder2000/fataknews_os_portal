import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class CommentsScreen extends StatelessWidget {
  const CommentsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();
    return Scaffold(
      appBar: AppBar(title: const Text('Comments')),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/comments'))['data'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) {
          if (rows.isEmpty) return const EmptyState(text: 'No comments');
          return ListView.separated(
            itemCount: rows.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final c = rows[i];
              return ListTile(
                title: Text('${c['content']}'),
                subtitle: Text('${c['full_name']} on "${c['post_title']}"',
                    style: const TextStyle(fontSize: 12, color: FnColors.gray)),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () async {
                    await auth.api.delete('/comments/${c['id']}');
                    reload();
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }
}
