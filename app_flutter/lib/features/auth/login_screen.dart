import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../../core/google_sign_in_button.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _pass = TextEditingController();
  bool _busy = false;
  bool _obscure = true;
  Future<void>? _googleReady;

  @override
  void initState() {
    super.initState();
    if (kIsWeb) {
      _googleReady = context.read<AuthService>().initializeGoogle();
    }
  }

  Future<void> _submit() async {
    if (_email.text.trim().isEmpty || _pass.text.isEmpty) return;
    setState(() => _busy = true);
    try {
      await context.read<AuthService>().login(_email.text, _pass.text);
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _signInWithGoogle() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthService>().loginWithGoogle();
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final googleError = context.watch<AuthService>().googleError;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 96,
                    child: Image.asset('assets/images/fataknews_logo.png', fit: BoxFit.contain),
                  ),
                  const SizedBox(height: 12),
                  const Text('FatakNews Staff',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  const Text('Sign in to manage the newsroom',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: FnColors.gray)),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    decoration: fieldDeco('Email or username'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _pass,
                    obscureText: _obscure,
                    onSubmitted: (_) => _submit(),
                    decoration: fieldDeco('Password').copyWith(
                      suffixIcon: IconButton(
                        icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    child: _busy
                        ? const SizedBox(
                            height: 20, width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Sign in'),
                  ),
                  const SizedBox(height: 18),
                  const Row(children: [
                    Expanded(child: Divider()),
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 12),
                      child: Text('or', style: TextStyle(color: FnColors.gray)),
                    ),
                    Expanded(child: Divider()),
                  ]),
                  const SizedBox(height: 18),
                  if (kIsWeb)
                    FutureBuilder<void>(
                      future: _googleReady,
                      builder: (context, snapshot) {
                        if (snapshot.hasError) {
                          return const Text('Google sign-in could not be initialized.', textAlign: TextAlign.center);
                        }
                        if (snapshot.connectionState != ConnectionState.done) {
                          return const SizedBox(height: 44, child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
                        }
                        return const WebGoogleSignInButton();
                      },
                    )
                  else
                    OutlinedButton.icon(
                      onPressed: _busy ? null : _signInWithGoogle,
                      icon: const Text('G', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF4285F4))),
                      label: const Text('Continue with Google'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                        foregroundColor: Theme.of(context).colorScheme.onSurface,
                      ),
                    ),
                  if (googleError != null) ...[
                    const SizedBox(height: 12),
                    Text(googleError, textAlign: TextAlign.center, style: const TextStyle(color: FnColors.error)),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
