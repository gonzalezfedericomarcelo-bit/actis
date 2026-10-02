import 'package:flutter/material.dart';
import '../../core/api_client.dart';
import 'ascensores_detalle_screen.dart';

class AscensoresTab extends StatefulWidget {
  const AscensoresTab({super.key});

  @override
  State<AscensoresTab> createState() => _AscensoresTabState();
}

class _AscensoresTabState extends State<AscensoresTab> {
  bool _isLoading = true;
  List<dynamic> _ascensores = [];

  @override
  void initState() {
    super.initState();
    _fetchAscensores();
  }

  Future<void> _fetchAscensores() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiClient.get('ascensores.php');
      if (res['status'] == 'success' && mounted) {
        setState(() {
          _ascensores = res['data'] ?? [];
          _isLoading = false;
        });
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _reportarIncidencia(Map<String, dynamic> ascensor, String titulo, String descripcion, String prioridad, int usuarioId) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) {
        Future.microtask(() async {
          try {
            final res = await ApiClient.post('ascensor_crear_incidencia.php', {
              'id_ascensor': ascensor['id'],
              'titulo': titulo,
              'descripcion': descripcion,
              'prioridad': prioridad,
              'usuario_id': usuarioId,
            });

            if (mounted) Navigator.pop(dialogCtx); // cerrar loader

            if (res['status'] == 'success') {
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(res['message'] ?? 'Incidencia creada con éxito'), backgroundColor: Colors.green),
                );
                _fetchAscensores();
              }
            } else {
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(res['message'] ?? 'Error desconocido'), backgroundColor: Colors.red),
                );
              }
            }
          } catch (e) {
            if (mounted) Navigator.pop(dialogCtx); // cerrar loader
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Error de red al crear la incidencia.'), backgroundColor: Colors.red),
              );
            }
          }
        });
        return const Center(child: CircularProgressIndicator());
      },
    );
  }

  void _solicitarPin(Map<String, dynamic> ascensor, String titulo, String descripcion, String prioridad) {
    String currentPin = "";
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setModalState) {
            Widget buildKey(String val) {
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(4.0),
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      backgroundColor: Colors.grey.shade200,
                      foregroundColor: Colors.black,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () {
                      if (currentPin.length < 10) {
                        setModalState(() => currentPin += val);
                      }
                    },
                    child: Text(val, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                  ),
                ),
              );
            }

            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 24, right: 24, top: 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Autorización de Firma', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  const Text('Ingrese su PIN de seguridad para enviar el PDF a la empresa.', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey)),
                  const SizedBox(height: 24),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300, width: 2), borderRadius: BorderRadius.circular(12)),
                    child: Text(
                      currentPin.replaceAll(RegExp(r'.'), '• '),
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 32, letterSpacing: 8, color: Colors.black),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Column(
                    children: [
                      Row(children: [buildKey('1'), buildKey('2'), buildKey('3')]),
                      Row(children: [buildKey('4'), buildKey('5'), buildKey('6')]),
                      Row(children: [buildKey('7'), buildKey('8'), buildKey('9')]),
                      Row(
                        children: [
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.all(4.0),
                              child: ElevatedButton(
                                style: ElevatedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 20),
                                  backgroundColor: Colors.red.shade100,
                                  foregroundColor: Colors.red,
                                  elevation: 0,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                onPressed: () {
                                  if (currentPin.isNotEmpty) {
                                    setModalState(() => currentPin = currentPin.substring(0, currentPin.length - 1));
                                  }
                                },
                                child: const Icon(Icons.backspace),
                              ),
                            ),
                          ),
                          buildKey('0'),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.all(4.0),
                              child: ElevatedButton(
                                style: ElevatedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 20),
                                  backgroundColor: Colors.blue.shade600,
                                  foregroundColor: Colors.white,
                                  elevation: 0,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                onPressed: () async {
                                  if (currentPin.isEmpty) return;
                                  Navigator.pop(ctx);
                                  
                                  showDialog(
                                    context: context,
                                    barrierDismissible: false,
                                    builder: (dialogCtx) {
                                      Future.microtask(() async {
                                        try {
                                          final res = await ApiClient.post('validar_pin.php', {'pin': currentPin});
                                          if (mounted) Navigator.pop(dialogCtx); // cerrar loader
                                          
                                          if (res['status'] == 'success') {
                                            int uid = res['usuario_id'] is String ? int.parse(res['usuario_id']) : res['usuario_id'];
                                            _reportarIncidencia(ascensor, titulo, descripcion, prioridad, uid);
                                          } else {
                                            if (mounted) {
                                              ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message']), backgroundColor: Colors.red));
                                            }
                                          }
                                        } catch (e) {
                                          if (mounted) Navigator.pop(dialogCtx);
                                          if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Error de conexión validando PIN'), backgroundColor: Colors.red));
                                        }
                                      });
                                      return const Center(child: CircularProgressIndicator());
                                    }
                                  );
                                },
                                child: const Icon(Icons.check),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            );
          }
        );
      },
    );
  }

  void _mostrarDialogoFalla(Map<String, dynamic> ascensor) {
    final titleCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    String prioridad = 'media';

    showDialog(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: Row(
                children: [
                  const Icon(Icons.warning_amber_rounded, color: Colors.orange, size: 28),
                  const SizedBox(width: 10),
                  Expanded(child: Text("Reportar Falla", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 20))),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text("Equipo: ${ascensor['nombre']}", style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.black54)),
                    const SizedBox(height: 16),
                    TextField(
                      controller: titleCtrl,
                      decoration: InputDecoration(
                        labelText: "Motivo Breve",
                        hintText: "Ej: Puerta trabada",
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        filled: true,
                        fillColor: Colors.grey.shade50,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: descCtrl,
                      maxLines: 3,
                      decoration: InputDecoration(
                        labelText: "Observaciones",
                        hintText: "Detalle lo que sucede...",
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        filled: true,
                        fillColor: Colors.grey.shade50,
                      ),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: prioridad,
                      decoration: InputDecoration(
                        labelText: "Clasificación",
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        filled: true,
                        fillColor: Colors.grey.shade50,
                      ),
                      items: const [
                        DropdownMenuItem(value: 'baja', child: Text('🔵 Mantenimiento Preventivo')),
                        DropdownMenuItem(value: 'media', child: Text('🟡 Falla Menor')),
                        DropdownMenuItem(value: 'alta', child: Text('🟠 Falla Grave (Inoperativo)')),
                        DropdownMenuItem(value: 'emergencia', child: Text('🔴 Emergencia')),
                      ],
                      onChanged: (val) {
                        setStateDialog(() => prioridad = val ?? 'media');
                      },
                    )
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: const Text("Cancelar", style: TextStyle(color: Colors.grey)),
                ),
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () {
                    if (titleCtrl.text.trim().isEmpty || descCtrl.text.trim().isEmpty) {
                      ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Complete todos los campos')));
                      return;
                    }
                    Navigator.pop(ctx);
                    _solicitarPin(ascensor, titleCtrl.text.trim(), descCtrl.text.trim(), prioridad);
                  },
                  icon: const Icon(Icons.arrow_forward),
                  label: const Text("Siguiente"),
                ),
              ],
            );
          }
        );
      },
    );
  }

  Color _getEstadoColor(String estado) {
    switch (estado.toLowerCase()) {
      case 'operativo': return Colors.green;
      case 'falla': return Colors.red;
      case 'mantenimiento': return Colors.orange;
      default: return Colors.grey;
    }
  }

  IconData _getEstadoIcon(String estado) {
    switch (estado.toLowerCase()) {
      case 'operativo': return Icons.check_circle;
      case 'falla': return Icons.error;
      case 'mantenimiento': return Icons.build;
      default: return Icons.help;
    }
  }

  Widget _buildAscensorCard(Map<String, dynamic> ascensor) {
    final estado = ascensor['estado'] ?? 'Desconocido';
    final color = _getEstadoColor(estado);
    
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: Colors.grey.shade200)),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
            child: Icon(_getEstadoIcon(estado), color: color, size: 28),
          ),
          title: Text(ascensor['nombre'] ?? 'Ascensor', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: Colors.black87)),
          subtitle: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.circle, size: 10, color: color),
                  const SizedBox(width: 4),
                  Text(estado.toUpperCase(), style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 1)),
                ],
              ),
            ],
          ),
          children: [
            Container(
              padding: const EdgeInsets.all(16.0),
              decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: const BorderRadius.vertical(bottom: Radius.circular(16))),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.business, size: 16, color: Colors.grey),
                      const SizedBox(width: 6),
                      Expanded(child: Text("Marca: ${ascensor['marca'] ?? '-'}", style: const TextStyle(color: Colors.black54, fontWeight: FontWeight.w600))),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.map, size: 16, color: Colors.grey),
                      const SizedBox(width: 6),
                      Expanded(child: Text("Ubicación: ${ascensor['ubicacion'] ?? '-'}", style: const TextStyle(color: Colors.black54, fontWeight: FontWeight.w600))),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.calendar_today, size: 16, color: Colors.grey),
                      const SizedBox(width: 6),
                      Expanded(child: Text("Últ. Rev: ${ascensor['ultima_revision'] ?? '-'}", style: const TextStyle(color: Colors.black54, fontWeight: FontWeight.w600))),
                    ],
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: Colors.red.shade50,
                        foregroundColor: Colors.red.shade700,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.red.shade200)),
                      ),
                      onPressed: () => _mostrarDialogoFalla(ascensor),
                      icon: const Icon(Icons.report_problem),
                      label: const Text('REPORTAR FALLA', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1)),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: Colors.blue.shade50,
                        foregroundColor: Colors.blue.shade700,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.blue.shade200)),
                      ),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (context) => AscensoresDetalleScreen(ascensor: ascensor)),
                        );
                      },
                      icon: const Icon(Icons.history),
                      label: const Text('VER BITÁCORA', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_ascensores.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.elevator, size: 64, color: Colors.grey),
            const SizedBox(height: 16),
            const Text("No hay unidades registradas", style: TextStyle(fontSize: 18, color: Colors.grey, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _fetchAscensores,
              icon: const Icon(Icons.refresh),
              label: const Text('Reintentar'),
              style: ElevatedButton.styleFrom(backgroundColor: Colors.black, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            )
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchAscensores,
      color: Colors.black,
      child: ListView.builder(
        padding: const EdgeInsets.only(top: 16, bottom: 32),
        itemCount: _ascensores.length,
        itemBuilder: (context, index) {
          return _buildAscensorCard(_ascensores[index] as Map<String, dynamic>);
        },
      ),
    );
  }
}
