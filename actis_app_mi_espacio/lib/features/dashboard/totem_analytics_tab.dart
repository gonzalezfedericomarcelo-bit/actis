import 'package:flutter/material.dart';
import '../../core/api_client.dart';

class TotemAnalyticsTab extends StatefulWidget {
  const TotemAnalyticsTab({super.key});

  @override
  State<TotemAnalyticsTab> createState() => _TotemAnalyticsTabState();
}

class _TotemAnalyticsTabState extends State<TotemAnalyticsTab> {
  bool _isLoading = true;
  List<dynamic> _reportes = [];
  String _searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();
  DateTimeRange? _dateRange;
  
  Set<String> _selectedModos = {};
  Set<String> _selectedServicios = {};

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    _dateRange = DateTimeRange(start: today, end: today);
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() => _isLoading = true);
    String url = 'reportes.php?origen=TOTEM';
    if (_dateRange != null) {
      final start = _dateRange!.start.toIso8601String().substring(0, 10);
      final end = _dateRange!.end.toIso8601String().substring(0, 10);
      url += '&fecha_desde=$start&fecha_hasta=$end';
    }

    final res = await ApiClient.get(url);
    if (res['status'] == 'success' && mounted) {
      setState(() {
        // Filtrar automáticamente solo los del Tótem
        _reportes = res['data'];
        _isLoading = false;
      });
    } else {
      setState(() => _isLoading = false);
    }
  }

  void _mostrarModalDetalle(dynamic r) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, left: 16, right: 16, top: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text("Detalle de la Operación", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.person),
                title: Text(r['nombre']?.toString().isNotEmpty == true ? r['nombre'] : 'Paciente Desconocido'),
                subtitle: Text("DNI: ${r['dni'] ?? 'N/A'}"),
              ),
              ListTile(
                leading: const Icon(Icons.local_hospital),
                title: Text("Servicio: ${r['servicio'] ?? 'N/A'}"),
                subtitle: Text("Modo: ${r['modo'] ?? 'N/A'}"),
              ),
              if (r['token_iofa']?.toString().isNotEmpty == true)
                ListTile(
                  leading: const Icon(Icons.qr_code, color: Colors.green),
                  title: const Text("Validación Estricta QR"),
                  subtitle: Text("Token: ${r['token_iofa']}"),
                ),
              ListTile(
                leading: const Icon(Icons.timer),
                title: Text("Tiempo: ${r['tiempo_operacion'] ?? '0'} seg"),
                subtitle: Text("Fecha/Hora: ${r['fecha_hora']}"),
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
        );
      },
    );
  }

  void _mostrarModalFiltrosAvanzados() {
    final modos = _reportes.map((r) => r['modo']?.toString() ?? 'N/A').toSet().toList();
    final servicios = _reportes.map((r) => r['servicio']?.toString() ?? 'General').toSet().toList();
    modos.removeWhere((e) => e.trim().isEmpty);
    servicios.removeWhere((e) => e.trim().isEmpty);

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
                    const Text("Modos de Atención", style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8.0,
                      children: modos.map((m) {
                        final isSelected = _selectedModos.contains(m);
                        return FilterChip(
                          label: Text(m),
                          selected: isSelected,
                          selectedColor: const Color(0xFF144973).withValues(alpha: 0.2),
                          checkmarkColor: const Color(0xFF144973),
                          onSelected: (val) {
                            setModalState(() {
                              if (val) _selectedModos.add(m);
                              else _selectedModos.remove(m);
                            });
                          },
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 16),
                    const Text("Servicios", style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8.0,
                      children: servicios.map((s) {
                        final isSelected = _selectedServicios.contains(s);
                        return FilterChip(
                          label: Text(s),
                          selected: isSelected,
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
                    const SizedBox(height: 24),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () {
                              setModalState(() {
                                _selectedModos.clear();
                                _selectedServicios.clear();
                                _dateRange = DateTimeRange(start: DateTime.now(), end: DateTime.now());
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
          },
        );
      },
    );
  }

  Widget _buildQuickStats(List<dynamic> filtrados) {
    int total = filtrados.length;
    int val = filtrados.where((r) => r['modo'] == 'VALIDACION').length;
    int asis = filtrados.where((r) => r['modo'] == 'ASISTENCIA').length;
    
    double sumTiempo = 0;
    int countTiempo = 0;
    for (var r in filtrados) {
      final t = int.tryParse(r['tiempo_operacion']?.toString() ?? '0') ?? 0;
      if (t > 0) {
        sumTiempo += t;
        countTiempo++;
      }
    }
    String prom = countTiempo > 0 ? (sumTiempo / countTiempo).toStringAsFixed(1) : '0';

    return Padding(
      padding: const EdgeInsets.only(top: 16.0),
      child: Row(
        children: [
          _buildStatCol("Total", "$total"),
          _buildStatCol("Val.", "$val"),
          _buildStatCol("Asis.", "$asis"),
          _buildStatCol("T. Prom.", "${prom}s"),
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
    if (_isLoading) return const Center(child: CircularProgressIndicator());

    final filtrados = _reportes.where((r) {
      final query = _searchQuery.toLowerCase();
      final dni = (r['dni'] ?? '').toString().toLowerCase();
      final servicio = (r['servicio'] ?? '').toString().toLowerCase();
      final nombre = (r['nombre'] ?? '').toString().toLowerCase();
      final token = (r['token_iofa'] ?? '').toString().toLowerCase();
      final modo = (r['modo'] ?? '').toString().toLowerCase();
      final fecha = (r['fecha_hora'] ?? '').toString().toLowerCase();
      
      final matchQuery = _searchQuery.isEmpty || 
             dni.contains(query) || 
             servicio.contains(query) || 
             nombre.contains(query) || 
             token.contains(query) || 
             modo.contains(query) || 
             fecha.contains(query);

      final matchModo = _selectedModos.isEmpty || _selectedModos.contains(r['modo']?.toString());
      final matchServ = _selectedServicios.isEmpty || _selectedServicios.contains(r['servicio']?.toString() ?? 'General');

      return matchQuery && matchModo && matchServ;
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
                  const Text("Analítica del Tótem", style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
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
                  hintText: 'Buscar token, nombre, DNI...',
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
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text("Historial de Operaciones", style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              Text("Total: ${filtrados.length}", style: const TextStyle(color: Colors.grey)),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _fetchData,
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: filtrados.length,
              itemBuilder: (context, i) {
                final r = filtrados[i];
                return Card(
                  elevation: 2,
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    onTap: () => _mostrarModalDetalle(r),
                    leading: const CircleAvatar(backgroundColor: Colors.purple, child: Icon(Icons.developer_board, color: Colors.white)),
                    title: Text(
                      r['nombre']?.toString().isNotEmpty == true ? r['nombre'].toString().toUpperCase() : 'SIN NOMBRE REGISTRADO', 
                      style: const TextStyle(fontWeight: FontWeight.bold)
                    ),
                    subtitle: Text("DNI: ${r['dni'] ?? 'N/A'}\nServicio: ${r['servicio'] ?? 'General'} | Modo: ${r['modo']}\nHora: ${r['fecha_hora']}"),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                  ),
                );
              },
            ),
          ),
        )
      ],
    );
  }
}
