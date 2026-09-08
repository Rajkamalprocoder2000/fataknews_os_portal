import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class CategoriesScreen extends StatelessWidget {
  const CategoriesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();
    final admin = auth.user!.isAdmin;

    Future<void> edit([Map<String, dynamic>? cat]) async {
      final name = TextEditingController(text: cat?['name'] ?? '');
      final desc = TextEditingController(text: cat?['description'] ?? '');
      final color = TextEditingController(text: cat?['color'] ?? '#2979FF');
      final ok = await showFormSheet<bool>(context, cat == null ? 'New category' : 'Edit category',
          Column(children: [
            TextField(controller: name, decoration: fieldDeco('Name')),
            const SizedBox(height: 10),
            TextField(controller: desc, decoration: fieldDeco('Description')),
            const SizedBox(height: 10),
            TextField(controller: color, decoration: fieldDeco('Color hex')),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: () async {
                final payload = {'name': name.text.trim(), 'description': desc.text.trim(), 'color': color.text.trim()};
                if (cat == null) {
                  await auth.api.post('/categories', payload);
                } else {
                  await auth.api.put('/categories/${cat['id']}', payload);
                }
                if (context.mounted) Navigator.pop(context, true);
              },
              child: const Text('Save'),
            ),
          ]));
      if (ok == true) key.currentState?.reload();
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Categories')),
      floatingActionButton: FloatingActionButton(
        backgroundColor: FnColors.red,
        onPressed: () => edit(),
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/categories'))['categories'] as List).cast<Map<String, dynamic>>(),
        builder: (context, cats, reload) => ListView.separated(
          itemCount: cats.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (_, i) {
            final c = cats[i];
            final isChild = c['parent_id'] != null;
            return ListTile(
              contentPadding: EdgeInsets.only(left: isChild ? 32 : 16, right: 8),
              leading: CircleAvatar(
                radius: 10,
                backgroundColor: _hex(c['color']),
              ),
              title: Text('${c['name']}'),
              subtitle: c['description'] != null && '${c['description']}'.isNotEmpty
                  ? Text('${c['description']}', maxLines: 1, overflow: TextOverflow.ellipsis)
                  : null,
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => edit(c)),
                if (admin)
                  IconButton(
                    icon: const Icon(Icons.delete_outline, size: 20),
                    onPressed: () async {
                      await auth.api.delete('/categories/${c['id']}');
                      reload();
                    },
                  ),
              ]),
            );
          },
        ),
      ),
    );
  }

  Color _hex(dynamic v) {
    try {
      return Color(int.parse('FF${'$v'.replaceAll('#', '')}', radix: 16));
    } catch (_) {
      return FnColors.gray;
    }
  }
}
