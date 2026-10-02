import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api_client.dart';
import '../auth/login_screen.dart';


class SeguridadLayout extends StatefulWidget {
  const SeguridadLayout({super.key});

  @override
  State<SeguridadLayout> createState() => _SeguridadLayoutState();
}

class _SeguridadLayoutState extends State<SeguridadLayout> {
  int _currentIndex = 0;
  String _userName = '';
  bool _isLoading = true;
  List<dynamic> _llaves = [];
  List<dynamic> _rondas = [];
  List<dynamic> _ingresos = [];
  String _estadoTotem = 'ACTIVO';
  bool _alertaRondas = false;
  bool _alertaPapel = false;
  DateTime? _lastPressedAt;
  bool _canPop = false;

  @override
  void initState() {
    super.initState();
    _loadUser();
    _fetchData();
  }

  Future<void> _loadUser() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = prefs.getString('user_name') ?? 'Guardia';
    });
  }

  Future<void> _fetchData() async {
    final res = await ApiClient.get('seguridad.php');
    if (res['status'] == 'success' && mounted) {
      setState(() {
        _llaves = res['data']['llaves'] ?? [];
        _rondas = res['data']['rondas'] ?? [];
        _ingresos = res['data']['ingresos'] ?? [];
        _estadoTotem = res['data']['estado_totem'] ?? 'ACTIVO';
        _alertaRondas = res['data']['alerta_rondas'] ?? false;
        _alertaPapel = res['data']['alerta_papel'] ?? false;
        _isLoading = false;
      });
    } else {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _togglePanico() async {
    final res = await ApiClient.post('seguridad_acciones.php', {'accion': 'toggle_panico'});
    if (res['status'] == 'success') {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Estado cambiado a: ${res['nuevo_estado']}'), backgroundColor: Colors.green),
        );
      }
      _fetchData();
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    if (mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    }
  }

  Widget _buildDashboardPanel() {
    final bool isEvacuacion = _estadoTotem == 'EVACUACION';
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        InkWell(
          onTap: () {
            showDialog(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('⚠️ Código Rojo'),
                content: Text(isEvacuacion 
                    ? '¿Desactivar la evacuación y volver a la normalidad el Tótem?' 
                    : '¿Activar EVACUACIÓN? Esto bloqueará el Tótem inmediatamente.'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancelar')),
                  TextButton(
                    onPressed: () {
                      Navigator.pop(c);
                      _togglePanico();
                    },
                    child: Text(isEvacuacion ? 'DESACTIVAR ROJO' : 'ACTIVAR ROJO', style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          },
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: isEvacuacion ? Colors.red.shade700 : Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: isEvacuacion ? Colors.red.shade900 : Colors.red.shade300, width: 2),
              boxShadow: [BoxShadow(color: Colors.red.withValues(alpha: 0.3), blurRadius: 10, offset: const Offset(0, 4))],
            ),
            child: Column(
              children: [
                Icon(Icons.warning_rounded, color: isEvacuacion ? Colors.white : Colors.red, size: 64),
                const SizedBox(height: 12),
                Text(
                  isEvacuacion ? 'CÓDIGO ROJO ACTIVO' : 'BOTÓN DE PÁNICO',
                  style: TextStyle(color: isEvacuacion ? Colors.white : Colors.red, fontSize: 24, fontWeight: FontWeight.w900),
                  textAlign: TextAlign.center,
                ),
                Text(
                  isEvacuacion ? 'Tótem bloqueado. Toque para desactivar.' : 'Toque para activar protocolo de evacuación',
                  style: TextStyle(color: isEvacuacion ? Colors.white70 : Colors.red.shade300),
                  textAlign: TextAlign.center,
                )
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),
        const Text("Alertas del Sistema", style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        ListTile(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          tileColor: _alertaRondas ? Colors.orange.shade50 : Colors.green.shade50,
          leading: Icon(Icons.security, color: _alertaRondas ? Colors.orange : Colors.green, size: 32),
          title: const Text("Rondas de Seguridad", style: TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text(_alertaRondas ? "¡ALERTA! Más de 2 horas sin novedades." : "Rondas al día."),
        ),
        const SizedBox(height: 12),
        ListTile(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          tileColor: _alertaPapel ? Colors.red.shade50 : Colors.green.shade50,
          leading: Icon(Icons.print, color: _alertaPapel ? Colors.red : Colors.green, size: 32),
          title: const Text("Insumos de Impresión", style: TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text(_alertaPapel ? "¡ALERTA! Queda muy poco papel en el Tótem." : "Niveles de papel normales."),
        )
      ],
    );
  }

  Widget _buildIngresosPanel() {
    if (_ingresos.isEmpty) return const Center(child: Text("No hay ingresos registrados"));
    return RefreshIndicator(
      onRefresh: _fetchData,
      child: ListView.builder(
        itemCount: _ingresos.length,
        itemBuilder: (context, i) {
          final ing = _ingresos[i];
          final noCitado = ing['alerta_no_citado'] == 1 || ing['alerta_no_citado'] == '1';
          return Card(
            margin: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            color: noCitado ? Colors.yellow.shade50 : Colors.white,
            child: ListTile(
              leading: (ing['foto_base64'] != null && ing['foto_base64'].toString().isNotEmpty)
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.memory(base64Decode(ing['foto_base64'].split(',').last), width: 50, height: 50, fit: BoxFit.cover,
                          errorBuilder: (c, e, s) => const Icon(Icons.broken_image)))
                  : const CircleAvatar(child: Icon(Icons.person)),
              title: Text("${ing['apellido'] ?? ''}, ${ing['nombre'] ?? ''}", style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text("DNI: ${ing['dni']} | ${ing['tipo_registro']}"),
                  if (noCitado) const Text("⚠️ ALERTA: Paciente NO citado hoy", style: TextStyle(color: Colors.deepOrange, fontWeight: FontWeight.bold)),
                  Text(ing['fecha_hora'] ?? '', style: const TextStyle(fontSize: 11)),
                ],
              ),
              isThreeLine: noCitado,
            ),
          );
        },
      ),
    );
  }

  Widget _buildLlavesPanel() {
    final horaActual = DateTime.now().hour;
    final bloqueado = horaActual < 6 || horaActual >= 20;

    return RefreshIndicator(
      onRefresh: _fetchData,
      child: Column(
        children: [
          if (bloqueado)
            Container(
              padding: const EdgeInsets.all(12),
              color: Colors.red.shade100,
              child: const Row(
                children: [
                  Icon(Icons.lock, color: Colors.red),
                  SizedBox(width: 8),
                  Expanded(child: Text("HORARIO BLOQUEADO: No se permite entrega de llaves (20:00 - 06:00)", style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold))),
                ],
              ),
            ),
          Expanded(
            child: ListView.builder(
              itemCount: _llaves.length,
              itemBuilder: (context, i) {
                final ll = _llaves[i];
                final isDisp = ll['estado'] == 'Disponible';
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: isDisp ? Colors.green.withValues(alpha: 0.2) : Colors.red.withValues(alpha: 0.2),
                    child: Icon(Icons.vpn_key, color: isDisp ? Colors.green : Colors.red),
                  ),
                  title: Text(ll['nombre'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text("Nº ${ll['num_llave']} - Estado: ${ll['estado']}"),
                  trailing: isDisp ? null : Text(ll['asignada_a'] ?? '', style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRondasPanel() {
    if (_rondas.isEmpty) return const Center(child: Text("No hay rondas registradas"));
    return RefreshIndicator(
      onRefresh: _fetchData,
      child: ListView.builder(
        itemCount: _rondas.length,
        itemBuilder: (context, i) {
          final r = _rondas[i];
          final isNormal = r['estado'] == 'Normal';
          return ListTile(
            leading: Icon(isNormal ? Icons.check_circle : Icons.warning, color: isNormal ? Colors.green : Colors.orange),
            title: Text(r['fecha_hora'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text("Guardia: ${r['guardia'] ?? 'Desconocido'}\nNovedades: ${r['novedades']}"),
            isThreeLine: true,
          );
        },
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    switch (_currentIndex) {
      case 0: return _buildDashboardPanel();
      case 1: return _buildIngresosPanel();
      case 2: return _buildLlavesPanel();
      case 3: return _buildRondasPanel();
      // Ascensores removido
      default: return _buildDashboardPanel();
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _canPop,
      onPopInvoked: (didPop) {
        if (didPop) return;
        if (_currentIndex != 0) {
          setState(() => _currentIndex = 0);
          return;
        }

        final now = DateTime.now();
        if (_lastPressedAt == null || now.difference(_lastPressedAt!) > const Duration(seconds: 2)) {
          _lastPressedAt = now;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Presioná de nuevo para salir'), duration: Duration(seconds: 2)),
          );
          setState(() => _canPop = true);
          Future.delayed(const Duration(seconds: 2), () {
            if (mounted) setState(() => _canPop = false);
          });
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Seguridad ACTIS'),
          actions: [
            IconButton(
              icon: const Icon(Icons.sync),
              tooltip: 'Actualizar',
              onPressed: () {
                setState(() => _isLoading = true);
                _fetchData();
              },
            ),
            IconButton(
              icon: const Icon(Icons.logout, color: Colors.redAccent),
              tooltip: 'Cerrar Sesión',
              onPressed: _logout,
            )
          ],
        ),
        body: _buildBody(),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (idx) => setState(() => _currentIndex = idx),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Panel'),
            NavigationDestination(icon: Icon(Icons.directions_walk_outlined), selectedIcon: Icon(Icons.directions_walk), label: 'Ingresos'),
            NavigationDestination(icon: Icon(Icons.vpn_key_outlined), selectedIcon: Icon(Icons.vpn_key), label: 'Llaves'),
            NavigationDestination(icon: Icon(Icons.security_outlined), selectedIcon: Icon(Icons.security), label: 'Rondas'),

          ],
        ),
      ),
    );
  }
}
