import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api_client.dart';
import 'tarea_detalle_screen.dart';

class TareasListaScreen extends StatefulWidget {
  const TareasListaScreen({super.key});

  @override
  State<TareasListaScreen> createState() => _TareasListaScreenState();
}

class _TareasListaScreenState extends State<TareasListaScreen>
    with SingleTickerProviderStateMixin {
  List<dynamic> _todasTareas = [];
  List<dynamic> _tareasFiltradas = [];
  bool _isLoading = true;
  String _error = '';
  String _filtroEstado = 'todas';
  String _busqueda = '';
  late TabController _tabController;

  final List<_TabFiltro> _tabs = [
    _TabFiltro('Todas', 'todas', Icons.layers),
    _TabFiltro('En Proceso', 'en_proceso', Icons.play_circle_outline),
    _TabFiltro('Asignadas', 'asignada', Icons.assignment),
    _TabFiltro('Revisión', 'finalizada_tecnico', Icons.rate_review),
    _TabFiltro('Verificadas', 'verificada', Icons.check_circle),
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        setState(() {
          _filtroEstado = _tabs[_tabController.index].estado;
          _aplicarFiltros();
        });
      }
    });
    _fetchTareas();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchTareas() async {
    setState(() { _isLoading = true; _error = ''; });
    final response = await ApiClient.get('tareas_lista.php');
    if (response['status'] == 'success') {
      setState(() {
        _todasTareas = response['tareas'] ?? [];
        _aplicarFiltros();
        _isLoading = false;
      });
    } else {
      setState(() { _error = response['message'] ?? 'Error desconocido'; _isLoading = false; });
    }
  }

  void _aplicarFiltros() {
    setState(() {
      _tareasFiltradas = _todasTareas.where((t) {
        final estado = t['estado'] ?? '';
        final titulo = (t['titulo'] ?? '').toString().toLowerCase();
        final asignado = (t['nombre_asignado'] ?? '').toString().toLowerCase();
        
        bool passEstado = _filtroEstado == 'todas' || estado == _filtroEstado;
        bool passBusqueda = _busqueda.isEmpty ||
            titulo.contains(_busqueda.toLowerCase()) ||
            asignado.contains(_busqueda.toLowerCase()) ||
            (t['id_tarea']?.toString() ?? '').contains(_busqueda);
        return passEstado && passBusqueda;
      }).toList();
    });
  }

  // Estadísticas
  int _contarPorEstado(String estado) {
    if (estado == 'todas') return _todasTareas.length;
    return _todasTareas.where((t) => t['estado'] == estado).length;
  }

  Color _colorEstado(String estado) {
    switch (estado) {
      case 'verificada': return Colors.green;
      case 'modificacion_requerida': return Colors.red;
      case 'finalizada_tecnico': return Colors.orange;
      case 'en_proceso': return Colors.blue;
      case 'asignada': return Colors.grey;
      case 'cancelada': return Colors.red.shade900;
      default: return Colors.indigo;
    }
  }

  String _textoEstado(String estado) {
    const map = {
      'verificada': 'Verificada',
      'modificacion_requerida': 'Corrección',
      'finalizada_tecnico': 'Revisión',
      'en_proceso': 'En Proceso',
      'asignada': 'Asignada',
      'cancelada': 'Cancelada',
      'en_reserva': 'En Reserva',
    };
    return map[estado] ?? estado;
  }

  Color _colorPrioridad(String p) {
    switch (p) {
      case 'urgente': return Colors.red;
      case 'alta': return Colors.orange;
      case 'media': return Colors.blue;
      default: return Colors.green;
    }
  }

  bool _estaVencida(Map<String, dynamic> t) {
    final limite = t['fecha_limite'];
    final estado = t['estado'] ?? '';
    if (limite == null || limite.toString().isEmpty) return false;
    if (['verificada', 'cancelada'].contains(estado)) return false;
    try {
      return DateTime.parse(limite.toString()).isBefore(DateTime.now());
    } catch (_) { return false; }
  }

  String _formatFecha(dynamic fecha) {
    if (fecha == null || fecha.toString().isEmpty) return 'S/F';
    try {
      final dt = DateTime.parse(fecha.toString());
      return '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}/${dt.year}';
    } catch (_) { return fecha.toString(); }
  }

  Future<void> _abrirTareaDetalle(int idTarea) async {
    Navigator.push(context, MaterialPageRoute(
      builder: (_) => TareaDetalleScreen(idTarea: idTarea),
    ));
  }

  Widget _buildStats() {
    final total = _todasTareas.length;
    final pendientes = _todasTareas.where((t) => !['verificada', 'cancelada'].contains(t['estado'])).length;
    final atrasadas = _todasTareas.where((t) => _estaVencida(t)).length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Row(children: [
        _statChip(Icons.layers_outlined, total.toString(), 'Total', Colors.indigo),
        const SizedBox(width: 8),
        _statChip(Icons.hourglass_empty, pendientes.toString(), 'Pendientes', Colors.orange),
        const SizedBox(width: 8),
        _statChip(Icons.timer_off_outlined, atrasadas.toString(), 'Atrasadas', Colors.red),
      ]),
    );
  }

  Widget _statChip(IconData icon, String valor, String label, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 6),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(valor, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
            Text(label, style: TextStyle(fontSize: 10, color: color.withValues(alpha: 0.8))),
          ]),
        ]),
      ),
    );
  }

  Widget _buildTareaCard(Map<String, dynamic> t) {
    final estado = t['estado'] ?? 'asignada';
    final prioridad = t['prioridad'] ?? 'baja';
    final vencida = _estaVencida(t);
    final colorEst = _colorEstado(estado);
    final colorPrio = _colorPrioridad(prioridad);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: vencida ? Colors.red.withValues(alpha: 0.5) : colorEst.withValues(alpha: 0.2),
          width: vencida ? 2 : 1,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => _abrirTareaDetalle(t['id_tarea']),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            // Row de cabecera
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: colorEst.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(_textoEstado(estado),
                  style: TextStyle(color: colorEst, fontWeight: FontWeight.bold, fontSize: 11)),
              ),
              const SizedBox(width: 8),
              if (vencida)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(6)),
                  child: const Row(children: [
                    Icon(Icons.alarm_off, size: 10, color: Colors.white),
                    SizedBox(width: 3),
                    Text('VENCIDA', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                  ]),
                ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: colorPrio.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(6)),
                child: Text(prioridad.toUpperCase(),
                  style: TextStyle(color: colorPrio, fontWeight: FontWeight.bold, fontSize: 10)),
              ),
            ]),
            
            const SizedBox(height: 10),
            
            // Número y Título
            Row(children: [
              Text('#${t['id_tarea']}',
                style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.bold, color: Colors.grey)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(t['titulo'] ?? 'Sin título',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
              ),
            ]),
            
            const SizedBox(height: 8),
            
            // Técnico asignado
            if ((t['nombre_asignado'] ?? '').isNotEmpty)
              _infoRow(Icons.engineering, t['nombre_asignado'], Colors.blueGrey),
            
            // Destino
            if ((t['destino_nombre'] ?? '').isNotEmpty) ...[
              const SizedBox(height: 4),
              _infoRow(Icons.location_on_outlined,
                '${t['destino_nombre']}${(t['area_nombre'] ?? '').isNotEmpty ? ' › ${t['area_nombre']}' : ''}',
                Colors.red.shade400),
            ],
            
            // Categoría
            if ((t['nombre_categoria'] ?? '').isNotEmpty) ...[
              const SizedBox(height: 4),
              _infoRow(Icons.label_outline, t['nombre_categoria'], Colors.purple.shade400),
            ],
            
            const SizedBox(height: 8),
            const Divider(height: 1),
            const SizedBox(height: 8),
            
            // Fechas + pedido
            Row(children: [
              Icon(Icons.calendar_today, size: 12, color: Colors.grey.shade500),
              const SizedBox(width: 4),
              Text('Creado: ${_formatFecha(t['fecha_creacion'])}',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
              if ((t['numero_orden'] ?? '').isNotEmpty) ...[
                const SizedBox(width: 10),
                Icon(Icons.receipt_long, size: 12, color: Colors.grey.shade500),
                const SizedBox(width: 4),
                Text('Pedido ${t['numero_orden']}',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
              ],
              const Spacer(),
              // Tap para ver en web
              GestureDetector(
                onTap: () => _abrirTareaDetalle(t['id_tarea']),
                child: Row(children: [
                  Text('Ver detalle', style: TextStyle(fontSize: 11, color: Colors.blue.shade600, fontWeight: FontWeight.bold)),
                  Icon(Icons.chevron_right, size: 14, color: Colors.blue.shade600),
                ]),
              ),
            ]),
          ]),
        ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String text, Color color) {
    return Row(children: [
      Icon(icon, size: 13, color: color),
      const SizedBox(width: 5),
      Expanded(child: Text(text, style: TextStyle(fontSize: 12, color: Colors.grey.shade800), overflow: TextOverflow.ellipsis)),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      appBar: AppBar(
        title: const Text('Lista de Tareas'),
        backgroundColor: const Color(0xFF102A57),
        foregroundColor: Colors.white,
        actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchTareas)],
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          indicatorColor: Colors.white,
          tabs: _tabs.map((tab) => Tab(
            child: Row(children: [
              Icon(tab.icon, size: 14),
              const SizedBox(width: 5),
              Text(tab.label),
              const SizedBox(width: 5),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(10)),
                child: Text('${_contarPorEstado(tab.estado)}', style: const TextStyle(fontSize: 10)),
              ),
            ]),
          )).toList(),
        ),
      ),
      body: _isLoading
        ? const Center(child: CircularProgressIndicator(color: Color(0xFF102A57)))
        : _error.isNotEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Icon(Icons.error_outline, size: 60, color: Colors.red),
              const SizedBox(height: 16),
              Text(_error, textAlign: TextAlign.center),
              ElevatedButton(onPressed: _fetchTareas, child: const Text('Reintentar')),
            ]))
          : Column(
              children: [
                _buildStats(),
                
                // Búsqueda
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                  child: TextField(
                    decoration: InputDecoration(
                      hintText: 'Buscar por título, técnico o ID...',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: _busqueda.isNotEmpty
                        ? IconButton(icon: const Icon(Icons.clear), onPressed: () {
                            setState(() { _busqueda = ''; _aplicarFiltros(); });
                          })
                        : null,
                      filled: true,
                      fillColor: Colors.white,
                      contentPadding: const EdgeInsets.symmetric(vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                    ),
                    onChanged: (val) { setState(() { _busqueda = val; _aplicarFiltros(); }); },
                  ),
                ),
                
                // Lista
                Expanded(
                  child: _tareasFiltradas.isEmpty
                    ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.task_alt, size: 60, color: Colors.grey.shade400),
                        const SizedBox(height: 16),
                        Text('No hay tareas en esta categoría', style: TextStyle(color: Colors.grey.shade600)),
                      ]))
                    : RefreshIndicator(
                        onRefresh: _fetchTareas,
                        child: ListView.builder(
                          padding: const EdgeInsets.only(top: 8, bottom: 24),
                          itemCount: _tareasFiltradas.length,
                          itemBuilder: (ctx, i) => _buildTareaCard(_tareasFiltradas[i]),
                        ),
                      ),
                ),
              ],
            ),
    );
  }
}

class _TabFiltro {
  final String label;
  final String estado;
  final IconData icon;
  const _TabFiltro(this.label, this.estado, this.icon);
}
