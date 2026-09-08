import 'package:flutter/material.dart';
import 'package:google_sign_in_web/web_only.dart' as google_web;

class WebGoogleSignInButton extends StatelessWidget {
  const WebGoogleSignInButton({super.key});

  @override
  Widget build(BuildContext context) {
    return const SizedBox(height: 44, child: google_web.renderButton());
  }
}
