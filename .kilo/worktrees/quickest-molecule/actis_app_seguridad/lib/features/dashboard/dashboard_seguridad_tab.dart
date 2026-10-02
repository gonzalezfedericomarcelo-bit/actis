import 'dart:async';
import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../core/api_client.dart';

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CONFIG DEL MÃ“DULO
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
const String _kEndpoint      = 'dashboard_seguridad.php';
const String _kCard1Label    = 'Ingresos Hoy';
const String _kCard2Label    = 'Rechazados';
const Color  _kCard1Color    = Color(0xFF0F766E);
const Color  _kCard2Color    = Color(0xFFDC2626);
const String _kChipTotalLabel= 'Total Ingresos del Mes';
const String _kSection1      = 'Centro de Operaciones';
const String _kSection2      = 'ValidaciÃ³n de Flota';
const String _kCircIzqLabel  = 'VÃ­a Operativos';
const String _kCircIzqA      = 'admitidos';
const String _kCircIzqB      = 'procesados';
const String _kCircDerLabel  = 'VÃ­a Mantenimiento';
const String _kCircDerA      = 'rondas';
const String _kCircDerB      = 'recorridos';
const String _kChipALabel    = 'Rondas';
const String _kChipBLabel    = 'Llaves';
const String _kSection3      = 'EstadÃ­sticas de Ascensores';
const String _kMini1Title    = '1. Ingresos vs Rechazados';
const String _kMini1A        = 'Ingresos';
const String _kMini1B        = 'Rechazados';
const Color  _kMini1AC       = Color(0xFF8B5CF6);
const Color  _kMini1BC       = Color(0xFFF59E0B);
const String _kMini2Title    = '2. Actividad Rondas';
const String _kMini2A        = 'Rondas';
const String _kMini2B        = 'Puntos QR';
const Color  _kMini2AC       = Color(0xFFDC2626);
const Color  _kMini2BC       = Color(0xFF059669);
const String _kMini3Title    = '3. Incidencias';
const String _kMini3A        = 'Graves';
const String _kMini3B        = 'Leves';
const Color  _kMini3AC       = Color(0xFF0EA5E9);
const Color  _kMini3BC       = Color(0xFF10B981);
const String _kCombLabel     = 'Ingresos en el mes';
const String _kRankSection   = 'Ranking del Dia';
const String _kRank1Title    = 'Top Motivos Ingreso';
const String _kRank2Title    = 'Top TÃ©cnicos';
const IconData _kRank1Icon   = Icons.person;
const IconData _kRank2Icon   = Icons.block;
const String _kAreaSection   = 'Ãrea TÃ©cnica';
const String _kArea1Label    = 'Ingresos Hoy';
const String _kArea2Label    = 'Rondas Hoy';
const Color  _kArea1Color    = Color(0xFF1E3A8A);
const Color  _kArea2Color    = Color(0xFF0369A1);
const IconData _kArea1Icon   = Icons.how_to_reg;
const IconData _kArea2Icon   = Icons.security;
const String _kPieALabel     = 'Ingresos';
const String _kPieBLabel     = 'Rechazados';
const String _kPieCLabel     = 'Rondas';
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class DashboardSeguridad extends StatefulWidget {
  final Function(int)? onNavigate;
  const DashboardSeguridad({super.key, this.onNavigate});
  @override
  State<DashboardSeguridad> createState() => _DashboardSeguridadState();
}

class _DashboardSeguridadState extends State<DashboardSeguridad> {
  bool _isLoading = true;
  Map<String, dynamic> _data = {};
  Timer? _timer;
  final GlobalKey<RefreshIndicatorState> _refreshKey = GlobalKey<RefreshIndicatorState>();

  @override
  void initState() { super.initState(); _fetchData(); _timer = Timer.periodic(const Duration(seconds: 15), (t) => _fetchData()); }
  @override
  void dispose() { _timer?.cancel(); super.dispose(); }

