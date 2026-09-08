import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../../models/post.dart';
import 'post_edit_screen.dart';

class ModerationScreen extends StatelessWidget {
  const ModerationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<PostRow>>>();
    return Scaffold(
      appBar: AppBar(title: const Text('Approvals')),
      body: AsyncView<List<PostRow>>(
        key: key,
        load: () async {
          final r = await auth.api.get('/posts', {'status': 'pending'});
          return (r['data'] as List).map((e) => PostRow.fromJson(e)).toList();
        },
        builder: (context, rows, reload) {
          if (rows.isEmpty) {
            return const EmptyState(text: 'Nothing waiting for review', icon: Icons.check_circle_outline);
          }
          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: rows.length,
            itemBuilder: (_, i) {
              final p = rows[i];
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(p.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 4),
                      Text('${p.author ?? "-"} · ${p.categoryName ?? "-"}',
                          style: const TextStyle(fontSize: 12, color: FnColors.gray)),
                      if (p.excerpt.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(p.excerpt, maxLines: 3, overflow: TextOverflow.ellipsis),
                      ],
                      const SizedBox(height: 12),
                      Row(children: [
                        TextButton(
                          onPressed: () => Navigator.push(context,
                              MaterialPageRoute(builder: (_) => PostEditScreen(postId: p.id))),
                          child: const Text('Open'),
                        ),
                        const Spacer(),
                        OutlinedButton(
                          onPressed: () async {
                            final reason = await _askReason(context);
                            if (reason == null) return;
                            await auth.api.post('/posts/${p.id}/reject', {'reason': reason});
                            reload();
                          },
                          child: const Text('Reject'),
                        ),
                        const SizedBox(width: 8),
                        FilledButton(
                          style: FilledButton.styleFrom(minimumSize: const Size(90, 40)),
                          onPressed: () async {
                            await auth.api.post('/posts/${p.id}/approve');
                            if (context.mounted) toast(context, 'Published');
                            reload();
                          },
                          child: const Text('Approve'),
                        ),
                      ]),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<String?> _askReason(BuildContext context) {
    final c = TextEditingController(text: 'Does not meet editorial standards');
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Rejection reason'),
        content: TextField(controller: c, maxLines: 3, decoration: fieldDeco('Reason')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('Reject')),
        ],
      ),
    );
  }
}
