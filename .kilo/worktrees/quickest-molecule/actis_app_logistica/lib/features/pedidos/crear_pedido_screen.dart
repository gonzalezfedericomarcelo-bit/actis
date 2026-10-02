import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:signature/signature.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api_client.dart';
import 'dart:typed_data';

class CrearPedidoScreen extends StatefulWidget {
  const CrearPedidoScreen({super.key});

  @override
  State<CrearPedidoScreen> createState() => _CrearPedidoScreenState();
}

class _CrearPedidoScreenState extends State<CrearPedidoScreen> {
  final _formKey = GlobalKey<FormState>();
  
  // Controllers
  final _tituloController = TextEditingController();
  final _descController = TextEditingController();
  final _nombreController = TextEditingController();
  final _telefonoController = TextEditingController();
  final _emailController = TextEditingController();
  final SignatureController _signatureController = SignatureController(
    penStrokeWidth: 3,
    penColor: const Color(0xFF144973),
    exportBackgroundColor: Colors.transparent,
  );

  // Datos
  List<dynamic> _destinos = [];
  List<dynamic> _areas = [];
  List<dynamic> _areasFiltradas = [];
  bool _isLoadingData = true;
  bool _isSubmitting = false;

  // Selecciones
  String? _selectedDestino;
  String? _selectedArea;
  String _selectedPrioridad = 'rutina';
  bool _esFirmaRemota = false;
  bool _forzarPresencial = false;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  @override
  void dispose() {
    _tituloController.dispose();
    _descController.dispose();
    _nombreController.dispose();
    _telefonoController.dispose();
    _emailController.dispose();
    _signatureController.dispose();
    super.dispose();
  }

