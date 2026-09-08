import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/widgets.dart';
import '../posts/post_edit_screen.dart';

class AiGenerateScreen extends StatefulWidget {
  const AiGenerateScreen({super.key});
  @override
  State<AiGenerateScreen> createState() => _AiGenerateScreenState();
}

class _AiGenerateScreenState extends State<AiGenerateScreen> {
  final _topic = TextEditingController();
  String _length = 'medium';
  String _tone = 'neutral';
  bool _busy = false;
  Map<String, dynamic>? _draft;

  Future<void> _run() async {
    if (_topic.text.trim().isEmpty) return;
    setState(() {
      _busy = true;
      _draft = null;
    });
    try {
      final r = await context.read<AuthService>().api.post('/ai/generate', {
        'topic': _topic.text.trim(),
        'length': _length,
        'tone': _tone,
      });
      setState(() => _draft = (r['draft'] as Map).cast<String, dynamic>());
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('AI Writer')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _topic, decoration: fieldDeco('Topic / headline idea'), maxLines: 2),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              child: DropdownButtonFormField<String>(
                value: _length,
                decoration: fieldDeco('Length'),
                items: const [
                  DropdownMenuItem(value: 'short', child: Text('Short')),
                  DropdownMenuItem(value: 'medium', child: Text('Medium')),
                  DropdownMenuItem(value: 'long', child: Text('Long')),
                ],
                onChanged: (v) => setState(() => _length = v ?? 'medium'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: DropdownButtonFormField<String>(
                value: _tone,
                decoration: fieldDeco('Tone'),
                items: const [
                  DropdownMenuItem(value: 'neutral', child: Text('Neutral')),
                  DropdownMenuItem(value: 'formal', child: Text('Formal')),
                  DropdownMenuItem(value: 'punchy', child: Text('Punchy')),
                ],
                onChanged: (v) => setState(() => _tone = v ?? 'neutral'),
              ),
            ),
          ]),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _busy ? null : _run,
            child: _busy
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Generate draft'),
          ),
          if (_draft != null) ...[
            const SizedBox(height: 20),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${_draft!['title'] ?? _topic.text}',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(height: 8),
                    Text('${_draft!['content'] ?? _draft!['body'] ?? _draft}',
                        maxLines: 12, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: 12),
                    Row(children: [
                      OutlinedButton.icon(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(
                              text: '${_draft!['content'] ?? _draft!['body'] ?? ''}'));
                          toast(context, 'Copied');
                        },
                        icon: const Icon(Icons.copy, size: 16),
                        label: const Text('Copy'),
                      ),
                      const Spacer(),
                      FilledButton(
                        style: FilledButton.styleFrom(minimumSize: const Size(120, 40)),
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const PostEditScreen()),
                        ),
                        child: const Text('New post'),
                      ),
                    ]),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
