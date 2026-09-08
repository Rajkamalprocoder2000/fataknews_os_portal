import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../dashboard/dashboard_screen.dart';
import '../posts/posts_list_screen.dart';
import '../posts/moderation_screen.dart';
import '../categories/categories_screen.dart';
import '../users/users_screen.dart';
import '../comments/comments_screen.dart';
import '../notifications/notifications_screen.dart';
import '../analytics/analytics_screen.dart';
import '../ads/ads_screen.dart';
import '../settings/settings_screen.dart';
import '../ai/ai_generate_screen.dart';
import '../stories/stories_screen.dart';
import '../hr/hr_home.dart';
import '../profile/profile_screen.dart';

class _Module {
  final String label;
  final IconData icon;
  final Widget Function() build;
  final bool Function(AuthService) visible;
  const _Module(this.label, this.icon, this.build, this.visible);
}

final _modules = <_Module>[
  _Module('My Posts', Icons.article_outlined, () => const PostsListScreen(mine: true),
      (a) => a.user!.isEditorial),
  _Module('All Posts', Icons.dynamic_feed_outlined, () => const PostsListScreen(mine: false),
      (a) => a.user!.isManager || a.user!.role == 'editor'),
  _Module('Approvals', Icons.fact_check_outlined, () => const ModerationScreen(),
      (a) => a.user!.isManager || a.user!.role == 'editor'),
  _Module('AI Writer', Icons.auto_awesome_outlined, () => const AiGenerateScreen(),
      (a) => a.user!.isEditorial),
  _Module('Categories', Icons.category_outlined, () => const CategoriesScreen(),
      (a) => a.user!.isEditorial),
  _Module('Comments', Icons.mode_comment_outlined, () => const CommentsScreen(),
      (a) => a.user!.isManager || a.user!.role == 'editor'),
  _Module('Stories', Icons.amp_stories_outlined, () => const StoriesScreen(),
      (a) => a.user!.isEditorial),
  _Module('Users', Icons.group_outlined, () => const UsersScreen(),
      (a) => a.user!.isAdmin),
  _Module('Analytics', Icons.insights_outlined, () => const AnalyticsScreen(),
      (a) => a.user!.isManager),
  _Module('Ads', Icons.ad_units_outlined, () => const AdsScreen(),
      (a) => a.user!.isAdmin),
  _Module('HR', Icons.badge_outlined, () => const HrHome(),
      (a) => a.user!.isHr || a.user!.isManager),
  _Module('Settings', Icons.settings_outlined, () => const SettingsScreen(),
      (a) => a.user!.isAdmin),
];

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      const DashboardScreen(),
      const _ModulesGrid(),
      const NotificationsScreen(),
      const ProfileScreen(),
    ];
    return Scaffold(
      body: pages[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.apps_outlined), selectedIcon: Icon(Icons.apps), label: 'Modules'),
          NavigationDestination(icon: Icon(Icons.notifications_outlined), selectedIcon: Icon(Icons.notifications), label: 'Alerts'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}

class _ModulesGrid extends StatelessWidget {
  const _ModulesGrid();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final mods = _modules.where((m) => m.visible(auth)).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Modules')),
      body: GridView.count(
        crossAxisCount: 3,
        padding: const EdgeInsets.all(16),
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.95,
        children: [
          for (final m in mods)
            InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => m.build()),
              ),
              child: Card(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(m.icon, size: 26, color: FnColors.red),
                    const SizedBox(height: 8),
                    Text(m.label,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