  Future<void> _fetchData() async {
    setState(() => _isLoadingData = true);
    final response = await ApiClient.get('destinos_areas.php');
    if (response['status'] == 'success') {
      setState(() {
        _destinos = response['destinos'] ?? [];
        _areas = response['areas'] ?? [];
        _isLoadingData = false;
      });
    } else {
      setState(() => _isLoadingData = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${response['message']}')),
        );
      }
    }
  }

  void _onDestinoChanged(String? value) {
    setState(() {
      _selectedDestino = value;
      _selectedArea = null;
      _areasFiltradas = _areas.where((a) => a['id_destino'].toString() == value).toList();
      
      final destinoData = _destinos.firstWhere((d) => d['id_destino'].toString() == value, orElse: () => null);
      if (destinoData != null && destinoData['firma_remota'].toString() == '1') {
        _esFirmaRemota = true;
      } else {
        _esFirmaRemota = false;
      }
      _forzarPresencial = false;
    });
  }

  Future<void> _submitForm() async {
    if (!_formKey.currentState!.validate()) return;
    
    String? firmaBase64;
    
    if (!_esFirmaRemota || _forzarPresencial) {
      if (_signatureController.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Debe firmar el pedido en el recuadro indicado.')),
        );
        return;
      }
      
      final Uint8List? data = await _signatureController.toPngBytes();
      if (data != null) {
        firmaBase64 = 'data:image/png;base64,' + base64Encode(data);
      }
    }

    setState(() => _isSubmitting = true);

    final prefs = await SharedPreferences.getInstance();
    final idUsuarioStr = prefs.getString('user_id');
    final idUsuario = idUsuarioStr != null ? int.tryParse(idUsuarioStr) : 0;

    final data = {
      'id_usuario': idUsuario,
      'titulo_pedido': _tituloController.text,
      'id_destino_interno': _selectedDestino,
      'id_area': _selectedArea,
      'prioridad': _selectedPrioridad,
      'descripcion_sintomas': _descController.text,
      'solicitante_real_nombre': _nombreController.text,
      'solicitante_telefono': _telefonoController.text,
      'email_solicitante_externo': _emailController.text,
      'firma_solicitante_base64': firmaBase64,
    };

    final response = await ApiClient.post('pedido_crear.php', data);

    setState(() => _isSubmitting = false);

    if (!mounted) return;

    if (response['status'] == 'success') {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(children: [Icon(Icons.check_circle, color: Colors.green), SizedBox(width: 10), Text('¡Orden Generada!')]),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('N° DE ORDEN', style: TextStyle(color: Colors.grey.shade600, fontSize: 12, fontWeight: FontWeight.bold)),
              const SizedBox(height: 5),
              Text(response['numero_orden'] ?? '', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.green)),
              const SizedBox(height: 15),
              Text(_tituloController.text, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(ctx); // Cierra modal
                Navigator.pop(context); // Vuelve al layout
              },
              child: const Text('VOLVER AL INICIO'),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: ${response['message']}')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Crear Pedido de Trabajo'),
        backgroundColor: const Color(0xFF144973),
        foregroundColor: Colors.white,
      ),
      body: _isLoadingData
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF144973)))
          : GestureDetector(
              onTap: () => FocusScope.of(context).unfocus(),
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.all(16.0),
                  children: [
                    
                    // --- SECCIÓN 1: Ubicación ---
                    const _SectionHeader(icon: Icons.map, title: '1. Ubicación y Prioridad'),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      decoration: const InputDecoration(labelText: 'Destino / Sede (*)', border: OutlineInputBorder()),
                      initialValue: _selectedDestino,
                      items: _destinos.map((d) => DropdownMenuItem<String>(
                        value: d['id_destino'].toString(),
                        child: Text(d['nombre']),
                      )).toList(),
                      onChanged: _onDestinoChanged,
                      validator: (val) => val == null ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      decoration: const InputDecoration(labelText: 'Área Solicitante (*)', border: OutlineInputBorder()),
                      initialValue: _selectedArea,
                      items: _areasFiltradas.map((a) => DropdownMenuItem<String>(
                        value: a['id_area'].toString(),
                        child: Text(a['nombre']),
                      )).toList(),
                      onChanged: (val) => setState(() => _selectedArea = val),
                      validator: (val) => val == null ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 16),
                    const Text('Nivel de Prioridad (*)', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                    Wrap(
                      spacing: 8,
                      children: [
                        ChoiceChip(
                          label: const Text('Rutina'),
                          selected: _selectedPrioridad == 'rutina',
                          onSelected: (val) { if(val) setState(() => _selectedPrioridad = 'rutina'); },
                        ),
                        ChoiceChip(
                          label: const Text('Importante'),
                          selectedColor: Colors.orange.shade200,
                          selected: _selectedPrioridad == 'importante',
                          onSelected: (val) { if(val) setState(() => _selectedPrioridad = 'importante'); },
                        ),
                        ChoiceChip(
                          label: const Text('URGENTE'),
                          selectedColor: Colors.red.shade200,
                          selected: _selectedPrioridad == 'urgente',
                          onSelected: (val) { if(val) setState(() => _selectedPrioridad = 'urgente'); },
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // --- SECCIÓN 2: Detalles ---
                    const _SectionHeader(icon: Icons.edit, title: '2. Detalles del Pedido'),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _tituloController,
                      decoration: const InputDecoration(labelText: 'Título Resumido (*)', border: OutlineInputBorder(), hintText: 'Ej: Reparar puerta'),
                      validator: (val) => val == null || val.isEmpty ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _descController,
                      maxLines: 4,
                      decoration: const InputDecoration(labelText: 'Descripción Completa (*)', border: OutlineInputBorder()),
                      validator: (val) => val == null || val.isEmpty ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 24),

                    // --- SECCIÓN 3: Firmas y Notificaciones ---
                    const _SectionHeader(icon: Icons.assignment_ind, title: '3. Conformidad y Solicitante'),
                    const SizedBox(height: 16),
                    
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: Colors.blue.shade50, borderRadius: BorderRadius.circular(8), border: Border.all(color: Colors.blue.shade200)),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Row(children: [Icon(Icons.info_outline, size: 16, color: Colors.blue), SizedBox(width: 8), Text('Datos de Notificación', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.blue))]),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(labelText: 'Correo del Solicitante (*)', border: OutlineInputBorder(), fillColor: Colors.white, filled: true),
                            validator: (val) => val == null || !val.contains('@') ? 'Correo inválido' : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    if (_esFirmaRemota)
                      SwitchListTile(
                        title: const Text('El solicitante está presente para firmar ahora'),
                        value: _forzarPresencial,
                        onChanged: (val) => setState(() => _forzarPresencial = val),
                        contentPadding: EdgeInsets.zero,
                      ),
                    
                    if (!_esFirmaRemota || _forzarPresencial) ...[
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _nombreController,
                        decoration: const InputDecoration(labelText: 'Aclaración Solicitante (*)', border: OutlineInputBorder()),
                        validator: (val) => val == null || val.isEmpty ? 'Requerido' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _telefonoController,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'Teléfono WhatsApp', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 24),
                      const Text('Firma Presencial (*)', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
                      const SizedBox(height: 8),
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.green, width: 2),
                          borderRadius: BorderRadius.circular(12),
                          color: Colors.grey.shade100,
                        ),
                        child: Column(
                          children: [
                            Signature(
                              controller: _signatureController,
                              height: 150,
                              backgroundColor: Colors.transparent,
                            ),
                            Container(
                              decoration: const BoxDecoration(
                                color: Colors.green,
                                borderRadius: BorderRadius.only(bottomLeft: Radius.circular(10), bottomRight: Radius.circular(10)),
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  TextButton.icon(
                                    onPressed: () => _signatureController.clear(),
                                    icon: const Icon(Icons.clear, color: Colors.white),
                                    label: const Text('Limpiar Firma', style: TextStyle(color: Colors.white)),
                                  )
                                ],
                              ),
                            )
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 40),
                    SizedBox(
                      height: 55,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF144973), foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                        onPressed: _isSubmitting ? null : _submitForm,
                        child: _isSubmitting 
                          ? const CircularProgressIndicator(color: Colors.white)
                          : const Text('CREAR PEDIDO', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                      ),
                    ),
                    const SizedBox(height: 30),
                  ],
                ),
              ),
            ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  const _SectionHeader({required this.icon, required this.title});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: const Color(0xFF144973)),
            const SizedBox(width: 8),
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF144973))),
          ],
        ),
        const Divider(),
      ],
    );
  }
}
