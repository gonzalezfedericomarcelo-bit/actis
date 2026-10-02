import 'dart:async';
import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'dart:async';
import '../../core/api_client.dart';
import '../../core/app_sync.dart';

class DashboardTab extends StatefulWidget {
  final Function(int)? onNavigate;
  const DashboardTab({super.key, this.onNavigate});

  @override
  State<DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<DashboardTab> {
  bool _isLoading = true;
  Map<String, dynamic> _data = {};
  Timer? _timer;

  StreamSubscription? _syncSub;
  final GlobalKey<RefreshIndicatorState> _refreshKey = GlobalKey<RefreshIndicatorState>();

  @override
  void initState() {
    super.initState();
    _fetchData();
    _timer = Timer.periodic(const Duration(seconds: 15), (t) => _fetchData());
    _syncSub = AppSync.notifier.stream.listen((_) {
      if (mounted) {
        _refreshKey.currentState?.show();
      }
    });
  }

  @override
  void dispose() {
    _syncSub?.cancel();
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _fetchData() async {
    final res = await ApiClient.get('dashboard_stats.php');
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _data = res['data'];
        _isLoading = false;
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  dynamic _get(String key1, String key2, [dynamic def = 0]) {
    if (_data.containsKey(key1) && _data[key1] is Map && _data[key1].containsKey(key2)) {
      return _data[key1][key2];
    }
    return def;
  }

  int _getInt(String key1, String key2, [int def = 0]) {
    final val = _get(key1, key2, def);
    if (val is int) return val;
    if (val is String) return int.tryParse(val) ?? def;
    if (val is double) return val.toInt();
    return def;
  }

  double _getDouble(String key1, String key2, [double def = 0.0]) {
    final val = _get(key1, key2, def);
    if (val is double) return val;
    if (val is int) return val.toDouble();
    if (val is String) return double.tryParse(val) ?? def;
    return def;
  }

  // --- WIDGETS DE UI ---

  Widget _buildSectionTitle(String title, IconData icon, {VoidCallback? onAction, String actionText = "Ver más"}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 20.0, horizontal: 8.0),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF1F2937), size: 26),
          const SizedBox(width: 8),
          Expanded(child: Text(title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF111827)))),
          if (onAction != null)
            TextButton(
              onPressed: onAction,
              child: Text(actionText, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2563EB))),
            )
        ],
      ),
    );
  }

  Widget _buildMetricCard(String title, String value, IconData icon, Color color, {VoidCallback? onTap}) {
    final card = Container(
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: color.withValues(alpha: 0.10), blurRadius: 15, offset: const Offset(0, 8))],
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 28),
          ),
          const SizedBox(height: 16),
          Text(value, style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: color)),
          const SizedBox(height: 8),
          Text(title, textAlign: TextAlign.center, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey.shade700)),
        ],
      ),
    );

    return Expanded(
      child: onTap != null 
        ? InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(16),
            child: card,
          )
        : card,
    );
  }

  Widget _buildTicketsRemainingCard(String title, int restantes, double horas, IconData icon) {
    String estimacion = horas > 100 ? "+4 días" : "${horas.toInt()} hrs est.";
    Color color = horas < 5 ? Colors.red.shade700 : const Color(0xFF0F766E); // Corporate Teal

    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: color.withValues(alpha: 0.4), blurRadius: 8, offset: const Offset(0, 4))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: Colors.white70, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))),
              ],
            ),
            const SizedBox(height: 12),
            Text("$restantes", style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900)),
            const Text("tickets disp.", style: TextStyle(color: Colors.white70, fontSize: 12)),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: Colors.black26, borderRadius: BorderRadius.circular(8)),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.timer, color: Colors.white, size: 14),
                  const SizedBox(width: 4),
                  Text(estimacion, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  Widget _buildPieChartTurnos() {
    int presentes = _getInt('turnos', 'presentes');
    int ausentes = _getInt('turnos', 'ausentes');
    int pendientes = _getInt('turnos', 'pendientes');
    int cancelados = _getInt('turnos', 'cancelados');
    int atendidos = _getInt('turnos', 'atendidos');
    int autorizados = _getInt('turnos', 'autorizados');

    if (presentes == 0 && ausentes == 0 && pendientes == 0 && cancelados == 0 && atendidos == 0 && autorizados == 0) {
      return const SizedBox(height: 200, child: Center(child: Text("Sin datos de turnos hoy")));
    }

    return Container(
      height: 250,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
      ),
      child: Column(
        children: [
          const Text("Estado Global de Turnos", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 16),
          Expanded(
            child: Row(
              children: [
                Expanded(
                  flex: 5,
                  child: PieChart(
                    PieChartData(
                      sectionsSpace: 2,
                      centerSpaceRadius: 30,
                      sections: [
                        if (atendidos > 0) PieChartSectionData(color: const Color(0xFF0284C7), value: atendidos.toDouble(), title: '$atendidos', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        if (presentes > 0) PieChartSectionData(color: const Color(0xFF059669), value: presentes.toDouble(), title: '$presentes', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        if (autorizados > 0) PieChartSectionData(color: const Color(0xFF8B5CF6), value: autorizados.toDouble(), title: '$autorizados', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        if (pendientes > 0) PieChartSectionData(color: const Color(0xFFD97706), value: pendientes.toDouble(), title: '$pendientes', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        if (ausentes > 0) PieChartSectionData(color: const Color(0xFFDC2626), value: ausentes.toDouble(), title: '$ausentes', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        if (cancelados > 0) PieChartSectionData(color: const Color(0xFF6B7280), value: cancelados.toDouble(), title: '$cancelados', radius: 40, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                      ],
                    ),
                  ),
                ),
                Expanded(
                  flex: 4,
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _indicator(const Color(0xFF0284C7), 'Atendidos'),
                        const SizedBox(height: 4),
                        _indicator(const Color(0xFF059669), 'Presentes'),
                        const SizedBox(height: 4),
                        _indicator(const Color(0xFF8B5CF6), 'Autorizados'),
                        const SizedBox(height: 4),
                        _indicator(const Color(0xFFD97706), 'Pendientes'),
                        const SizedBox(height: 4),
                        _indicator(const Color(0xFFDC2626), 'Ausentes'),
                        const SizedBox(height: 4),
                        _indicator(const Color(0xFF6B7280), 'Cancelados'),
                      ],
                    ),
                  ),
                )
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _indicator(Color color, String text) {
    return Row(
      children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 4),
        Expanded(
          child: Text(
            text, 
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF374151)),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _buildTopList(List data, String titleKey, IconData icon, String type) {
    if (data.isEmpty) return const Text("Sin datos", style: TextStyle(color: Colors.grey));
    return Column(
      children: data.map((item) {
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: CircleAvatar(backgroundColor: const Color(0xFFF3F4F6), child: Icon(icon, color: const Color(0xFF1E3A8A), size: 18)),
          title: Text(item[titleKey] ?? '-', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          trailing: const Icon(Icons.chevron_right, size: 20, color: Colors.blueGrey),
          onTap: () => _mostrarTurnosPorFiltro(type, item[titleKey]),
        );
      }).toList(),
    );
  }

  Widget _buildPieChartSimple(String title, String label1, int val1, Color c1, String label2, int val2, Color c2) {
    if (val1 == 0 && val2 == 0) {
      return _buildMiniCard(title, const Center(child: Text("Sin datos", style: TextStyle(color: Colors.grey))));
    }
    return _buildMiniCard(
      title,
      Row(
        children: [
          Expanded(
            flex: 2,
            child: PieChart(
              PieChartData(
                sectionsSpace: 2,
                centerSpaceRadius: 10,
                sections: [
                  if (val1 > 0) PieChartSectionData(color: c1, value: val1.toDouble(), title: '$val1', radius: 20, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 10)),
                  if (val2 > 0) PieChartSectionData(color: c2, value: val2.toDouble(), title: '$val2', radius: 20, titleStyle: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 10)),
                ]
              )
            ),
          ),
          Expanded(
            flex: 3,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _indicator(c1, label1),
                const SizedBox(height: 4),
                _indicator(c2, label2),
              ],
            )
          )
        ],
      ),
    );
  }

  Widget _buildMiniCard(String title, Widget content) {
    return Container(
      height: 140,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF1E3A8A))),
          const SizedBox(height: 8),
          Expanded(child: content),
        ],
      ),
    );
  }

  Widget _buildEstadisticasTotemVentanilla() {
    int tVal = _getInt('operaciones', 'totem_val');
    int tAsis = _getInt('operaciones', 'totem_asis');
    int vVal = _getInt('operaciones', 'ventanilla_val');
    int vAsis = _getInt('operaciones', 'ventanilla_asis');
    int tTotal = _getInt('operaciones', 'totem_total');
    int vTotal = _getInt('operaciones', 'ventanilla_total');
    double eficacia = _getDouble('operaciones', 'eficacia_validacion');
    int tProm = _getInt('operaciones', 'tiempo_promedio');
    int tMax = _getInt('operaciones', 'tiempo_maximo');
    int combinado = _getInt('operaciones', 'total_combinado');

    return Column(
      children: [
        Row(
          children: [
            Expanded(child: _buildPieChartSimple("1. Origen de Operaciones", "Tótem", tTotal, const Color(0xFF0EA5E9), "Ventanilla", vTotal, const Color(0xFF10B981))),
            const SizedBox(width: 12),
            Expanded(child: _buildPieChartSimple("2. Distribución Tótem", "Validación", tVal, const Color(0xFF8B5CF6), "Asistencia", tAsis, const Color(0xFFF59E0B))),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _buildPieChartSimple("3. Distrib. Ventanilla", "Validación", vVal, const Color(0xFF8B5CF6), "Asistencia", vAsis, const Color(0xFFF59E0B))),
            const SizedBox(width: 12),
            Expanded(
              child: _buildMiniCard(
                "4. Eficacia Global Valid.",
                Center(
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      SizedBox(
                        width: 70, height: 70,
                        child: CircularProgressIndicator(value: eficacia / 100, backgroundColor: Colors.grey.shade200, color: const Color(0xFF10B981), strokeWidth: 8),
                      ),
                      Text("${eficacia.toInt()}%", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                    ],
                  )
                )
              )
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildMiniCard(
                "5. Tiempos de Operación",
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.timer, color: Color(0xFF3B82F6)),
                        Text("${tProm}s", style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                        const Text("Promedio", style: TextStyle(fontSize: 10, color: Colors.grey)),
                      ],
                    ),
                    Container(width: 1, height: 40, color: Colors.grey.shade300),
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.warning_amber, color: Color(0xFFEF4444)),
                        Text("${tMax}s", style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                        const Text("Máximo", style: TextStyle(fontSize: 10, color: Colors.grey)),
                      ],
                    )
                  ],
                )
              )
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildMiniCard(
                "6. Total Procesado",
                Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text("$combinado", style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w900, color: Color(0xFF1E3A8A))),
                      const Text("Pacientes en el día", style: TextStyle(fontSize: 11, color: Colors.blueGrey)),
                    ],
                  ),
                )
              )
            ),
          ],
        ),
      ],
    );
  }

  void _mostrarTurnosPorFiltro(String tipo, String valor) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return _FiltroTurnosModal(tipoFiltro: tipo, valorFiltro: valor);
      },
    );
  }

  Widget _buildValidacionTurnos() {
    int adopcion = _getInt('operaciones', 'adopcion_digital'); // Totem %
    int humano = 100 - adopcion;
    
    final content = Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B), // Corporate Slate Dark
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Column(
        children: [
          const Row(
            children: [
              Icon(Icons.qr_code_scanner, color: Colors.white70),
              SizedBox(width: 8),
              Text("Códigos Generados Hoy", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Stack(
                      alignment: Alignment.center,
                      children: [
                        SizedBox(
                          width: 80, height: 80,
                          child: CircularProgressIndicator(value: adopcion / 100, backgroundColor: Colors.white12, color: const Color(0xFF38BDF8), strokeWidth: 6),
                        ),
                        Text("$adopcion%", style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Text("Vía Tótem", style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w600)),
                    Text("${_getInt('operaciones', 'totem_val')} validaciones", style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 12)),
                    Text("${_getInt('operaciones', 'totem_asis')} asistencias", style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 12)),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Stack(
                      alignment: Alignment.center,
                      children: [
                        SizedBox(
                          width: 80, height: 80,
                          child: CircularProgressIndicator(value: humano / 100, backgroundColor: Colors.white12, color: const Color(0xFF10B981), strokeWidth: 6),
                        ),
                        Text("$humano%", style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Text("Vía Ventanilla", style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w600)),
                    Text("${_getInt('operaciones', 'ventanilla_val')} validaciones", style: const TextStyle(color: Color(0xFF10B981), fontSize: 12)),
                    Text("${_getInt('operaciones', 'ventanilla_asis')} asistencias", style: const TextStyle(color: Color(0xFF10B981), fontSize: 12)),
                  ],
                ),
              ),
            ],
          )
        ],
      ),
    );
    
    return InkWell(
      onTap: () => widget.onNavigate?.call(4), // Ir a Tótem
      borderRadius: BorderRadius.circular(16),
      child: content,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_data.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 16),
            const Text("Error al cargar datos", style: TextStyle(color: Colors.grey, fontSize: 16)),
            TextButton(onPressed: _fetchData, child: const Text("Reintentar"))
          ],
        ),
      );
    }
    
    return RefreshIndicator(
      key: _refreshKey,
      onRefresh: _fetchData,
      child: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          Center(
            child: Text(
              _data['fecha_actual'] ?? '',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF111827))
            ),
          ),
          const SizedBox(height: 16),

          // 1. HARDWARE Y TICKETS (PRIORIDAD MAXIMA)
          _buildSectionTitle("Centro de Operaciones", Icons.hardware),
          Padding(
            padding: const EdgeInsets.only(left: 8, bottom: 16),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFBBF7D0)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.inventory_2, color: Color(0xFF16A34A), size: 16),
                      const SizedBox(width: 6),
                      const Text("Autonomía Global:", style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF166534), fontSize: 13)),
                      const SizedBox(width: 4),
                      Text("${_getInt('insumos', 'autonomia_global_tickets')} tickets", style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF14532D), fontSize: 13)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Row(
            children: [
              _buildTicketsRemainingCard("Tótem", _getInt('insumos', 'restantes_totem'), _getDouble('insumos', 'horas_restantes_totem'), Icons.developer_board),
              const SizedBox(width: 16),
              _buildTicketsRemainingCard("Ventanilla", _getInt('insumos', 'restantes_vent'), _getDouble('insumos', 'horas_restantes_vent'), Icons.desktop_windows),
            ],
          ),


          // 2. VALIDACION DE TURNOS (EX AUTOGESTION)
          _buildSectionTitle("Validación de Turnos", Icons.verified_user),
          _buildValidacionTurnos(),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFFEFF6FF),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFBFDBFE)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.history, color: Color(0xFF2563EB), size: 16),
                  const SizedBox(width: 6),
                  const Text("Historial Rollos:", style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1E3A8A), fontSize: 13)),
                  const SizedBox(width: 4),
                  Text("Tót: ${_getInt('insumos', 'rollos_historicos_totem')} | Ven: ${_getInt('insumos', 'rollos_historicos_vent')}", style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF1E3A8A), fontSize: 13)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // 3. HORARIO PICO
          InkWell(
            onTap: () => widget.onNavigate?.call(4),
            borderRadius: BorderRadius.circular(12),
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFED7AA))),
              child: Row(
                children: [
                  const Icon(Icons.access_time_filled, color: Color(0xFFEA580C), size: 32),
                  const SizedBox(width: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text("Horario Pico del Día", style: TextStyle(color: Color(0xFF9A3412), fontWeight: FontWeight.bold)),
                      Text(_get('operaciones', 'hora_pico', 'N/A'), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF9A3412))),
                    ],
                  )
                ],
              ),
            ),
          ),
          
          // 4. ESTADISTICAS AVANZADAS TÓTEM Y VENTANILLA
          _buildSectionTitle("Estadísticas de Autogestión", Icons.insights),
          _buildEstadisticasTotemVentanilla(),
          const SizedBox(height: 16),

          // 5. RANKING DEL DÍA
          _buildSectionTitle("Ranking de Turnos del Día", Icons.leaderboard),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                margin: const EdgeInsets.only(bottom: 16),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text("Top Especialidades", style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1E3A8A))),
                    const Divider(),
                    _buildTopList(_get('turnos', 'top_3_especialidades', []), 'servicio', Icons.medical_services, 'especialidad'),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text("Top Profesionales", style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1E3A8A))),
                    const Divider(),
                    _buildTopList(_get('turnos', 'top_3_profesionales', []), 'profesional', Icons.person, 'profesional'),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // 5. AREA MEDICA (PACIENTES Y TURNOS)
          _buildSectionTitle("Área Médica", Icons.local_hospital),
          Row(
            children: [
              _buildMetricCard(
                "Pacientes Hoy", 
                "${_getInt('pacientes', 'nuevos_hoy')}", 
                Icons.people, 
                const Color(0xFF1E3A8A), // Corporate Dark Blue
                onTap: () => widget.onNavigate?.call(1) // Ir a Pacientes
              ),
              const SizedBox(width: 16),
              _buildMetricCard(
                "Turnos Hoy", 
                "${_getInt('turnos', 'totales')}", 
                Icons.calendar_month, 
                const Color(0xFF0369A1), // Corporate Deep Sky Blue
                onTap: () => widget.onNavigate?.call(2) // Ir a Turnos
              ),
            ],
          ),
          const SizedBox(height: 24),
          _buildPieChartTurnos(),
          const SizedBox(height: 40),
        ],
      ),
    );
  }
}

