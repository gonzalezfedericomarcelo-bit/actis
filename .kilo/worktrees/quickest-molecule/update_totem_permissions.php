<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/conexion.php';

echo "<div style='font-family:sans-serif; padding:20px; max-width:800px; margin:auto;'>";
echo "<h2><span style='color:#0284c7;'>⚙️ Antigravity:</span> Actualizador Automático de Roles del Tótem</h2>";

$nuevos_permisos = [
    'modulo_totem_admin' => 'Tótem (admin_totem.php) - Acceso TOTAL a toda la configuración',
    'modulo_totem_ascensores' => 'Tótem (totem_ascensores.php) - Pantalla de logística de ascensores',
    'modulo_totem_pantalla' => 'Tótem (dashboard_totem.php) - Ver interfaz pública del Tótem',
    'modulo_totem_descargas' => 'Tótem (descargas.php) - Ver documentos descargables',
    'modulo_totem_estadisticas' => 'Tótem (Próximamente) - Estadísticas generales',
    'modulo_totem_papel' => 'Tótem (admin_totem.php) - Acceso SOLO a resetear el papel',
    'modulo_totem_estado' => 'Tótem (admin_totem.php) - Acceso SOLO a horarios y estado',
    'modulo_totem_diseno' => 'Tótem (admin_totem.php) - Acceso SOLO a colores y carrusel',
    'modulo_totem_seguridad' => 'Tótem (admin_totem.php) - Acceso SOLO a los PINes de seguridad'
];

// Forzamos la actualización de descripciones para que se lea claro
foreach ($nuevos_permisos as $nombre => $desc) {
    $conexion->query("UPDATE permisos SET descripcion = '$desc' WHERE nombre_permiso = '$nombre'");
}

// Limpiar el permiso de menú que ya no sirve
$conexion->query("DELETE FROM rol_permiso WHERE permiso_id IN (SELECT id FROM permisos WHERE nombre_permiso = 'modulo_totem_menu')");
$conexion->query("DELETE FROM permisos WHERE nombre_permiso = 'modulo_totem_menu'");

foreach ($nuevos_permisos as $nombre => $desc) {
    echo "<p><strong>Agregando permiso: {$nombre}</strong><br>";
    $existe = $conexion->query("SELECT id FROM permisos WHERE nombre_permiso = '$nombre'");
    
    if ($existe && $existe->num_rows > 0) {
        echo "<span style='color:orange;'>⚠️ Ya existía (Omitido).</span></p>";
    } else {
        if ($conexion->query("INSERT INTO permisos (nombre_permiso, descripcion) VALUES ('$nombre', '$desc')")) {
            echo "<span style='color:green;'>✅ Éxito.</span></p>";
        } else {
            echo "<span style='color:red;'>❌ Error: " . $conexion->error . "</span></p>";
        }
    }
}

echo "<hr><h3 style='color:green;'>¡Actualización Completada! 🎉</h3>";
echo "</div>";
?>
