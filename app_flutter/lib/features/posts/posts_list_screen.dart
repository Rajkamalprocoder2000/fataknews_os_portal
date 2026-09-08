import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../../models/post.dart';
import 'post_edit_screen.dart';

class PostsListScreen extends StatefulWidget {
  const PostsListScreen({super.key, required this.mine});
  final bool mine;
  @override
  State<PostsListScreen> createState() => _PostsListScreenState();
}

class _PostsListScreenState extends State<PostsListScreen> {
  final _keyView = GlobalKey<AsyncViewState<List<PostRow>>>();
  String _status = '';
  String _q = '';

  Future<List<PostRow>> _load(AuthService auth) async {
    final r = await auth.api.get('/posts', {
      if (widget.mine) 'mine': '1',
      if (_status.isNotEmpty) 'status': _status,
      if (_q.isNotEmpty) 'q': _q,
    });
    return ((r['data'] as List)).map((e) => PostRow.fromJson(e)).toList();
  }

  Color _statusColor(String s) => switch (s) {
        'published' => FnColors.success,
        'pending' => FnColors.warning,
        'rejected' => FnColors.error,
        _ => FnColors.gray,
      };

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.mine ? 'My Posts' : 'All Posts'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
            child: Row(children: [
              Expanded(
                child: SizedBox(
                  height: 40,
                  child: TextField(
                    decoration: fieldDeco('Search').copyWith(
                        prefixIcon: const Icon(Icons.search, size: 18),
                        contentPadding: EdgeInsets.zero),
                    onSubmitted: (v) {
                      _q = v;
                      _keyView.currentState?.reload();
                    },
                  ),
                ),
              ),
              const SizedBox(width: 8),
              DropdownButton<String>(
                value: _status,
                underline: const SizedBox(),
                items: const [
                  DropdownMenuItem(value: '', child: Text('All')),
                  DropdownMenuItem(value: 'draft', child: Text('Draft')),
                  DropdownMenuItem(value: 'pending', child: Text('Pending')),
                  DropdownMenuItem(value: 'published', child: Text('Published')),
                  DropdownMenuItem(value: 'rejected', child: Text('Rejected')),
                ],
                onChanged: (v) {
                  setState(() => _status = v ?? '');
                  _keyView.currentState?.reload();
                },
              ),
            ]),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FnColors.red,
        foregroundColor: Colors.white,
        onPressed: () async {
          final ok = await Navigator.push<bool>(context,
              MaterialPageRoute(builder: (_) => const PostEditScreen()));
          if (ok == true) _keyView.currentState?.reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('New'),
      ),
      body: AsyncView<List<PostRow>>(
        key: _keyView,
        load: () => _load(auth),
        builder: (context, rows, reload) {
          if (rows.isEmpty) return const EmptyState(text: 'No posts');
          return RefreshIndicator(
            onRefresh: () async => reload(),
            child: ListView.separated(
              itemCount: rows.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, i) {
                final p = rows[i];
                return ListTile(
                  leading: p.thumbnailUrl != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(p.thumbnailUrl!,
                              width: 54, height: 54, fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => const Icon(Icons.image)),
                        )
                      : const Icon(Icons.article_outlined),
                  title: Text(p.title, maxLines: 2, overflow: TextOverflow.ellipsis),
                  subtitle: Row(children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                      decoration: BoxDecoration(
                        color: _statusColor(p.status).withOpacity(.14),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(p.status,
                          style: TextStyle(fontSize: 10, color: _statusColor(p.status), fontWeight: FontWeight.w700)),
                    ),
                    const SizedBox(width: 8),
                    Text('${p.views} views · ${p.categoryName ?? "-"}',
                        style: const TextStyle(fontSize: 11)),
                  ]),
                  onTap: () async {
                    final ok = await Navigator.push<bool>(context,
                        MaterialPageRoute(builder: (_) => PostEditScreen(postId: p.id)));
                    if (ok == true) reload();
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
