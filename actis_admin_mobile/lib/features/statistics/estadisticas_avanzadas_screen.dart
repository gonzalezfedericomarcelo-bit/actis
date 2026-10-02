import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../core/api_client.dart';

class EstadisticasAvanzadasScreen extends StatefulWidget {
  const EstadisticasAvanzadasScreen({super.key});

  @override
  State<EstadisticasAvanzadasScreen> createState() => _EstadisticasAvanzadasScreenState();
}

class _EstadisticasAvanzadasScreenState extends State<EstadisticasAvanzadasScreen> {
  String _selectedModulo = 'turnos';
  bool _isLoading = true;
  Map<String, dynamic> _data = {};

  final List<String> _modulos = ['turnos', 'totem', 'ventanilla', 'pacientes', 'seguridad'];

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() {
      _isLoading = true;
      _data.clear(); // Limpiar el estado para evitar bugs visuales (datos cruzados)
    });
    final res = await ApiClient.get('estadisticas_avanzadas.php?modulo=$_selectedModulo');
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _data = res['charts'];
        _isLoading = false;
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Estadísticas (Big Data)', style: TextStyle(color: Colors.white)),
        backgroundColor: const Color(0xFF144973),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Column(
        children: [
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                const Text("Módulo: ", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButton<String>(
                    value: _selectedModulo,
                    isExpanded: true,
                    items: _modulos.map((m) => DropdownMenuItem(
                      value: m, 
                      child: Text(m.toUpperCase())
                    )).toList(),
                    onChanged: (v) {
                      if (v != null) {
                        setState(() => _selectedModulo = v);
                        _fetchData();
                      }
                    },
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: _isLoading 
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _fetchData,
                    child: _buildChartsList(),
                  ),
          )
        ],
      ),
    );
  }

  Widget _buildChartsList() {
    if (_data.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 100),
          Center(child: Text("No hay datos para este módulo")),
        ],
      );
    }

    List<Widget> widgets = [];
    _data.forEach((key, chartData) {
      if (chartData != null && chartData is List && chartData.isNotEmpty) {
        widgets.add(_buildChartCard(key, chartData));
      }
    });

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: widgets.length,
      itemBuilder: (context, i) => widgets[i],
    );
  }

  Widget _buildChartCard(String key, List data) {
    String title = key.toUpperCase();
    Widget chart;
    Widget? legend;

    if (key == 'evolucion' || key == 'ingresos' || key == 'egresos' || key == 'rondas' || key == 'tiempo_operacion') {
      chart = _buildLineChart(data);
    } else if (key == 'estados' || key == 'modos' || key == 'qr' || key == 'validacion' || key == 'llaves' || key == 'genero' || key == 'estado_civil' || key == 'ausentismo') {
      chart = _buildPieChart(data);
      legend = _buildPieLegend(data);
    } else {
      chart = _buildBarChart(data);
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 24),
      elevation: 3,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title.replaceAll('_', ' '), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
            const SizedBox(height: 24),
            SizedBox(
              height: 250,
              child: chart,
            ),
            if (legend != null) ...[
              const SizedBox(height: 16),
              legend,
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildLineChart(List data) {
    List<FlSpot> spots = [];
    for (int i = 0; i < data.length; i++) {
      spots.add(FlSpot(i.toDouble(), double.parse(data[i]['value'].toString())));
    }

    return LineChart(
      LineChartData(
        gridData: const FlGridData(show: true),
        titlesData: FlTitlesData(
          bottomTitles: AxisTitles(sideTitles: SideTitles(
            showTitles: true,
            getTitlesWidget: (value, meta) {
              if (value.toInt() >= 0 && value.toInt() < data.length) {
                String lbl = data[value.toInt()]['label'].toString();
                List<String> parts = lbl.split('-');
                if (parts.length == 3) lbl = "${parts[2]}/${parts[1]}";
                return Padding(
                  padding: const EdgeInsets.only(top: 8), 
                  child: Transform.rotate(
                    angle: -0.5,
                    child: Text(lbl, style: const TextStyle(fontSize: 10))
                  )
                );
              }
              return const Text('');
            },
            interval: data.length > 7 ? (data.length / 7).ceilToDouble() : 1,
            reservedSize: 40,
          )),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          LineChartBarData(
            spots: spots,
            isCurved: true,
            color: const Color(0xFF0ea5e9),
            barWidth: 3,
            isStrokeCapRound: true,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(show: true, color: const Color(0xFF0ea5e9).withAlpha(50)),
          ),
        ],
      ),
    );
  }

  Widget _buildBarChart(List data) {
    List<BarChartGroupData> groups = [];
    for (int i = 0; i < data.length; i++) {
      groups.add(BarChartGroupData(
        x: i,
        barRods: [
          BarChartRodData(
            toY: double.parse(data[i]['value'].toString()),
            color: const Color(0xFF144973),
            width: 16,
            borderRadius: BorderRadius.circular(4),
          )
        ],
      ));
    }

    return BarChart(
      BarChartData(
        gridData: const FlGridData(show: false),
        titlesData: FlTitlesData(
          bottomTitles: AxisTitles(sideTitles: SideTitles(
            showTitles: true,
            getTitlesWidget: (value, meta) {
              if (value.toInt() >= 0 && value.toInt() < data.length) {
                String lbl = data[value.toInt()]['label'].toString();
                if (lbl.length > 8) lbl = "${lbl.substring(0, 8)}.";
                return Padding(
                  padding: const EdgeInsets.only(top: 8), 
                  child: Transform.rotate(
                    angle: -0.5,
                    child: Text(lbl, style: const TextStyle(fontSize: 9))
                  )
                );
              }
              return const Text('');
            },
            reservedSize: 40,
            interval: 1,
          )),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        barGroups: groups,
      ),
    );
  }

  Widget _buildPieChart(List data) {
    List<Color> colors = [Colors.blue, Colors.green, Colors.orange, Colors.red, Colors.purple, Colors.teal, Colors.indigo, Colors.brown, Colors.pink, Colors.cyan];
    List<PieChartSectionData> sections = [];
    for (int i = 0; i < data.length; i++) {
      sections.add(PieChartSectionData(
        color: colors[i % colors.length],
        value: double.parse(data[i]['value'].toString()),
        title: "${data[i]['value']}",
        radius: 80,
        titleStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.white),
      ));
    }

    return PieChart(
      PieChartData(
        sectionsSpace: 2,
        centerSpaceRadius: 40,
        sections: sections,
      ),
    );
  }

  Widget _buildPieLegend(List data) {
    List<Color> colors = [Colors.blue, Colors.green, Colors.orange, Colors.red, Colors.purple, Colors.teal, Colors.indigo, Colors.brown, Colors.pink, Colors.cyan];
    
    return Wrap(
      spacing: 12,
      runSpacing: 8,
      children: data.asMap().entries.map((entry) {
        int idx = entry.key;
        var row = entry.value;
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(width: 12, height: 12, decoration: BoxDecoration(color: colors[idx % colors.length], shape: BoxShape.circle)),
            const SizedBox(width: 4),
            Text("${row['label']} (${row['value']})", style: const TextStyle(fontSize: 12)),
          ],
        );
      }).toList(),
    );
  }
}
