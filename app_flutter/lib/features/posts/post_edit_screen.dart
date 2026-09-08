import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class PostEditScreen extends StatefulWidget {
  const PostEditScreen({super.key, this.postId});
  final int? postId;
  @override
  State<PostEditScreen> createState() => _PostEditScreenState();
}

class _PostEditScreenState extends State<PostEditScreen> {
  final _title = TextEditingController();
  final _excerpt = TextEditingController();
  final _content = TextEditingController();
  final _tags = TextEditingController();
  final _videoUrl = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  List<Map<String, dynamic>> _categories = [];
  int? _categoryId;
  String _type = 'news';
  String _location = 'both';
  bool _featured = false;
  bool _breaking = false;
  String? _thumbnailUrl;

  bool get _isEdit => widget.postId != null;

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    final auth = context.read<AuthService>();
    try {
      final cats = await auth.api.get('/categories');
      _categories = ((cats['categories'] as List)).cast<Map<String, dynamic>>();
      if (_isEdit) {
        final r = await auth.api.get('/posts/${widget.postId}');
        final p = r['post'] as Map<String, dynamic>;
        _title.text = p['title'] ?? '';
        _excerpt.text = p['excerpt'] ?? '';
        _content.text = p['content'] ?? '';
        _tags.text = ((p['tags'] as List?) ?? const []).join(', ');
        _videoUrl.text = p['video_url'] ?? '';
        _categoryId = p['category_id'];
        _type = p['type'] ?? 'news';
        _location = p['location'] ?? 'both';
        _featured = p['is_featured'] == 1;
        _breaking = p['is_breaking'] == 1;
        _thumbnailUrl = p['thumbnail_url'];
      }
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _pickThumb() async {
    final auth = context.read<AuthService>();
    final x = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (x == null) return;
    setState(() => _saving = true);
    try {
      final r = await auth.api.upload('/upload', File(x.path),
          field: 'file', fields: {'dir': 'thumbnails'});
      setState(() => _thumbnailUrl = r['url']);
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _save(String status) async {
    if (_title.text.trim().isEmpty || _content.text.trim().isEmpty) {
      toast(context, 'Title and content are required', error: true);
      return;
    }
    setState(() => _saving = true);
    final auth = context.read<AuthService>();
    final payload = {
      'title': _title.text.trim(),
      'excerpt': _excerpt.text.trim(),
      'content': _content.text.trim(),
      'tags': _tags.text.trim(),
      'video_url': _videoUrl.text.trim(),
      'category_id': _categoryId ?? 0,
      'type': _type,
      'location': _location,
      'is_featured': _featured,
      'is_breaking': _breaking,
      'status': status,
      if (_thumbnailUrl != null) 'thumbnail': _thumbnailUrl!.split('/').last,
    };
    try {
      if (_isEdit) {
        await auth.api.put('/posts/${widget.postId}', payload);
      } else {
        await auth.api.post('/posts', payload);
      }
      if (mounted) {
        toast(context, status == 'draft' ? 'Draft saved' : 'Submitted');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_isEdit ? 'Edit Post' : 'New Post'),
        actions: [
          if (!_loading)
            TextButton(
              onPressed: _saving ? null : () => _save('draft'),
              child: const Text('Draft'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                TextField(controller: _title, decoration: fieldDeco('Title'), maxLines: 2),
                const SizedBox(height: 12),
                GestureDetector(
                  onTap: _pickThumb,
                  child: Container(
                    height: 150,
                    decoration: BoxDecoration(
                      color: FnColors.lightGray,
                      borderRadius: BorderRadius.circular(12),
                      image: _thumbnailUrl != null
                          ? DecorationImage(image: NetworkImage(_thumbnailUrl!), fit: BoxFit.cover)
                          : null,
                    ),
                    child: _thumbnailUrl == null
                        ? const Center(
                            child: Column(mainAxisSize: MainAxisSize.min, children: [
                            Icon(Icons.add_photo_alternate_outlined, color: FnColors.gray),
                            SizedBox(height: 4),
                            Text('Add cover image', style: TextStyle(color: FnColors.gray)),
                          ]))
                        : null,
                  ),
                ),
                const SizedBox(height: 12),
                TextField(controller: _excerpt, decoration: fieldDeco('Excerpt / summary'), maxLines: 2),
                const SizedBox(height: 12),
                TextField(controller: _content, decoration: fieldDeco('Content'), minLines: 6, maxLines: 16),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  value: _categoryId,
                  decoration: fieldDeco('Category'),
                  items: [
                    for (final c in _categories)
                      DropdownMenuItem(value: c['id'] as int, child: Text('${c['name']}')),
                  ],
                  onChanged: (v) => setState(() => _categoryId = v),
                ),
                const SizedBox(height: 12),
                Row(children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      value: _type,
                      decoration: fieldDeco('Type'),
                      items: const [
                        DropdownMenuItem(value: 'news', child: Text('News')),
                        DropdownMenuItem(value: 'article', child: Text('Article')),
                        DropdownMenuItem(value: 'breaking', child: Text('Breaking')),
                      ],
                      onChanged: (v) => setState(() => _type = v ?? 'news'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      value: _location,
                      decoration: fieldDeco('Placement'),
                      items: const [
                        DropdownMenuItem(value: 'both', child: Text('Home + Cat')),
                        DropdownMenuItem(value: 'home', child: Text('Home')),
                        DropdownMenuItem(value: 'category', child: Text('Category')),
                        DropdownMenuItem(value: 'explore', child: Text('Explore')),
                        DropdownMenuItem(value: 'shorts', child: Text('Shorts')),
                      ],
                      onChanged: (v) => setState(() => _location = v ?? 'both'),
                    ),
                  ),
                ]),
                const SizedBox(height: 12),
                TextField(controller: _videoUrl, decoration: fieldDeco('Video URL (YouTube / FB / IG / X)')),
                const SizedBox(height: 12),
                TextField(controller: _tags, decoration: fieldDeco('Tags (comma separated)')),
                const SizedBox(height: 4),
                SwitchListTile(
                  title: const Text('Featured'),
                  value: _featured,
                  onChanged: (v) => setState(() => _featured = v),
                ),
                if (context.read<AuthService>().user!.isManager)
                  SwitchListTile(
                    title: const Text('Breaking'),
                    value: _breaking,
                    onChanged: (v) => setState(() => _breaking = v),
                  ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _saving ? null : () => _save('published'),
                  child: _saving
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text(context.read<AuthService>().user!.isManager ? 'Publish' : 'Submit for review'),
                ),
              ],
            ),
    );
  }
}
