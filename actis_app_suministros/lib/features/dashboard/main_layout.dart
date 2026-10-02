import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../auth/login_screen.dart';
import 'dashboard_suministros_tab.dart';
import 'ascensores_tab.dart';

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
    if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final tabs = <_AppTab>[
      _AppTab('Dashboard', Icons.dashboard, DashboardSuministros(onNavigate: (i) => setState(() => _currentIndex = i))),
      _AppTab('Inventario', Icons.inventory, const AscensoresTab()),
    ];

    return PopScope(
      canPop: _canPop,
      onPopInvoked: (didPop) {
        if (didPop) return;
        if (_currentIndex != 0) { setState(() => _currentIndex = 0); return; }
        final now = DateTime.now();
        if (_lastPressedAt == null || now.difference(_lastPressedAt!) > const Duration(seconds: 2)) {
          _lastPressedAt = now;
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Presioná de nuevo para salir'), duration: Duration(seconds: 2)));
          setState(() => _canPop = true);
          Future.delayed(const Duration(seconds: 2), () { if (mounted) setState(() => _canPop = false); });
        }
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('ACTIS Suministros')),
        drawer: Drawer(
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.only(top: 50, bottom: 20),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: [Color(0xFF144973), Color(0xFF1E6C99)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                ),
                child: Column(children: [
                  CircleAvatar(radius: 40, backgroundColor: Colors.white, child: Padding(padding: const EdgeInsets.all(8.0), child: Image.asset('assets/icon.png'))),
                  const SizedBox(height: 12),
                  Text(_userName, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                  const Text('Depósito de Suministros', style: TextStyle(color: Colors.white70, fontSize: 14)),
                ]),
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: tabs.asMap().entries.map((e) => ListTile(
                    leading: Icon(e.value.icon, color: const Color(0xFF144973)),
                    title: Text(e.value.label, style: const TextStyle(fontWeight: FontWeight.w600)),
                    selected: _currentIndex == e.key,
                    selectedTileColor: const Color(0xFF144973).withValues(alpha: 0.08),
                    onTap: () { Navigator.pop(context); setState(() => _currentIndex = e.key); },
                  )).toList(),
                ),
              ),
              const Divider(height: 1),
              Padding(
                padding: const EdgeInsets.all(16.0),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red, side: const BorderSide(color: Colors.red),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.logout),
                    label: const Text('Cerrar Sesión', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    onPressed: () {
                      Navigator.pop(context);
                      showDialog(context: context, builder: (c) => AlertDialog(
                        title: const Text('Cerrar Sesión'),
                        content: const Text('¿Estás seguro que deseas salir?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(c), child: const Text('CANCELAR')),
                          TextButton(onPressed: () { Navigator.pop(c); _logout(); }, child: const Text('SALIR', style: TextStyle(color: Colors.red))),
                        ],
                      ));
                    },
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
        body: tabs[_currentIndex].child,
        bottomNavigationBar: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (idx) => setState(() => _currentIndex = idx),
          destinations: tabs.map((t) => NavigationDestination(icon: Icon(t.icon), label: t.label)).toList(),
        ),
      ),
    );
  }
}
