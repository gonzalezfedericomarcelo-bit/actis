import 'package:flutter/material.dart';
import 'package:signature/signature.dart';
import '../../core/api_client.dart';
import 'dart:convert';
import 'dart:typed_data';
import 'package:url_launcher/url_launcher.dart';

class AscensoresDetalleScreen extends StatefulWidget {
  final Map<String, dynamic> ascensor;

  const AscensoresDetalleScreen({super.key, required this.ascensor});

  @override
  State<AscensoresDetalleScreen> createState() => _AscensoresDetalleScreenState();
}

class _AscensoresDetalleScreenState extends State<AscensoresDetalleScreen> {
  bool _isLoading = true;
  List<dynamic> _historial = [];

  final SignatureController _signatureController = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
    exportBackgroundColor: Colors.white,
  );

  @override
  void initState() {
    super.initState();
    _fetchHistorial();
  }

  Future<void> _fetchHistorial() async {
    setState(() => _isLoading = true);
    try {
      final idAscensor = widget.ascensor['id'] ?? widget.ascensor['id_ascensor'];
      final res = await ApiClient.get('ascensor_historial.php?id_ascensor=$idAscensor');
      if (res['status'] == 'success' && mounted) {
        setState(() {
          _historial = res['data'] ?? [];
          _isLoading = false;
        });
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _dibujarFirmaFullscreen() async {
    await showDialog(
      context: context,
      builder: (ctx) {
        return Dialog(
          insetPadding: const EdgeInsets.all(10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: Container(
            width: double.infinity,
            height: MediaQuery.of(ctx).size.height * 0.7,
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text("Dibuje su firma", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: () => Navigator.pop(ctx),
                    )
                  ],
                ),
                const SizedBox(height: 10),
                Expanded(
                  child: Container(
                    decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade400, width: 2), borderRadius: BorderRadius.circular(12)),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Signature(
                        controller: _signatureController,
                        backgroundColor: Colors.grey.shade100,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _signatureController.clear(),
                        style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                        child: const Text("BORRAR", style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.black, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 16)),
                        onPressed: () => Navigator.pop(ctx),
                        child: const Text("ACEPTAR FIRMA", style: TextStyle(fontWeight: FontWeight.bold)),
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

  void _mostrarFirmaModal(Map<String, dynamic> ticket) {
    final _tecnicoController = TextEditingController();
    final _detalleController = TextEditingController();
    String _estado = 'en_proceso';
    _signatureController.clear();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(ctx).viewInsets.bottom,
                left: 20, right: 20, top: 20
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text("Registrar Visita Técnica", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  TextField(
                    controller: _tecnicoController,
                    decoration: const InputDecoration(labelText: 'Técnico / Empresa', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _detalleController,
                    decoration: const InputDecoration(labelText: 'Detalle de Tareas', border: OutlineInputBorder()),
                    maxLines: 2,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _estado,
                    decoration: const InputDecoration(labelText: 'Estado', border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(value: 'en_proceso', child: Text('🟠 En Proceso')),
                      DropdownMenuItem(value: 'resuelto', child: Text('🟢 Resuelto')),
                    ],
                    onChanged: (val) => setModalState(() => _estado = val ?? 'en_proceso'),
                  ),
                  const SizedBox(height: 15),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.grey.shade200,
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade400))
                    ),
                    onPressed: () async {
                      await _dibujarFirmaFullscreen();
                      setModalState(() {});
                    },
                    icon: Icon(_signatureController.isNotEmpty ? Icons.check_circle : Icons.draw, color: _signatureController.isNotEmpty ? Colors.green : Colors.black),
                    label: Text(_signatureController.isNotEmpty ? "Firma Registrada (Tocar para cambiar)" : "TOCAR PARA DIBUJAR FIRMA", style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                    ),
                    onPressed: () async {
                      if (_tecnicoController.text.isEmpty || _detalleController.text.isEmpty) {
                        ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Complete todos los campos')));
                        return;
                      }
                      if (_signatureController.isEmpty) {
                        ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('La firma es requerida')));
                        return;
                      }

                      final Uint8List? signatureBytes = await _signatureController.toPngBytes();
                      if (signatureBytes == null) return;
                      
                      final String signatureBase64 = base64Encode(signatureBytes);
                      
                      Navigator.pop(ctx);
                      _solicitarPin(ticket['id_incidencia'], _tecnicoController.text, _detalleController.text, _estado, signatureBase64);
                    },
                    child: const Text("GUARDAR VISITA"),
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            );
          }
        );
      }
    );
  }

  void _solicitarPin(dynamic idIncidencia, String tecnico, String detalle, String estado, String firmaBase64) {
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
                    ),
                    onPressed: () {
                      if (currentPin.length < 10) setModalState(() => currentPin += val);
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
                  const Text('Autorización Guardia', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  const Text('PIN de seguridad para autorizar la visita.', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey)),
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
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.red.shade100, foregroundColor: Colors.red, padding: const EdgeInsets.symmetric(vertical: 20), elevation: 0),
                                onPressed: () {
                                  if (currentPin.isNotEmpty) setModalState(() => currentPin = currentPin.substring(0, currentPin.length - 1));
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
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.blue.shade600, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 20), elevation: 0),
                                onPressed: () async {
                                  if (currentPin.isEmpty) return;
                                  Navigator.pop(ctx);
                                  
                                  showDialog(
                                    context: context,
                                    barrierDismissible: false,
                                    builder: (dialogCtx) {
                                      // Ejecutar en background y cerrar este contexto
                                      Future.microtask(() async {
                                        try {
                                          final res = await ApiClient.post('validar_pin.php', {'pin': currentPin});
                                          if (res['status'] == 'success') {
                                            int uid = res['usuario_id'] is String ? int.parse(res['usuario_id']) : res['usuario_id'];
                                            
                                            // Call registrar visita API
                                            final resVisita = await ApiClient.post('ascensor_registrar_visita.php', {
                                              'id_incidencia': idIncidencia,
                                              'tecnico': tecnico,
                                              'detalle_trabajo': detalle,
                                              'estado': estado,
                                              'id_receptor': uid,
                                              'firma_base64': firmaBase64
                                            });
                                            
                                            if (mounted) Navigator.pop(dialogCtx); // close loader
                                            
                                            if (resVisita['status'] == 'success') {
                                              if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(resVisita['message']), backgroundColor: Colors.green));
                                              _fetchHistorial();
                                            } else {
                                              if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(resVisita['message'] ?? 'Error registrando'), backgroundColor: Colors.red));
                                            }

                                          } else {
                                            if (mounted) Navigator.pop(dialogCtx); // close loader
                                            if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message']), backgroundColor: Colors.red));
                                          }
                                        } catch (e) {
                                          if (mounted) Navigator.pop(dialogCtx); // close loader
                                          if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Error de conexión'), backgroundColor: Colors.red));
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade100,
      appBar: AppBar(
        title: Text('Bitácora: ${widget.ascensor['nombre']}', style: const TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _historial.isEmpty
              ? const Center(child: Text("El historial está limpio.", style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _historial.length,
                  itemBuilder: (ctx, index) {
                    final h = _historial[index];
                    final estado = h['estado'] ?? 'desconocido';
                    final bool isResuelto = estado == 'resuelto';
                    final visitas = h['visitas'] ?? [];

                    return Card(
                      elevation: 2,
                      margin: const EdgeInsets.only(bottom: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text("Ticket #${h['id_incidencia']}", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.blueAccent)),
                                Row(
                                  children: [
                                    if (h['url_pdf'] != null)
                                      IconButton(
                                        padding: EdgeInsets.zero,
                                        constraints: const BoxConstraints(),
                                        icon: const Icon(Icons.picture_as_pdf, color: Colors.red, size: 20),
                                        onPressed: () async {
                                          final uri = Uri.parse(h['url_pdf']);
                                          if (await canLaunchUrl(uri)) {
                                            await launchUrl(uri, mode: LaunchMode.externalApplication);
                                          }
                                        },
                                      ),
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: isResuelto ? Colors.green.shade100 : Colors.orange.shade100,
                                        borderRadius: BorderRadius.circular(6)
                                      ),
                                      child: Text(estado.toUpperCase(), style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: isResuelto ? Colors.green.shade800 : Colors.orange.shade800)),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(h['titulo'] ?? '', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 4),
                            Text(h['descripcion_problema'] ?? '', style: TextStyle(color: Colors.grey.shade700)),
                            const SizedBox(height: 16),
                            const Text("Visitas Técnicas:", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
                            const SizedBox(height: 8),
                            if (visitas.isEmpty)
                              const Text("Sin visitas registradas", style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                            ...visitas.map<Widget>((v) => Container(
                              margin: const EdgeInsets.only(bottom: 6),
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(8), border: Border.all(color: Colors.grey.shade200)),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(v['tecnico_nombre'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                                      Text(v['fecha_visita']?.substring(0, 10) ?? '', style: const TextStyle(fontSize: 10, color: Colors.grey)),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(v['descripcion_trabajo'] ?? '', style: const TextStyle(fontSize: 12)),
                                  const SizedBox(height: 4),
                                  Text("Auth: ${v['guardia'] ?? ''}", style: TextStyle(fontSize: 10, color: Colors.blue.shade700, fontWeight: FontWeight.w600)),
                                ],
                              ),
                            )).toList(),
                            
                            const SizedBox(height: 12),
                            if (!isResuelto)
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(backgroundColor: Colors.blue.shade50, foregroundColor: Colors.blue.shade700, elevation: 0),
                                  icon: const Icon(Icons.edit_document),
                                  label: const Text("REGISTRAR VISITA"),
                                  onPressed: () => _mostrarFirmaModal(h),
                                ),
                              )
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
