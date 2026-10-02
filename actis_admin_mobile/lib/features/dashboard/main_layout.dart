import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../auth/login_screen.dart';
import 'dashboard_tab.dart';
import 'turnos_tab.dart';
import 'pacientes_tab.dart';
import 'config_tab.dart';
import 'totem_analytics_tab.dart';
import 'ventanilla_analytics_tab.dart';
import 'seguridad_layout.dart';
import 'ascensores_tab.dart';
import '../reports/reportes_screen.dart';
import '../statistics/estadisticas_avanzadas_screen.dart';

class _AppTab {
  final String label;
  final IconData icon;
  final Widget child;
  _AppTab(this.label, this.icon, this.child);
}

class MainLayout extends StatefulWidget {
  const MainLayout({super.key});

  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout> {
  int _currentIndex = 0;
  String _userName = '';
  List<String> _permisos = [];
  List<_AppTab> _availableTabs = [];
  DateTime? _lastPressedAt;
  bool _canPop = false;

  @override
  void initState() {
    super.initState();
    _loadUserAndPermissions();
  }

  Future<void> _loadUserAndPermissions() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = prefs.getString('user_name') ?? 'Admin';
      _permisos = prefs.getStringList('auth_permisos') ?? [];
      _buildTabs();
    });
  }

  void _buildTabs() {
    _availableTabs = [];

    bool esAdmin = _permisos.contains('modulo_roles') || _permisos.contains('modulo_usuarios');
    bool veReportes = _permisos.contains('modulo_reportes');

    if (esAdmin || veReportes || _permisos.contains('modulo_turnos') || _permisos.contains('modulo_recepcion')) {
      _availableTabs.add(_AppTab('Dashboard', Icons.dashboard, DashboardTab(key: UniqueKey(), onNavigate: _onNavigateByLabel)));
    }

    if (_permisos.contains('modulo_pacientes') || esAdmin) {
      _availableTabs.add(_AppTab('Pacientes', Icons.badge, PacientesTab(key: UniqueKey())));
    }

    if (_permisos.contains('modulo_turnos') || esAdmin) {
      _availableTabs.add(_AppTab('Turnos', Icons.people, TurnosTab(key: UniqueKey())));
    }

    if (_permisos.contains('modulo_recepcion') || esAdmin) {
      _availableTabs.add(_AppTab('Ventanilla', Icons.desktop_windows, VentanillaAnalyticsTab(key: UniqueKey())));
    }

    if (_permisos.contains('modulo_totem_admin') || _permisos.contains('modulo_validador') || esAdmin) {
      _availableTabs.add(_AppTab('Tótem', Icons.developer_board, TotemAnalyticsTab(key: UniqueKey())));
    }

    // Ascensores movido al menú lateral (Drawer)

    if (_availableTabs.isEmpty) {
      _availableTabs.add(_AppTab('Sin Acceso', Icons.block, Center(key: UniqueKey(), child: const Text("Tu rol no tiene módulos asignados en la App Móvil."))));
    }
  }

  void _onNavigateByLabel(int legacyIndex) {
    String target = 'Dashboard';
    if (legacyIndex == 1) target = 'Pacientes';
    if (legacyIndex == 2) target = 'Turnos';
    if (legacyIndex == 4) target = 'Tótem';

    int newIndex = _availableTabs.indexWhere((t) => t.label == target);
    if (newIndex != -1) {
      setState(() => _currentIndex = newIndex);
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

  @override
  Widget build(BuildContext context) {
    if (_permisos.isEmpty && _availableTabs.isEmpty) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    // Lógica dinámica para saber si el usuario es de Seguridad
    bool hasSecurity = _permisos.contains('modulo_seguridad') || _permisos.contains('modulo_seguridad_admin');
    bool hasOtherModules = _permisos.any((p) => p != 'modulo_seguridad' && p != 'modulo_seguridad_admin');
    
    // Si SOLO tiene permisos de seguridad (o no tiene otros módulos)
    if (hasSecurity && !hasOtherModules) {
      return const SeguridadLayout();
    }

    bool esAdmin = _permisos.contains('modulo_roles') || _permisos.contains('modulo_usuarios');
    bool veReportes = _permisos.contains('modulo_reportes');

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
        title: const Text('ACTIS Core'),
        actions: [
          IconButton(
            icon: const Icon(Icons.sync),
            tooltip: 'Sincronizar',
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Sincronizando información...'), duration: Duration(seconds: 1)));
              setState(() {
                _buildTabs();
              });
            },
          )
        ],
      ),
      drawer: Drawer(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.only(top: 50, bottom: 20),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFF144973), Color(0xFF1E6C99)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Column(
                children: [
                  CircleAvatar(radius: 40, backgroundColor: Colors.white, child: Padding(padding: const EdgeInsets.all(8.0), child: Image.asset('assets/icon.png'))),
                  const SizedBox(height: 12),
                  Text(_userName, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                  const Text('Sistema Actis', style: TextStyle(color: Colors.white70, fontSize: 14)),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  if (esAdmin)
                    ListTile(
                      leading: const Icon(Icons.settings_suggest, color: Color(0xFF144973)),
                      title: const Text('Configuración Avanzada', style: TextStyle(fontWeight: FontWeight.w600)),
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => Scaffold(appBar: AppBar(title: const Text('Configuración Avanzada')), body: const ConfigTab())));
                      },
                    ),
                  if (veReportes || esAdmin) ...[
                    ListTile(
                      leading: const Icon(Icons.pie_chart, color: Color(0xFF144973)),
                      title: const Text('Reportes Estadísticos', style: TextStyle(fontWeight: FontWeight.w600)),
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportesScreen()));
                      },
                    ),
                    ListTile(
                      leading: const Icon(Icons.analytics, color: Color(0xFF144973)),
                      title: const Text('Estadísticas Big Data', style: TextStyle(fontWeight: FontWeight.w600)),
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const EstadisticasAvanzadasScreen()));
                      },
                    ),
                  ],
                  // Si tiene permiso de seguridad, pero también otros permisos, se le muestra en el drawer
                  // porque el layout especial (SeguridadLayout) solo se activa si SOLO tiene seguridad.
                  if (hasSecurity)
                    ListTile(
                      leading: const Icon(Icons.security, color: Color(0xFF144973)),
                      title: const Text('Módulo Seguridad', style: TextStyle(fontWeight: FontWeight.w600)),
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const SeguridadLayout()));
                      },
                    ),
                  if (_permisos.contains('modulo_totem_ascensores') || _permisos.contains('modulo_ascensores') || esAdmin)
                    ListTile(
                      leading: const Icon(Icons.elevator, color: Color(0xFF144973)),
                      title: const Text('Gestión de Ascensores', style: TextStyle(fontWeight: FontWeight.w600)),
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(builder: (_) => Scaffold(appBar: AppBar(title: const Text('Gestión de Ascensores')), body: const AscensoresTab())));
                      },
                    ),
                ],
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.logout),
                  label: const Text('Cerrar Sesión', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  onPressed: () {
                    Navigator.pop(context); // close drawer
                    showDialog(
                      context: context,
                      builder: (c) => AlertDialog(
                        title: const Text('Cerrar Sesión'),
                        content: const Text('¿Estás seguro que deseas salir?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(c), child: const Text('CANCELAR')),
                          TextButton(
                            onPressed: () {
                              Navigator.pop(c);
                              _logout();
                            },
                            child: const Text('SALIR', style: TextStyle(color: Colors.red)),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
      body: _availableTabs.isNotEmpty 
          ? _availableTabs[_currentIndex].child 
          : const Center(child: Text("Sin contenido")),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex >= _availableTabs.length ? 0 : _currentIndex,
        onDestinationSelected: (idx) => setState(() => _currentIndex = idx),
        destinations: _availableTabs.map((t) => NavigationDestination(
          icon: Icon(t.icon),
          label: t.label,
        )).toList(),
      ),
    ));
  }
}
