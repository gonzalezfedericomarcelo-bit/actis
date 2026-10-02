<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}
$hoy = date('Y-m-d');
?>
<style>
    .evac-container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 16px; border: 2px solid #ef4444; box-shadow: 0 4px 15px rgba(239,68,68,0.2); }
    .evac-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #fca5a5; padding-bottom: 15px; margin-bottom: 20px; }
    .evac-title { margin: 0; color: #dc2626; font-size: 1.8rem; font-weight: 900; }
    .btn-print { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; }
    .table-evac { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .table-evac th, .table-evac td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .table-evac th { background: #fef2f2; color: #991b1b; }
    .tag-rol { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
    .tag-paciente { background: #dbeafe; color: #1e3a8a; }
    .tag-empleado { background: #d1fae5; color: #065f46; }
    .tag-visita { background: #fef3c7; color: #92400e; }
    @media print {
        .btn-print { display: none; }
        .evac-container { border: none; box-shadow: none; }
    }
</style>

<div class="evac-container">
    <div class="evac-header">
        <h1 class="evac-title"><i class="fa-solid fa-fire"></i> PROTOCOLO DE EVACUACIÓN ACTIVO</h1>
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir Lista de Presentes</button>
    </div>
    
    <p style="font-weight: 800; font-size:1.1rem; color:#1e293b;">Personas estimadas dentro del edificio hoy: <?php echo date('d/m/Y H:i'); ?></p>

    <table class="table-evac">
        <thead>
            <tr>
                <th>Tipo de Persona</th>
                <th>DNI / Identificador</th>
                <th>Nombre y Apellido</th>
                <th>Última Actividad</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // 1. Pacientes en espera (Asistieron hoy y su turno está Presente/Llamando)
            $q_pacientes = $conexion->query("SELECT documento_paciente, apellido, nombre, estado, fecha_modificacion FROM turnos WHERE fecha_turno = '$hoy' AND estado IN ('Presente', 'Llamando', 'En Espera')");
            if ($q_pacientes) {
                while($p = $q_pacientes->fetch_assoc()) {
                    echo "<tr>
                            <td><span class='tag-rol tag-paciente'>Paciente (En Espera)</span></td>
                            <td style='font-family:monospace; font-size:1.1rem;'><b>{$p['documento_paciente']}</b></td>
                            <td>{$p['apellido']}, {$p['nombre']}</td>
                            <td>Tótem/Recepción a las ".date('H:i', strtotime($p['fecha_modificacion']))."</td>
                          </tr>";
                }
            }
            
            // 2. Ingresos Peatonales (Visitantes, Proveedores, etc) registrados por Seguridad
            $q_ingresos = $conexion->query("SELECT dni, motivo, fecha_hora FROM registro_ingresos WHERE DATE(fecha_hora) = '$hoy' AND tipo_registro = 'INGRESO'");
            if ($q_ingresos) {
                while($i = $q_ingresos->fetch_assoc()) {
                    $clase_tag = ($i['motivo'] == 'Proveedor / Mantenimiento') ? 'tag-visita' : 'tag-paciente';
                    echo "<tr>
                            <td><span class='tag-rol $clase_tag'>{$i['motivo']}</span></td>
                            <td style='font-family:monospace; font-size:1.1rem;'><b>{$i['dni']}</b></td>
                            <td><i>(No Registrado)</i></td>
                            <td>Acceso Seguridad a las ".date('H:i', strtotime($i['fecha_hora']))."</td>
                          </tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
