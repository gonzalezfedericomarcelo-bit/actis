<?php
session_start();
require_once 'includes/conexion.php';

$q = $conexion->query("SELECT * FROM registro_rechazados ORDER BY fecha_hora DESC LIMIT 500");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Rechazados</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 20px; }
        h1 { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #0284c7; color: white; }
        tr:hover { background: #f8fafc; }
        .badge-origen { padding: 4px 8px; border-radius: 6px; font-weight: bold; font-size: 0.85em; }
        .origen-totem { background: #e0e7ff; color: #1d4ed8; }
        .origen-ventanilla { background: #fce7f3; color: #4338ca; }
    </style>
</head>
<body>
    <h1>📋 Registro de Consultas Rechazadas (No Afiliados)</h1>
    <p>Últimos 500 intentos registrados en el sistema.</p>
    <table>
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>DNI</th>
                <th>Nombre Devuelto</th>
                <th>Motivo de Rechazo</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
            <?php while($r = $q->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i:s', strtotime($r['fecha_hora'])); ?></td>
                <td><strong><?php echo htmlspecialchars($r['dni']); ?></strong></td>
                <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                <td style="color: #dc2626; font-weight: bold;"><?php echo htmlspecialchars($r['motivo']); ?></td>
                <td>
                    <span class="badge-origen <?php echo $r['origen'] === 'TOTEM' ? 'origen-totem' : 'origen-ventanilla'; ?>">
                        <?php echo htmlspecialchars($r['origen']); ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
