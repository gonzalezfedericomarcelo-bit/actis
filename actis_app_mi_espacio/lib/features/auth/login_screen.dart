import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api_client.dart';
import '../dashboard/main_layout.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _userController = TextEditingController();
  final _passController = TextEditingController();
  bool _isLoading = false;
  String _errorMsg = '';

  Future<void> _doLogin() async {
    setState(() {
      _isLoading = true;
      _errorMsg = '';
    });

    final res = await ApiClient.post('auth.php', {
      'usuario': _userController.text,
      'password': _passController.text,
    });

    if (res['status'] == 'success') {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', res['token']);
      await prefs.setString('user_name', res['user']['nombre']);
      
      List<String> permisos = [];
      if (res['user']['permisos'] != null) {
        permisos = List<String>.from(res['user']['permisos']);
      }
      await prefs.setStringList('auth_permisos', permisos);
      
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const MainLayout()),
        );
      }
    } else {
      setState(() {
        _errorMsg = res['message'] ?? 'Error desconocido';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        blurRadius: 20,
                      )
                    ],
                  ),
                  child: Image.network(
                    'https://federicogonzalez.net/actis/img/osfa.svg',
                    height: 80,
                    errorBuilder: (c, e, s) => const Icon(Icons.security, size: 80, color: Color(0xFF144973)),
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'ACTIS Admin',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF144973),
                  ),
                ),
                const Text(
                  'SISTEMA DE GESTIÓN MÓVIL',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 1.5,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(height: 48),
                if (_errorMsg.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Text(
                      _errorMsg,
                      style: TextStyle(color: Colors.red.shade700, fontWeight: FontWeight.bold),
                      textAlign: TextAlign.center,
                    ),
                  ),
                TextField(
                  controller: _userController,
                  decoration: const InputDecoration(
                    labelText: 'USUARIO',
                    prefixIcon: Icon(Icons.person),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _passController,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'CONTRASEÑA',
                    prefixIcon: Icon(Icons.lock),
                  ),
                ),
                const SizedBox(height: 32),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _doLogin,
                    child: _isLoading 
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                        : const Text('INGRESAR'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
