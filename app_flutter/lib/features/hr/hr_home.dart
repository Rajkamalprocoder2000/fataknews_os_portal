import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class HrHome extends StatelessWidget {
  const HrHome({super.key});
  @override
  Widget build(BuildContext context) {
    final admin = context.read<AuthService>().user!.isHr || context.read<AuthService>().user!.isManager;
    final tabs = <Tab>[
      const Tab(text: 'Attendance'),
      const Tab(text: 'Leaves'),
      if (admin) const Tab(text: 'Employees'),
      if (admin) const Tab(text: 'Payroll'),
      if (admin) const Tab(text: 'Departments'),
    ];
    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('HR'),
          bottom: TabBar(isScrollable: true, tabs: tabs),
        ),
        body: TabBarView(children: [
          const _Attendance(),
          const _Leaves(),
          if (admin) const _Employees(),
          if (admin) const _Payroll(),
          if (admin) const _Departments(),
        ]),
      ),
    );
  }
}

class _Attendance extends StatelessWidget {
  const _Attendance();
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FnColors.red,
        foregroundColor: Colors.white,
        onPressed: () async {
          await auth.api.post('/hr/attendance', {'status': 'present'});
          if (context.mounted) toast(context, 'Marked present');
          key.currentState?.reload();
        },
        icon: const Icon(Icons.fingerprint),
        label: const Text('Check in'),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/hr/attendance'))['attendance'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) => rows.isEmpty
            ? const EmptyState(text: 'No attendance this month')
            : ListView(children: [
                for (final a in rows)
                  ListTile(title: Text('${a['date']}'), trailing: Text('${a['status']}')),
              ]),
      ),
    );
  }
}

class _Leaves extends StatelessWidget {
  const _Leaves();
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final admin = auth.user!.isHr || auth.user!.isManager;
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();

    Future<void> apply() async {
      final type = TextEditingController(text: 'casual');
      final start = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
      final end = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
      final reason = TextEditingController();
      final ok = await showFormSheet<bool>(context, 'Apply for leave', Column(children: [
        TextField(controller: type, decoration: fieldDeco('Type (casual / sick / earned)')),
        const SizedBox(height: 10),
        TextField(controller: start, decoration: fieldDeco('Start date (YYYY-MM-DD)')),
        const SizedBox(height: 10),
        TextField(controller: end, decoration: fieldDeco('End date (YYYY-MM-DD)')),
        const SizedBox(height: 10),
        TextField(controller: reason, decoration: fieldDeco('Reason'), maxLines: 2),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: () async {
            await auth.api.post('/hr/leaves', {
              'leave_type': type.text.trim(),
              'start_date': start.text.trim(),
              'end_date': end.text.trim(),
              'reason': reason.text.trim(),
            });
            if (context.mounted) Navigator.pop(context, true);
          },
          child: const Text('Submit'),
        ),
      ]));
      if (ok == true) key.currentState?.reload();
    }

    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FnColors.red,
        foregroundColor: Colors.white,
        onPressed: apply,
        icon: const Icon(Icons.add),
        label: const Text('Apply'),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/hr/leaves', admin ? {'pending': '1'} : null))['leaves'] as List)
            .cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) => rows.isEmpty
            ? const EmptyState(text: 'No leave requests')
            : ListView(children: [
                for (final l in rows)
                  ListTile(
                    title: Text('${l['leave_type'] ?? l['type']} · ${l['start_date']} → ${l['end_date']}'),
                    subtitle: Text('${l['full_name'] ?? l['reason'] ?? ''}'),
                    trailing: admin && (l['status'] == 'pending')
                        ? Row(mainAxisSize: MainAxisSize.min, children: [
                            IconButton(
                              icon: const Icon(Icons.check, color: FnColors.success),
                              onPressed: () async {
                                await auth.api.post('/hr/leaves/${l['id']}/approve');
                                reload();
                              },
                            ),
                            IconButton(
                              icon: const Icon(Icons.close, color: FnColors.error),
                              onPressed: () async {
                                await auth.api.post('/hr/leaves/${l['id']}/reject');
                                reload();
                              },
                            ),
                          ])
                        : Text('${l['status']}'),
                  ),
              ]),
      ),
    );
  }
}

class _Employees extends StatelessWidget {
  const _Employees();
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return AsyncView<Map<String, dynamic>>(
      load: () async => (await auth.api.get('/hr/employees')) as Map<String, dynamic>,
      builder: (context, data, reload) {
        final rows = ((data['data'] ?? data['employees']) as List).cast<Map<String, dynamic>>();
        return ListView(children: [
          for (final e in rows)
            ListTile(
              leading: CircleAvatar(backgroundImage: NetworkImage(e['avatar'] ?? '')),
              title: Text('${e['full_name']}'),
              subtitle: Text('${e['role_name'] ?? e['department_name'] ?? ''}'),
            ),
        ]);
      },
    );
  }
}

class _Payroll extends StatelessWidget {
  const _Payroll();
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return AsyncView<List<Map<String, dynamic>>>(
      load: () async => ((await auth.api.get('/hr/payroll'))['payroll'] as List).cast<Map<String, dynamic>>(),
      builder: (context, rows, reload) => rows.isEmpty
          ? const EmptyState(text: 'No payroll for this month')
          : ListView(children: [
              for (final p in rows)
                ListTile(
                  title: Text('${p['full_name']}'),
                  trailing: Text('₹${p['net_pay']}'),
                ),
            ]),
    );
  }
}

class _Departments extends StatelessWidget {
  const _Departments();
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    final key = GlobalKey<AsyncViewState<List<Map<String, dynamic>>>>();
    Future<void> add() async {
      final name = TextEditingController();
      final ok = await showFormSheet<bool>(context, 'New department', Column(children: [
        TextField(controller: name, decoration: fieldDeco('Name')),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: () async {
            await auth.api.post('/hr/departments', {'name': name.text.trim()});
            if (context.mounted) Navigator.pop(context, true);
          },
          child: const Text('Save'),
        ),
      ]));
      if (ok == true) key.currentState?.reload();
    }

    return Scaffold(
      floatingActionButton: FloatingActionButton(
        backgroundColor: FnColors.red,
        onPressed: add,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: AsyncView<List<Map<String, dynamic>>>(
        key: key,
        load: () async => ((await auth.api.get('/hr/departments'))['departments'] as List).cast<Map<String, dynamic>>(),
        builder: (context, rows, reload) => ListView(children: [
          for (final d in rows)
            ListTile(
              title: Text('${d['name']}'),
              trailing: IconButton(
                icon: const Icon(Icons.delete_outline),
                onPressed: () async {
                  await auth.api.delete('/hr/departments/${d['id']}');
                  reload();
                },
              ),
            ),
        ]),
      ),
    );
  }
}
