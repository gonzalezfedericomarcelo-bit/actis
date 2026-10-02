import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api_client.dart';

class ConfigTab extends StatefulWidget {
  const ConfigTab({super.key});

  @override
  State<ConfigTab> createState() => _ConfigTabState();
}

class _ConfigTabState extends State<ConfigTab> {
  bool _isLoading = true;
  Map<String, dynamic> _totemData = {};

  final TextEditingController _horaReinicioCtrl = TextEditingController();

  final TextEditingController _alertaMinPapelCtrl = TextEditingController();
  final TextEditingController _alertaMinPapelVenCtrl = TextEditingController();
  final TextEditingController _bloqueoMinPapelCtrl = TextEditingController();
  final TextEditingController _bloqueoMinPapelVenCtrl = TextEditingController();

  final TextEditingController _emailSoporteCtrl = TextEditingController();
  final TextEditingController _emailPapelCtrl = TextEditingController();

  final TextEditingController _pinSoporteCtrl = TextEditingController();
  final TextEditingController _pinRolloCtrl = TextEditingController();
  final TextEditingController _pinRolloVenCtrl = TextEditingController();
  final TextEditingController _pinDemandaTotemCtrl = TextEditingController();
  final TextEditingController _pinDemandaVenCtrl = TextEditingController();

  final TextEditingController _ticketsTotemCtrl = TextEditingController();
  final TextEditingController _ticketsVenCtrl = TextEditingController();
  final TextEditingController _capTotemCtrl = TextEditingController();
  final TextEditingController _capVenCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    final res = await ApiClient.get('totem.php');
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _totemData = res['data'];
        _isLoading = false;

        _horaReinicioCtrl.text = _totemData['hora_reinicio_totem'] ?? '';

        _alertaMinPapelCtrl.text = _totemData['alerta_min_papel'] ?? '';
        _alertaMinPapelVenCtrl.text =
            _totemData['alerta_min_papel_ventanilla'] ?? '';
        _bloqueoMinPapelCtrl.text = _totemData['bloqueo_min_papel'] ?? '';
        _bloqueoMinPapelVenCtrl.text =
            _totemData['bloqueo_min_papel_ventanilla'] ?? '';

        _emailSoporteCtrl.text = _totemData['email_soporte'] ?? '';
        _emailPapelCtrl.text = _totemData['email_papel'] ?? '';

        _pinSoporteCtrl.text = _totemData['pin_soporte'] ?? '';
        _pinRolloCtrl.text = _totemData['pin_rollo'] ?? '';
        _pinRolloVenCtrl.text = _totemData['pin_rollo_ventanilla'] ?? '';
        _pinDemandaTotemCtrl.text =
            _totemData['pin_demanda_espontanea_totem'] ?? '';
        _pinDemandaVenCtrl.text =
            _totemData['pin_demanda_espontanea_ventanilla'] ?? '';

