import 'package:flutter/material.dart';
import 'dart:async';
import '../../core/api_client.dart';
import '../../core/app_sync.dart';

class PacientesTab extends StatefulWidget {
  const PacientesTab({super.key});

  @override
  State<PacientesTab> createState() => _PacientesTabState();
}

class _PacientesTabState extends State<PacientesTab> {
  bool _isLoading = true;
  List<dynamic> _pacientes = [];
  Map<String, dynamic> _stats = {};
  
  // Filtros
  String _searchQuery = '';
  Set<String> _selectedObrasSociales = {};
  DateTimeRange? _dateRange;

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
    String url = 'pacientes.php?';
    
    if (_searchQuery.isNotEmpty) url += 'dni=$_searchQuery&';
    // Nota: Como la API probablemente espere un string para obra_social (o tal vez no la soporte múltiple), 
    // pasamos la primera si hay una, o adaptamos según sea necesario. 
    // Para no romper la API actual, podemos pasar la primera seleccionada si hay 1, o ignorarlo en API y filtrar localmente.
    // Lo mejor es no pasarlo a la API si hay múltiples, y filtrar localmente.
    if (_selectedObrasSociales.length == 1) {
      url += 'obra_social=${_selectedObrasSociales.first}&';
    }
    if (_dateRange != null) {
      final start = _dateRange!.start.toIso8601String().substring(0, 10);
      final end = _dateRange!.end.toIso8601String().substring(0, 10);
      url += 'fecha_desde=$start&fecha_hasta=$end&';
    }

