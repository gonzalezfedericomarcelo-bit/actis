import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api_client.dart';
import 'package:shared_preferences/shared_preferences.dart';

class EncargadoPedidosScreen extends StatefulWidget {
  const EncargadoPedidosScreen({super.key});

  @override
  State<EncargadoPedidosScreen> createState() => _EncargadoPedidosScreenState();
}

class _EncargadoPedidosScreenState extends State<EncargadoPedidosScreen> {
  List<dynamic> _pedidos = [];
  bool _isLoading = true;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _fetchPedidos();
  }

  Future<void> _fetchPedidos() async {
    setState(() { _isLoading = true; _error = ''; });
    final response = await ApiClient.get('encargado_pedidos_lista.php');
    if (response['status'] == 'success') {
      setState(() { _pedidos = response['pedidos'] ?? []; _isLoading = false; });
    } else {
      setState(() { _error = response['message'] ?? 'Error desconocido'; _isLoading = false; });
    }
  }

  Future<void> _rechazarPedido(Map<String, dynamic> pedido) async {
    final motivoController = TextEditingController();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(children: [
          const Icon(Icons.cancel_outlined, color: Colors.red),
          const SizedBox(width: 8),
          Flexible(child: Text('Rechazar Pedido #${pedido['numero_orden']}', style: const TextStyle(fontSize: 16))),
        ]),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('¿Estás seguro? Esta acción cambia el estado del pedido a rechazado.'),
          const SizedBox(height: 12),
          TextField(
            controller: motivoController,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Motivo (opcional)', border: OutlineInputBorder()),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('CANCELAR')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('RECHAZAR', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    final prefs = await SharedPreferences.getInstance();
    final idUsuario = int.tryParse(prefs.getString('user_id') ?? '0') ?? 0;

    final res = await ApiClient.post('encargado_pedidos_procesar.php', {
      'action': 'rechazar',
      'id_pedido': pedido['id_pedido'],
      'id_usuario': idUsuario,
      'motivo_rechazo': motivoController.text,
    });

    if (!mounted) return;
    if (res['status'] == 'success') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('✅ Pedido rechazado correctamente.'), backgroundColor: Colors.green),
      );
      _fetchPedidos();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _abrirPdf(Map<String, dynamic> pedido) async {
    final num = (pedido['numero_orden'] ?? '').replaceAll('/', '-');
    final url = 'https://federicogonzalez.net/logistica/pdfs_publicos/Pedido_Trabajo_${num}_Inicial.pdf';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No se pudo abrir el PDF')));
    }
  }

  Color _colorPrioridad(String prioridad) {
    switch (prioridad) {
      case 'urgente': return Colors.red;
      case 'importante': return Colors.orange;
      default: return Colors.blue;
    }
  }

  IconData _iconPrioridad(String prioridad) {
    switch (prioridad) {
      case 'urgente': return Icons.priority_high;
      case 'importante': return Icons.warning_amber;
      default: return Icons.info_outline;
    }
  }

  Widget _buildPedidoCard(Map<String, dynamic> pedido) {
    final prioridad = pedido['prioridad'] ?? 'rutina';
    final estado = pedido['estado_pedido'] ?? '';
    final esFirmaRemota = estado == 'pendiente_firma_remota';
    final colorPrio = _colorPrioridad(prioridad);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: 3,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: colorPrio.withValues(alpha: 0.4), width: 1.5),
      ),
      child: Column(
        children: [
          // Header con número de orden y prioridad
          Container(
            decoration: BoxDecoration(
              color: colorPrio.withValues(alpha: 0.1),
              borderRadius: const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(_iconPrioridad(prioridad), color: colorPrio, size: 18),
                const SizedBox(width: 8),
                Text('#${pedido['numero_orden'] ?? 'S/N'}',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: colorPrio)),
                const Spacer(),
                if (esFirmaRemota)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.amber.shade100,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.amber),
                    ),
                    child: const Row(children: [
                      Icon(Icons.edit_note, size: 12, color: Colors.orange),
                      SizedBox(width: 4),
                      Text('P/Firma', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.orange)),
                    ]),
                  ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: colorPrio, borderRadius: BorderRadius.circular(8)),
                  child: Text(prioridad.toUpperCase(),
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),
          
          // Cuerpo
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(pedido['titulo_pedido'] ?? 'Sin título',
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                const SizedBox(height: 6),
                Text(pedido['descripcion_sintomas'] ?? '',
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 12),
                const Divider(height: 1),
                const SizedBox(height: 12),
                
                // Destino y Área
                if ((pedido['nombre_destino'] ?? '').isNotEmpty)
                  _infoRow(Icons.location_on, '${pedido['nombre_destino']}${(pedido['nombre_area'] ?? '').isNotEmpty ? ' › ${pedido['nombre_area']}' : ''}', Colors.red.shade400),
                const SizedBox(height: 6),
                
                // Solicitante
                _infoRow(Icons.person, pedido['solicitante_real_nombre'] ?? 'Sin solicitante', Colors.indigo),
                const SizedBox(height: 4),
                
                // Registrado por
                _infoRow(Icons.badge_outlined, 'Registró: ${pedido['nombre_auxiliar'] ?? 'N/A'}', Colors.grey),
                
                if ((pedido['solicitante_telefono'] ?? '').isNotEmpty) ...[
                  const SizedBox(height: 4),
                  _infoRow(Icons.phone, pedido['solicitante_telefono'], Colors.green),
                ],
                
                const SizedBox(height: 4),
                _infoRow(Icons.calendar_today, _formatFecha(pedido['fecha_emision']), Colors.blueGrey),
              ],
            ),
          ),
          
          // Botones de acción
          Container(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: Row(
              children: [
                // Ver PDF
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _abrirPdf(pedido),
                    icon: const Icon(Icons.picture_as_pdf, size: 16),
                    label: const Text('PDF'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.grey.shade700,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                // Rechazar
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _rechazarPedido(pedido),
                    icon: const Icon(Icons.close, size: 16),
                    label: const Text('Rechazar'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
                      side: const BorderSide(color: Colors.red),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                if (!esFirmaRemota) ...[
                  const SizedBox(width: 8),
                  // Aprobar → Ir a crear tarea (por ahora informa)
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _confirmarAprobar(pedido),
                      icon: const Icon(Icons.check, size: 16),
                      label: const Text('Aprobar'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmarAprobar(Map<String, dynamic> pedido) async {
    // Por ahora redirige a la web para convertir en tarea
    final url = 'https://federicogonzalez.net/logistica/tarea_crear.php?convertir_pedido=${pedido['id_pedido']}';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Widget _infoRow(IconData icon, String text, Color color) {
    return Row(children: [
      Icon(icon, size: 14, color: color),
      const SizedBox(width: 6),
      Expanded(child: Text(text, style: TextStyle(fontSize: 13, color: Colors.grey.shade800), overflow: TextOverflow.ellipsis)),
    ]);
  }

  String _formatFecha(dynamic fecha) {
    if (fecha == null || fecha.toString().isEmpty) return 'Sin fecha';
    try {
      final dt = DateTime.parse(fecha.toString());
      return '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}/${dt.year}  ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')} hs';
    } catch (_) { return fecha.toString(); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      appBar: AppBar(
        title: const Text('Bandeja de Pedidos'),
        backgroundColor: const Color(0xFF102A57),
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchPedidos),
        ],
      ),
      body: _isLoading
        ? const Center(child: CircularProgressIndicator(color: Color(0xFF102A57)))
        : _error.isNotEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.error_outline, size: 60, color: Colors.red),
              const SizedBox(height: 16),
              Text(_error, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(onPressed: _fetchPedidos, child: const Text('Reintentar')),
            ]))
          : _pedidos.isEmpty
            ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Icon(Icons.inbox_outlined, size: 80, color: Colors.grey.shade400),
                const SizedBox(height: 16),
                Text('¡Bandeja Vacía!', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.grey.shade600)),
                const SizedBox(height: 8),
                Text('No hay pedidos pendientes de aprobación.', style: TextStyle(color: Colors.grey.shade500)),
              ]))
            : RefreshIndicator(
                onRefresh: _fetchPedidos,
                child: ListView(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(color: const Color(0xFF102A57), borderRadius: BorderRadius.circular(20)),
                          child: Text('${_pedidos.length} pedidos pendientes',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                        ),
                      ]),
                    ),
                    ..._pedidos.map((p) => _buildPedidoCard(p)).toList(),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
    );
  }
}
