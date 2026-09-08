import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/auth_service.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class AnalyticsScreen extends StatelessWidget {
  const AnalyticsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthService>();
    return Scaffold(
      appBar: AppBar(title: const Text('Analytics')),
      body: AsyncView<Map<String, dynamic>>(
        load: () async => (await auth.api.get('/analytics', {'days': 30})) as Map<String, dynamic>,
        builder: (context, d, reload) {
          final totals = (d['totals'] as Map).cast<String, dynamic>();
          final series = (d['series'] as List).cast<Map<String, dynamic>>();
          final top = (d['top_posts'] as List).cast<Map<String, dynamic>>();
          return RefreshIndicator(
            onRefresh: () async => reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.7,
                  children: [
                    StatCard('Published', '${totals['posts']}'),
                    StatCard('Total views', '${totals['views']}'),
                    StatCard('Users', '${totals['users']}'),
                    StatCard('Comments', '${totals['comments']}'),
                  ],
                ),
                const SizedBox(height: 20),
                const Text('Posts per day (30d)', style: TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                SizedBox(
                  height: 180,
                  child: LineChart(LineChartData(
                    gridData: const FlGridData(show: false),
                    titlesData: const FlTitlesData(show: false),
                    borderData: FlBorderData(show: false),
                    lineBarsData: [
                      LineChartBarData(
                        spots: [
                          for (var i = 0; i < series.length; i++)
                            FlSpot(i.toDouble(), (series[i]['posts'] as num).toDouble()),
                        ],
                        isCurved: true,
                        color: FnColors.red,
                        dotData: const FlDotData(show: false),
                        belowBarData: BarAreaData(show: true, color: FnColors.red.withOpacity(.12)),
                      ),
                    ],
                  )),
                ),
                const SizedBox(height: 20),
                const Text('Top posts by views', style: TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Card(
                  child: Column(children: [
                    for (final p in top)
                      ListTile(
                        dense: true,
                        title: Text('${p['title']}', maxLines: 1, overflow: TextOverflow.ellipsis),
                        trailing: Text('${p['views_count']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      ),
                  ]),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
