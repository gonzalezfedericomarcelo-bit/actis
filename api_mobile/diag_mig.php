<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');
$cols = [];
try { $cols['log_asistencia_partes'] = $pdo->query("DESCRIBE log_asistencia_partes")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) { $cols['log_asistencia_partes'] = 'ERROR:'.$e->getMessage(); }
try { $cols['log_pedidos_trabajo'] = $pdo->query("DESCRIBE log_pedidos_trabajo")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) { $cols['log_pedidos_trabajo'] = 'ERROR:'.$e->getMessage(); }
try { $cols['log_tareas'] = $pdo->query("DESCRIBE log_tareas")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) { $cols['log_tareas'] = 'ERROR:'.$e->getMessage(); }
echo json_encode($cols, JSON_PRETTY_PRINT);
