import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final u = auth.user!;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [
            CircleAvatar(radius: 30, backgroundImage: NetworkImage(u.avatar)),
            const SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(u.fullName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                Text('@${u.username} · ${u.roleName}', style: const TextStyle(color: FnColors.gray)),
              ]),
            ),
          ]),
          const SizedBox(height: 24),
          _tile(context, Icons.edit_outlined, 'Edit profile', () => _editProfile(context)),
          _tile(context, Icons.lock_outline, 'Change password', () => _changePassword(context)),
          _tile(context, Icons.shield_outlined, 'Permissions', () {
            showDialog(
              context: context,
              builder: (_) => AlertDialog(
                title: const Text('Your permissions'),
                content: Text(u.permissions.join('\n')),
                actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('OK'))],
              ),
            );
          }),
          const Divider(),
          _tile(context, Icons.logout, 'Sign out', () => auth.logout(), color: FnColors.red),
        ],
      ),
    );
  }

  Widget _tile(BuildContext context, IconData i, String label, VoidCallback onTap, {Color? color}) =>
      ListTile(
        leading: Icon(i, color: color),
        title: Text(label, style: TextStyle(color: color)),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      );

  Future<void> _editProfile(BuildContext context) async {
    final auth = context.read<AuthService>();
    final u = auth.user!;
    final name = TextEditingController(text: u.fullName);
    final phone = TextEditingController(text: u.phone ?? '');
    final bio = TextEditingController(text: u.bio);
    final loc = TextEditingController(text: u.location);
    await showFormSheet(context, 'Edit profile', Column(children: [
      TextField(controller: name, decoration: fieldDeco('Full name')),
      const SizedBox(height: 10),
      TextField(controller: phone, decoration: fieldDeco('Phone')),
      const SizedBox(height: 10),
      TextField(controller: loc, decoration: fieldDeco('Location')),
      const SizedBox(height: 10),
      TextField(controller: bio, decoration: fieldDeco('Bio'), maxLines: 3),
      const SizedBox(height: 14),
      FilledButton(
        onPressed: () async {
          try {
            await auth.updateProfile({
              'full_name': name.text.trim(),
              'phone': phone.text.trim(),
              'location': loc.text.trim(),
              'bio': bio.text.trim(),
            });
            if (context.mounted) {
              Navigator.pop(context);
              toast(context, 'Updated');
            }
          } catch (e) {
            if (context.mounted) toast(context, '$e', error: true);
          }
        },
        child: const Text('Save'),
      ),
    ]));
  }

  Future<void> _changePassword(BuildContext context) async {
    final auth = context.read<AuthService>();
    final pass = TextEditingController();
    await showFormSheet(context, 'Change password', Column(children: [
      TextField(controller: pass, decoration: fieldDeco('New password (min 8)'), obscureText: true),
      const SizedBox(height: 14),
      FilledButton(
        onPressed: () async {
          try {
            await auth.updateProfile({'password': pass.text});
            if (context.mounted) {
              Navigator.pop(context);
              toast(context, 'Password changed');
            }
          } catch (e) {
            if (context.mounted) toast(context, '$e', error: true);
          }
        },
        child: const Text('Update'),
      ),
    ]));
  }
}
