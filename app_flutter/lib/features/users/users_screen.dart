import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class UsersScreen extends StatefulWidget {
  const UsersScreen({super.key});
  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  String _q = '';
  List<Map<String, dynamic>> _roles = [];

  Future<Map<String, dynamic>> _load(AuthService auth) async {
    if (_roles.isEmpty) {
      final r = await auth.api.get('/roles');
      _roles = (r['roles'] as List).cast<Map<String, dynamic>>();
    }
    return (await auth.api.get('/users', {if (_q.isNotEmpty) 'q': _q})) as Map<String, dynamic>;
  }

  Future<void> _edit(AuthService auth, [Map<String, dynamic>? u]) async {
    final name = TextEditingController(text: u?['full_name'] ?? '');
    final username = TextEditingController(text: u?['username'] ?? '');
    final email = TextEditingController(text: u?['email'] ?? '');
    final pass = TextEditingController();
    String? roleName = u?['role_name'];
    int? roleId = _roles.firstWhere((r) => r['name'] == roleName, orElse: () => {'id': null})['id'];
    final ok = await showFormSheet<bool>(context, u == null ? 'New user' : 'Edit user',
        StatefulBuilder(builder: (ctx, setSheet) {
      return Column(children: [
        TextField(controller: name, decoration: fieldDeco('Full name')),
        const SizedBox(height: 10),
        TextField(controller: username, decoration: fieldDeco('Username')),
        const SizedBox(height: 10),
        TextField(controller: email, decoration: fieldDeco('Email')),
        const SizedBox(height: 10),
        TextField(controller: pass, decoration: fieldDeco(u == null ? 'Password' : 'New password (optional)')),
        const SizedBox(height: 10),
        DropdownButtonFormField<int>(
          value: roleId,
          decoration: fieldDeco('Role'),
          items: [for (final r in _roles) DropdownMenuItem(value: r['id'] as int, child: Text('${r['name']}'))],
          onChanged: (v) => setSheet(() => roleId = v),
        ),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: () async {
            final payload = {
              'full_name': name.text.trim(),
              'username': username.text.trim(),
              'email': email.text.trim(),
              if (pass.text.isNotEmpty) 'password': pass.text,
              if (roleId != null) 'role_id': roleId,
            };
            try {
              if (u == null) {
                await auth.api.post('/users', payload);
              } else {
                await auth.api.put('/users/${u['id']}', payload);
              }
              if (ctx.mounted) Navigator.pop(ctx, true);
            } catch (e) {
              if (ctx.mounted) toast(ctx, '$e', error: true);
            }
          },
          child: const Text('Save'),
        ),
      ]);
    }));
    if (ok == true) _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Users'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(50),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
            child: SizedBox(
              height: 40,
              child: TextField(
                decoration: fieldDeco('Search users').copyWith(prefixIcon: const Icon(Icons.search, size: 18)),
                onSubmitted: (v) {
                  _q = v;
                  _key.currentState?.reload();
                },
              ),
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: FnColors.red,
        onPressed: () => _edit(auth),
        child: const Icon(Icons.person_add, color: Colors.white),
      ),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => _load(auth),
        builder: (context, data, reload) {
          final rows = (data['data'] as List).cast<Map<String, dynamic>>();
          return ListView.separated(
            itemCount: rows.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final u = rows[i];
              return ListTile(
                leading: CircleAvatar(backgroundImage: NetworkImage(u['avatar'] ?? '')),
                title: Text('${u['full_name']}'),
                subtitle: Text('@${u['username']} · ${u['role_name']}'),
                trailing: PopupMenuButton<String>(
                  onSelected: (v) async {
                    if (v == 'edit') return _edit(auth, u);
                    if (v == 'active') await auth.api.post('/users/${u['id']}/toggle-active');
                    if (v == 'verified') await auth.api.post('/users/${u['id']}/toggle-verified');
                    if (v == 'delete') await auth.api.delete('/users/${u['id']}');
                    reload();
                  },
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: 'edit', child: Text('Edit')),
                    PopupMenuItem(value: 'active', child: Text('Toggle active')),
                    PopupMenuItem(value: 'verified', child: Text('Toggle verified')),
                    PopupMenuItem(value: 'delete', child: Text('Delete')),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
