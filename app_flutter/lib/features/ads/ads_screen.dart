import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class AdsScreen extends StatelessWidget {
  const AdsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();

    Future<void> edit([Map<String, dynamic>? ad]) async {
      final name = TextEditingController(text: ad?['name'] ?? '');
      final placement = TextEditingController(text: ad?['placement'] ?? 'sidebar');
      final image = TextEditingController(text: ad?['image_url'] ?? '');
      final target = TextEditingController(text: ad?['target_url'] ?? '');
      final html = TextEditingController(text: ad?['html_code'] ?? '');
      final ok = await showFormSheet<bool>(context, ad == null ? 'New ad' : 'Edit ad', Column(children: [
        TextField(controller: name, decoration: fieldDeco('Name')),
        const SizedBox(height: 10),
        TextField(controller: placement, decoration: fieldDeco('Placement (sidebar / header / in-feed)')),
        const SizedBox(height: 10),
        TextField(controller: image, decoration: fieldDeco('Image URL')),
        const SizedBox(height: 10),
        TextField(controller: target, decoration: fieldDeco('Target URL')),
        const SizedBox(height: 10),
        TextField(controller: html, decoration: fieldDeco('Or ad HTML/script'), maxLines: 3),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: () async {
            final payload = {
              'name': name.text.trim(),
              'placement': placement.text.trim(),
              'image_url': image.text.trim(),
              'target_url': target.text.trim(),
              'html_code': html.text,
            };
            if (ad == null) {
              await auth.api.post('/ads', payload);
            } else {
              await auth.api.put('/ads/${ad['id']}', payload);
            }
            if (context.mounted) Navigator.pop(context, true);
          },
          child: const Text('Save'),
        ),
      ]));
      if (ok == true) key.currentState?.reload();
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Ads')),
      floatingActionButton: FloatingActionButton(
        backgroundColor: FnColors.red,
        onPressed: () => edit(),
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/ads'))['ads'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) {
          if (rows.isEmpty) return const EmptyState(text: 'No ads');
          return ListView.separated(
            itemCount: rows.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final a = rows[i];
              return ListTile(
                title: Text('${a['name']}'),
                subtitle: Text('${a['placement']}'),
                trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                  IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => edit(a)),
                  IconButton(
                    icon: const Icon(Icons.delete_outline, size: 20),
                    onPressed: () async {
                      await auth.api.delete('/ads/${a['id']}');
                      reload();
                    },
                  ),
                ]),
              );
            },
          );
        },
      ),
    );
  }
}