    final res = await ApiClient.get(url);
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _pacientes = res['data'] ?? [];
        if (res['stats'] != null && res['stats'].isNotEmpty) {
          _stats = res['stats'];
        }
        _isLoading = false;
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _mostrarFiltros() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                  bottom: MediaQuery.of(context).viewInsets.bottom + 24,
                  left: 24, right: 24, top: 24),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text("Filtros Universales", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
                    const Divider(),
                    
                    const Text("Rango de Fechas (Registro)", style: TextStyle(fontWeight: FontWeight.bold)),
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
                            lastDate: DateTime.now(),
                            initialDateRange: _dateRange,
                            builder: (context, child) => Theme(
                              data: Theme.of(context).copyWith(
                                colorScheme: const ColorScheme.light(primary: Color(0xFF144973), onPrimary: Colors.white, onSurface: Colors.black),
                              ),
                              child: child!,
                            ),
                          );
                          if (picked != null) {
                            setModalState(() => _dateRange = picked);
                          }
                        },
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    const Text("Obras Sociales", style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8.0,
                      children: ['FUERZAS ARMADAS', 'FUERZAS de SEGURIDAD', 'IOSFA'].map((e) {
                        return FilterChip(
                          label: Text(e),
                          selected: _selectedObrasSociales.contains(e),
                          selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                          checkmarkColor: const Color(0xFF144973),
                          onSelected: (val) {
                            setModalState(() {
                              if (val) _selectedObrasSociales.add(e);
                              else _selectedObrasSociales.remove(e);
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
                              setState(() {
                                _selectedObrasSociales.clear();
                                _dateRange = null;
                                _searchQuery = '';
                                _searchCtrl.clear();
                              });
                              Navigator.pop(context);
                              _fetchData();
                            },
                            child: const Text("LIMPIAR"),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.pop(context);
                              setState(() {}); // Actualiza la lista filtrada localmente
                              _fetchData(); // Refresca API si cambió fecha
                            },
                            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF144973)),
                            child: const Text("APLICAR", style: TextStyle(color: Colors.white)),
                          ),
                        ),
                      ],
                    )
                  ],
                ),
              ),
            );
          }
        );
      }
    );
  }

  void _mostrarDetalle(dynamic p) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, left: 16, right: 16, top: 24),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text("Perfil del Paciente", style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
                const Divider(),
                ListTile(
                  leading: CircleAvatar(
                    backgroundColor: const Color(0xFF144973).withValues(alpha: 0.1),
                    radius: 30,
                    child: const Icon(Icons.person, color: Color(0xFF144973), size: 35),
                  ),
                  title: Text("${p['apellido'] ?? ''}, ${p['nombre'] ?? ''}", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                  subtitle: Text("DNI: ${p['dni'] ?? '-'}\nAfiliado: ${p['hc'] ?? '-'}"),
                  isThreeLine: true,
                ),
                const SizedBox(height: 10),
                _buildDetailRow(Icons.health_and_safety, "Obra Social", p['obra_social'] ?? 'No registrada'),
                _buildDetailRow(Icons.phone, "Teléfono", p['telefono'] ?? 'No registrado'),
                _buildDetailRow(Icons.email, "Email", p['email'] ?? 'No registrado'),
                _buildDetailRow(Icons.calendar_today, "Nacimiento", p['fecha_nacimiento'] ?? 'No registrada'),
                const Divider(),
                _buildDetailRow(Icons.local_hospital, "Total Turnos", "${p['total_turnos'] ?? 0} turnos solicitados", onTap: () => _verHistorialTurnos(p)),
                _buildDetailRow(Icons.history, "Último Turno", p['ultimo_turno'] ?? 'Sin historial'),
                _buildDetailRow(Icons.app_registration, "Registrado el", p['fecha_registro'] ?? '-'),
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF144973), padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text("Cerrar", style: TextStyle(color: Colors.white, fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        );
      },
    );
  }

  void _verHistorialTurnos(dynamic p) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return _HistorialTurnosModal(paciente: p);
      },
    );
  }

  Widget _buildDetailRow(IconData icon, String label, String value, {VoidCallback? onTap}) {
    final row = Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0, horizontal: 8.0),
      child: Row(
        children: [
          Icon(icon, color: Colors.blueGrey, size: 20),
          const SizedBox(width: 12),
          Text("$label: ", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black54)),
          Expanded(child: Text(value, style: TextStyle(fontWeight: FontWeight.w500, color: onTap != null ? const Color(0xFF144973) : Colors.black, decoration: onTap != null ? TextDecoration.underline : TextDecoration.none))),
        ],
      ),
    );
    if (onTap != null) {
      return InkWell(onTap: onTap, child: row);
    }
    return row;
  }

  Widget _buildQuickStats(List<dynamic> pacientes, Map<String, dynamic> stats) {
    String totalStr = stats['total']?.toString() ?? '0';
    String nuevosStr = stats['nuevos_mes']?.toString() ?? '0';
    
    int conHistorial = pacientes.where((p) => (int.tryParse(p['total_turnos']?.toString() ?? '0') ?? 0) > 0).length;
    int sinHistorial = pacientes.length - conHistorial;

    return Padding(
      padding: const EdgeInsets.only(top: 16.0),
      child: Row(
        children: [
          _buildStatCol("Totales", totalStr),
          _buildStatCol("Nuevos Mes", nuevosStr),
          _buildStatCol("Con Turnos", "$conHistorial"),
          _buildStatCol("Sin Turnos", "$sinHistorial"),
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
    // Calculamos localmente los pacientes por si hay filtros aplicados
    final _pacientesLocales = _pacientes.where((p) {
      if (_selectedObrasSociales.isNotEmpty) {
        if (!_selectedObrasSociales.contains(p['obra_social']?.toString())) {
          return false;
        }
      }
      return true;
    }).toList();

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
                  const Text("Directorio de Pacientes", style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                  IconButton(
                    icon: const Icon(Icons.filter_list, color: Colors.white),
                    onPressed: _mostrarFiltros,
                  ),
                ],
              ),
              if (_stats.isNotEmpty && _searchQuery.isEmpty && _selectedObrasSociales.isEmpty && _dateRange == null)
                _buildQuickStats(_pacientes, _stats)
              else
                _buildQuickStats(_pacientesLocales, {
                  'total': _pacientesLocales.length,
                  'nuevos_mes': 'N/A'
                }),
              const SizedBox(height: 16),
              TextField(
                controller: _searchCtrl,
                decoration: InputDecoration(
                  hintText: 'Buscar paciente (DNI, Nombre)...',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: Colors.white,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 0),
                ),
                onSubmitted: (val) {
                  _searchQuery = val;
                  _fetchData();
                },
              ),
            ],
          ),
        ),

        // Lista de Pacientes
        Expanded(
          child: (() {
            if (_isLoading) return const Center(child: CircularProgressIndicator());
            
            final _pacientesLocales = _pacientes.where((p) {
              if (_selectedObrasSociales.isNotEmpty) {
                // Filtrar localmente si tenemos múltiples obras sociales seleccionadas
                if (!_selectedObrasSociales.contains(p['obra_social']?.toString())) {
                  return false;
                }
              }
              return true;
            }).toList();

            if (_pacientesLocales.isEmpty) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.person_off, size: 60, color: Colors.grey.shade400),
                    const SizedBox(height: 16),
                    Text("No se encontraron pacientes.", style: TextStyle(color: Colors.grey.shade600, fontSize: 16)),
                  ],
                ),
              );
            }
            
            return RefreshIndicator(
              key: _refreshKey,
              onRefresh: () => _fetchData(initial: false),
              child: ListView.builder(
                itemCount: _pacientesLocales.length,
                itemBuilder: (context, i) {
                  final p = _pacientesLocales[i];
                          return Card(
                            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(color: Colors.grey.shade200)
                            ),
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: const Color(0xFF144973).withValues(alpha: 0.1),
                                child: const Icon(Icons.person, color: Color(0xFF144973)),
                              ),
                              title: Text("${p['apellido'] ?? ''}, ${p['nombre'] ?? ''}", style: const TextStyle(fontWeight: FontWeight.bold)),
                              subtitle: Text("DNI: ${p['dni'] ?? '-'}\nOS: ${p['obra_social'] ?? '-'}"),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.history, color: Colors.blueGrey, size: 16),
                                  Text("${p['total_turnos'] ?? 0}", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                ],
                              ),
                              isThreeLine: true,
                              onTap: () => _mostrarDetalle(p),
                            ),
                  );
                },
              ),
            );
          })(),
        )
      ],
    );
  }
}

