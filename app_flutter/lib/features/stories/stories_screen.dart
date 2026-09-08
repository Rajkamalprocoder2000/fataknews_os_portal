import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class StoriesScreen extends StatelessWidget {
  const StoriesScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();

    Future<void> add() async {
      final caption = TextEditingController();
      final color = TextEditingController(text: '#2D2244');
      final ok = await showFormSheet<bool>(context, 'New story', Column(children: [
        TextField(controller: caption, decoration: fieldDeco('Story text'), maxLines: 3),
        const SizedBox(height: 10),
        TextField(controller: color, decoration: fieldDeco('Background color')),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: () async {
            await auth.api.post('/stories', {
              'caption': caption.text.trim(),
              'background_color': color.text.trim(),
            });
            if (context.mounted) Navigator.pop(context, true);
          },
          child: const Text('Publish (24h)'),
        ),
      ]));
      if (ok == true) key.currentState?.reload();
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Stories')),
      floatingActionButton: FloatingActionButton(
        backgroundColor: FnColors.red,
        onPressed: add,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/stories'))['groups'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) {
          if (rows.isEmpty) return const EmptyState(text: 'No active stories');
          return ListView.separated(
            itemCount: rows.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final g = rows[i];
              return ListTile(
                leading: CircleAvatar(backgroundImage: NetworkImage(g['avatar'] ?? '')),
                title: Text('${g['full_name']}'),
                subtitle: Text('${g['story_count']} active'),
              );
            },
          );
        },
      ),
    );
  }
}