class _FiltroTurnosModal extends StatefulWidget {
  final String tipoFiltro; // 'especialidad' o 'profesional'
  final String valorFiltro;
  
  const _FiltroTurnosModal({required this.tipoFiltro, required this.valorFiltro});

  @override
  State<_FiltroTurnosModal> createState() => _FiltroTurnosModalState();
}

class _FiltroTurnosModalState extends State<_FiltroTurnosModal> {
  bool _isLoading = true;
  List<dynamic> _turnos = [];

  @override
  void initState() {
    super.initState();
    _fetchTurnos();
  }

  Future<void> _fetchTurnos() async {
    setState(() => _isLoading = true);
    final res = await ApiClient.get('turnos.php?${widget.tipoFiltro}=${Uri.encodeComponent(widget.valorFiltro)}');
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _turnos = res['data'] ?? [];
        _isLoading = false;
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _mostrarDetalleTurno(dynamic t) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        final operario = (t['operador_externo']?.toString().isNotEmpty == true) 
          ? t['operador_externo'] 
          : (t['creador_nombre'] ?? 'Desconocido');
          
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, left: 16, right: 16, top: 24),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text("Turno N° ${t['numero_turno'] ?? '-'}", style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.person, color: Colors.blueGrey),
                  title: Text("${t['apellido'] ?? ''}, ${t['nombre'] ?? ''}", style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text("DNI: ${t['dni'] ?? '-'}\nAfiliado: ${t['afiliado'] ?? '-'}\nTel: ${t['telefono'] ?? '-'}"),
                  isThreeLine: true,
                ),
                ListTile(
                  leading: const Icon(Icons.medical_services, color: Colors.blueGrey),
                  title: Text("Profesional: ${t['profesional'] ?? '-'}"),
                  subtitle: Text("Práctica/Motivo: ${t['motivo_visita'] ?? t['especialidad'] ?? '-'}"),
                ),
                ListTile(
                  leading: const Icon(Icons.calendar_today, color: Colors.blueGrey),
                  title: Text("Para el: ${t['fecha_turno']} a las ${t['hora_turno'] ?? '-'}"),
                  subtitle: Text("Estado actual: ${t['estado'] ?? '-'}"),
                ),
                ListTile(
                  leading: const Icon(Icons.admin_panel_settings, color: Colors.blueGrey),
                  title: Text("Operario: $operario"),
                  subtitle: Text("Registrado el: ${t['creado_el'] ?? '-'}"),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cerrar Detalle'),
                  ),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, top: 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0),
            child: Row(
              children: [
                const Icon(Icons.list_alt, color: Color(0xFF144973), size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    "Turnos: ${widget.valorFiltro}", 
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF144973))
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                )
              ],
            ),
          ),
          const Divider(),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(40.0),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_turnos.isEmpty)
            const Padding(
              padding: EdgeInsets.all(40.0),
              child: Center(
                child: Text("No hay turnos para este filtro hoy.", style: TextStyle(fontSize: 16, color: Colors.grey)),
              ),
            )
          else
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.6),
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _turnos.length,
                itemBuilder: (context, i) {
                  final t = _turnos[i];
                  return ListTile(
                    leading: CircleAvatar(
                      backgroundColor: Colors.blueGrey.withValues(alpha: 0.1),
                      child: const Icon(Icons.person, color: Colors.blueGrey),
                    ),
                    title: Text("${t['apellido'] ?? ''}, ${t['nombre'] ?? ''}", style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text("${t['fecha_turno']} - ${t['hora_turno']} | ${t['estado']}"),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => _mostrarDetalleTurno(t),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

