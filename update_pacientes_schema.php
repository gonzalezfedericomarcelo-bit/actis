<?php
require_once 'includes/conexion.php';

$log = [];

// 1. Agregar columnas si no existen
$columnas_a_agregar = [
    'obra_social' => 'VARCHAR(150) NULL AFTER hc',
    'sexo' => 'VARCHAR(20) NULL AFTER obra_social',
    'estado_civil' => 'VARCHAR(50) NULL AFTER sexo'
];

foreach ($columnas_a_agregar as $col_name => $col_def) {
    $check_query = "SHOW COLUMNS FROM pacientes LIKE '$col_name'";
    $res = $conexion->query($check_query);
    if ($res && $res->num_rows == 0) {
        $alter_query = "ALTER TABLE pacientes ADD COLUMN $col_name $col_def";
        if ($conexion->query($alter_query)) {
            $log[] = "Columna '$col_name' agregada exitosamente.";
        } else {
            $log[] = "Error al agregar '$col_name': " . $conexion->error;
        }
    } else {
        $log[] = "Columna '$col_name' ya existe.";
    }
}

// 2. Backfill: Mover "Fuerza:" desde turnos.comentario_paciente hacia pacientes.obra_social
$backfill_query = "
    UPDATE pacientes p
    INNER JOIN (
        SELECT paciente_id, TRIM(REPLACE(SUBSTRING_INDEX(comentario_paciente, '|', 1), 'Fuerza:', '')) as fuerza_extraida
        FROM turnos
        WHERE comentario_paciente LIKE '%Fuerza:%'
        GROUP BY paciente_id
    ) t ON p.id = t.paciente_id
    SET p.obra_social = t.fuerza_extraida
    WHERE p.obra_social IS NULL OR p.obra_social = ''
";

if ($conexion->query($backfill_query)) {
    $log[] = "Backfill de 'obra_social' exitoso. Filas afectadas: " . $conexion->affected_rows;
} else {
    $log[] = "Error en el backfill: " . $conexion->error;
}

echo json_encode(['status' => 'success', 'log' => $log]);
?>
