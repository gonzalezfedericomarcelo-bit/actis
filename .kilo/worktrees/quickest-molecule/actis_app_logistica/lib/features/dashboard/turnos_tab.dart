import 'package:flutter/material.dart';
import 'dart:async';
import '../../core/api_client.dart';
import '../../core/app_sync.dart';

class TurnosTab extends StatefulWidget {
  const TurnosTab({super.key});

  @override
  State<TurnosTab> createState() => _TurnosTabState();
}

class _TurnosTabState extends State<TurnosTab> {
  bool _isLoading = true;
  List<dynamic> _turnos = [];
  String _searchQuery = '';
  Set<String> _selectedEstadosRapidos = {'SACADOS HOY'}; // Filtros rápidos combinables
  
  // Filtros Avanzados
  DateTimeRange? _dateRange;
  Set<String> _selectedProfesionales = {};
  Set<String> _selectedServicios = {};
  Set<String> _selectedOperadores = {};

  final TextEditingController _searchCtrl = TextEditingController();

  StreamSubscription? _syncSub;
  final GlobalKey<RefreshIndicatorState> _refreshKey = GlobalKey<RefreshIndicatorState>();

  @override
  void initState() {
    super.initState();
    _fetchData(initial: true);
    _syncSub = AppSync.notifier.stream.listen((_) {
      if (mounted) {
        _refreshKey.currentState?.show();
      }
    });
  }

  @override
  void dispose() {
    _syncSub?.cancel();
    super.dispose();
  }