  Future<void> _fetchData() async {
    try {
      final res = await ApiClient.get(_kEndpoint);
      if (mounted) setState(() { _data = res; _isLoading = false; });
    } catch (_) { if (mounted) setState(() => _isLoading = false); }
  }

  dynamic _get(String k, [dynamic d]) => _data[k] ?? d;
  int _getInt(String k, [int d = 0]) { final v = _data[k]; if (v is int) return v; if (v is String) return int.tryParse(v) ?? d; if (v is double) return v.toInt(); return d; }
  double _getDouble(String k, [double d = 0.0]) { final v = _data[k]; if (v is double) return v; if (v is int) return v.toDouble(); if (v is String) return double.tryParse(v) ?? d; return d; }

  // â”€â”€â”€ WIDGETS IDÃ‰NTICOS AL DASHBOARD ORIGINAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  Widget _buildSectionTitle(String title, IconData icon, {VoidCallback? onAction, String actionText = "Ver mÃ¡s"}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 20.0, horizontal: 8.0),
      child: Row(children: [
        Icon(icon, color: const Color(0xFF1F2937), size: 26), const SizedBox(width: 8),
        Expanded(child: Text(title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF111827)))),
        if (onAction != null) TextButton(onPressed: onAction, child: Text(actionText, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2563EB)))),
      ]),
    );
  }

  Widget _buildMetricCard(String title, String value, IconData icon, Color color, {VoidCallback? onTap}) {
    final card = Container(
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 8),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: color.withValues(alpha: 0.10), blurRadius: 15, offset: const Offset(0, 8))],
        border: Border.all(color: color.withValues(alpha: 0.2))),
      child: Column(children: [
        Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: color.withValues(alpha: 0.1), shape: BoxShape.circle), child: Icon(icon, color: color, size: 28)),
        const SizedBox(height: 16),
        Text(value, style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: color)),
        const SizedBox(height: 8),
        Text(title, textAlign: TextAlign.center, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey.shade700)),
      ]),
    );
    return Expanded(child: onTap != null ? InkWell(onTap: onTap, borderRadius: BorderRadius.circular(16), child: card) : card);
  }

  Widget _buildTicketsRemainingCard(String title, int restantes, String estimacion, IconData icon) {
    Color color = restantes == 0 ? Colors.red.shade700 : const Color(0xFF0F766E);
    return Expanded(child: Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: color.withValues(alpha: 0.4), blurRadius: 8, offset: const Offset(0, 4))]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Icon(icon, color: Colors.white70, size: 20), const SizedBox(width: 8), Expanded(child: Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)))]),
        const SizedBox(height: 12),
        Text("$restantes", style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900)),
        const Text("unidades", style: TextStyle(color: Colors.white70, fontSize: 12)),
        const SizedBox(height: 12),
        Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: Colors.black26, borderRadius: BorderRadius.circular(8)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.timer, color: Colors.white, size: 14), const SizedBox(width: 4),
            Text(estimacion, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
          ])),
      ]),
    ));
  }

  Widget _buildValidacion() {
    int pctIzq = _getInt('pct_izq');
    int pctDer = _getInt('pct_der');
    int valIzqA = _getInt('val_izq_a');
    int valDerA = _getInt('val_der_a');
    return InkWell(
      onTap: () => widget.onNavigate?.call(1),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 10, offset: const Offset(0, 4))]),
        child: Column(children: [
          const Row(children: [Icon(Icons.qr_code_scanner, color: Colors.white70), SizedBox(width: 8), Text("Eficiencia del MÃ³dulo", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16))]),
          const SizedBox(height: 24),
          Row(children: [
            Expanded(child: Column(children: [
              Stack(alignment: Alignment.center, children: [
                SizedBox(width: 80, height: 80, child: CircularProgressIndicator(value: pctIzq / 100, backgroundColor: Colors.white12, color: const Color(0xFF38BDF8), strokeWidth: 6)),
                Text("$pctIzq%", style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
              ]),
              const SizedBox(height: 12),
              Text(_kCircIzqLabel, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w600)),
              Text("$valIzqA $_kCircIzqA", style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 12)),
              Text("0 $_kCircIzqB", style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 12)),
            ])),
            Expanded(child: Column(children: [
              Stack(alignment: Alignment.center, children: [
                SizedBox(width: 80, height: 80, child: CircularProgressIndicator(value: pctDer / 100, backgroundColor: Colors.white12, color: const Color(0xFF10B981), strokeWidth: 6)),
                Text("$pctDer%", style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
              ]),
              const SizedBox(height: 12),
              Text(_kCircDerLabel, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w600)),
              Text("$valDerA $_kCircDerA", style: const TextStyle(color: Color(0xFF10B981), fontSize: 12)),
              Text("0 $_kCircDerB", style: const TextStyle(color: Color(0xFF10B981), fontSize: 12)),
            ])),
          ]),
        ]),
      ),
    );
  }

  Widget _indicator(Color color, String text) {
    return Row(children: [
      Container(width: 12, height: 12, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 4),
      Expanded(child: Text(text, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF374151)), maxLines: 2, overflow: TextOverflow.ellipsis)),
    ]);
  }

  Widget _buildPieChartSimple(String title, String lA, int vA, Color cA, String lB, int vB, Color cB) {
    if (vA == 0 && vB == 0) return _buildMiniCard(title, const Center(child: Text("Sin datos", style: TextStyle(color: Colors.grey))));
    return _buildMiniCard(title, Row(children: [
      Expanded(flex: 2, child: PieChart(PieChartData(sectionsSpace: 2, centerSpaceRadius: 10, sections: [
        if (vA > 0) PieChartSectionData(color: cA, value: vA.toDouble(), title: '$vA', radius: 20, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 10)),
        if (vB > 0) PieChartSectionData(color: cB, value: vB.toDouble(), title: '$vB', radius: 20, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 10)),
      ]))),
      Expanded(flex: 3, child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
        _indicator(cA, lA), const SizedBox(height: 4), _indicator(cB, lB),
      ])),
    ]));
  }

  Widget _buildMiniCard(String title, Widget content) {
    return Container(height: 140, padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF1E3A8A))),
        const SizedBox(height: 8), Expanded(child: content),
      ]));
  }

  Widget _buildEstadisticas() {
    int m1a = _getInt('mini1_a'), m1b = _getInt('mini1_b');
    int m2a = _getInt('mini2_a'), m2b = _getInt('mini2_b');
    int m3a = _getInt('mini3_a'), m3b = _getInt('mini3_b');
    double ef = _getDouble('eficacia');
    String tp = _get('tiempo_prom', '0m');
    String tm = _get('tiempo_max', '0m');
    int comb = _getInt('combinado');
    return Column(children: [
      Row(children: [
        Expanded(child: _buildPieChartSimple(_kMini1Title, _kMini1A, m1a, _kMini1AC, _kMini1B, m1b, _kMini1BC)),
        const SizedBox(width: 12),
        Expanded(child: _buildPieChartSimple(_kMini2Title, _kMini2A, m2a, _kMini2AC, _kMini2B, m2b, _kMini2BC)),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _buildPieChartSimple(_kMini3Title, _kMini3A, m3a, _kMini3AC, _kMini3B, m3b, const Color(0xFF10B981))),
        const SizedBox(width: 12),
        Expanded(child: _buildMiniCard("4. Eficacia Global", Center(child: Stack(alignment: Alignment.center, children: [
          SizedBox(width: 70, height: 70, child: CircularProgressIndicator(value: ef / 100, backgroundColor: Colors.grey.shade200, color: const Color(0xFF10B981), strokeWidth: 8)),
          Text("${ef.toInt()}%", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        ])))),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _buildMiniCard("5. Tiempos de OperaciÃ³n",
          Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
            Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.timer, color: Color(0xFF3B82F6)),
              Text(tp, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const Text("Promedio", style: TextStyle(fontSize: 10, color: Colors.grey)),
            ]),
            Container(width: 1, height: 40, color: Colors.grey.shade300),
            Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.warning_amber, color: Color(0xFFEF4444)),
              Text(tm, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const Text("MÃ¡ximo", style: TextStyle(fontSize: 10, color: Colors.grey)),
            ]),
          ])
        )),
        const SizedBox(width: 12),
        Expanded(child: _buildMiniCard("6. Total Procesado",
          Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text("$comb", style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w900, color: Color(0xFF1E3A8A))),
            Text(_kCombLabel, style: const TextStyle(fontSize: 11, color: Colors.blueGrey)),
          ]))
        )),
      ]),
    ]);
  }

  Widget _buildTopList(List data, String nameKey, IconData icon) {
    if (data.isEmpty) return const Text("Sin datos", style: TextStyle(color: Colors.grey));
    return Column(children: data.map((item) {
      return ListTile(
        contentPadding: EdgeInsets.zero,
        leading: CircleAvatar(backgroundColor: const Color(0xFFF3F4F6), child: Icon(icon, color: const Color(0xFF1E3A8A), size: 18)),
        title: Text(item[nameKey]?.toString() ?? '-', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
        trailing: const Icon(Icons.chevron_right, size: 20, color: Colors.blueGrey),
        onTap: () => widget.onNavigate?.call(1),
      );
    }).toList());
  }

  Widget _buildPieChartMain(int a, int b, int c) {
    if (a == 0 && b == 0 && c == 0) return const SizedBox(height: 200, child: Center(child: Text("Sin datos")));
    return Container(height: 250, padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)]),
      child: Column(children: [
        const Text("Estado Global de Turnos", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        const SizedBox(height: 16),
        Expanded(child: Row(children: [
          Expanded(flex: 5, child: PieChart(PieChartData(sectionsSpace: 2, centerSpaceRadius: 30, sections: [
            if (a > 0) PieChartSectionData(color: const Color(0xFF0284C7), value: a.toDouble(), title: '$a', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
            if (b > 0) PieChartSectionData(color: const Color(0xFF059669), value: b.toDouble(), title: '$b', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
            if (c > 0) PieChartSectionData(color: const Color(0xFFDC2626), value: c.toDouble(), title: '$c', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
          ]))),
          Expanded(flex: 4, child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
            _indicator(const Color(0xFF0284C7), _kPieALabel), const SizedBox(height: 4),
            _indicator(const Color(0xFF059669), _kPieBLabel), const SizedBox(height: 4),
            _indicator(const Color(0xFFDC2626), _kPieCLabel),
          ])),
        ])),
      ]));
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_data.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Icon(Icons.error_outline, size: 48, color: Colors.grey), const SizedBox(height: 16),
      const Text("Error al cargar datos", style: TextStyle(color: Colors.grey, fontSize: 16)),
      TextButton(onPressed: _fetchData, child: const Text("Reintentar"))
    ]));

    int total     = _getInt('total');
    int card1     = _getInt('card1_val');
    int card2     = _getInt('card2_val');
    int chipA     = _getInt('chip_a');
    int chipB     = _getInt('chip_b');
    int areaCard1 = _getInt('area_card1_val');
    int areaCard2 = _getInt('area_card2_val');
    int pieA      = _getInt('pie_a');
    int pieB      = _getInt('pie_b');
    int pieC      = _getInt('pie_c');
    String horaPico = _get('hora_pico', 'N/A');
    List top1 = _get('top1', []);
    List top2 = _get('top2', []);
    String top1Key = _get('top1_key', 'nombre');
    String top2Key = _get('top2_key', 'nombre');

    return RefreshIndicator(
      key: _refreshKey,
      onRefresh: _fetchData,
      child: ListView(padding: const EdgeInsets.all(16.0), children: [

        Center(child: Text(_get('fecha_actual', ''), textAlign: TextAlign.center, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF111827)))),
        const SizedBox(height: 16),

        // 1. CENTRO DE OPERACIONES
        _buildSectionTitle(_kSection1, Icons.hardware),
        Padding(padding: const EdgeInsets.only(left: 8, bottom: 16), child: Wrap(spacing: 8, runSpacing: 8, children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFBBF7D0))),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.inventory_2, color: Color(0xFF16A34A), size: 16), const SizedBox(width: 6),
              Text("$_kChipTotalLabel:", style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF166534), fontSize: 13)),
              const SizedBox(width: 4),
              Text("$total unidades", style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF14532D), fontSize: 13)),
            ])),
        ])),
        Row(children: [
          _buildTicketsRemainingCard(_kCard1Label, card1, "+4 dÃ­as", Icons.developer_board),
          const SizedBox(width: 16),
          _buildTicketsRemainingCard(_kCard2Label, card2, card2 > 0 ? "urgente" : "normal", Icons.desktop_windows),
        ]),

        // 2. VALIDACIÃ“N
        _buildSectionTitle(_kSection2, Icons.verified_user),
        _buildValidacion(),
        const SizedBox(height: 12),
        Align(alignment: Alignment.centerLeft, child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFBFDBFE))),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.history, color: Color(0xFF2563EB), size: 16), const SizedBox(width: 6),
            const Text("Historial:", style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1E3A8A), fontSize: 13)),
            const SizedBox(width: 4),
            Text("$_kChipALabel: $chipA | $_kChipBLabel: $chipB", style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF1E3A8A), fontSize: 13)),
          ]),
        )),
        const SizedBox(height: 16),

        // 3. HORARIO PICO
        InkWell(onTap: () => widget.onNavigate?.call(1), borderRadius: BorderRadius.circular(12), child: Container(
          margin: const EdgeInsets.symmetric(vertical: 12), padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFED7AA))),
          child: Row(children: [
            const Icon(Icons.access_time_filled, color: Color(0xFFEA580C), size: 32), const SizedBox(width: 16),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text("Horario Pico del DÃ­a", style: TextStyle(color: Color(0xFF9A3412), fontWeight: FontWeight.bold)),
              Text(horaPico, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF9A3412))),
            ]),
          ]),
        )),

        // 4. ESTADÃSTICAS
        _buildSectionTitle(_kSection3, Icons.insights),
        _buildEstadisticas(),
        const SizedBox(height: 16),

        // 5. RANKING
        _buildSectionTitle(_kRankSection, Icons.leaderboard),
        Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Container(margin: const EdgeInsets.only(bottom: 16), padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_kRank1Title, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1E3A8A))),
              const Divider(),
              _buildTopList(top1, top1Key, _kRank1Icon),
            ])),
          Container(padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_kRank2Title, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1E3A8A))),
              const Divider(),
              _buildTopList(top2, top2Key, _kRank2Icon),
            ])),
        ]),
        const SizedBox(height: 24),

        // 6. ÃREA
        _buildSectionTitle(_kAreaSection, Icons.local_hospital),
        Row(children: [
          _buildMetricCard(_kArea1Label, "$areaCard1", _kArea1Icon, _kArea1Color, onTap: () => widget.onNavigate?.call(1)),
          const SizedBox(width: 16),
          _buildMetricCard(_kArea2Label, "$areaCard2", _kArea2Icon, _kArea2Color, onTap: () => widget.onNavigate?.call(1)),
        ]),
        const SizedBox(height: 24),

        // 7. PIE CHART GLOBAL
        _buildPieChartMain(pieA, pieB, pieC),
        const SizedBox(height: 40),
      ]),
    );
  }
}
