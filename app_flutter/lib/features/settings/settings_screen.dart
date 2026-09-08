import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/widgets.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return Scaffold(
      appBar: AppBar(title: const Text('Site Settings')),
      body: AsyncView<Map<String, dynamic>>(
        load: () async => ((await auth.api.get('/settings'))['settings'] as Map).cast<String, dynamic>(),
        builder: (context, settings, reload) {
          final ctrls = {for (final e in settings.entries) e.key: TextEditingController(text: '${e.value}')};
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              for (final e in ctrls.entries) ...[
                TextField(controller: e.value, decoration: fieldDeco(e.key)),
                const SizedBox(height: 12),
              ],
              FilledButton(
                onPressed: () async {
                  try {
                    await auth.api.put('/settings',
                        {'settings': {for (final e in ctrls.entries) e.key: e.value.text}});
                    if (context.mounted) toast(context, 'Saved');
                  } catch (e) {
                    if (context.mounted) toast(context, '$e', error: true);
                  }
                },
                child: const Text('Save settings'),
              ),
            ],
          );
        },
      ),
    );
  }
}