  Future<void> _fetchData({bool initial = true}) async {
    if (initial) setState(() => _isLoading = true);
    String url = 'turnos.php';
    if (_dateRange != null) {
      final start = _dateRange!.start.toIso8601String().substring(0, 10);
      final end = _dateRange!.end.toIso8601String().substring(0, 10);
      url += '?fecha_desde=$start&fecha_hasta=$end';
    }

    final res = await ApiClient.get(url);
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _turnos = res['data'] ?? [];
        _isLoading = false;
      });
    } else {
      setState(() => _isLoading = false);
    }
  }

  String _formatDate(String? rawDate) {
    if (rawDate == null || rawDate.isEmpty) return '';
    try {
      final parts = rawDate.split('-');
      if (parts.length != 3) return rawDate;
      final year = parts[0];
      final month = int.tryParse(parts[1]) ?? 1;
      final day = parts[2].length > 2 ? parts[2].substring(0, 2) : parts[2];
      
      const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
      final monthStr = meses[month - 1];
      
      return '$day $monthStr $year';
    } catch (e) {
      return rawDate;
    }
  }


  void _mostrarModalDetalle(dynamic t) {
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
                  title: Text("Para el: ${_formatDate(t['fecha_turno'])} a las ${t['hora_turno'] ?? '-'}"),
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
                    child: const Text('Cerrar'),
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

  void _mostrarModalFiltrosAvanzados() {
    final profesionales = _turnos.map((t) => t['profesional']?.toString() ?? '').where((s) => s.isNotEmpty).toSet().toList()..sort();
    final servicios = _turnos.map((t) => t['especialidad']?.toString() ?? '').where((s) => s.isNotEmpty).toSet().toList()..sort();
    
    // Obtener operarios que cargaron turnos
    final operarios = _turnos.map((t) {
      if (t['operador_externo']?.toString().isNotEmpty == true) return t['operador_externo'].toString();
      return t['creador_nombre']?.toString() ?? '';
    }).where((s) => s.isNotEmpty).toSet().toList()..sort();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return StatefulBuilder(builder: (context, setModalState) {
          return Padding(
            padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 24, left: 24, right: 24, top: 24),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text("Filtros Universales", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
                  const Divider(),
                  
                  const Text("Rango de Fechas", style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.date_range),
                      label: Text(_dateRange == null 
                          ? "Seleccionar Rango" 
                          : "${_dateRange!.start.day}/${_dateRange!.start.month}/${_dateRange!.start.year} - ${_dateRange!.end.day}/${_dateRange!.end.month}/${_dateRange!.end.year}"),
                      onPressed: () async {
                        final picked = await showDateRangePicker(
                          context: context,
                          firstDate: DateTime(2020),
                          lastDate: DateTime(2030),
                          initialDateRange: _dateRange,
                        );
                        if (picked != null) {
                          setModalState(() => _dateRange = picked);
                        }
                      },
                    ),
                  ),
                  const SizedBox(height: 16),
                  
                  const Text("Estados (Rápidos)", style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8.0,
                    children: ['TODOS', 'HOY', 'SACADOS HOY', 'CANCELADOS', 'ATENDIDOS', 'EN ESPERA'].map((e) {
                      return FilterChip(
                        label: Text(e),
                        selected: _selectedEstadosRapidos.contains(e),
                        selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                        checkmarkColor: const Color(0xFF144973),
                        onSelected: (val) {
                          setModalState(() {
                            if (val) {
                              if (e == 'TODOS') _selectedEstadosRapidos.clear();
                              else _selectedEstadosRapidos.remove('TODOS');
                              _selectedEstadosRapidos.add(e);
                            } else {
                              _selectedEstadosRapidos.remove(e);
                              if (_selectedEstadosRapidos.isEmpty) _selectedEstadosRapidos.add('TODOS');
                            }
                          });
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  const Text("Profesionales", style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8.0,
                    children: profesionales.map((p) {
                      return FilterChip(
                        label: Text(p),
                        selected: _selectedProfesionales.contains(p),
                        selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                        checkmarkColor: const Color(0xFF144973),
                        onSelected: (val) {
                          setModalState(() {
                            if (val) _selectedProfesionales.add(p);
                            else _selectedProfesionales.remove(p);
                          });
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  const Text("Servicios / Especialidades", style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8.0,
                    children: servicios.map((s) {
                      return FilterChip(
                        label: Text(s),
                        selected: _selectedServicios.contains(s),
                        selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                        checkmarkColor: const Color(0xFF144973),
                        onSelected: (val) {
                          setModalState(() {
                            if (val) _selectedServicios.add(s);
                            else _selectedServicios.remove(s);
                          });
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  const Text("Operadores", style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8.0,
                    children: operarios.map((o) {
                      return FilterChip(
                        label: Text(o),
                        selected: _selectedOperadores.contains(o),
                        selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                        checkmarkColor: const Color(0xFF144973),
                        onSelected: (val) {
                          setModalState(() {
                            if (val) _selectedOperadores.add(o);
                            else _selectedOperadores.remove(o);
                          });
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 24),
                  
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            setModalState(() {
                              _selectedEstadosRapidos = {'TODOS'};
                              _selectedProfesionales.clear();
                              _selectedServicios.clear();
                              _selectedOperadores.clear();
                              _dateRange = null;
                            });
                          },
                          child: const Text("LIMPIAR"),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF144973)),
                          onPressed: () {
                            setState(() {});
                            Navigator.pop(context);
                            _fetchData(); // Necesario por si cambió la fecha
                          },
                          child: const Text("APLICAR", style: TextStyle(color: Colors.white)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        });
      },
    );
  }

  Widget _buildQuickStats(List<dynamic> filtrados) {
    int total = filtrados.length;
    int atendidos = filtrados.where((t) => t['estado'] == 'ATENDIDO').length;
    int espera = filtrados.where((t) {
      final e = t['estado']?.toString() ?? '';
      return e == 'PENDIENTE' || e == 'PRESENTE' || e == 'EN CONSULTORIO';
    }).length;
    int cancelados = filtrados.where((t) {
      final e = t['estado']?.toString() ?? '';
      return e == 'CANCELADO' || e == 'AUSENTE';
    }).length;

    return Padding(
      padding: const EdgeInsets.only(top: 16.0),
      child: Row(
        children: [
          _buildStatCol("Total", "$total"),
          _buildStatCol("Atendidos", "$atendidos"),
          _buildStatCol("En Espera", "$espera"),
          _buildStatCol("Cancelados", "$cancelados"),
        ],
      ),
    );
  }

  Widget _buildStatCol(String label, String value) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12), textAlign: TextAlign.center),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final filtrados = _turnos.where((t) {
      final query = _searchQuery.toLowerCase();
      final apellido = (t['apellido'] ?? '').toString().toLowerCase();
      final nombre = (t['nombre'] ?? '').toString().toLowerCase();
      final dni = (t['dni'] ?? '').toString().toLowerCase();
      
      bool matchQuery = query.isEmpty || apellido.contains(query) || nombre.contains(query) || dni.contains(query);
      
      final todayStr = DateTime.now().toIso8601String().substring(0, 10);
      bool matchState = _selectedEstadosRapidos.contains('TODOS');
      
      final estado = t['estado']?.toString().toUpperCase() ?? '';

      if (!matchState) {
        bool matchHoy = false;
        bool matchSacadosHoy = false;
        bool matchCancelados = false;
        bool matchAtendidos = false;
        bool matchEspera = false;
        
        if (_selectedEstadosRapidos.contains('HOY')) {
          matchHoy = (t['fecha_turno'] == todayStr);
        }
        if (_selectedEstadosRapidos.contains('SACADOS HOY')) {
          final creado = t['creado_el']?.toString() ?? '';
          if (creado.length >= 10) {
            matchSacadosHoy = (creado.substring(0, 10) == todayStr);
          }
        }
        if (_selectedEstadosRapidos.contains('CANCELADOS')) {
          matchCancelados = (estado == 'CANCELADO');
        }
        if (_selectedEstadosRapidos.contains('ATENDIDOS')) {
          matchAtendidos = (estado == 'ATENDIDO');
        }
        if (_selectedEstadosRapidos.contains('EN ESPERA')) {
          matchEspera = (estado == 'PENDIENTE' || estado == 'PRESENTE' || estado == 'EN CONSULTORIO');
        }
        
        matchState = matchHoy || matchSacadosHoy || matchCancelados || matchAtendidos || matchEspera;
      }

      // Evaluar Filtros Avanzados Locales
      bool matchProfesional = _selectedProfesionales.isEmpty || _selectedProfesionales.contains(t['profesional']?.toString());
      bool matchServicio = _selectedServicios.isEmpty || _selectedServicios.contains(t['especialidad']?.toString());
      
      String operario = (t['operador_externo']?.toString().isNotEmpty == true) 
          ? t['operador_externo'].toString() 
          : (t['creador_nombre']?.toString() ?? '');
      bool matchOperador = _selectedOperadores.isEmpty || _selectedOperadores.contains(operario);

      return matchQuery && matchState && matchProfesional && matchServicio && matchOperador;
    }).toList();

    // Ordenar por los más recientes primero (ID descendente)
    filtrados.sort((a, b) {
      int idA = int.tryParse(a['id']?.toString() ?? '0') ?? 0;
      int idB = int.tryParse(b['id']?.toString() ?? '0') ?? 0;
      return idB.compareTo(idA);
    });

    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(16.0),
          decoration: const BoxDecoration(
            color: Color(0xFF144973),
            borderRadius: BorderRadius.only(bottomLeft: Radius.circular(20), bottomRight: Radius.circular(20)),
          ),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text("Gestión de Turnos", style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                  IconButton(
                    icon: const Icon(Icons.filter_list, color: Colors.white),
                    onPressed: _mostrarModalFiltrosAvanzados,
                  ),
                ],
              ),
              _buildQuickStats(filtrados),
              const SizedBox(height: 16),
              TextField(
                controller: _searchCtrl,
                onChanged: (val) => setState(() => _searchQuery = val),
                decoration: InputDecoration(
                  hintText: 'Buscar paciente o DNI...',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: Colors.white,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 0),
                ),
              ),
            ],
          ),
        ),

        Expanded(
          child: filtrados.isEmpty
            ? const Center(child: Text("No hay turnos con este filtro."))
            : RefreshIndicator(
                key: _refreshKey,
                onRefresh: () => _fetchData(initial: false),
                child: ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: filtrados.length,
                  itemBuilder: (context, i) {
                    final t = filtrados[i];
                    final estado = t['estado']?.toString().toUpperCase() ?? 'PENDIENTE';
                    Color estadoColor = Colors.grey;
                    if (estado == 'AUTORIZADO') estadoColor = Colors.green;
                    if (estado == 'CANCELADO') estadoColor = Colors.red;
                    if (estado == 'ATENDIDO') estadoColor = Colors.teal;
                    if (estado == 'LLAMANDO' || estado == 'EN CONSULTORIO') estadoColor = Colors.blue;
                    if (estado == 'PRESENTE' || estado == 'PENDIENTE') estadoColor = Colors.orange;

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: InkWell(
                        onTap: () => _mostrarModalDetalle(t),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    "${t['hora_turno']} - Turno ${t['numero_turno']}",
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: estadoColor.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: estadoColor),
                                    ),
                                    child: Text(
                                      estado,
                                      style: TextStyle(color: estadoColor, fontWeight: FontWeight.bold, fontSize: 12),
                                    ),
                                  )
                                ],
                              ),
                              const Divider(),
                              Text("Paciente: ${t['apellido']}, ${t['nombre']}", style: const TextStyle(fontSize: 16)),
                              Text("DNI: ${t['dni']}", style: const TextStyle(color: Colors.grey)),
                              const SizedBox(height: 8),
                              Text("Médico: ${t['profesional']}", style: const TextStyle(fontWeight: FontWeight.w500)),
                              Text("Especialidad: ${t['especialidad']}", style: const TextStyle(fontStyle: FontStyle.italic)),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
        ),
      ],
    );
  }
}
