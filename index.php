<?php
session_start();
if(isset($_SESSION['usuario_id'])) { header("Location: dashboard.php"); exit; }
require_once 'includes/conexion.php';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $conexion->real_escape_string($_POST['usuario']);
    $pass = $_POST['password']; 
    $sql = "SELECT * FROM usuarios WHERE usuario = '$user' AND password = '$pass' AND estado = 1";
    $res = $conexion->query($sql);
    
    if($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $_SESSION['usuario_id'] = $row['id'];
        $_SESSION['nombre'] = $row['nombre_completo'];
        $_SESSION['rol_id'] = $row['rol_id'];
        header("Location: dashboard.php");
        exit;
    } else { 
        $error = 'Credenciales incorrectas o usuario inactivo.'; 
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <meta property="og:title" content="Acceso - ACTIS Core">
    <meta property="og:description" content="Sistema Integral de Gestión, Auditoría y Seguridad Médica.">
    <meta property="og:image" itemprop="image" content="https://federicogonzalez.net/actis/img/osfa.svg">
    <meta property="og:url" content="https://federicogonzalez.net/actis/">
    <meta property="og:type" content="website">
    <link rel="icon" href="https://federicogonzalez.net/actis/img/osfa.svg" type="image/svg+xml">
    
    <title>Acceso Seguro - ACTIS Core</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Uso de 100dvh para adaptarse a las barras de navegación móviles dinámicas */
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100dvh; overflow: hidden; position: relative; }
        
        /* Contenedor Principal (PC) */
        .login-wrapper { display: flex; width: 100%; max-width: 1000px; height: 600px; background: #ffffff; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; position: relative; z-index: 2; }
        
        /* Panel Izquierdo: Información Corporativa */
        .login-info { flex: 1.3; background: #144973; color: #ffffff; padding: 40px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .login-info::before { content: ''; position: absolute; top: -50px; left: -50px; width: 250px; height: 250px; border-radius: 50%; background: rgba(255,255,255,0.03); }
        .login-info::after { content: ''; position: absolute; bottom: -50px; right: -50px; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,0.03); }

        .brand-header { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; position: relative; z-index: 1; }
        .brand-logo { width: 70px; height: 70px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); background: white; padding: 2px; }
        .brand-text h1 { margin: 0; font-size: 2.2rem; font-weight: 900; letter-spacing: -0.5px; }
        .brand-text p { margin: 0; font-size: 0.95rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-list { display: flex; flex-direction: column; gap: 20px; position: relative; z-index: 1; }
        .info-item { display: flex; gap: 15px; align-items: flex-start; }
        .info-icon { width: 45px; height: 45px; background: rgba(255,255,255,0.1); border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 1.2rem; color: #38bdf8; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.05); }
        .info-text h3 { margin: 0 0 5px 0; font-size: 1.05rem; font-weight: 800; }
        .info-text p { margin: 0; font-size: 0.85rem; color: #cbd5e1; line-height: 1.4; font-weight: 500; }

        /* Panel Derecho: Formulario de Login */
        .login-form-container { flex: 1; padding: 40px; display: flex; flex-direction: column; justify-content: center; background: #ffffff; }
        
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-header h2 { margin: 0 0 5px 0; font-size: 1.8rem; font-weight: 900; color: #0f172a; }
        .form-header p { margin: 0; color: #64748b; font-size: 0.95rem; font-weight: 500; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        .form-group label { font-size: 0.85rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input-box { position: relative; }
        .form-input-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; transition: color 0.3s; }
        .form-input { width: 100%; padding: 14px 15px 14px 45px; border-radius: 12px; border: 2px solid #e2e8f0; font-family: 'Poppins', sans-serif; font-size: 1rem; font-weight: 600; color: #1e293b; background: #f8fafc; outline: none; transition: all 0.3s; box-sizing: border-box; }
        .form-input:focus { border-color: #144973; background: #ffffff; box-shadow: 0 0 0 4px rgba(20,73,115,0.1); }
        .form-input:focus + i, .form-input-box:focus-within i { color: #144973; }
        
        .btn-submit { width: 100%; padding: 15px; border-radius: 12px; background: #144973; color: #ffffff; border: none; font-size: 1.1rem; font-weight: 800; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; transition: transform 0.2s, box-shadow 0.2s; font-family: 'Poppins', sans-serif; text-transform: uppercase; }
        .btn-submit:active { transform: scale(0.98); }

        /* MODO APP NATIVA (MÓVILES) - Ajuste Milimétrico para que entre sin Scroll */
        @media (max-width: 900px) {
            body { background: #ffffff; }
            
            .login-wrapper { flex-direction: column; margin: 0; border: none; box-shadow: none; border-radius: 0; width: 100%; height: 100dvh; display: flex; justify-content: space-between; }
            
            /* Ocultar elementos largos en móvil */
            .info-list, .login-info::before, .login-info::after { display: none; }
            
            /* Cabecera Móvil (Arriba) */
            .login-info { padding: 4vh 20px 0 20px; flex: none; background: transparent; align-items: center; text-align: center; }
            .brand-header { margin-bottom: 0; flex-direction: column; gap: 1vh; }
            .brand-logo { width: 55px; height: 55px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
            .brand-text h1 { font-size: 1.6rem; color: #144973; }
            .brand-text p { font-size: 0.8rem; color: #64748b; margin-top: -3px; }
            
            /* Formulario (Centro) */
            .login-form-container { padding: 0 25px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
            .form-header { margin-bottom: 3vh; }
            .form-header h2 { font-size: 1.4rem; margin-bottom: 2px; }
            .form-header p { font-size: 0.85rem; }
            
            .form-group { margin-bottom: 2vh; }
            .form-group label { font-size: 0.75rem; margin-bottom: 0; }
            .form-input { padding: 12px 15px 12px 40px; font-size: 0.95rem; border-radius: 10px; }
            .btn-submit { padding: 14px; font-size: 1rem; border-radius: 10px; margin-top: 1vh; }
            
            /* Footer (Abajo) */
            .login-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 2vh 15px; flex: none; text-align: center; }
            .copyright-text { font-size: 0.7rem; font-weight: 600; margin-bottom: 4px; color: #64748b; }
            .copyright-text strong { color: #144973; font-weight: 800; }
            .login-support { display: flex; flex-direction: column; gap: 2px; }
            .telefono { font-size: 0.8rem; font-weight: 800; color: #0ea5e9; }
            .firma { font-size: 0.65rem; font-weight: 700; color: #94a3b8; }
        }
        
        /* Ocultamos el footer integrado en versión PC (ya que ahí no se ve bien como franja) */
        @media (min-width: 901px) { .login-footer { display: none; } }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-info">
        <div class="brand-header">
            <img src="img/osfa.svg" alt="Logo ACTIS" class="brand-logo">
            <div class="brand-text">
                <h1>ACTIS Core</h1>
                <p>Policlínica General</p>
            </div>
        </div>

        <div class="info-list">
            <div class="info-item">
                <div class="info-icon"><i class="fa-solid fa-hospital-user"></i></div>
                <div class="info-text">
                    <h3>Gestión Médica Integral</h3>
                    <p>Administración centralizada de padrones, turnos médicos, agendas y control de historiales clínicos.</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon" style="color: #10b981;"><i class="fa-solid fa-microchip"></i></div>
                <div class="info-text">
                    <h3>Integración y Autogestión</h3>
                    <p>Sincronización en tiempo real con SGPS IOSFA, tótems interactivos y control de hardware de impresión.</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon" style="color: #f59e0b;"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="info-text">
                    <h3>Seguridad y Auditoría</h3>
                    <p>Trazabilidad criptográfica de comprobantes, llaves maestras y rondas de vigilancia perimetral.</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon" style="color: #a855f7;"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="info-text">
                    <h3>Métricas de Alto Nivel</h3>
                    <p>Dashboards gerenciales con análisis de flujos y estadísticas de atención en vivo.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="login-form-container">
        <div class="form-header">
            <h2>Acceso Restringido</h2>
            <p>Ingrese sus credenciales operativas</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario Identificador</label>
                <div class="form-input-box">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="usuario" id="usuario" class="form-input" placeholder="Ej: admin_actis" required autofocus autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Clave de Seguridad</label>
                <div class="form-input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Ingresar al Sistema <i class="fa-solid fa-right-to-bracket"></i>
            </button>
        </form>
    </div>
    
    <footer class="login-footer">
        <div class="copyright-text">
            <i class="fa-solid fa-shield-halved" style="color: #144973;"></i> ACTIS Core System v3.0 &copy; <?php echo date('Y'); ?> <strong>Policlínica ACTIS</strong>.
        </div>
        <div class="login-support">
            <span class="telefono"><i class="fa-solid fa-headset"></i> Soporte: +54 11 6611-6861</span>
            <span class="firma"><i class="fa-solid fa-user-shield" style="color: #144973;"></i> SG Mec Info F. GONZÁLEZ | Enc Info | OSFA</span>
        </div>
    </footer>
</div>

<?php if($error != ''): ?>
<script>
    Swal.fire({ 
        icon: 'error', 
        title: 'Acceso Denegado', 
        text: '<?php echo $error; ?>', 
        confirmButtonColor: '#144973', 
        background: '#ffffff', 
        borderRadius: '20px',
        customClass: { popup: 'swal2-custom-font' }
    });
</script>
<style> .swal2-custom-font { font-family: 'Poppins', sans-serif !important; } </style>
<?php endif; ?>

</body>
</html>
