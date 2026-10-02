<?php
/**
 * admin_totem_staff.php
 * Dashboard privado del Staff / Tótem ACTIS.
 * Acceso mediante PIN dinámico validado en api_validar_pin_staff.php
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'includes/conexion.php';

// Datos de contexto
$nombre_usuario = $_SESSION['nombre'] ?? 'Operador';
$fecha_hoy = (new DateTime())->format('l d \d\e F \d\e Y');

// Obtener permisos del usuario
$mis_permisos = [];
if (isset($_SESSION['rol_id'])) {
    $res_p = $conexion->query("SELECT p.nombre_permiso FROM rol_permiso rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = " . (int)$_SESSION['rol_id']);
    if ($res_p) {
        while($p = $res_p->fetch_assoc()){
            $mis_permisos[] = $p['nombre_permiso'];
        }
    }
}

$tiene_ascensores = in_array('modulo_totem_ascensores', $mis_permisos);
$tiene_pantalla = in_array('modulo_totem_pantalla', $mis_permisos);
$tiene_descargas = in_array('modulo_totem_descargas', $mis_permisos);
$tiene_estadisticas = in_array('modulo_totem_estadisticas', $mis_permisos);

// El botón de "Admin Tótem" se muestra si tiene CUALQUIERA de estos permisos
$tiene_admin_totem = in_array('modulo_totem_admin', $mis_permisos) 
    || in_array('modulo_totem_estado', $mis_permisos) 
    || in_array('modulo_totem_papel', $mis_permisos) 
    || in_array('modulo_totem_diseno', $mis_permisos) 
    || in_array('modulo_totem_seguridad', $mis_permisos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Staff — ACTIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body { 
        font-family: 'Poppins', sans-serif; 
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
        min-height: 100vh; margin: 0; padding: 0;
    }
    .staff-header {
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding: 18px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .staff-header-left { display: flex; align-items: center; gap: 15px; }
    .staff-header-left img { height: 42px; filter: brightness(0) invert(1); }
    .staff-header-left h1 { 
        font-size: 1.2rem; font-weight: 900; color: #fff; margin: 0;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .staff-header-left p { font-size: 0.75rem; color: #94a3b8; margin: 0; }
    .staff-header-right { display: flex; align-items: center; gap: 12px; }
    .badge-user {
        background: rgba(255,255,255,0.1);
        color: #e2e8f0;
        padding: 8px 14px;
        border-radius: 25px;
        font-size: 0.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-back {
        background: rgba(255,255,255,0.1);
        color: #e2e8f0;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
        border: 1px solid rgba(255,255,255,0.15);
    }
    .btn-back:hover { background: rgba(255,255,255,0.2); color: #fff; }

    /* --- Contenido Principal --- */
    .staff-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px 60px;
    }
    .section-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin: 40px 0 16px;
    }

    /* --- Grilla de Módulos --- */
    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 20px;
    }
    .module-card {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 28px 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.25s ease;
        position: relative;
        overflow: hidden;
    }
    .module-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--card-color, #0284c7);
        border-radius: 4px 0 0 4px;
    }
    .module-card:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    .module-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        background: rgba(255,255,255,0.08);
        color: var(--card-color, #0284c7);
        flex-shrink: 0;
    }
    .module-title {
        font-size: 1rem;
        font-weight: 800;
        color: #f1f5f9;
        margin: 0;
    }
    .module-desc {
        font-size: 0.8rem;
        color: #94a3b8;
        margin: 0;
        line-height: 1.5;
    }
    .module-arrow {
        margin-top: auto;
        font-size: 0.75rem;
        color: var(--card-color, #0284c7);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .module-card.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    .badge-pronto {
        position: absolute;
        top: 12px; right: 12px;
        background: rgba(255,255,255,0.08);
        color: #94a3b8;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* --- Stat Cards --- */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }
    .stat-card {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
    }
    .stat-number { font-size: 2.5rem; font-weight: 900; color: #f1f5f9; line-height: 1; }
    .stat-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-top: 6px; }

    @media (max-width: 640px) {
        .staff-header { flex-wrap: wrap; gap: 10px; }
        .staff-header-left h1 { font-size: 1rem; }
        .modules-grid { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="staff-header">
    <div class="staff-header-left">
        <img src="img/logo_iosfa.png" onerror="this.style.display='none'">
        <div>
            <h1><i class="fa-solid fa-shield-halved" style="color: #38bdf8;"></i> Staff Dashboard</h1>
            <p>Panel de Administración — ACTIS Tótem</p>
        </div>
    </div>
    <div class="staff-header-right">
        <div class="badge-user">
            <i class="fa-solid fa-user-tie"></i>
            <?php echo htmlspecialchars($nombre_usuario); ?>
        </div>
        <a href="dashboard_totem.php" class="btn-back">
            <i class="fa-solid fa-person-walking-arrow-right"></i> Salir al Tótem
        </a>
    </div>
</div>

<div class="staff-container">

    <!-- Stats Rápidas -->
    <?php
    $tickets_totem = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'")->fetch_assoc()['estado'] ?? 0);
    $capacidad_totem = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'")->fetch_assoc()['estado'] ?? 120);
    $tickets_ventanilla = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'")->fetch_assoc()['estado'] ?? 0);
    $estado_manual = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'")->fetch_assoc()['estado'] ?? 'automatico';
    $papel_restante = max(0, $capacidad_totem - $tickets_totem);
    ?>
    <p class="section-title"><i class="fa-solid fa-chart-bar"></i> Estado del Sistema</p>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" style="color: <?php echo $estado_manual === 'abierto' ? '#10b981' : ($estado_manual === 'cerrado' ? '#ef4444' : '#f59e0b'); ?>;">
                <?php echo strtoupper($estado_manual); ?>
            </div>
            <div class="stat-label">Estado del Tótem</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $tickets_totem; ?></div>
            <div class="stat-label">Tickets Impresos (Tótem)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $tickets_ventanilla; ?></div>
            <div class="stat-label">Tickets Impresos (Ventanilla)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: <?php echo $papel_restante < 20 ? '#ef4444' : '#10b981'; ?>;">
                <?php echo $papel_restante; ?>
            </div>
            <div class="stat-label">Papel Restante (Tótem)</div>
        </div>
    </div>

    <!-- Módulos Disponibles -->
    <p class="section-title"><i class="fa-solid fa-th-large"></i> Módulos de Gestión</p>
    <div class="modules-grid">

        <!-- Logística de Ascensores -->
        <?php if($tiene_ascensores): ?>
        <a href="totem_ascensores.php" class="module-card" style="--card-color: #f59e0b;">
            <div class="module-icon"><i class="fa-solid fa-elevator"></i></div>
            <h3 class="module-title">Logística de Ascensores</h3>
            <p class="module-desc">Monitoreo en tiempo real de incidencias, fallas y estado de los equipos.</p>
            <div class="module-arrow"><i class="fa-solid fa-arrow-right"></i> Acceder</div>
        </a>
        <?php endif; ?>

        <!-- Tótem Principal -->
        <?php if($tiene_pantalla): ?>
        <a href="dashboard_totem.php" class="module-card" style="--card-color: #0284c7;">
            <div class="module-icon"><i class="fa-solid fa-tablet-screen-button"></i></div>
            <h3 class="module-title">Pantalla del Tótem</h3>
            <p class="module-desc">Ver el Tótem de autogestión tal como lo ve el paciente.</p>
            <div class="module-arrow"><i class="fa-solid fa-arrow-right"></i> Ver Tótem</div>
        </a>
        <?php endif; ?>

        <!-- Admin Tótem -->
        <?php if($tiene_admin_totem): ?>
        <a href="admin_totem_kiosco.php" class="module-card" style="--card-color: #10b981;">
            <div class="module-icon"><i class="fa-solid fa-sliders"></i></div>
            <h3 class="module-title">Configuración Tótem</h3>
            <p class="module-desc">Ajustes según tus permisos habilitados.</p>
            <div class="module-arrow"><i class="fa-solid fa-arrow-right"></i> Configurar</div>
        </a>
        <?php endif; ?>

        <!-- Descargas (Documentos) -->
        <?php if($tiene_descargas): ?>
        <a href="descargas.php" target="_blank" class="module-card" style="--card-color: #ef4444;">
            <div class="module-icon"><i class="fa-solid fa-file-pdf"></i></div>
            <h3 class="module-title">Documentos Descargables</h3>
            <p class="module-desc">Ver la página de descargas de Recetas, Órdenes y Fichas tal como la ve el paciente.</p>
            <div class="module-arrow"><i class="fa-solid fa-arrow-right"></i> Ver Página</div>
        </a>
        <?php endif; ?>

        <!-- Módulos Futuros -->
        <?php if($tiene_estadisticas): ?>
        <div class="module-card disabled" style="--card-color: #8b5cf6;">
            <span class="badge-pronto">Próximamente</span>
            <div class="module-icon"><i class="fa-solid fa-chart-line"></i></div>
            <h3 class="module-title">Estadísticas y Reportes</h3>
            <p class="module-desc">Resumen mensual de atención, turnos y uso del Tótem.</p>
            <div class="module-arrow"><i class="fa-solid fa-lock"></i> No disponible</div>
        </div>
        <?php endif; ?>

        <?php if($tiene_estadisticas): ?>
        <div class="module-card disabled" style="--card-color: #ec4899;">
            <span class="badge-pronto">Próximamente</span>
            <div class="module-icon"><i class="fa-solid fa-bell"></i></div>
            <h3 class="module-title">Alertas y Notificaciones</h3>
            <p class="module-desc">Centro unificado de alertas de papel, fallas y eventos del sistema.</p>
            <div class="module-arrow"><i class="fa-solid fa-lock"></i> No disponible</div>
        </div>
        <?php endif; ?>

    </div>

</div>


</body>
</html>
