<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();
$mobile_token = isset($_GET['api_mobile_token']) ? $_GET['api_mobile_token'] : '';
if(!isset($_SESSION['usuario_id']) && $mobile_token !== 'ACTIS_MOBILE_SECURE_PDF_TOKEN_2026') { die("Acceso denegado."); }
date_default_timezone_set('America/Argentina/Buenos_Aires');
error_reporting(0);
require('fpdf/fpdf.php');
require_once 'includes/conexion.php';

function convertir_texto($str) { 
    if ($str === null || $str === '') return '';
    $out = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8'); 
    return ($out === '' && $str !== '') ? utf8_decode($str) : $out;
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? $conexion->real_escape_string($_GET['fecha_inicio']) : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $conexion->real_escape_string($_GET['fecha_fin']) : date('Y-m-d');
$filtro_servicio = isset($_GET['servicio']) ? $conexion->real_escape_string($_GET['servicio']) : '';
$filtro_busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$hora_inicio = isset($_GET['hora_inicio']) ? $conexion->real_escape_string($_GET['hora_inicio']) : '';
$hora_fin = isset($_GET['hora_fin']) ? $conexion->real_escape_string($_GET['hora_fin']) : '';
$filtro_origen = isset($_GET['origen']) ? $conexion->real_escape_string($_GET['origen']) : 'todos';

if (isset($_GET['fecha']) && !isset($_GET['fecha_inicio'])) {
    $fecha_inicio = $conexion->real_escape_string($_GET['fecha']);
    $fecha_fin = $conexion->real_escape_string($_GET['fecha']);
}

if ($fecha_inicio == $fecha_fin) {
    $fecha_formateada = date("d/m/Y", strtotime($fecha_inicio));
} else {
    $fecha_formateada = date("d/m/Y", strtotime($fecha_inicio)) . " al " . date("d/m/Y", strtotime($fecha_fin));
}

$where_clauses = ["DATE(fecha_hora) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
if (!empty($filtro_servicio)) { $where_clauses[] = "servicio = '$filtro_servicio'"; }
if (!empty($filtro_busqueda)) { $where_clauses[] = "(dni LIKE '%$filtro_busqueda%' OR nombre LIKE '%$filtro_busqueda%')"; }
if (!empty($hora_inicio)) { $where_clauses[] = "TIME(fecha_hora) >= '$hora_inicio'"; }
if (!empty($hora_fin)) { $where_clauses[] = "TIME(fecha_hora) <= '$hora_fin'"; }

// Lógica de Origen
$titulo_pdf_origen = "UNIFICADO";
if ($filtro_origen === 'totem') {
    $where_clauses[] = "origen = 'TOTEM'";
    $titulo_pdf_origen = "TÓTEM";
} elseif ($filtro_origen === 'ventanilla') {
    $where_clauses[] = "origen = 'VENTANILLA'";
    $titulo_pdf_origen = "VENTANILLA";
}

$sql_where = implode(' AND ', $where_clauses);
if(empty($sql_where)) $sql_where = "1=1";

$col_check = $conexion->query("SHOW COLUMNS FROM estadisticas_totem LIKE 'tiempo_operacion'");
$has_tiempo = ($col_check && $col_check->num_rows > 0);

$conexion->query("SET time_zone = '-03:00';");
$res_validaciones = $conexion->query("SELECT COUNT(*) as total FROM estadisticas_totem WHERE modo = 'VALIDACION' AND $sql_where");
$total_validaciones = $res_validaciones ? $res_validaciones->fetch_assoc()['total'] : 0;

$res_asistencias = $conexion->query("SELECT COUNT(*) as total FROM estadisticas_totem WHERE modo = 'ASISTENCIA' AND $sql_where");
$total_asistencias = $res_asistencias ? $res_asistencias->fetch_assoc()['total'] : 0;

$total_tickets = $total_validaciones + $total_asistencias;
$res_top_servicios = $conexion->query("SELECT servicio, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where GROUP BY servicio ORDER BY cantidad DESC LIMIT 5");
$res_detalle = $conexion->query("SELECT * FROM estadisticas_totem WHERE $sql_where ORDER BY fecha_hora DESC");

$res_hora_pico = $conexion->query("SELECT HOUR(fecha_hora) as hora, COUNT(*) as cant FROM estadisticas_totem WHERE $sql_where GROUP BY HOUR(fecha_hora) ORDER BY cant DESC LIMIT 1");
$hora_pico_dato = ($res_hora_pico && $res_hora_pico->num_rows > 0) ? $res_hora_pico->fetch_assoc() : null;
$hora_pico = $hora_pico_dato ? $hora_pico_dato['hora'] . ":00 - " . $hora_pico_dato['hora'] . ":59 (" . $hora_pico_dato['cant'] . " turnos)" : "Sin datos";

$porc_val = ($total_tickets > 0) ? round(($total_validaciones / $total_tickets) * 100, 1) : 0;
$porc_asis = ($total_tickets > 0) ? round(($total_asistencias / $total_tickets) * 100, 1) : 0;

$res_extremos = $conexion->query("SELECT MIN(fecha_hora) as primer, MAX(fecha_hora) as ultimo FROM estadisticas_totem WHERE $sql_where");
$extremos = $res_extremos ? $res_extremos->fetch_assoc() : ['primer' => null, 'ultimo' => null];
$primer_ticket = $extremos['primer'] ? date("H:i", strtotime($extremos['primer'])) . " hs" : "-";
$ultimo_ticket = $extremos['ultimo'] ? date("H:i", strtotime($extremos['ultimo'])) . " hs" : "-";

$tiempo_promedio = 0; $tiempo_max = 0; $tiempo_min = 0;
$prom_modo = ['VALIDACION' => 0, 'ASISTENCIA' => 0];

$prom_origen = ['TOTEM' => 0, 'VENTANILLA' => 0];

if($has_tiempo && $total_tickets > 0) {
    $res_tiempos = $conexion->query("SELECT AVG(tiempo_operacion) as prom, MAX(tiempo_operacion) as max_t, MIN(CASE WHEN tiempo_operacion > 0 THEN tiempo_operacion ELSE NULL END) as min_t FROM estadisticas_totem WHERE $sql_where");
    if($res_tiempos && $row_t = $res_tiempos->fetch_assoc()) {
        $tiempo_promedio = round($row_t['prom'], 1);
        $tiempo_max = $row_t['max_t'] ?: 0;
        $tiempo_min = $row_t['min_t'] ?: 0;
    }
    
    $res_prom_modo = $conexion->query("SELECT modo, AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 GROUP BY modo");
    if($res_prom_modo) {
        while($r = $res_prom_modo->fetch_assoc()) {
            $prom_modo[$r['modo']] = round($r['prom'], 1);
        }
    }

    $res_prom_origen = $conexion->query("SELECT origen, AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 GROUP BY origen");
    if($res_prom_origen) {
        while($r = $res_prom_origen->fetch_assoc()) {
            $prom_origen[$r['origen']] = round($r['prom'], 1);
        }
    }
}

$tiempos_entre_totem = [];
$tiempos_entre_ventanilla = [];
$res_tiempos_entre = $conexion->query("SELECT origen, fecha_hora, tiempo_operacion FROM estadisticas_totem WHERE $sql_where ORDER BY origen, fecha_hora ASC");
$last_end_totem = null;
$last_end_ventanilla = null;
if ($res_tiempos_entre) {
    while($row = $res_tiempos_entre->fetch_assoc()) {
        $current_end = strtotime($row['fecha_hora']);
        $current_duration = (float)$row['tiempo_operacion'];
        $current_start = $current_end - $current_duration;

        if ($row['origen'] == 'TOTEM' || $row['origen'] == '') {
            if ($last_end_totem !== null) {
                $espera = $current_start - ($last_end_totem + 1);
                if ($espera < 0) $espera = 0;
                if ($espera < 3600) { $tiempos_entre_totem[] = $espera; }
            }
            $last_end_totem = $current_end;
        } elseif ($row['origen'] == 'VENTANILLA') {
            if ($last_end_ventanilla !== null) {
                $espera = $current_start - ($last_end_ventanilla + 1);
                if ($espera < 0) $espera = 0;
                if ($espera < 3600) { $tiempos_entre_ventanilla[] = $espera; }
            }
            $last_end_ventanilla = $current_end;
        }
    }
}
$prom_entre_totem = count($tiempos_entre_totem) > 0 ? round(array_sum($tiempos_entre_totem) / count($tiempos_entre_totem)) : 0;
$prom_entre_ventanilla = count($tiempos_entre_ventanilla) > 0 ? round(array_sum($tiempos_entre_ventanilla) / count($tiempos_entre_ventanilla)) : 0;

$tiempo_manual_promedio = 60; 
$ahorro_por_ticket = max(0, $tiempo_manual_promedio - $tiempo_promedio);
$ahorro_total_seg = (int)($ahorro_por_ticket * $total_tickets);
$ahorro_horas = floor($ahorro_total_seg / 3600);
$ahorro_min = floor(($ahorro_total_seg % 3600) / 60);
$texto_ahorro_tiempo = $ahorro_horas > 0 ? "{$ahorro_horas}h {$ahorro_min}m" : "{$ahorro_min} min";
$mejora_porcentual = $tiempo_manual_promedio > 0 ? round(($ahorro_por_ticket / $tiempo_manual_promedio) * 100) : 0;

$res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'");
$tickets_impresos = $res_papel ? (int)$res_papel->fetch_assoc()['estado'] : 0;
$res_cap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'");
$capacidad_rollo = $res_cap ? (int)$res_cap->fetch_assoc()['estado'] : 120;
$papel_restante = $capacidad_rollo - $tickets_impresos;

$hash_seguridad = md5($fecha_inicio . $fecha_fin . "ACTIS" . time());
$url_verificacion = "https://federicogonzalez.net/actis/verificar_reporte.php?hash=" . $hash_seguridad . "&fecha=" . urlencode($fecha_inicio);
$qr_url = "https://quickchart.io/qr?size=150&margin=0&text=" . urlencode($url_verificacion);

class PDF_Reporte extends FPDF {
    public $fecha_rep;
    public $qr_link;
    public $hash_doc;
    public $titulo_rep;

    function Header() {
        if(file_exists('img/osfa_negro.png')) {
            $this->Image('img/osfa_negro.png', 10, 8, 25); 
        }
        if(file_exists('img/sello_log.png')) {
            $this->Image('img/sello_log.png', 178, 8, 18); 
        }
        
        $this->SetY(12);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(15, 23, 42); 
        $this->Cell(0, 7, convertir_texto('REPORTE ESTADÍSTICO - ' . $this->titulo_rep), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 116, 139); 
        $this->Cell(0, 5, convertir_texto('POLICLÍNICA GENERAL ACTIS - OSFA'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, convertir_texto('Fecha Analizada: ' . $this->fecha_rep), 0, 1, 'C');
        
        $this->SetY(35);
        
        $this->SetDrawColor(226, 232, 240); 
        $this->SetLineWidth(0.5);
        $this->Line(10, 33, 200, 33);
        $this->Ln(4);
        
        $this->SetTextColor(15, 23, 42);
        $this->SetLineWidth(0.2);
    }
    
    function Footer() {
        $this->SetY(-30); 
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        
        if ($this->qr_link) {
            $this->Image($this->qr_link, 10, $this->GetY(), 20, 20, 'PNG');
        }
        
        $this->SetXY(35, $this->GetY() + 2); 
        $this->SetTextColor(71, 85, 105);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 4, convertir_texto('DOCUMENTO ESTADÍSTICO OFICIAL'), 0, 1, 'L');
        
        $this->SetX(35);
        $this->SetFont('Courier', '', 7);
        $this->Cell(0, 4, convertir_texto('Hash Seguridad: ' . $this->hash_doc), 0, 1, 'L');
        
        $this->SetX(35);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 4, convertir_texto("Escanee el código QR para verificar la autenticidad online."), 0, 1, 'L');

        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, convertir_texto('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF_Reporte('P','mm','A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 35); 
$pdf->fecha_rep = $fecha_formateada;
$pdf->titulo_rep = $titulo_pdf_origen;
$pdf->qr_link = $qr_url;
$pdf->hash_doc = strtoupper(substr($hash_seguridad, 0, 12));
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, convertir_texto(' INDICADORES DE RENDIMIENTO (KPIs) DETALLADOS'), 0, 1, 'L', true);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetDrawColor(226, 232, 240); 
$pdf->SetFont('Arial','B',8);
$pdf->Cell(38, 7, convertir_texto('TOTAL TICKETS'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('VALIDACIONES'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('% VALIDACIÓN'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('ASISTENCIAS'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('% ASISTENCIA'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(15, 23, 42); 
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(38, 10, $total_tickets, 'B', 0, 'C', true);
$pdf->SetTextColor(37, 99, 235); 
$pdf->Cell(38, 10, $total_validaciones, 'B', 0, 'C', true);
$pdf->Cell(38, 10, $porc_val . '%', 'B', 0, 'C', true);
$pdf->SetTextColor(245, 158, 11); 
$pdf->Cell(38, 10, $total_asistencias, 'B', 0, 'C', true);
$pdf->Cell(38, 10, $porc_asis . '%', 'B', 1, 'C', true);

$pdf->Ln(4);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(63, 7, convertir_texto('HORA PICO (MÁX. DEMANDA)'), 'B', 0, 'C', true);
$pdf->Cell(63, 7, convertir_texto('PRIMER TICKET EMITIDO'), 'B', 0, 'C', true);
$pdf->Cell(64, 7, convertir_texto('ÚLTIMO TICKET EMITIDO'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(15, 23, 42); 
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(63, 9, convertir_texto($hora_pico), 'B', 0, 'C', true);
$pdf->Cell(63, 9, $primer_ticket, 'B', 0, 'C', true);
$pdf->Cell(64, 9, $ultimo_ticket, 'B', 1, 'C', true);
    
$pdf->Ln(4);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(47, 7, convertir_texto('TIEMPO PROMEDIO GLOBAL'), 'B', 0, 'C', true);
$pdf->Cell(47, 7, convertir_texto('RÉCORD MÁS RÁPIDO'), 'B', 0, 'C', true);
$pdf->Cell(48, 7, convertir_texto('MÁXIMA DEMORA'), 'B', 0, 'C', true);
$pdf->Cell(48, 7, convertir_texto('PROM. VALIDACIÓN'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',11);
$pdf->SetTextColor(15, 23, 42); 
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(47, 9, $tiempo_promedio . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(16, 185, 129); 
$pdf->Cell(47, 9, $tiempo_min . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(239, 68, 68); 
$pdf->Cell(48, 9, $tiempo_max . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(37, 99, 235); 
$pdf->Cell(48, 9, ($prom_modo['VALIDACION'] ?? 0) . ' seg', 'B', 1, 'C', true);

$pdf->Ln(4);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(47, 7, convertir_texto('SISTEMA ANTIGUO'), 'B', 0, 'C', true);
$pdf->Cell(47, 7, convertir_texto('NUEVA VENTANILLA'), 'B', 0, 'C', true);
$pdf->Cell(48, 7, convertir_texto('NUEVO TÓTEM'), 'B', 0, 'C', true);
$pdf->Cell(48, 7, convertir_texto('AHORRO VS MANUAL'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(239, 68, 68); 
$pdf->Cell(47, 9, '60 seg', 'B', 0, 'C', true);
$pdf->SetTextColor(245, 158, 11); 
$pdf->Cell(47, 9, ($prom_origen['VENTANILLA'] ?? 0) . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(16, 185, 129); 
$pdf->Cell(48, 9, ($prom_origen['TOTEM'] ?? 0) . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(37, 99, 235); 
$pdf->Cell(48, 9, $texto_ahorro_tiempo, 'B', 1, 'C', true);

$pdf->Ln(4);

// Nueva fila de Tiempo de Espera entre pacientes
$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(94, 7, convertir_texto('ESPERA PROMEDIO ENTRE PACIENTES (TÓTEM)'), 'B', 0, 'C', true);
$pdf->Cell(96, 7, convertir_texto('ESPERA PROMEDIO ENTRE PACIENTES (VENTANILLA)'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(20, 184, 166); 
$pdf->Cell(94, 9, $prom_entre_totem . ' seg', 'B', 0, 'C', true);
$pdf->SetTextColor(244, 63, 94); 
$pdf->Cell(96, 9, $prom_entre_ventanilla . ' seg', 'B', 1, 'C', true);

$pdf->Ln(4);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(190, 7, convertir_texto('ESTADO DEL HARDWARE: NIVEL DE PAPEL TÉRMICO'), 'B', 1, 'C', true);
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(255, 255, 255);

if($papel_restante <= 20) {
    $pdf->SetTextColor(239, 68, 68); 
} else {
    $pdf->SetTextColor(16, 185, 129); 
}
$pdf->Cell(190, 9, convertir_texto("QUEDAN APROXIMADAMENTE $papel_restante TICKETS EN EL ROLLO ACTUAL"), 'B', 1, 'C', true);
$pdf->Ln(8);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, convertir_texto(' SERVICIOS MÁS SOLICITADOS'), 0, 1, 'L', true);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetFont('Arial','B',8);
$pdf->Cell(150, 7, convertir_texto('SERVICIO'), 'B', 0, 'L', true);
$pdf->Cell(40, 7, convertir_texto('CANTIDAD'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',9);
$pdf->SetTextColor(15, 23, 42); 
$fill_row = false;

if($res_top_servicios && $res_top_servicios->num_rows > 0) {
    while($s = $res_top_servicios->fetch_assoc()) {
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Cell(150, 7, convertir_texto($s['servicio']), 'B', 0, 'L', $fill_row);
        $pdf->Cell(40, 7, $s['cantidad'], 'B', 1, 'C', $fill_row);
        $fill_row = !$fill_row;
    }
} else {
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(190, 8, convertir_texto('Sin datos en este período.'), 'B', 1, 'C');
}
$pdf->Ln(8);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, convertir_texto(' DETALLE DE OPERACIONES'), 0, 1, 'L', true);

function imprimir_cabecera_detalles($pdf) {
    $pdf->SetFillColor(241, 245, 249); 
    $pdf->SetTextColor(71, 85, 105);   
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial','B',7); 
    $pdf->Cell(12, 7, 'HORA', 'B', 0, 'C', true);
    $pdf->Cell(18, 7, 'DNI', 'B', 0, 'C', true);
    $pdf->Cell(50, 7, 'PACIENTE', 'B', 0, 'L', true);
    $pdf->Cell(37, 7, 'SERVICIO', 'B', 0, 'L', true);
    $pdf->Cell(15, 7, 'ORDEN', 'B', 0, 'C', true);
    $pdf->Cell(22, 7, 'TOKEN', 'B', 0, 'C', true);
    $pdf->Cell(18, 7, 'TIPO', 'B', 0, 'C', true);
    $pdf->Cell(18, 7, 'TPO(s)', 'B', 1, 'C', true); 
}

imprimir_cabecera_detalles($pdf);

$pdf->SetFont('Arial','',7);
$pdf->SetTextColor(15, 23, 42); 
$fill_row = false;

if($res_detalle && $res_detalle->num_rows > 0) {
    while($fila = $res_detalle->fetch_assoc()) {
        
        if($pdf->GetY() > 255) {
            $pdf->AddPage();
            imprimir_cabecera_detalles($pdf);
            $pdf->SetFont('Arial','',7);
            $pdf->SetTextColor(15, 23, 42); 
        }

        $pdf->SetFillColor(248, 250, 252);
        
        $pdf->Cell(12, 7, date("H:i", strtotime($fila['fecha_hora'])), 'B', 0, 'C', $fill_row);
        $pdf->Cell(18, 7, $fila['dni'], 'B', 0, 'C', $fill_row);
        
        $nombre_corto = mb_substr(convertir_texto($fila['nombre']), 0, 25, 'ISO-8859-1');
        $pdf->Cell(50, 7, $nombre_corto, 'B', 0, 'L', $fill_row);
        
        $serv_corto = mb_substr(convertir_texto($fila['servicio']), 0, 20, 'ISO-8859-1');
        $pdf->Cell(37, 7, $serv_corto, 'B', 0, 'L', $fill_row);

        $pdf->SetFont('Arial','B',7);
        $orden_str = isset($fila['numero_orden']) && $fila['numero_orden'] ? $fila['numero_orden'] : '-';
        $pdf->Cell(15, 7, $orden_str, 'B', 0, 'C', $fill_row);
        
        $pdf->Cell(22, 7, $fila['token_iofa'], 'B', 0, 'C', $fill_row);
        
        $pdf->SetFont('Arial','',7);
        $modo = $fila['modo'] == 'VALIDACION' ? 'VAL' : 'ASIS';
        $pdf->Cell(18, 7, $modo, 'B', 0, 'C', $fill_row);

        $pdf->SetFont('Arial','B',7);
        $pdf->SetTextColor(100, 116, 139);
        $t_op = $has_tiempo ? $fila['tiempo_operacion'] . 's' : '-';
        $pdf->Cell(18, 7, $t_op, 'B', 1, 'C', $fill_row);
        $pdf->SetTextColor(15, 23, 42); 
        $pdf->SetFont('Arial','',7);
        
        $fill_row = !$fill_row;
    }
} else {
    $pdf->Cell(190, 8, convertir_texto('No hay registros en este período.'), 'B', 1, 'C');
}

$pdf->AddPage();
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, convertir_texto(' ANÁLISIS GRÁFICO DE DATOS Y TIEMPOS'), 0, 1, 'C', true);
$pdf->Ln(10);

$pdf->SetTextColor(15, 23, 42); 

// Gráfico 1: Proporción
if ($total_tickets > 0) {
    $chart1_config = "{type:'pie',data:{labels:['Validaciones','Asistencias'],datasets:[{data:[$total_validaciones,$total_asistencias],backgroundColor:['#2563eb','#f59e0b']}]},options:{legend:{position:'right',labels:{fontSize:14}},plugins:{datalabels:{color:'#fff',font:{weight:'bold',size:16}}}}}";
    // Aumentamos la resolución (w=600) para que las letras se vean pequeñas y proporcionadas
    $chart1_url = "https://quickchart.io/chart?c=" . urlencode($chart1_config) . "&w=600&h=300&bkg=white";
    
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('PROPORCIÓN DE TRAMITES: VALIDACIONES VS ASISTENCIAS'), 0, 1, 'C');
    $pdf->Image($chart1_url, 45, $pdf->GetY() + 2, 120, 0, 'PNG');
    $pdf->Ln(70); 
}

// Gráfico 2: Demanda
$res_chart_hora = $conexion->query("SELECT HOUR(fecha_hora) as hora, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where GROUP BY HOUR(fecha_hora) ORDER BY hora ASC");
$labels_hora = []; $data_hora = [];
if($res_chart_hora && $res_chart_hora->num_rows > 0) {
    while($row = $res_chart_hora->fetch_assoc()) {
        $labels_hora[] = "'" . $row['hora'] . ":00'";
        $data_hora[] = $row['cantidad'];
    }
    $chart2_config = "{type:'line',data:{labels:[" . implode(",", $labels_hora) . "],datasets:[{label:'Tickets Emitidos',data:[" . implode(",", $data_hora) . "],fill:true,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,0.2)'}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:12}}],yAxes:[{ticks:{fontSize:12,beginAtZero:true}}]}}}";
    $chart2_url = "https://quickchart.io/chart?c=" . urlencode($chart2_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('CURVA DE DEMANDA POR HORA'), 0, 1, 'C');
    $pdf->Image($chart2_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 3: Velocidad
if ($has_tiempo && $total_tickets > 0) {
    $t_val = $prom_modo['VALIDACION'] ?? 0;
    $t_asi = $prom_modo['ASISTENCIA'] ?? 0;
    
    $chart3_config = "{type:'bar',data:{labels:['Promedio Validación','Promedio Asistencia'],datasets:[{label:'Segundos',data:[$t_val,$t_asi],backgroundColor:['#2563eb','#f59e0b']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14}}],yAxes:[{ticks:{fontSize:14,beginAtZero:true}}]}}}";
    $chart3_url = "https://quickchart.io/chart?c=" . urlencode($chart3_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('VELOCIDAD DEL PACIENTE (Tiempos Promedio en Segundos)'), 0, 1, 'C');
    $pdf->Image($chart3_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 4: Eficiencia de Sistemas
if ($has_tiempo && $total_tickets > 0) {
    $t_ven = $prom_origen['VENTANILLA'] ?? 0;
    $t_tot = $prom_origen['TOTEM'] ?? 0;
    
    $chart4_config = "{type:'horizontalBar',data:{labels:['Sistema Antiguo','Ventanilla','Tótem'],datasets:[{label:'Segundos',data:[60,$t_ven,$t_tot],backgroundColor:['#ef4444','#f59e0b','#10b981']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14,beginAtZero:true}}],yAxes:[{ticks:{fontSize:14}}]}}}";
    $chart4_url = "https://quickchart.io/chart?c=" . urlencode($chart4_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('EFICIENCIA: EVOLUCIÓN DE TIEMPOS POR SISTEMA (Segundos)'), 0, 1, 'C');
    $pdf->Image($chart4_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 5: Cola de Espera
if ($prom_entre_totem > 0 || $prom_entre_ventanilla > 0) {
    $chart5_config = "{type:'bar',data:{labels:['Espera en Tótem','Espera en Ventanilla'],datasets:[{label:'Segundos de Espera',data:[$prom_entre_totem,$prom_entre_ventanilla],backgroundColor:['#14b8a6','#f43f5e']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14}}],yAxes:[{ticks:{fontSize:14,beginAtZero:true}}]}}}";
    $chart5_url = "https://quickchart.io/chart?c=" . urlencode($chart5_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('TIEMPO DE ESPERA EN COLA (Segundos entre pacientes)'), 0, 1, 'C');
    $pdf->Image($chart5_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 6: Top 5 Servicios más solicitados
$res_servicios = $conexion->query("SELECT servicio, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where AND servicio != '' AND servicio IS NOT NULL GROUP BY servicio ORDER BY cantidad DESC LIMIT 5");
$labels_srv = []; $data_srv = [];
if($res_servicios && $res_servicios->num_rows > 0) {
    while($row = $res_servicios->fetch_assoc()) {
        $nombre_corto = mb_substr($row['servicio'], 0, 20, 'UTF-8');
        $labels_srv[] = "'" . $nombre_corto . "'"; 
        $data_srv[] = $row['cantidad'];
    }
    $chart6_config = "{type:'horizontalBar',data:{labels:[" . implode(",", $labels_srv) . "],datasets:[{label:'Pacientes',data:[" . implode(",", $data_srv) . "],backgroundColor:'#3b82f6'}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14,beginAtZero:true}}],yAxes:[{ticks:{fontSize:12}}]}}}";
    $chart6_url = "https://quickchart.io/chart?c=" . urlencode($chart6_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('TOP 5: ESPECIALIDADES / SERVICIOS MÁS SOLICITADOS'), 0, 1, 'C');
    $pdf->Image($chart6_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 7: Demanda por Turno - Mañana vs Tarde
$res_turno = $conexion->query("SELECT IF(HOUR(fecha_hora) < 13, 'Mañana (hasta 13hs)', 'Tarde (desde 13hs)') as franja, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where GROUP BY franja");
$labels_trn = []; $data_trn = [];
if($res_turno && $res_turno->num_rows > 0) {
    while($row = $res_turno->fetch_assoc()) {
        $labels_trn[] = "'" . $row['franja'] . "'";
        $data_trn[] = $row['cantidad'];
    }
    $chart7_config = "{type:'pie',data:{labels:[" . implode(",", $labels_trn) . "],datasets:[{data:[" . implode(",", $data_trn) . "],backgroundColor:['#8b5cf6','#ec4899']}]},options:{legend:{position:'right',labels:{fontSize:14}},plugins:{datalabels:{color:'#fff',font:{weight:'bold',size:16}}}}}";
    $chart7_url = "https://quickchart.io/chart?c=" . urlencode($chart7_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('VOLUMEN DE PACIENTES: TURNO MAÑANA VS TARDE'), 0, 1, 'C');
    $pdf->Image($chart7_url, 45, $pdf->GetY() + 2, 120, 0, 'PNG');
    $pdf->Ln(80);
}

// Preparación de datos de Edades por DNI
$sql_edades = "SELECT 
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) >= 45000000 THEN 1 ELSE 0 END) as ninos,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) BETWEEN 35000000 AND 44999999 THEN 1 ELSE 0 END) as jovenes,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) BETWEEN 20000000 AND 34999999 THEN 1 ELSE 0 END) as adultos,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) > 0 AND CAST(REPLACE(dni, '.', '') AS UNSIGNED) < 20000000 THEN 1 ELSE 0 END) as mayores
    FROM estadisticas_totem WHERE $sql_where AND dni != ''";
$res_edades = $conexion->query($sql_edades);
$edades = $res_edades ? $res_edades->fetch_assoc() : ['ninos'=>0,'jovenes'=>0,'adultos'=>0,'mayores'=>0];
$total_edades = $edades['ninos'] + $edades['jovenes'] + $edades['adultos'] + $edades['mayores'];

if ($total_edades > 0) {
    // Gráfico 8: Distribución de Edades
    $chart8_config = "{type:'bar',data:{labels:['Niños/Adolesc.','Jóvenes','Adultos','Adultos Mayores'],datasets:[{label:'Pacientes',data:[{$edades['ninos']},{$edades['jovenes']},{$edades['adultos']},{$edades['mayores']}],backgroundColor:['#10b981','#3b82f6','#f59e0b','#ef4444']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14}}],yAxes:[{ticks:{fontSize:14,beginAtZero:true}}]}}}";
    $chart8_url = "https://quickchart.io/chart?c=" . urlencode($chart8_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('DEMOGRAFÍA: DISTRIBUCIÓN POR EDADES (APROX. POR DNI)'), 0, 1, 'C');
    $pdf->Image($chart8_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);

    // Gráfico 9: Proporción Jóvenes vs Adultos
    $jovenes_total = $edades['ninos'] + $edades['jovenes'];
    $mayores_total = $edades['adultos'] + $edades['mayores'];
    $chart9_config = "{type:'doughnut',data:{labels:['Sub 35 Años (Jóvenes)','Mayores de 35 (Adultos)'],datasets:[{data:[$jovenes_total,$mayores_total],backgroundColor:['#3b82f6','#ef4444']}]},options:{legend:{position:'right',labels:{fontSize:14}},plugins:{datalabels:{color:'#fff',font:{weight:'bold',size:16}}}}}";
    $chart9_url = "https://quickchart.io/chart?c=" . urlencode($chart9_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('PROPORCIÓN GENERACIONAL GENERAL'), 0, 1, 'C');
    $pdf->Image($chart9_url, 45, $pdf->GetY() + 2, 120, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 10: Preferencia de Servicios según Edad (Jóvenes vs Mayores)
$sql_srv_edad = "SELECT servicio,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) >= 35000000 THEN 1 ELSE 0 END) as jovenes,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) > 0 AND CAST(REPLACE(dni, '.', '') AS UNSIGNED) < 35000000 THEN 1 ELSE 0 END) as mayores
    FROM estadisticas_totem 
    WHERE $sql_where AND servicio != '' AND servicio IS NOT NULL AND dni != ''
    GROUP BY servicio ORDER BY COUNT(*) DESC LIMIT 4";
$res_srv_edad = $conexion->query($sql_srv_edad);
$labels_srv2 = []; $data_jovenes = []; $data_mayores = [];
if ($res_srv_edad && $res_srv_edad->num_rows > 0) {
    while($row = $res_srv_edad->fetch_assoc()) {
        $labels_srv2[] = "'" . mb_substr($row['servicio'], 0, 15, 'UTF-8') . "'";
        $data_jovenes[] = $row['jovenes'];
        $data_mayores[] = $row['mayores'];
    }
    $chart10_config = "{type:'bar',data:{labels:[" . implode(",", $labels_srv2) . "],datasets:[{label:'Jóvenes (<35)',data:[" . implode(",", $data_jovenes) . "],backgroundColor:'#3b82f6'},{label:'Adultos (>35)',data:[" . implode(",", $data_mayores) . "],backgroundColor:'#ef4444'}]},options:{legend:{position:'bottom',labels:{fontSize:12}},scales:{xAxes:[{stacked:true,ticks:{fontSize:12}}],yAxes:[{stacked:true,ticks:{fontSize:12,beginAtZero:true}}]}}}";
    $chart10_url = "https://quickchart.io/chart?c=" . urlencode($chart10_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('PREFERENCIA DE SERVICIOS POR GENERACIÓN (TOP 4)'), 0, 1, 'C');
    $pdf->Image($chart10_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 11: Afluencia por Horario según Edad
$sql_horario_edad = "SELECT 
    IF(HOUR(fecha_hora) < 13, 'Mañana', 'Tarde') as franja,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) >= 45000000 THEN 1 ELSE 0 END) as ninos,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) BETWEEN 35000000 AND 44999999 THEN 1 ELSE 0 END) as jovenes,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) BETWEEN 20000000 AND 34999999 THEN 1 ELSE 0 END) as adultos,
    SUM(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) > 0 AND CAST(REPLACE(dni, '.', '') AS UNSIGNED) < 20000000 THEN 1 ELSE 0 END) as mayores
    FROM estadisticas_totem 
    WHERE $sql_where AND dni != ''
    GROUP BY franja";
$res_horario_edad = $conexion->query($sql_horario_edad);
$labels_h = []; $d_ninos = []; $d_jovenes = []; $d_adultos = []; $d_mayores = [];
if ($res_horario_edad && $res_horario_edad->num_rows > 0) {
    while($row = $res_horario_edad->fetch_assoc()) {
        $labels_h[] = "'" . $row['franja'] . "'";
        $d_ninos[] = $row['ninos']; $d_jovenes[] = $row['jovenes'];
        $d_adultos[] = $row['adultos']; $d_mayores[] = $row['mayores'];
    }
    $chart11_config = "{type:'bar',data:{labels:[" . implode(",", $labels_h) . "],datasets:[{label:'Niños',data:[" . implode(",", $d_ninos) . "],backgroundColor:'#10b981'},{label:'Jóvenes',data:[" . implode(",", $d_jovenes) . "],backgroundColor:'#3b82f6'},{label:'Adultos',data:[" . implode(",", $d_adultos) . "],backgroundColor:'#f59e0b'},{label:'Mayores',data:[" . implode(",", $d_mayores) . "],backgroundColor:'#ef4444'}]},options:{legend:{position:'bottom',labels:{fontSize:12}},scales:{xAxes:[{ticks:{fontSize:14}}],yAxes:[{ticks:{fontSize:14,beginAtZero:true}}]}}}";
    $chart11_url = "https://quickchart.io/chart?c=" . urlencode($chart11_config) . "&w=600&h=300&bkg=white";
    
    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('COMPORTAMIENTO HORARIO SEGÚN EDAD'), 0, 1, 'C');
    $pdf->Image($chart11_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}
// Gráfico 12: Afluencia por Día de la Semana
$sql_dias = "SELECT DAYOFWEEK(fecha_hora) as dia, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where GROUP BY dia ORDER BY dia ASC";
$res_dias = $conexion->query($sql_dias);
$nombres_dias = [1=>'Dom', 2=>'Lun', 3=>'Mar', 4=>'Mié', 5=>'Jue', 6=>'Vie', 7=>'Sáb'];
$labels_dia = []; $data_dia = [];
if ($res_dias && $res_dias->num_rows > 0) {
    while($row = $res_dias->fetch_assoc()) {
        $labels_dia[] = "'" . $nombres_dias[$row['dia']] . "'";
        $data_dia[] = $row['cantidad'];
    }
    $chart12_config = "{type:'bar',data:{labels:[" . implode(",", $labels_dia) . "],datasets:[{label:'Pacientes',data:[" . implode(",", $data_dia) . "],backgroundColor:'#8b5cf6'}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14}}],yAxes:[{ticks:{fontSize:14,beginAtZero:true}}]}}}";
    $chart12_url = "https://quickchart.io/chart?c=" . urlencode($chart12_config) . "&w=600&h=300&bkg=white";

    if($pdf->GetY() > 180) { $pdf->AddPage(); }
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir_texto('COMPORTAMIENTO SEMANAL (Afluencia por Día)'), 0, 1, 'C');
    $pdf->Image($chart12_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
    $pdf->Ln(80);
}

// Gráfico 13: Accesibilidad Tecnológica (Tiempo promedio en Tótem según Edad)
$sql_acc = "SELECT 
    AVG(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) >= 35000000 THEN tiempo_operacion ELSE NULL END) as t_jovenes,
    AVG(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) BETWEEN 20000000 AND 34999999 THEN tiempo_operacion ELSE NULL END) as t_adultos,
    AVG(CASE WHEN CAST(REPLACE(dni, '.', '') AS UNSIGNED) > 0 AND CAST(REPLACE(dni, '.', '') AS UNSIGNED) < 20000000 THEN tiempo_operacion ELSE NULL END) as t_mayores
    FROM estadisticas_totem 
    WHERE $sql_where AND dni != '' AND (origen = 'TOTEM' OR origen = '') AND tiempo_operacion > 0";
$res_acc = $conexion->query($sql_acc);
if ($res_acc && $row_acc = $res_acc->fetch_assoc()) {
    $tj = round((float)$row_acc['t_jovenes'], 1);
    $ta = round((float)$row_acc['t_adultos'], 1);
    $tm = round((float)$row_acc['t_mayores'], 1);
    
    if ($tj > 0 || $ta > 0 || $tm > 0) {
        $chart13_config = "{type:'horizontalBar',data:{labels:['Jóvenes (<35)','Adultos (35-60)','Mayores (>60)'],datasets:[{label:'Segundos',data:[$tj,$ta,$tm],backgroundColor:['#3b82f6','#f59e0b','#ef4444']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:14,beginAtZero:true}}],yAxes:[{ticks:{fontSize:14}}]}}}";
        $chart13_url = "https://quickchart.io/chart?c=" . urlencode($chart13_config) . "&w=600&h=300&bkg=white";
        
        if($pdf->GetY() > 180) { $pdf->AddPage(); }
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('ACCESIBILIDAD: TIEMPO DE USO DEL TÓTEM POR GENERACIÓN (Segundos)'), 0, 1, 'C');
        $pdf->Image($chart13_url, 35, $pdf->GetY() + 2, 140, 0, 'PNG');
        $pdf->Ln(80);
    }
}

// Gráfico 14: Nivel de Fluidez (Trámites Express vs Lentos)
$sql_dif = "SELECT 
    SUM(CASE WHEN tiempo_operacion <= 30 THEN 1 ELSE 0 END) as express,
    SUM(CASE WHEN tiempo_operacion > 30 AND tiempo_operacion <= 60 THEN 1 ELSE 0 END) as normales,
    SUM(CASE WHEN tiempo_operacion > 60 THEN 1 ELSE 0 END) as lentos
    FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0";
$res_dif = $conexion->query($sql_dif);
if ($res_dif && $row_dif = $res_dif->fetch_assoc()) {
    $exp = (int)$row_dif['express'];
    $nor = (int)$row_dif['normales'];
    $len = (int)$row_dif['lentos'];
    
    if ($exp > 0 || $nor > 0 || $len > 0) {
        $chart14_config = "{type:'doughnut',data:{labels:['Express (<30s)','Normales (30-60s)','Lentos (>60s)'],datasets:[{data:[$exp,$nor,$len],backgroundColor:['#10b981','#f59e0b','#ef4444']}]},options:{legend:{position:'right',labels:{fontSize:14}},plugins:{datalabels:{color:'#fff',font:{weight:'bold',size:16}}}}}";
        $chart14_url = "https://quickchart.io/chart?c=" . urlencode($chart14_config) . "&w=600&h=300&bkg=white";
        
        if($pdf->GetY() > 180) { $pdf->AddPage(); }
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('NIVEL DE FLUIDEZ DE ATENCIÓN (Distribución de Tiempos)'), 0, 1, 'C');
        $pdf->Image($chart14_url, 45, $pdf->GetY() + 2, 120, 0, 'PNG');
        $pdf->Ln(80);
    }
}

$pdf->Output('I', 'Reporte_General_' . $fecha_inicio . '.pdf');
?>

$pdf->SetTextColor(15, 23, 42); 

// Gráfico 1: Proporción (Arriba Izquierda)
if ($total_tickets > 0) {
    $chart1_config = "{type:'pie',data:{labels:['Validaciones','Asistencias'],datasets:[{data:[$total_validaciones,$total_asistencias],backgroundColor:['#2563eb','#f59e0b']}]},options:{legend:{position:'bottom'},plugins:{datalabels:{color:'#fff',font:{weight:'bold',size:10}}}}}";
    $chart1_url = "https://quickchart.io/chart?c=" . urlencode($chart1_config) . "&w=300&h=250&bkg=white";
    
    $pdf->SetXY(15, 40);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(85, 6, convertir_texto('PROPORCIÓN DE TRAMITES'), 0, 0, 'C');
    $pdf->Image($chart1_url, 15, 46, 85, 0, 'PNG');
}

// Gráfico 2: Demanda por Hora (Arriba Derecha)
$res_chart_hora = $conexion->query("SELECT HOUR(fecha_hora) as hora, COUNT(*) as cantidad FROM estadisticas_totem WHERE $sql_where GROUP BY HOUR(fecha_hora) ORDER BY hora ASC");
$labels_hora = []; $data_hora = [];
if($res_chart_hora && $res_chart_hora->num_rows > 0) {
    while($row = $res_chart_hora->fetch_assoc()) {
        $labels_hora[] = "'" . $row['hora'] . "h'";
        $data_hora[] = $row['cantidad'];
    }
    $labels_hora_str = implode(",", $labels_hora);
    $data_hora_str = implode(",", $data_hora);
    
    $chart2_config = "{type:'line',data:{labels:[$labels_hora_str],datasets:[{label:'Tickets',data:[$data_hora_str],fill:true,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,0.2)'}]},options:{legend:{display:false}}}";
    $chart2_url = "https://quickchart.io/chart?c=" . urlencode($chart2_config) . "&w=300&h=250&bkg=white";
    
    $pdf->SetXY(110, 40);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(85, 6, convertir_texto('CURVA DE DEMANDA POR HORA'), 0, 0, 'C');
    $pdf->Image($chart2_url, 110, 46, 85, 0, 'PNG');
}

// Gráfico 3: Velocidad del Paciente (Medio Izquierda)
if ($has_tiempo && $total_tickets > 0) {
    $t_val = $prom_modo['VALIDACION'] ?? 0;
    $t_asi = $prom_modo['ASISTENCIA'] ?? 0;
    
    $chart3_config = "{type:'bar',data:{labels:['Validación','Asistencia'],datasets:[{label:'Segundos',data:[$t_val,$t_asi],backgroundColor:['#2563eb','#f59e0b']}]},options:{legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true}}]}}}";
    $chart3_url = "https://quickchart.io/chart?c=" . urlencode($chart3_config) . "&w=300&h=250&bkg=white";
    
    $pdf->SetXY(15, 120);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(85, 6, convertir_texto('VELOCIDAD EN PANTALLA (Segundos)'), 0, 0, 'C');
    $pdf->Image($chart3_url, 15, 126, 85, 0, 'PNG');
}

// Gráfico 4: Eficiencia de Sistemas (Medio Derecha)
if ($has_tiempo && $total_tickets > 0) {
    $t_ven = $prom_origen['VENTANILLA'] ?? 0;
    $t_tot = $prom_origen['TOTEM'] ?? 0;
    
    $chart4_config = "{type:'horizontalBar',data:{labels:['Antiguo','Ventanilla','Tótem'],datasets:[{label:'Segundos',data:[60,$t_ven,$t_tot],backgroundColor:['#ef4444','#f59e0b','#10b981']}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{beginAtZero:true}}]}}}";
    $chart4_url = "https://quickchart.io/chart?c=" . urlencode($chart4_config) . "&w=300&h=250&bkg=white";
    
    $pdf->SetXY(110, 120);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(85, 6, convertir_texto('EFICIENCIA GLOBAL (Segundos)'), 0, 0, 'C');
    $pdf->Image($chart4_url, 110, 126, 85, 0, 'PNG');
}

// Gráfico 5: Cola de Espera (Abajo Centro)
if ($prom_entre_totem > 0 || $prom_entre_ventanilla > 0) {
    $chart5_config = "{type:'bar',data:{labels:['Tótem','Ventanilla'],datasets:[{label:'Segundos de Espera',data:[$prom_entre_totem,$prom_entre_ventanilla],backgroundColor:['#14b8a6','#f43f5e']}]},options:{legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true}}]}}}";
    $chart5_url = "https://quickchart.io/chart?c=" . urlencode($chart5_config) . "&w=350&h=200&bkg=white";
    
    $pdf->SetXY(60, 205);
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(90, 6, convertir_texto('TIEMPO DE ESPERA EN COLA (Segundos)'), 0, 0, 'C');
    $pdf->Image($chart5_url, 60, 211, 90, 0, 'PNG');
}

$pdf->Output('I', 'Reporte_General_' . $fecha_inicio . '.pdf');
?>
