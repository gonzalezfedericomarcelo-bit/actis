import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../auth/login_screen.dart';
import 'dashboard_logistica_tab.dart';
import 'webview_screen.dart';
import '../pedidos/crear_pedido_screen.dart';
import '../pedidos/encargado_pedidos_screen.dart';
import '../tareas/tareas_lista_screen.dart';

// ─── Constante base URL logística ───────────────────────────────────────────
const String _baseUrl = 'https://federicogonzalez.net/logistica';

class MainLayout extends StatefulWidget {
  const MainLayout({super.key});
  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout> {
  String _userName = '';
  DateTime? _lastPressedAt;
  bool _canPop = false;

  @override
  void initState() { super.initState(); _loadUser(); }

  Future<void> _loadUser() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) setState(() => _userName = prefs.getString('user_name') ?? 'Usuario');
  }

  Future<void> _logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    if (mounted) Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  // ── Abre WebView con título y URL ────────────────────────────────────────
  void _openWebView(String title, String url) {
    Navigator.pop(context); // cierra el drawer
    Navigator.push(context, MaterialPageRoute(
      builder: (_) => WebViewScreen(title: title, url: url),
    ));
  }

  // ── Item del drawer ──────────────────────────────────────────────────────
  Widget _drawerItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
    Color iconColor = const Color(0xFF144973),
  }) {
    return ListTile(
      leading: Icon(icon, color: iconColor),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      onTap: onTap,
    );
  }

  // ── Sub-item (indentado) ─────────────────────────────────────────────────
  Widget _subItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    return ListTile(
      contentPadding: const EdgeInsets.only(left: 40),
      leading: Icon(icon, color: const Color(0xFF1E6C99), size: 20),
      title: Text(title, style: const TextStyle(fontSize: 14, color: Color(0xFF333333))),
      onTap: onTap,
      dense: true,
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _canPop,
      onPopInvoked: (didPop) {
        if (didPop) return;
        final now = DateTime.now();
        if (_lastPressedAt == null ||
            now.difference(_lastPressedAt!) > const Duration(seconds: 2)) {
          _lastPressedAt = now;
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('Presioná de nuevo para salir'),
              duration: Duration(seconds: 2)));
          setState(() => _canPop = true);
          Future.delayed(
              const Duration(seconds: 2), () { if (mounted) setState(() => _canPop = false); });
        }
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('ACTIS Logística')),
        // ── DRAWER ────────────────────────────────────────────────────────
        drawer: Drawer(
          child: Column(
            children: [
              // Header
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
                child: Column(children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: Colors.white,
                    child: Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: Image.asset('assets/icon.png'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(_userName,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.bold)),
                  const Text('Módulo de Logística',
                      style: TextStyle(color: Colors.white70, fontSize: 14)),
                ]),
              ),

              // ── Menú items ───────────────────────────────────────────────
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [

                    // Dashboard
                    _drawerItem(
                      icon: Icons.dashboard,
                      title: 'Dashboard',
                      onTap: () { Navigator.pop(context); },
                    ),

                    const Divider(height: 1, indent: 16, endIndent: 16),

                    // Pedidos de Trabajo
                    _drawerItem(
                      icon: Icons.add_box_outlined,
                      title: 'Crear Pedido de Trabajo',
                      onTap: () {
                        Navigator.pop(context); // Cierra drawer
                        Navigator.push(context, MaterialPageRoute(
                          builder: (_) => const CrearPedidoScreen(),
                        ));
                      },
                    ),

                    _drawerItem(
                      icon: Icons.list_alt,
                      title: 'Lista de Pedidos (Encargado)',
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(
                          builder: (_) => const EncargadoPedidosScreen(),
                        ));
                      },
                    ),

                    const Divider(height: 1, indent: 16, endIndent: 16),

                    // Tareas
                    _drawerItem(
                      icon: Icons.task_alt,
                      title: 'Lista de Tareas',
                      onTap: () {
                        Navigator.pop(context);
                        Navigator.push(context, MaterialPageRoute(
                          builder: (_) => const TareasListaScreen(),
                        ));
                      },
                    ),

                    const Divider(height: 1, indent: 16, endIndent: 16),

                    // Personal / Asistencias (expandible)
                    ExpansionTile(
                      leading: const Icon(Icons.people_alt_outlined,
                          color: Color(0xFF144973)),
                      title: const Text('Personal / Asistencias',
                          style: TextStyle(fontWeight: FontWeight.w600)),
                      childrenPadding: EdgeInsets.zero,
                      children: [
                        _subItem(
                          icon: Icons.how_to_reg,
                          title: 'Tomar Asistencia',
                          onTap: () => _openWebView(
                              'Tomar Asistencia', '$_baseUrl/asistencia_tomar.php'),
                        ),
                        _subItem(
                          icon: Icons.format_list_bulleted,
                          title: 'Listado General',
                          onTap: () => _openWebView(
                              'Listado de Asistencias',
                              '$_baseUrl/asistencia_listado_general.php'),
                        ),
                        _subItem(
                          icon: Icons.bar_chart,
                          title: 'Estadísticas',
                          onTap: () => _openWebView(
                              'Estadísticas', '$_baseUrl/asistencia_estadisticas.php'),
                        ),
                      ],
                    ),

                    const Divider(height: 1, indent: 16, endIndent: 16),

                    // Pizarra Kanban
                    _drawerItem(
                      icon: Icons.view_kanban_outlined,
                      title: 'Pizarra Kanban',
                      onTap: () => _openWebView(
                          'Pizarra Kanban', '$_baseUrl/pizarra_kanban.php'),
                    ),

                    const Divider(height: 1, indent: 16, endIndent: 16),

                    // Dashboard web completo
                    _drawerItem(
                      icon: Icons.open_in_browser,
                      title: 'Dashboard Web',
                      onTap: () => _openWebView(
                          'Dashboard', '$_baseUrl/dashboard.php'),
                    ),
                  ],
                ),
              ),

              // Botón cerrar sesión
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
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.logout),
                    label: const Text('Cerrar Sesión',
                        style: TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 16)),
                    onPressed: () {
                      Navigator.pop(context);
                      showDialog(
                        context: context,
                        builder: (c) => AlertDialog(
                          title: const Text('Cerrar Sesión'),
                          content:
                              const Text('¿Estás seguro que deseas salir?'),
                          actions: [
                            TextButton(
                                onPressed: () => Navigator.pop(c),
                                child: const Text('CANCELAR')),
                            TextButton(
                                onPressed: () {
                                  Navigator.pop(c);
                                  _logout();
                                },
                                child: const Text('SALIR',
                                    style: TextStyle(color: Colors.red))),
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

        // ── BODY ──────────────────────────────────────────────────────────
        body: DashboardLogistica(onNavigate: (_) {}),
      ),
    );
  }
}
