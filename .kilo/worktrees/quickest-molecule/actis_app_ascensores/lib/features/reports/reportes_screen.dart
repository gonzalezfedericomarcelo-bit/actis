import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api_client.dart';

class ReportesScreen extends StatefulWidget {
  const ReportesScreen({super.key});

  @override
  State<ReportesScreen> createState() => _ReportesScreenState();
}

class _ReportesScreenState extends State<ReportesScreen> {
  String _tipoReporte = 'totem'; // totem, ventanilla, todos, turnos

  // Filtros comunes (Totem/Ventanilla/Todos)
  DateTimeRange? _dateRange;
  String _busqueda = '';
  String? _servicioSeleccionado;
  TimeOfDay? _horaInicio;
  TimeOfDay? _horaFin;

  // Filtros Turnos
  DateTime? _fechaTurno;
  String? _estadoTurno;
  String? _profesionalTurno;
  bool _creadoHoy = false;

  final TextEditingController _busquedaCtrl = TextEditingController();

  final List<String> _servicios = ['GENERAL', 'ASISTENCIA', 'RECEPCION', 'TURNOS', 'CONSULTORIOS'];
  final List<String> _estados = ['PENDIENTE', 'ATENDIDO', 'AUSENTE', 'CANCELADO'];

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    _dateRange = DateTimeRange(start: today, end: today);
    _fechaTurno = today;
  }

  Future<void> _generarReporte() async {
    // Construir la URL según el tipo
    String urlBase = ApiClient.baseUrl.replaceAll('/api_mobile', ''); // Ej: https://dominio.com/actis
    String url = '';

    if (_tipoReporte == 'turnos') {
      url = '$urlBase/reporte_turnos_pdf.php?api_mobile_token=ACTIS_MOBILE_SECURE_PDF_TOKEN_2026';
      if (_fechaTurno != null) {
        url += '&fecha=${_fechaTurno!.toIso8601String().substring(0, 10)}';
      }
      if (_estadoTurno != null) url += '&estado=$_estadoTurno';
      if (_profesionalTurno != null && _profesionalTurno!.isNotEmpty) url += '&profesional=$_profesionalTurno';
      if (_servicioSeleccionado != null) url += '&servicio=$_servicioSeleccionado';
      if (_creadoHoy) url += '&creado_hoy=1';
    } else {
      url = '$urlBase/reporte_general_pdf.php?api_mobile_token=ACTIS_MOBILE_SECURE_PDF_TOKEN_2026';
      url += '&origen=$_tipoReporte';
      
      if (_dateRange != null) {
        url += '&fecha_inicio=${_dateRange!.start.toIso8601String().substring(0, 10)}';
        url += '&fecha_fin=${_dateRange!.end.toIso8601String().substring(0, 10)}';
      }
      if (_busqueda.isNotEmpty) url += '&busqueda=$_busqueda';
      if (_servicioSeleccionado != null) url += '&servicio=$_servicioSeleccionado';
      
      if (_horaInicio != null) {
        final h = _horaInicio!.hour.toString().padLeft(2, '0');
        final m = _horaInicio!.minute.toString().padLeft(2, '0');
        url += '&hora_inicio=$h:$m:00';
      }
      if (_horaFin != null) {
        final h = _horaFin!.hour.toString().padLeft(2, '0');
        final m = _horaFin!.minute.toString().padLeft(2, '0');
        url += '&hora_fin=$h:$m:59';
      }
    }

    final uri = Uri.parse(url);
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: No se pudo abrir el reporte ($e)')));
      }
    }
  }

  Widget _buildTotemVentanillaFilters() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          title: const Text("Rango de Fechas"),
          subtitle: Text(_dateRange != null 
            ? "${_dateRange!.start.day}/${_dateRange!.start.month}/${_dateRange!.start.year} al ${_dateRange!.end.day}/${_dateRange!.end.month}/${_dateRange!.end.year}"
            : "Seleccionar fechas"),
          trailing: const Icon(Icons.date_range),
          onTap: () async {
            final picked = await showDateRangePicker(
              context: context,
              firstDate: DateTime(2020),
              lastDate: DateTime(2030),
              initialDateRange: _dateRange,
            );
            if (picked != null) setState(() => _dateRange = picked);
          },
        ),
        const Divider(),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: TextField(
            controller: _busquedaCtrl,
            decoration: const InputDecoration(
              labelText: "Búsqueda (DNI, Nombre...)",
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.search),
            ),
            onChanged: (val) => _busqueda = val,
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: DropdownButtonFormField<String>(
            decoration: const InputDecoration(labelText: "Servicio", border: OutlineInputBorder()),
            initialValue: _servicioSeleccionado,
            items: [
              const DropdownMenuItem(value: null, child: Text("Todos los Servicios")),
              ..._servicios.map((s) => DropdownMenuItem(value: s, child: Text(s))),
            ],
            onChanged: (val) => setState(() => _servicioSeleccionado = val),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: Row(
            children: [
              Expanded(
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text("Hora Inicio"),
                  subtitle: Text(_horaInicio?.format(context) ?? '--:--'),
                  trailing: const Icon(Icons.access_time),
                  onTap: () async {
                    final t = await showTimePicker(context: context, initialTime: TimeOfDay.now());
                    if (t != null) setState(() => _horaInicio = t);
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text("Hora Fin"),
                  subtitle: Text(_horaFin?.format(context) ?? '--:--'),
                  trailing: const Icon(Icons.access_time),
                  onTap: () async {
                    final t = await showTimePicker(context: context, initialTime: TimeOfDay.now());
                    if (t != null) setState(() => _horaFin = t);
                  },
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildTurnosFilters() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          title: const Text("Fecha del Turno"),
          subtitle: Text(_fechaTurno != null 
            ? "${_fechaTurno!.day}/${_fechaTurno!.month}/${_fechaTurno!.year}"
            : "Seleccionar fecha"),
          trailing: const Icon(Icons.calendar_today),
          onTap: () async {
            final picked = await showDatePicker(
              context: context,
              initialDate: _fechaTurno ?? DateTime.now(),
              firstDate: DateTime(2020),
              lastDate: DateTime(2030),
            );
            if (picked != null) setState(() => _fechaTurno = picked);
          },
        ),
        const Divider(),
        SwitchListTile(
          title: const Text("Solo Turnos Creados Hoy"),
          value: _creadoHoy,
          onChanged: (val) => setState(() => _creadoHoy = val),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: DropdownButtonFormField<String>(
            decoration: const InputDecoration(labelText: "Estado del Turno", border: OutlineInputBorder()),
            initialValue: _estadoTurno,
            items: [
              const DropdownMenuItem(value: null, child: Text("Cualquier Estado")),
              ..._estados.map((e) => DropdownMenuItem(value: e, child: Text(e))),
            ],
            onChanged: (val) => setState(() => _estadoTurno = val),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: DropdownButtonFormField<String>(
            decoration: const InputDecoration(labelText: "Servicio", border: OutlineInputBorder()),
            initialValue: _servicioSeleccionado,
            items: [
              const DropdownMenuItem(value: null, child: Text("Cualquier Servicio")),
              ..._servicios.map((s) => DropdownMenuItem(value: s, child: Text(s))),
            ],
            onChanged: (val) => setState(() => _servicioSeleccionado = val),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Generador de Reportes', style: TextStyle(color: Colors.white)),
        backgroundColor: const Color(0xFF144973),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              "Tipo de Reporte a Generar:",
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF144973)),
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: _tipoReporte,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                filled: true,
                fillColor: Colors.white,
              ),
              items: const [
                DropdownMenuItem(value: 'totem', child: Text("Estadísticas de Tótem")),
                DropdownMenuItem(value: 'ventanilla', child: Text("Estadísticas de Ventanilla")),
                DropdownMenuItem(value: 'todos', child: Text("Tótem y Ventanilla (Unificado)")),
                DropdownMenuItem(value: 'turnos', child: Text("Reporte de Turnos")),
              ],
              onChanged: (val) {
                if (val != null) {
                  setState(() {
                    _tipoReporte = val;
                    // Resetear filtros
                    _servicioSeleccionado = null;
                  });
                }
              },
            ),
            const SizedBox(height: 24),
            const Text(
              "Filtros de Datos:",
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF144973)),
            ),
            const SizedBox(height: 8),
            Card(
              elevation: 3,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8.0),
                child: _tipoReporte == 'turnos' ? _buildTurnosFilters() : _buildTotemVentanillaFilters(),
              ),
            ),
            const SizedBox(height: 32),
            ElevatedButton.icon(
              icon: const Icon(Icons.picture_as_pdf, color: Colors.white),
              label: const Text("GENERAR Y DESCARGAR PDF", style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10b981), // Verde para descargar
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: _generarReporte,
            ),
          ],
        ),
      ),
    );
  }
}
