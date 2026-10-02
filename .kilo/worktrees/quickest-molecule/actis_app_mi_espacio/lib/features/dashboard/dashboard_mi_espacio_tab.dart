import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api_client.dart';

class DashboardMiEspacio extends StatefulWidget {
  final Function(int)? onNavigate;
  const DashboardMiEspacio({super.key, this.onNavigate});
  @override
  State<DashboardMiEspacio> createState() => _DashboardMiEspacioState();
}

class _DashboardMiEspacioState extends State<DashboardMiEspacio> {
  bool _isLoading = true;
  Map<String, dynamic> _data = {};
  Timer? _timer;
  final GlobalKey<RefreshIndicatorState> _refreshKey = GlobalKey<RefreshIndicatorState>();

  @override
  void initState() { super.initState(); _fetchData(); _timer = Timer.periodic(const Duration(seconds: 30), (_) => _fetchData()); }
  @override
  void dispose() { _timer?.cancel(); super.dispose(); }

  Future<void> _fetchData() async {
    try {
      final res = await ApiClient.get('api_mobile/dashboard_mi_espacio.php');
      if (mounted) setState(() { _data = res; _isLoading = false; });
    } catch (_) { if (mounted) setState(() => _isLoading = false); }
  }

  int _getInt(String k, [int d = 0]) { final v = _data[k]; if (v is int) return v; if (v is String) return int.tryParse(v) ?? d; if (v is double) return v.toInt(); return d; }
  dynamic _get(String k, [dynamic d]) => _data[k] ?? d;

  Widget _sectionTitle(String t, IconData i, {VoidCallback? onAction}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 8),
    child: Row(children: [
      Icon(i, color: const Color(0xFF1F2937), size: 26), const SizedBox(width: 8),
      Expanded(child: Text(t, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF111827)))),
      if (onAction != null) TextButton(onPressed: onAction, child: const Text("Ver más", style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2563EB)))),
    ]),
  );

  Widget _metricCard(String title, String value, IconData icon, Color color, {VoidCallback? onTap}) {
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

  Widget _miniCard(String title, Widget content) => Container(height: 140, padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF1E3A8A))),
      const SizedBox(height: 8), Expanded(child: content),
    ]));

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_data.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Icon(Icons.error_outline, size: 48, color: Colors.grey), const SizedBox(height: 16),
      const Text("Error al cargar datos", style: TextStyle(color: Colors.grey, fontSize: 16)),
      TextButton(onPressed: _fetchData, child: const Text("Reintentar"))
    ]));

    int pedidosPendientes = _getInt('pedidos_pendientes');
    int pedidosAprobados  = _getInt('pedidos_aprobados');
    int pedidosRechazados = _getInt('pedidos_rechazados');
    int totalPedidos = _getInt('total_pedidos');
    List ultimos = _get('ultimos_pedidos', []);
    String userName = _get('nombre_usuario', 'Usuario');

    return RefreshIndicator(key: _refreshKey, onRefresh: _fetchData, child: ListView(padding: const EdgeInsets.all(16), children: [

      // Saludo personalizado con el nombre del usuario
      Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: const Color(0xFF1E293B),
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 10, offset: const Offset(0, 4))],
        ),
        child: Row(children: [
          CircleAvatar(radius: 28, backgroundColor: Colors.white24, child: Icon(Icons.person, color: Colors.white, size: 32)),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text("Hola, $userName", style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
            Text(_get('fecha_actual', ''), style: const TextStyle(color: Colors.white70, fontSize: 13)),
          ])),
        ]),
      ),

      _sectionTitle("Mis Pedidos", Icons.shopping_bag),
      Row(children: [
        _metricCard("Pendientes", "$pedidosPendientes", Icons.hourglass_empty, const Color(0xFFD97706), onTap: () => widget.onNavigate?.call(1)),
        const SizedBox(width: 16),
        _metricCard("Aprobados", "$pedidosAprobados", Icons.check_circle, const Color(0xFF059669), onTap: () => widget.onNavigate?.call(1)),
      ]),
      const SizedBox(height: 16),
      Row(children: [
        _metricCard("Rechazados", "$pedidosRechazados", Icons.cancel, const Color(0xFFDC2626)),
        const SizedBox(width: 16),
        _metricCard("Total Pedidos", "$totalPedidos", Icons.list_alt, const Color(0xFF1E3A8A), onTap: () => widget.onNavigate?.call(1)),
      ]),
      const SizedBox(height: 16),

      Row(children: [
        Expanded(child: _miniCard("Tasa de Aprobación", Center(child: Stack(alignment: Alignment.center, children: [
          SizedBox(width: 70, height: 70, child: CircularProgressIndicator(
            value: totalPedidos > 0 ? pedidosAprobados / totalPedidos : 0,
            backgroundColor: Colors.grey.shade200, color: const Color(0xFF10B981), strokeWidth: 8)),
          Text(totalPedidos > 0 ? "${((pedidosAprobados / totalPedidos) * 100).toInt()}%" : "0%",
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        ])))),
        const SizedBox(width: 12),
        Expanded(child: _miniCard("Estado General", Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          pedidosPendientes > 0
            ? Text("$pedidosPendientes", style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w900, color: Color(0xFFD97706)))
            : const Text("0", style: TextStyle(fontSize: 36, fontWeight: FontWeight.w900, color: Color(0xFF059669))),
          Text(pedidosPendientes > 0 ? "pedidos pendientes" : "todo al día",
            style: const TextStyle(fontSize: 11, color: Colors.blueGrey)),
        ])))),
      ]),
      const SizedBox(height: 24),

      if (ultimos.isNotEmpty) ...[
        _sectionTitle("Últimos Pedidos", Icons.history, onAction: () => widget.onNavigate?.call(1)),
        Container(padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
          child: Column(children: ultimos.map<Widget>((p) {
            final estado = p['estado']?.toString() ?? '';
            Color chip = estado == 'Aprobado' ? const Color(0xFF059669) : estado == 'Rechazado' ? const Color(0xFFDC2626) : const Color(0xFFD97706);
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(backgroundColor: chip.withValues(alpha: 0.1), child: Icon(Icons.shopping_bag, color: chip, size: 18)),
              title: Text(p['descripcion']?.toString() ?? '-', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
              subtitle: Text(estado, style: TextStyle(fontSize: 11, color: chip, fontWeight: FontWeight.w600)),
              trailing: const Icon(Icons.chevron_right, size: 20, color: Colors.blueGrey),
              onTap: () => widget.onNavigate?.call(1),
            );
          }).toList())),
      ],
      const SizedBox(height: 40),
    ]));
  }
}
