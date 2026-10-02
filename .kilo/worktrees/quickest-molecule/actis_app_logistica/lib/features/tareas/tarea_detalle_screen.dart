import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api_client.dart';

class TareaDetalleScreen extends StatefulWidget {
  final int idTarea;
  const TareaDetalleScreen({super.key, required this.idTarea});

  @override
  State<TareaDetalleScreen> createState() => _TareaDetalleScreenState();
}

class _TareaDetalleScreenState extends State<TareaDetalleScreen>
    with SingleTickerProviderStateMixin {
  Map<String, dynamic>? _tarea;
  List<dynamic> _actualizaciones = [];
  List<dynamic> _adjuntosIni = [];
  List<dynamic> _adjuntosFin = [];
  bool _isLoading = true;
  String _error = '';
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _fetchDetalle();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchDetalle() async {
    setState(() { _isLoading = true; _error = ''; });
    final response = await ApiClient.get('tarea_detalle.php?id=${widget.idTarea}');
    if (response['status'] == 'success') {
      setState(() {
        _tarea = Map<String, dynamic>.from(response['tarea']);
        _actualizaciones = response['actualizaciones'] ?? [];
        _adjuntosIni = response['adjuntos_ini'] ?? [];
        _adjuntosFin = response['adjuntos_fin'] ?? [];
        _isLoading = false;
      });
    } else {
      setState(() { _error = response['message'] ?? 'Error'; _isLoading = false; });
    }
  }

  // ── Colores y helpers ────────────────────────────────────────────────────────
  Color _colorEstado(String e) {
    const m = {
      'verificada': Colors.green, 'modificacion_requerida': Colors.red,
      'finalizada_tecnico': Colors.orange, 'en_proceso': Colors.blue,
      'asignada': Colors.grey, 'cancelada': Colors.red, 'en_reserva': Colors.black87,
    };
    return m[e] ?? Colors.indigo;
  }

  String _textoEstado(String e) {
    const m = {
      'verificada': 'Verificada', 'modificacion_requerida': 'Corrección',
      'finalizada_tecnico': 'P/Revisión', 'en_proceso': 'En Proceso',
      'asignada': 'Asignada', 'cancelada': 'Cancelada', 'en_reserva': 'En Reserva',
    };
    return m[e] ?? e;
  }

  Color _colorPrioridad(String p) {
    const m = {'urgente': Colors.red, 'alta': Colors.orange, 'media': Colors.blue, 'baja': Colors.green};
    return m[p] ?? Colors.grey;
  }

  String _formatFechaLarga(dynamic f) {
    if (f == null || f.toString().isEmpty) return 'Sin fecha';
    try {
      final dt = DateTime.parse(f.toString());
      const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
      return '${dt.day} ${meses[dt.month-1]} ${dt.year}, ${dt.hour.toString().padLeft(2,'0')}:${dt.minute.toString().padLeft(2,'0')} hs';
    } catch (_) { return f.toString(); }
  }

  String _formatFechaCorta(dynamic f) {
    if (f == null || f.toString().isEmpty) return 'S/F';
    try {
      final dt = DateTime.parse(f.toString());
      return '${dt.day.toString().padLeft(2,'0')}/${dt.month.toString().padLeft(2,'0')}/${dt.year}';
    } catch (_) { return f.toString(); }
  }

  bool _estaVencida() {
    final t = _tarea;
    if (t == null) return false;
    final lim = t['fecha_limite'];
    if (lim == null || lim.toString().isEmpty) return false;
    if (['verificada','cancelada'].contains(t['estado'])) return false;
    try { return DateTime.parse(lim.toString()).isBefore(DateTime.now()); }
    catch (_) { return false; }
  }

  Widget _chip(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withValues(alpha: 0.4))),
    child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12)),
  );

  Widget _infoTile(IconData icon, String label, String? value, {Color iconColor = Colors.blueGrey}) {
    if (value == null || value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 16, color: iconColor),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 14)),
        ])),
      ]),
    );
  }

  // ── TAB 1: Información ───────────────────────────────────────────────────────
  Widget _buildTabInfo() {
    final t = _tarea!;
    final estado = t['estado'] ?? '';
    final prioridad = t['prioridad'] ?? '';
    final colorEst = _colorEstado(estado);
    final colorPrio = _colorPrioridad(prioridad);
    final vencida = _estaVencida();

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // Chips de estado + prioridad
        Wrap(spacing: 8, runSpacing: 8, children: [
          _chip(_textoEstado(estado), colorEst),
          _chip(prioridad.toUpperCase(), colorPrio),
          if (vencida) _chip('⚠ VENCIDA', Colors.red),
        ]),
        const SizedBox(height: 16),

        // Título
        Text(t['titulo'] ?? 'Sin título',
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),

        // Descripción
        if ((t['descripcion'] ?? '').isNotEmpty) ...[
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(10)),
            child: Text(t['descripcion'], style: const TextStyle(fontSize: 14, height: 1.5)),
          ),
          const SizedBox(height: 16),
        ],

        const Divider(),
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 10),
          child: Text('DETALLES OPERATIVOS', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1)),
        ),

        _infoTile(Icons.engineering, 'Técnico Responsable', t['responsable_nombre'], iconColor: Colors.blue),
        _infoTile(Icons.group, 'Colaboradores', t['colaboradores_nombres'], iconColor: Colors.indigo),
        _infoTile(Icons.person_outline, 'Creado por', t['creador_nombre']),
        _infoTile(Icons.label_outline, 'Categoría', t['categoria_nombre'], iconColor: Colors.purple),
        _infoTile(Icons.location_on_outlined, 'Destino',
          [t['destino_nombre'], t['area_nombre']].where((s) => s != null && s.isNotEmpty).join(' › '),
          iconColor: Colors.red),
        _infoTile(Icons.calendar_today, 'Fecha de Creación', _formatFechaLarga(t['fecha_creacion'])),
        if ((t['fecha_limite'] ?? '').isNotEmpty)
          _infoTile(Icons.alarm, 'Fecha Límite', _formatFechaCorta(t['fecha_limite']),
            iconColor: vencida ? Colors.red : Colors.orange),
        if ((t['fecha_cierre'] ?? '').isNotEmpty)
          _infoTile(Icons.check_circle_outline, 'Fecha de Cierre', _formatFechaLarga(t['fecha_cierre']), iconColor: Colors.green),

        // Bloque del Pedido de Origen
        if ((t['numero_orden_pedido'] ?? '').isNotEmpty) ...[
          const Divider(),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Text('PEDIDO DE ORIGEN', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1)),
          ),
          _infoTile(Icons.receipt_long, 'N° de Orden', t['numero_orden_pedido'], iconColor: Colors.blueGrey),
          _infoTile(Icons.person, 'Solicitante', t['solicitante_real_nombre']),
          _infoTile(Icons.phone, 'Teléfono', t['solicitante_telefono'], iconColor: Colors.green),
          _infoTile(Icons.email_outlined, 'Email', t['solicitante_email']),
          _infoTile(Icons.calendar_today, 'Fecha del Pedido', _formatFechaCorta(t['fecha_pedido'])),
          if ((t['fecha_requerida'] ?? '').isNotEmpty)
            _infoTile(Icons.event, 'Fecha Requerida', _formatFechaCorta(t['fecha_requerida'])),
          if ((t['descripcion_pedido'] ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.blue.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.blue.shade100)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Descripción del Pedido', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.blue)),
                const SizedBox(height: 6),
                Text(t['descripcion_pedido'], style: const TextStyle(fontSize: 14, height: 1.4)),
              ]),
            ),
          ],
        ],

        // Notas / Bitácora
        if ((t['bitacora'] ?? '').isNotEmpty || (t['notas_internas'] ?? '').isNotEmpty) ...[
          const Divider(),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Text('NOTAS INTERNAS', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1)),
          ),
          if ((t['bitacora'] ?? '').isNotEmpty)
            _infoTile(Icons.notes, 'Bitácora', t['bitacora'], iconColor: Colors.teal),
          if ((t['notas_internas'] ?? '').isNotEmpty)
            _infoTile(Icons.sticky_note_2_outlined, 'Notas Internas', t['notas_internas'], iconColor: Colors.amber),
        ],

        const SizedBox(height: 30),
      ]),
    );
  }

  // ── TAB 2: Historial de Novedades ────────────────────────────────────────────
  Widget _buildTabHistorial() {
    if (_actualizaciones.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.history, size: 60, color: Colors.grey.shade400),
        const SizedBox(height: 12),
        Text('Sin novedades registradas', style: TextStyle(color: Colors.grey.shade500)),
      ]));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemCount: _actualizaciones.length,
      itemBuilder: (ctx, i) {
        final act = _actualizaciones[i];
        final esReserva = act['causo_reserva'].toString() == '1';
        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: esReserva ? Colors.black.withValues(alpha: 0.05) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: esReserva ? Colors.black26 : Colors.grey.shade200),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              CircleAvatar(
                radius: 14,
                backgroundColor: Colors.blue.shade100,
                child: Text((act['usuario_nombre'] ?? '?')[0].toUpperCase(),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.blue)),
              ),
              const SizedBox(width: 8),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(act['usuario_nombre'] ?? 'Desconocido',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                Text(_formatFechaLarga(act['fecha_actualizacion']),
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
              ])),
              if (esReserva)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(color: Colors.black12, borderRadius: BorderRadius.circular(6)),
                  child: const Text('En Reserva', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                ),
            ]),
            const SizedBox(height: 10),
            const Divider(height: 1),
            const SizedBox(height: 10),
            Text(act['contenido'] ?? '', style: const TextStyle(fontSize: 14, height: 1.4)),
          ]),
        );
      },
    );
  }

  // ── TAB 3: Adjuntos ──────────────────────────────────────────────────────────
  Widget _buildTabAdjuntos() {
    final baseUrl = 'https://federicogonzalez.net/logistica/';

    Widget adjuntoTile(Map a, String tipo) {
      final nombre = a['nombre_archivo'] ?? 'archivo';
      final url = baseUrl + (a['ruta_archivo'] ?? '');
      return ListTile(
        leading: Icon(Icons.attach_file, color: tipo == 'inicial' ? Colors.blue : Colors.green),
        title: Text(nombre, style: const TextStyle(fontSize: 14)),
        subtitle: Text(tipo == 'inicial' ? 'Documento Inicial' : 'Documento Final',
          style: TextStyle(fontSize: 11, color: tipo == 'inicial' ? Colors.blue : Colors.green)),
        trailing: IconButton(
          icon: const Icon(Icons.open_in_new, size: 18),
          onPressed: () async {
            final uri = Uri.parse(url);
            if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
          },
        ),
      );
    }

    final todos = [
      ..._adjuntosIni.map((a) => adjuntoTile(Map<String, dynamic>.from(a), 'inicial')),
      ..._adjuntosFin.map((a) => adjuntoTile(Map<String, dynamic>.from(a), 'final')),
    ];

    if (todos.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.folder_open, size: 60, color: Colors.grey.shade400),
        const SizedBox(height: 12),
        Text('Sin adjuntos', style: TextStyle(color: Colors.grey.shade500)),
      ]));
    }

    return ListView(padding: const EdgeInsets.all(8), children: todos);
  }

  // ── Build principal ─────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final t = _tarea;
    final titulo = t?['titulo'] ?? 'Detalle de Tarea';
    final estado = t?['estado'] ?? '';
    final colorEst = _colorEstado(estado);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      appBar: AppBar(
        title: Text('Tarea #${widget.idTarea}'),
        backgroundColor: const Color(0xFF102A57),
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchDetalle),
          // Botón para abrir en el navegador por si necesitan acciones avanzadas
          IconButton(
            icon: const Icon(Icons.open_in_browser),
            tooltip: 'Abrir en navegador',
            onPressed: () async {
              final url = 'https://federicogonzalez.net/logistica/tarea_ver.php?id=${widget.idTarea}';
              final uri = Uri.parse(url);
              if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
            },
          ),
        ],
        bottom: _isLoading || _error.isNotEmpty ? null : TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(icon: Icon(Icons.info_outline, size: 18), text: 'Info'),
            Tab(icon: Icon(Icons.history, size: 18), text: 'Historial'),
            Tab(icon: Icon(Icons.attach_file, size: 18), text: 'Adjuntos'),
          ],
        ),
      ),
      body: _isLoading
        ? const Center(child: CircularProgressIndicator(color: Color(0xFF102A57)))
        : _error.isNotEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.error_outline, size: 60, color: Colors.red),
              const SizedBox(height: 16),
              Text(_error, textAlign: TextAlign.center, style: const TextStyle(fontSize: 16)),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _fetchDetalle,
                icon: const Icon(Icons.refresh),
                label: const Text('Reintentar'),
              ),
            ]))
          : Column(
              children: [
                // Banner de estado (colorstrip arriba)
                Container(
                  color: colorEst.withValues(alpha: 0.08),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(children: [
                    Container(width: 4, height: 36, decoration: BoxDecoration(color: colorEst, borderRadius: BorderRadius.circular(4))),
                    const SizedBox(width: 12),
                    Expanded(child: Text(titulo, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15), maxLines: 2, overflow: TextOverflow.ellipsis)),
                    _chip(_textoEstado(estado), colorEst),
                  ]),
                ),
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      _buildTabInfo(),
                      _buildTabHistorial(),
                      _buildTabAdjuntos(),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}
