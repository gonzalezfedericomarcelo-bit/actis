<?php
/**
 * migrar_logistica_v2.php — Versión 2 con mapeo de IDs de usuario
 * Migra tareas, pedidos, pizarra, asistencias de logistica → actis
 * Usa prefijo log_ en actis. No modifica logistica.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

require_once 'includes/conexion.php'; // $pdo = actis

$pdo_log = new PDO(
    'mysql:host=localhost;dbname=u415354546_logistica;charset=utf8mb4',
    'u415354546_logistica', 'l0g15t1C@!',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
     PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8mb4']
);
$pdo_log->exec("SET time_zone='-03:00'");
$pdo->exec("SET time_zone='-03:00'");

$log = []; $errors = [];
function log_msg(&$log, $m) { $log[] = $m; }

// ════════════════════════════════════════════════════════════
// PASO 0: MAPA id_logistica → id_actis (por nombre_completo)
// ════════════════════════════════════════════════════════════
log_msg($log, "=== PASO 0: Construyendo mapa de IDs usuario ===");

$users_log = $pdo_log->query("SELECT id_usuario, nombre_completo FROM usuarios")->fetchAll();
$users_act = $pdo->query("SELECT id, nombre_completo FROM usuarios")->fetchAll();

// índice actis: nombre → id
$actis_by_nombre = [];
foreach ($users_act as $u) $actis_by_nombre[strtolower(trim($u['nombre_completo']))] = (int)$u['id'];

$id_map = []; // log_id → actis_id
$no_match = [];
foreach ($users_log as $u) {
    $key = strtolower(trim($u['nombre_completo']));
    if (isset($actis_by_nombre[$key])) {
        $id_map[(int)$u['id_usuario']] = $actis_by_nombre[$key];
    } else {
        $no_match[] = $u['nombre_completo'];
    }
}
log_msg($log, "✅ " . count($id_map) . " usuarios mapeados correctamente.");
if ($no_match) log_msg($log, "⚠️  Sin match en actis: " . implode(', ', array_slice($no_match, 0, 10)));

// ════════════════════════════════════════════════════════════
// PASO 1: TABLAS DE SOPORTE (areas, destinos, categorias)
// Estas no tienen FK a usuarios, se copian directo
// ════════════════════════════════════════════════════════════
log_msg($log, "\n=== PASO 1: Tablas de soporte ===");

$tablas_simples = ['areas'=>'log_areas', 'destinos_internos'=>'log_destinos_internos', 'categorias'=>'log_categorias', 'agenda_interna'=>'log_agenda_interna'];
foreach ($tablas_simples as $origen => $destino) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$destino` LIKE `u415354546_logistica`.`$origen`");
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM `$destino`")->fetchColumn();
        if ($existing > 0) { log_msg($log, "⚠️  $destino ya tiene $existing filas, omitido."); continue; }
        $pdo->exec("INSERT INTO `u415354546_clinica_actis_`.`$destino` SELECT * FROM `u415354546_logistica`.`$origen`");
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `$destino`")->fetchColumn();
        log_msg($log, "✅ $origen → $destino: $cnt filas.");
    } catch (Exception $e) { $errors[] = "❌ $origen: " . $e->getMessage(); }
}

// ════════════════════════════════════════════════════════════
// PASO 2: TAREAS (con remapeo de id_usuario → actis id)
// ════════════════════════════════════════════════════════════
log_msg($log, "\n=== PASO 2: Tareas ===");

// 2a. Crear tabla log_tareas copiando estructura
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_tareas` LIKE `u415354546_logistica`.`tareas`");
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_tareas")->fetchColumn();
    if ($existing > 0) {
        log_msg($log, "⚠️  log_tareas ya tiene $existing filas, omitido.");
    } else {
        // Leer tareas de logistica
        $tareas = $pdo_log->query("SELECT * FROM tareas")->fetchAll();
        if (empty($tareas)) { log_msg($log, "ℹ️  Sin tareas en logística."); }
        else {
            $cols_t = array_keys($tareas[0]);
            // Campos que tienen FK a usuarios en tareas
            $fk_user_fields = ['id_creador','id_responsable','id_solicitante','id_usuario','cerrado_por','modificado_por'];
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO log_tareas (" . implode(',', array_map(fn($c)=>"`$c`",$cols_t)) . ") VALUES (" . implode(',', array_fill(0, count($cols_t), '?')) . ")");
            $count_t = 0; $skip_t = 0;
            foreach ($tareas as $t) {
                // Remap FK de usuarios
                foreach ($fk_user_fields as $fk) {
                    if (isset($t[$fk]) && $t[$fk] !== null) {
                        $mapped = $id_map[(int)$t[$fk]] ?? null;
                        $t[$fk] = $mapped; // null si no hay match (dejamos null)
                    }
                }
                try { $stmt->execute(array_values($t)); $count_t++; }
                catch (Exception $e2) { $skip_t++; }
            }
            $pdo->commit();
            log_msg($log, "✅ tareas → log_tareas: $count_t insertadas" . ($skip_t ? ", $skip_t omitidas" : "."));
        }
    }
} catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ tareas: " . $e->getMessage(); }

// 2b. Asignaciones (mapear id_usuario)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_tareas_asignaciones` LIKE `u415354546_logistica`.`tareas_asignaciones`");
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_tareas_asignaciones")->fetchColumn();
    if ($existing > 0) { log_msg($log,"⚠️  log_tareas_asignaciones ya tiene $existing filas, omitido."); }
    else {
        $rows = $pdo_log->query("SELECT * FROM tareas_asignaciones")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO log_tareas_asignaciones (" . implode(',', array_map(fn($c)=>"`$c`",$cols)) . ") VALUES (" . implode(',', array_fill(0,count($cols),'?')) . ")");
            $cnt=0;
            foreach ($rows as $r) {
                if (isset($r['id_usuario']) && $r['id_usuario']) $r['id_usuario'] = $id_map[(int)$r['id_usuario']] ?? null;
                try { $stmt->execute(array_values($r)); $cnt++; } catch (Exception $e2) {}
            }
            $pdo->commit();
            log_msg($log,"✅ tareas_asignaciones → log_tareas_asignaciones: $cnt filas.");
        }
    }
} catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ tareas_asignaciones: " . $e->getMessage(); }

// 2c. Actualizaciones de tarea
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_actualizaciones_tarea` LIKE `u415354546_logistica`.`actualizaciones_tarea`");
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_actualizaciones_tarea")->fetchColumn();
    if ($existing > 0) { log_msg($log,"⚠️  log_actualizaciones_tarea ya tiene $existing filas, omitido."); }
    else {
        $rows = $pdo_log->query("SELECT * FROM actualizaciones_tarea")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO log_actualizaciones_tarea (" . implode(',', array_map(fn($c)=>"`$c`",$cols)) . ") VALUES (" . implode(',', array_fill(0,count($cols),'?')) . ")");
            $cnt=0;
            foreach ($rows as $r) {
                foreach (['id_usuario','id_autor'] as $fk) if (isset($r[$fk]) && $r[$fk]) $r[$fk] = $id_map[(int)$r[$fk]] ?? null;
                try { $stmt->execute(array_values($r)); $cnt++; } catch (Exception $e2) {}
            }
            $pdo->commit();
            log_msg($log,"✅ actualizaciones_tarea → log_actualizaciones_tarea: $cnt filas.");
        }
    }
} catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ actualizaciones_tarea: " . $e->getMessage(); }

// 2d. Pines de usuario
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_tareas_pines_usuarios` LIKE `u415354546_logistica`.`tareas_pines_usuarios`");
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_tareas_pines_usuarios")->fetchColumn();
    if ($existing > 0) { log_msg($log,"⚠️  log_tareas_pines_usuarios ya tiene $existing filas, omitido."); }
    else {
        $rows = $pdo_log->query("SELECT * FROM tareas_pines_usuarios")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO log_tareas_pines_usuarios (" . implode(',', array_map(fn($c)=>"`$c`",$cols)) . ") VALUES (" . implode(',', array_fill(0,count($cols),'?')) . ")");
            $cnt=0;
            foreach ($rows as $r) {
                if (isset($r['id_usuario']) && $r['id_usuario']) $r['id_usuario'] = $id_map[(int)$r['id_usuario']] ?? null;
                try { $stmt->execute(array_values($r)); $cnt++; } catch (Exception $e2) {}
            }
            $pdo->commit();
            log_msg($log,"✅ tareas_pines_usuarios → log_tareas_pines_usuarios: $cnt filas.");
        }
    }
} catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ pines: " . $e->getMessage(); }

// ════════════════════════════════════════════════════════════
// PASO 3: PEDIDOS DE TRABAJO
// ════════════════════════════════════════════════════════════
log_msg($log, "\n=== PASO 3: Pedidos de Trabajo ===");
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_pedidos_trabajo` LIKE `u415354546_logistica`.`pedidos_trabajo`");
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_pedidos_trabajo")->fetchColumn();
    if ($existing > 0) { log_msg($log,"⚠️  log_pedidos_trabajo ya tiene $existing filas, omitido."); }
    else {
        $rows = $pdo_log->query("SELECT * FROM pedidos_trabajo")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO log_pedidos_trabajo (" . implode(',', array_map(fn($c)=>"`$c`",$cols)) . ") VALUES (" . implode(',', array_fill(0,count($cols),'?')) . ")");
            $cnt=0;
            foreach ($rows as $r) {
                foreach (['id_auxiliar','id_encargado','id_autoriza','id_solicitante','id_usuario','firmado_por'] as $fk)
                    if (isset($r[$fk]) && $r[$fk]) $r[$fk] = $id_map[(int)$r[$fk]] ?? null;
                try { $stmt->execute(array_values($r)); $cnt++; } catch (Exception $e2) {}
            }
            $pdo->commit();
            log_msg($log,"✅ pedidos_trabajo → log_pedidos_trabajo: $cnt filas.");
        }
    }
} catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ pedidos_trabajo: " . $e->getMessage(); }

// ════════════════════════════════════════════════════════════
// PASO 4: ASISTENCIAS (historial completo)
// ════════════════════════════════════════════════════════════
log_msg($log, "\n=== PASO 4: Asistencias ===");

foreach (['asistencia_partes'=>'log_asistencia_partes', 'asistencia_detalles'=>'log_asistencia_detalles', 'turnos_validador'=>'log_turnos_validador'] as $orig => $dest) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$dest` LIKE `u415354546_logistica`.`$orig`");
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM `$dest`")->fetchColumn();
        if ($existing > 0) { log_msg($log,"⚠️  $dest ya tiene $existing filas, omitido."); continue; }
        $rows = $pdo_log->query("SELECT * FROM `$orig`")->fetchAll();
        if (!$rows) { log_msg($log,"ℹ️  $orig vacía."); continue; }
        $cols = array_keys($rows[0]);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO `$dest` (" . implode(',', array_map(fn($c)=>"`$c`",$cols)) . ") VALUES (" . implode(',', array_fill(0,count($cols),'?')) . ")");
        $cnt=0;
        foreach ($rows as $r) {
            foreach (['id_usuario','id_jefe','tomado_por'] as $fk)
                if (isset($r[$fk]) && $r[$fk]) $r[$fk] = $id_map[(int)$r[$fk]] ?? null;
            try { $stmt->execute(array_values($r)); $cnt++; } catch (Exception $e2) {}
        }
        $pdo->commit();
        log_msg($log,"✅ $orig → $dest: $cnt filas.");
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "❌ $orig: " . $e->getMessage(); }
}

// ════════════════════════════════════════════════════════════
// PASO 5: SOLICITUDES DE ACCESO
// ════════════════════════════════════════════════════════════
log_msg($log, "\n=== PASO 5: Solicitudes de acceso ===");
$chk = $pdo_log->query("SHOW TABLES LIKE 'solicitudes_registro'")->fetchColumn();
$tabla_sol = $chk ? 'solicitudes_registro' : null;
if (!$tabla_sol) { $chk2 = $pdo_log->query("SHOW TABLES LIKE 'solicitudes_acceso'")->fetchColumn(); $tabla_sol = $chk2 ?: null; }
if ($tabla_sol) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `log_solicitudes_acceso` LIKE `u415354546_logistica`.`$tabla_sol`");
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM log_solicitudes_acceso")->fetchColumn();
        if ($existing > 0) { log_msg($log,"⚠️  log_solicitudes_acceso ya tiene $existing filas, omitido."); }
        else {
            $pdo->exec("INSERT INTO `u415354546_clinica_actis_`.`log_solicitudes_acceso` SELECT * FROM `u415354546_logistica`.`$tabla_sol`");
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM log_solicitudes_acceso")->fetchColumn();
            log_msg($log,"✅ $tabla_sol → log_solicitudes_acceso: $cnt filas.");
        }
    } catch (Exception $e) { $errors[] = "❌ solicitudes: " . $e->getMessage(); }
} else { log_msg($log,"ℹ️  No se encontró tabla de solicitudes de acceso."); }

// ════════════════════════════════════════════════════════════
// SALIDA HTML
// ════════════════════════════════════════════════════════════
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Migración Logística → ACTIS</title>
<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:30px}h1{color:#38bdf8}.ok{color:#4ade80}.warn{color:#facc15}.err{color:#f87171}.sec{color:#a78bfa;margin-top:15px;font-weight:bold}</style></head>
<body>
<h1>🚀 Migración Logística → ACTIS</h1>
<p style="color:#94a3b8">DB Origen: <b>u415354546_logistica</b> → DB Destino: <b>u415354546_clinica_actis_</b></p>
<?php foreach ($log as $l): ?>
    <?php $cls = str_contains($l,'===') ? 'sec' : (str_contains($l,'✅') ? 'ok' : (str_contains($l,'❌') ? 'err' : 'warn')); ?>
    <div class="<?=$cls?>"><?=htmlspecialchars($l)?></div>
<?php endforeach; ?>
<?php if ($errors): ?><h2 style="color:#f87171;margin-top:20px">Errores</h2><?php foreach($errors as $e): ?><div class="err"><?=htmlspecialchars($e)?></div><?php endforeach; endif; ?>
<div style="margin-top:30px;padding:15px;background:#1e293b;border-radius:8px">
<b style="color:#38bdf8">Resumen:</b>
<span class="ok"><?=count($log)?> mensajes</span> | <span class="err"><?=count($errors)?> errores</span>
<?php if(empty($errors)):?><br><br><b class="ok">✅ ¡Migración completada!</b> Podés borrar este archivo del servidor.<?php endif;?>
</div></body></html>