        _ticketsTotemCtrl.text = _totemData['tickets_impresos'] ?? '';
        _ticketsVenCtrl.text = _totemData['tickets_impresos_ventanilla'] ?? '';
        _capTotemCtrl.text = _totemData['capacidad_rollo'] ?? '';
        _capVenCtrl.text = _totemData['capacidad_rollo_ventanilla'] ?? '';
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _updateConfig(String key, String value) async {
    setState(() => _isLoading = true);
    await ApiClient.post('totem_update.php', {
      'action': 'update_config',
      'key': key,
      'value': value,
    });
    _fetchData();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text("Guardado"),
          duration: Duration(seconds: 1),
        ),
      );
    }
  }

  Future<void> _pickTime(String key, String title) async {
    final String currentVal = _totemData[key] ?? '00:00';
    final parts = currentVal.split(':');
    TimeOfDay initialTime = const TimeOfDay(hour: 0, minute: 0);
    if (parts.length == 2) {
      initialTime = TimeOfDay(
        hour: int.tryParse(parts[0]) ?? 0,
        minute: int.tryParse(parts[1]) ?? 0,
      );
    }

    final TimeOfDay? newTime = await showTimePicker(
      context: context,
      initialTime: initialTime,
      helpText: title,
    );

    if (newTime != null) {
      final String formattedTime =
          '${newTime.hour.toString().padLeft(2, '0')}:${newTime.minute.toString().padLeft(2, '0')}';
      _updateConfig(key, formattedTime);
    }
  }

  Future<void> _pickDateForFeriado() async {
    final DateTime? newDate = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365 * 2)),
      helpText: 'Seleccionar Fecha a Bloquear',
    );

    if (newDate != null) {
      final String formattedDate =
          '${newDate.year}-${newDate.month.toString().padLeft(2, '0')}-${newDate.day.toString().padLeft(2, '0')}';
      setState(() => _isLoading = true);
      await ApiClient.post('totem_update.php', {
        'action': 'agregar_feriado',
        'fecha': formattedDate,
      });
      _fetchData();
    }
  }

  Widget _buildSection(
    String title,
    List<Widget> children, {
    Color color = const Color(0xFF144973),
  }) {
    return Card(
      elevation: 2,
      margin: const EdgeInsets.only(bottom: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const Divider(),
            ...children,
          ],
        ),
      ),
    );
  }

  Widget _buildSwitch(
    String title,
    String key,
    String valueOn,
    String valueOff,
  ) {
    bool isOn = (_totemData[key] == valueOn);
    return SwitchListTile(
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
      value: isOn,
      activeThumbColor: Colors.blue,
      onChanged: (val) {
        _updateConfig(key, val ? valueOn : valueOff);
      },
    );
  }

  Widget _buildChoiceChips(
    String title,
    String key,
    Map<String, String> options,
  ) {
    String currentValue = _totemData[key] ?? options.keys.first;
    if (!options.containsKey(currentValue)) currentValue = options.keys.first;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8.0,
            runSpacing: 8.0,
            children: options.entries.map((e) {
              final isSelected = e.key == currentValue;
              return ChoiceChip(
                label: Text(e.value),
                selected: isSelected,
                selectedColor: const Color(0xFF144973),
                labelStyle: TextStyle(
                  color: isSelected ? Colors.white : Colors.black87,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                ),
                onSelected: (selected) {
                  if (selected) _updateConfig(key, e.key);
                },
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildFieldRow(
    String label,
    TextEditingController ctrl,
    String key, {
    bool isNumber = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Row(
        children: [
          Expanded(flex: 2, child: Text(label)),
          Expanded(
            flex: 3,
            child: TextField(
              controller: ctrl,
              keyboardType: isNumber
                  ? TextInputType.number
                  : TextInputType.text,
              decoration: const InputDecoration(
                isDense: true,
                border: OutlineInputBorder(),
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.save, color: Colors.blue),
            onPressed: () => _updateConfig(key, ctrl.text),
          ),
        ],
      ),
    );
  }

  Widget _buildTimePickerRow(String title, String key) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            _totemData[key] ?? '00:00',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.access_time, color: Colors.blue),
        ],
      ),
      onTap: () => _pickTime(key, title),
    );
  }

  Widget _buildFeriadosSection() {
    List feriados = _totemData['feriados'] ?? [];
    return Card(
      elevation: 2,
      margin: const EdgeInsets.only(bottom: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Días Bloqueados (Feriados)",
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF144973),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.calendar_month, color: Colors.blue),
                  onPressed: _pickDateForFeriado,
                ),
              ],
            ),
            const Divider(),
            if (feriados.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16.0),
                child: Text(
                  "Sin fechas bloqueadas.",
                  style: TextStyle(
                    color: Colors.grey,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              )
            else
              ...feriados
                  .map(
                    (f) => ListTile(
                      leading: const Icon(Icons.event_busy, color: Colors.red),
                      title: Text(
                        f['fecha'],
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      trailing: IconButton(
                        icon: const Icon(Icons.delete, color: Colors.red),
                        onPressed: () {
                          ApiClient.post('totem_update.php', {
                            'action': 'eliminar_feriado',
                            'id': f['id'],
                          }).then((_) => _fetchData());
                        },
                      ),
                    ),
                  )
                  .toList(),
          ],
        ),
      ),
    );
  }

  Widget _buildRolloCard({
    required String titulo,
    required String actionReset,
    required String labelImpresos,
    required TextEditingController ctrlImpresos,
    required String keyImpresos,
    required String labelCapacidad,
    required TextEditingController ctrlCapacidad,
    required String keyCapacidad,
    required int pct,
    required int impresos,
    required int capacidad,
  }) {
    Color barColor = pct < 20 ? Colors.red : Colors.green;

    return Card(
      elevation: 3,
      margin: const EdgeInsets.only(bottom: 16),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.blue.withValues(alpha: 0.3), width: 1),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.print, color: Color(0xFF144973)),
                const SizedBox(width: 8),
                Text(
                  "Hardware: $titulo",
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF144973),
                  ),
                ),
              ],
            ),
            const Divider(thickness: 1.5),
            const SizedBox(height: 8),

            // Visual Bar
            Center(
              child: Column(
                children: [
                  RichText(
                    text: TextSpan(
                      style: const TextStyle(
                        color: Colors.black,
                        fontSize: 32,
                        fontWeight: FontWeight.bold,
                      ),
                      children: [
                        TextSpan(text: '$impresos'),
                        TextSpan(
                          text: ' / $capacidad',
                          style: const TextStyle(
                            fontSize: 16,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Text(
                    "TICKETS IMPRESOS",
                    style: TextStyle(
                      fontSize: 10,
                      color: Colors.grey,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: LinearProgressIndicator(
                      value: pct / 100,
                      backgroundColor: Colors.grey.shade200,
                      color: barColor,
                      minHeight: 12,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    "RESTANTE: $pct%",
                    style: TextStyle(
                      color: barColor,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green.shade600,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                icon: const Icon(Icons.refresh, size: 24),
                label: const Text(
                  "REGISTRAR ROLLO NUEVO",
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                onPressed: () => _confirmReset(titulo, actionReset),
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    "Ajuste Manual:",
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  _buildFieldRow(
                    labelImpresos,
                    ctrlImpresos,
                    keyImpresos,
                    isNumber: true,
                  ),
                  _buildFieldRow(
                    labelCapacidad,
                    ctrlCapacidad,
                    keyCapacidad,
                    isNumber: true,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResetSection() {
    int pctTotem = _totemData['papel_totem_pct'] ?? 0;
    int impTotem =
        int.tryParse(_totemData['tickets_impresos']?.toString() ?? '0') ?? 0;
    int capTotem =
        int.tryParse(_totemData['capacidad_rollo']?.toString() ?? '120') ?? 120;

    int pctVen = _totemData['papel_ven_pct'] ?? 0;
    int impVen =
        int.tryParse(
          _totemData['tickets_impresos_ventanilla']?.toString() ?? '0',
        ) ??
        0;
    int capVen =
        int.tryParse(
          _totemData['capacidad_rollo_ventanilla']?.toString() ?? '120',
        ) ??
        120;

    return Column(
      children: [
        _buildRolloCard(
          titulo: 'TÓTEM',
          actionReset: 'resetear_papel_totem',
          labelImpresos: 'Impresos',
          ctrlImpresos: _ticketsTotemCtrl,
          keyImpresos: 'tickets_impresos',
          labelCapacidad: 'Capacidad',
          ctrlCapacidad: _capTotemCtrl,
          keyCapacidad: 'capacidad_rollo',
          pct: pctTotem,
          impresos: impTotem,
          capacidad: capTotem,
        ),
        _buildRolloCard(
          titulo: 'VENTANILLA',
          actionReset: 'resetear_papel_ventanilla',
          labelImpresos: 'Impresos',
          ctrlImpresos: _ticketsVenCtrl,
          keyImpresos: 'tickets_impresos_ventanilla',
          labelCapacidad: 'Capacidad',
          ctrlCapacidad: _capVenCtrl,
          keyCapacidad: 'capacidad_rollo_ventanilla',
          pct: pctVen,
          impresos: impVen,
          capacidad: capVen,
        ),
      ],
    );
  }

  void _confirmReset(String nombreCorto, String actionReset) {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        title: Text("Confirmar Cambio - $nombreCorto"),
        content: Text(
          "¿Colocaste un rollo NUEVO en $nombreCorto? Esto pondrá el contador a 0.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text("CANCELAR"),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () {
              Navigator.pop(c);
              setState(() => _isLoading = true);
              ApiClient.post('totem_update.php', {'action': actionReset}).then((
                _,
              ) {
                _fetchData();
              });
            },
            child: const Text(
              "SÍ, RESETEAR",
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSimulatorSection() {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              "Simulador de Tickets (Vistas Previas)",
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Color(0xFF144973),
              ),
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.print, color: Colors.blue),
              title: const Text("Ver Ticket Asistencia"),
              onTap: () async {
                final url = Uri.parse(
                  'https://federicogonzalez.net/actis/imprimir_asistencia.php?servicio=ASISTENCIA&preview=1',
                );
                if (await canLaunchUrl(url))
                  await launchUrl(url, mode: LaunchMode.externalApplication);
              },
            ),
            ListTile(
              leading: const Icon(Icons.qr_code, color: Colors.blue),
              title: const Text("Ver Ticket Validación (OSFA)"),
              onTap: () async {
                final url = Uri.parse(
                  'https://federicogonzalez.net/actis/imprimir_ticket_iofa.php?modo=VALIDACION&servicio=GENERAL&codigo=TOKENPRUEBA&dni=12345678&nombre=PACIENTE%20DE%20PRUEBA&preview=1',
                );
                if (await canLaunchUrl(url))
                  await launchUrl(url, mode: LaunchMode.externalApplication);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading && _totemData.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: _fetchData,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildSection("Control General", [
            _buildChoiceChips("Modo de Funcionamiento:", "estado_manual", {
              "automatico": "🤖 AUTOMÁTICO (Horarios)",
              "abierto": "🟢 ABIERTO",
              "cerrado": "🔴 CERRADO",
              "sin_internet": "🦖 SIMULAR CAÍDA RED",
            }),
            const Divider(),
            _buildChoiceChips(
              "Demanda (Tótem):",
              "totem_demanda_espontanea_habilitado",
              {
                "2": "Libre (Sin PIN)",
                "1": "Permitir (Con PIN)",
                "0": "Deshabilitada",
              },
            ),
            const Divider(),
            _buildChoiceChips(
              "Demanda (Ventanilla):",
              "ventanilla_demanda_espontanea_habilitada",
              {
                "2": "Libre (Sin PIN)",
                "1": "Permitir (Con PIN)",
                "0": "Deshabilitada",
              },
            ),
            const Divider(),
            _buildSwitch(
              "Mostrar Header/Footer Ticket IOSFA",
              "mostrar_header_footer",
              "1",
              "0",
            ),
            _buildSwitch(
              "Silenciar Alertas Tótem",
              "alertas_silenciadas",
              "1",
              "0",
            ),
            _buildSwitch(
              "Silenciar Alertas Ventanilla",
              "alertas_silenciadas_ventanilla",
              "1",
              "0",
            ),
            _buildSwitch(
              "Modo Simulación Sin Papel (TEST)",
              "simular_sin_papel_test",
              "1",
              "0",
            ),
          ]),

          _buildResetSection(),
          _buildSimulatorSection(),

          _buildSection("Horarios Fijos", [
            _buildTimePickerRow("Hora de Apertura", "hora_apertura"),
            _buildTimePickerRow("Hora de Cierre", "hora_cierre"),
            _buildTimePickerRow(
              "Reinicio Automático (Tótem)",
              "hora_reinicio_totem",
            ),
            _buildFieldRow(
              "Reinicio (Opcional texto)",
              _horaReinicioCtrl,
              "hora_reinicio_totem",
            ),
          ]),

          _buildFeriadosSection(),

          _buildSection("Pines de Seguridad", [
            _buildFieldRow("PIN Soporte", _pinSoporteCtrl, "pin_soporte"),
            _buildFieldRow("PIN Rollo Tótem", _pinRolloCtrl, "pin_rollo"),
            _buildFieldRow(
              "PIN Rollo Ventanilla",
              _pinRolloVenCtrl,
              "pin_rollo_ventanilla",
            ),
            _buildFieldRow(
              "PIN Demanda Tótem",
              _pinDemandaTotemCtrl,
              "pin_demanda_espontanea_totem",
            ),
            _buildFieldRow(
              "PIN Demanda Ventanilla",
              _pinDemandaVenCtrl,
              "pin_demanda_espontanea_ventanilla",
            ),
          ]),

          _buildSection("Alertas y Correos", [
            _buildFieldRow("Email Soporte", _emailSoporteCtrl, "email_soporte"),
            _buildFieldRow("Email Papel", _emailPapelCtrl, "email_papel"),
            const Divider(),
            _buildFieldRow(
              "Alerta Mín. Tótem",
              _alertaMinPapelCtrl,
              "alerta_min_papel",
              isNumber: true,
            ),
            _buildFieldRow(
              "Bloqueo Mín. Tótem",
              _bloqueoMinPapelCtrl,
              "bloqueo_min_papel",
              isNumber: true,
            ),
            _buildFieldRow(
              "Alerta Mín. Ventanilla",
              _alertaMinPapelVenCtrl,
              "alerta_min_papel_ventanilla",
              isNumber: true,
            ),
            _buildFieldRow(
              "Bloqueo Mín. Ventanilla",
              _bloqueoMinPapelVenCtrl,
              "bloqueo_min_papel_ventanilla",
              isNumber: true,
            ),
          ]),
        ],
      ),
    );
  }
}