class _HistorialTurnosModal extends StatefulWidget {
  final dynamic paciente;
  const _HistorialTurnosModal({required this.paciente});

  @override
  State<_HistorialTurnosModal> createState() => _HistorialTurnosModalState();
}

class _HistorialTurnosModalState extends State<_HistorialTurnosModal> {
  bool _isLoading = true;
  List<dynamic> _turnos = [];

  @override
  void initState() {
    super.initState();
    _fetchTurnos();
  }

  Future<void> _fetchTurnos() async {
    final res = await ApiClient.get('turnos.php?paciente_id=${widget.paciente['id']}');
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
                  leading: const Icon(Icons.medical_services, color: Colors.blueGrey),
                  title: Text("Profesional: ${t['profesional'] ?? '-'}"),
                  subtitle: Text("Práctica/Motivo: ${t['motivo_visita'] ?? t['especialidad'] ?? '-'}"),
                ),
                ListTile(
                  leading: const Icon(Icons.calendar_today, color: Colors.blueGrey),
                  title: Text("Fecha: ${t['fecha_turno'] ?? '-'}"),
                  subtitle: Text("Hora: ${t['hora_turno'] ?? '-'}"),
                ),
                ListTile(
                  leading: const Icon(Icons.info_outline, color: Colors.blueGrey),
                  title: Text("Estado: ${t['estado'] ?? '-'}"),
                  subtitle: Text("Operador: ${t['operador_externo'] ?? t['creador_nombre'] ?? '-'}"),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF144973), padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text("Cerrar", style: TextStyle(color: Colors.white, fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 16),
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
                const Icon(Icons.history, color: Color(0xFF144973), size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    "Historial de Turnos", 
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF144973))
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
                child: Text("No se encontraron turnos para este paciente.", style: TextStyle(fontSize: 16, color: Colors.grey)),
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
                      child: const Icon(Icons.calendar_month, color: Colors.blueGrey),
                    ),
                    title: Text("${t['fecha_turno']} - ${t['hora_turno']}", style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text("${t['especialidad']} - ${t['profesional']}"),
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

