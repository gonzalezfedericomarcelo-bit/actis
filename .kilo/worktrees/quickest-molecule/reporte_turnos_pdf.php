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

// 1. Recibir TODO lo que venga de los filtros
$filtro_dni = isset($_GET['dni']) ? $conexion->real_escape_string($_GET['dni']) : '';
$filtro_fecha = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : '';
$filtro_hora = isset($_GET['hora']) ? $conexion->real_escape_string($_GET['hora']) : '';
$filtro_estado = isset($_GET['estado']) ? $conexion->real_escape_string($_GET['estado']) : '';
$filtro_profesional = isset($_GET['profesional']) ? $conexion->real_escape_string($_GET['profesional']) : '';
$filtro_servicio = isset($_GET['servicio']) ? $conexion->real_escape_string($_GET['servicio']) : '';
$filtro_operador = isset($_GET['operador']) ? $conexion->real_escape_string($_GET['operador']) : '';
$filtro_hora_creacion = isset($_GET['hora_creacion']) ? $conexion->real_escape_string($_GET['hora_creacion']) : '';
$filtro_creado_hoy = (isset($_GET['creado_hoy']) && $_GET['creado_hoy'] == '1') ? 1 : 0;

$where_clauses = ["1=1"];
$texto_filtros = [];

// 2. Aplicar condiciones idénticas al listado visual
if($filtro_dni != '') { 
    $where_clauses[] = "(p.dni LIKE '%$filtro_dni%' OR p.nombre LIKE '%$filtro_dni%' OR p.apellido LIKE '%$filtro_dni%')"; 
    $texto_filtros[] = "Paciente: $filtro_dni";
}
if($filtro_fecha != '') { 
    $where_clauses[] = "t.fecha_turno = '$filtro_fecha'"; 
    $texto_filtros[] = "Fecha: " . date("d/m/Y", strtotime($filtro_fecha));
}
if($filtro_hora != '') { 
    $where_clauses[] = "t.hora_turno LIKE '$filtro_hora%'"; 
    $texto_filtros[] = "Hora: $filtro_hora";
}
if($filtro_estado != '') { 
    $where_clauses[] = "t.estado = '$filtro_estado'"; 
    $texto_filtros[] = "Estado: $filtro_estado";
}
if($filtro_profesional != '') { 
    $where_clauses[] = "t.profesional LIKE '%$filtro_profesional%'"; 
    $texto_filtros[] = "Prof: $filtro_profesional";
}
if($filtro_servicio != '') { 
    $where_clauses[] = "t.servicio LIKE '%$filtro_servicio%'"; 
    $texto_filtros[] = "Servicio: $filtro_servicio";
}
if($filtro_operador != '') { 
    $where_clauses[] = "(t.operador_externo LIKE '%$filtro_operador%' OR u.nombre_completo LIKE '%$filtro_operador%')"; 
    $texto_filtros[] = "Operador: $filtro_operador";
}
if($filtro_hora_creacion != '') { 
    $where_clauses[] = "TIME(t.creado_el) LIKE '$filtro_hora_creacion%'"; 
    $texto_filtros[] = "Hora Carga: $filtro_hora_creacion";
}
if($filtro_creado_hoy) { 
    $hoy_str = date('Y-m-d'); 
    $where_clauses[] = "DATE(t.creado_el) = '$hoy_str'"; 
    $texto_filtros[] = "Sacados Hoy";
}

$sql_where = implode(' AND ', $where_clauses);
$subtitulo_filtro = count($texto_filtros) > 0 ? implode(" | ", $texto_filtros) : "Todos los turnos históricos";

// 3. Consultar las Estadísticas exactas
$sql_stats = "SELECT COUNT(*) as total_turnos, 
              SUM(CASE WHEN t.estado = 'Pendiente' THEN 1 ELSE 0 END) as total_pendientes,
              SUM(CASE WHEN t.estado = 'Autorizado' THEN 1 ELSE 0 END) as total_autorizados,
              SUM(CASE WHEN t.estado = 'Presente' THEN 1 ELSE 0 END) as total_presentes,
              SUM(CASE WHEN t.estado = 'Atendido' THEN 1 ELSE 0 END) as total_atendidos,
              SUM(CASE WHEN t.estado = 'Cancelado' THEN 1 ELSE 0 END) as total_cancelados
              FROM turnos t 
              INNER JOIN pacientes p ON t.paciente_id = p.id 
              LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
              WHERE $sql_where";
$stats = $conexion->query($sql_stats)->fetch_assoc();

$total_turnos = $stats['total_turnos'] ?: 0;
$total_pendientes = $stats['total_pendientes'] ?: 0;
$total_autorizados = $stats['total_autorizados'] ?: 0;
$total_presentes = $stats['total_presentes'] ?: 0;
$total_atendidos = $stats['total_atendidos'] ?: 0;
$total_cancelados = $stats['total_cancelados'] ?: 0;

// Detalles
$sql_detalle = "SELECT t.*, p.nombre, p.apellido, p.dni, u.nombre_completo AS creador_nombre 
                FROM turnos t 
                INNER JOIN pacientes p ON t.paciente_id = p.id 
                LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                WHERE $sql_where ORDER BY t.fecha_turno ASC, t.hora_turno ASC";
$res_detalle = $conexion->query($sql_detalle);

$hash_seguridad = md5($sql_where . "ACTIS_TURNOS" . time());
$url_verificacion = "https://federicogonzalez.net/actis/verificar_reporte.php?hash=" . $hash_seguridad;
$qr_url = "https://quickchart.io/qr?size=150&margin=0&text=" . urlencode($url_verificacion);

// 4. Armar el PDF Premium
class PDF_Reporte extends FPDF {
    public $subtitulo_filtro;
    public $qr_link;
    public $hash_doc;

    function Header() {
        if(file_exists('img/osfa_negro.png')) { $this->Image('img/osfa_negro.png', 10, 8, 25); }
        if(file_exists('img/sello_log.png')) { $this->Image('img/sello_log.png', 178, 8, 18); }
        
        $this->SetY(12);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(15, 23, 42); 
        $this->Cell(0, 7, convertir_texto('REPORTE ESTADÍSTICO - GESTIÓN DE TURNOS'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 116, 139); 
        $this->Cell(0, 5, convertir_texto('POLICLÍNICA GENERAL ACTIS - OSFA'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, convertir_texto('Filtros: ' . $this->subtitulo_filtro), 0, 1, 'C');
        
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
        
        if ($this->qr_link) { $this->Image($this->qr_link, 10, $this->GetY(), 20, 20, 'PNG'); }
        
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
        $this->Cell(0, 4, convertir_texto("Escanee el código QR para verificar autenticidad."), 0, 1, 'L');

        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, convertir_texto('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF_Reporte('P','mm','A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 35); 
$pdf->subtitulo_filtro = $subtitulo_filtro;
$pdf->qr_link = $qr_url;
$pdf->hash_doc = strtoupper(substr($hash_seguridad, 0, 12));
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, convertir_texto(' INDICADORES GENERALES DE ESTE REPORTE'), 0, 1, 'L', true);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105);   
$pdf->SetDrawColor(226, 232, 240); 
$pdf->SetFont('Arial','B',8);
$pdf->Cell(38, 7, convertir_texto('TOTAL TURNOS'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('PENDIENTES/AUT.'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('PRESENTES'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('ATENDIDOS'), 'B', 0, 'C', true);
$pdf->Cell(38, 7, convertir_texto('CANCELADOS'), 'B', 1, 'C', true);

$pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(15, 23, 42); 
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(38, 10, $total_turnos, 'B', 0, 'C', true);
$pdf->SetTextColor(100, 116, 139); 
$pdf->Cell(38, 10, ($total_pendientes + $total_autorizados), 'B', 0, 'C', true);
$pdf->SetTextColor(245, 158, 11); 
$pdf->Cell(38, 10, $total_presentes, 'B', 0, 'C', true);
$pdf->SetTextColor(16, 185, 129); 
$pdf->Cell(38, 10, $total_atendidos, 'B', 0, 'C', true);
$pdf->SetTextColor(239, 68, 68); 
$pdf->Cell(38, 10, $total_cancelados, 'B', 1, 'C', true);
$pdf->Ln(8);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, convertir_texto(' DETALLE DE TURNOS'), 0, 1, 'L', true);

function imprimir_cabecera_detalles($pdf) {
    $pdf->SetFillColor(241, 245, 249); 
    $pdf->SetTextColor(71, 85, 105);   
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial','B',7); 
    $pdf->Cell(25, 7, 'FECHA / HORA', 'B', 0, 'C', true);
    $pdf->Cell(18, 7, 'DNI', 'B', 0, 'C', true);
    $pdf->Cell(45, 7, 'PACIENTE', 'B', 0, 'L', true);
    $pdf->Cell(45, 7, 'SERVICIO / PROFESIONAL', 'B', 0, 'L', true);
    $pdf->Cell(22, 7, 'ESTADO', 'B', 0, 'C', true);
    $pdf->Cell(35, 7, 'CARGADO POR', 'B', 1, 'C', true); 
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
        $fecha_txt = !empty($fila['fecha_turno']) ? date("d/m/Y", strtotime($fila['fecha_turno'])) : '-';
        $hora_txt = !empty($fila['hora_turno']) ? date("H:i", strtotime($fila['hora_turno'])) : '-';
        
        $pdf->Cell(25, 7, "$fecha_txt $hora_txt", 'B', 0, 'C', $fill_row);
        $pdf->Cell(18, 7, $fila['dni'], 'B', 0, 'C', $fill_row);
        $nombre_corto = mb_substr(convertir_texto($fila['apellido'].', '.$fila['nombre']), 0, 25, 'ISO-8859-1');
        $pdf->Cell(45, 7, $nombre_corto, 'B', 0, 'L', $fill_row);
        $serv_prof = $fila['servicio'] ? mb_substr(convertir_texto($fila['servicio']), 0, 10, 'ISO-8859-1') . ' - ' : '';
        $serv_prof .= mb_substr(convertir_texto($fila['profesional']), 0, 15, 'ISO-8859-1');
        $pdf->Cell(45, 7, $serv_prof, 'B', 0, 'L', $fill_row);

        $pdf->SetFont('Arial','B',7);
        $pdf->Cell(22, 7, convertir_texto($fila['estado']), 'B', 0, 'C', $fill_row);
        
        $pdf->SetFont('Arial','',7);
        $creador = !empty($fila['operador_externo']) ? $fila['operador_externo'] : ($fila['creador_nombre'] ?: 'Sistema');
        $creador_corto = mb_substr(convertir_texto($creador), 0, 20, 'ISO-8859-1');
        $pdf->Cell(35, 7, $creador_corto, 'B', 1, 'C', $fill_row);
        
        $fill_row = !$fill_row;
    }
} else {
    $pdf->Cell(190, 8, convertir_texto('No hay registros en este período o filtro.'), 'B', 1, 'C');
}

$pdf->AddPage();
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, convertir_texto(' ANÁLISIS GRÁFICO BASADO EN ESTA BÚSQUEDA'), 0, 1, 'C', true);
$pdf->Ln(10);
$pdf->SetTextColor(15, 23, 42); 

if ($total_turnos > 0) {
    $graficos_agregados = 0;

    $check_page_break = function($pdf, &$graficos_agregados) {
        if($graficos_agregados == 2) {
            $pdf->AddPage();
            $graficos_agregados = 0;
        }
    };

    // 1. Rendimiento de TODOS los Operadores
    $sql_op = "SELECT IFNULL(NULLIF(t.operador_externo, ''), IFNULL(u.nombre_completo, 'Sistema')) as operador, COUNT(*) as cantidad 
               FROM turnos t 
               INNER JOIN pacientes p ON t.paciente_id = p.id 
               LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
               WHERE $sql_where 
               GROUP BY operador ORDER BY cantidad DESC";
    $res_op = $conexion->query($sql_op);
    $labels_op = []; $data_op = [];
    if($res_op && $res_op->num_rows > 0) {
        while($r = $res_op->fetch_assoc()) {
            $lbl = str_replace(["'", '"'], '', $r['operador']);
            $labels_op[] = "'" . mb_substr($lbl, 0, 18, 'UTF-8') . "'";
            $data_op[] = $r['cantidad'];
        }
        $chart_op_config = "{type:'horizontalBar',data:{labels:[" . implode(",", $labels_op) . "],datasets:[{label:'Turnos Cargados',data:[" . implode(",", $data_op) . "],backgroundColor:'#8b5cf6'}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{fontSize:12,beginAtZero:true}}],yAxes:[{ticks:{fontSize:11}}]}}}";
        $chart_op_url = "https://quickchart.io/chart?c=" . urlencode($chart_op_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('1. RENDIMIENTO DE OPERADORES (TODOS)'), 0, 1, 'C');
        $pdf->Image($chart_op_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }

    // 2. Horarios Pico
    $sql_hora = "SELECT SUBSTRING(t.hora_turno, 1, 2) as hora, COUNT(*) as cantidad 
                 FROM turnos t 
                 INNER JOIN pacientes p ON t.paciente_id = p.id 
                 LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                 WHERE $sql_where AND t.hora_turno IS NOT NULL AND t.hora_turno != ''
                 GROUP BY hora ORDER BY hora ASC";
    $res_hora = $conexion->query($sql_hora);
    $labels_h = []; $data_h = [];
    if($res_hora && $res_hora->num_rows > 0) {
        while($r = $res_hora->fetch_assoc()) {
            $labels_h[] = "'" . $r['hora'] . ":00'";
            $data_h[] = $r['cantidad'];
        }
        $chart_h_config = "{type:'bar',data:{labels:[" . implode(",", $labels_h) . "],datasets:[{label:'Turnos',data:[" . implode(",", $data_h) . "],backgroundColor:'#f59e0b'}]},options:{legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true}}]}}}";
        $chart_h_url = "https://quickchart.io/chart?c=" . urlencode($chart_h_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('2. HORARIOS PICO (DEMANDA POR HORA)'), 0, 1, 'C');
        $pdf->Image($chart_h_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }

    // 3. Días Pico (Días en que se SACAN los turnos)
    $sql_dias = "SELECT DATE(t.creado_el) as fecha_creacion, COUNT(*) as cantidad 
                 FROM turnos t 
                 INNER JOIN pacientes p ON t.paciente_id = p.id 
                 LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                 WHERE $sql_where AND t.creado_el IS NOT NULL
                 GROUP BY DATE(t.creado_el) ORDER BY DATE(t.creado_el) ASC";
    $res_dias = $conexion->query($sql_dias);
    $labels_d = []; $data_d = [];
    if($res_dias && $res_dias->num_rows > 1) { 
        while($r = $res_dias->fetch_assoc()) {
            $labels_d[] = "'" . date('d/m', strtotime($r['fecha_creacion'])) . "'";
            $data_d[] = $r['cantidad'];
        }

        $chart_d_config = "{type:'line',data:{labels:[" . implode(",", $labels_d) . "],datasets:[{label:'Turnos',data:[" . implode(",", $data_d) . "],borderColor:'#10b981',backgroundColor:'rgba(16,185,129,0.1)',fill:true}]},options:{legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true}}]}}}";
        $chart_d_url = "https://quickchart.io/chart?c=" . urlencode($chart_d_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('3. DÍAS PICO (TENDENCIA DE DEMANDA)'), 0, 1, 'C');
        $pdf->Image($chart_d_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }

    // 4. Distribución por Servicio (Top 5)
    $sql_srv = "SELECT IFNULL(NULLIF(t.servicio, ''), 'Sin Definir') as servicio, COUNT(*) as cantidad 
                 FROM turnos t 
                 INNER JOIN pacientes p ON t.paciente_id = p.id 
                 LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                 WHERE $sql_where 
                 GROUP BY servicio ORDER BY cantidad DESC LIMIT 5";
    $res_srv = $conexion->query($sql_srv);
    $labels_s = []; $data_s = [];
    if($res_srv && $res_srv->num_rows > 0) {
        while($r = $res_srv->fetch_assoc()) {
            $lbl = str_replace(["'", '"'], '', $r['servicio']);
            $labels_s[] = "'" . mb_substr($lbl, 0, 18, 'UTF-8') . "'";
            $data_s[] = $r['cantidad'];
        }
        $chart_s_config = "{type:'doughnut',data:{labels:[" . implode(",", $labels_s) . "],datasets:[{data:[" . implode(",", $data_s) . "],backgroundColor:['#3b82f6','#ec4899','#14b8a6','#f97316','#06b6d4']}]},options:{legend:{position:'right'}}}";
        $chart_s_url = "https://quickchart.io/chart?c=" . urlencode($chart_s_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('4. DISTRIBUCIÓN POR SERVICIO (TOP 5)'), 0, 1, 'C');
        $pdf->Image($chart_s_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }

    // 5. Top Profesionales + Servicio
    $sql_prof = "SELECT CONCAT(IFNULL(NULLIF(t.profesional, ''), 'Sin Asignar'), ' (', IFNULL(NULLIF(t.servicio, ''), 'S/D'), ')') as profesional_servicio, COUNT(*) as cantidad 
                 FROM turnos t 
                 INNER JOIN pacientes p ON t.paciente_id = p.id 
                 LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                 WHERE $sql_where 
                 GROUP BY profesional_servicio ORDER BY cantidad DESC LIMIT 5";
    $res_prof = $conexion->query($sql_prof);
    $labels_p = []; $data_p = [];
    if($res_prof && $res_prof->num_rows > 0) {
        while($r = $res_prof->fetch_assoc()) {
            $lbl = str_replace(["'", '"'], '', $r['profesional_servicio']);
            $labels_p[] = "'" . mb_substr($lbl, 0, 24, 'UTF-8') . "'";
            $data_p[] = $r['cantidad'];
        }

        $chart_p_config = "{type:'horizontalBar',data:{labels:[" . implode(",", $labels_p) . "],datasets:[{label:'Turnos',data:[" . implode(",", $data_p) . "],backgroundColor:'#0ea5e9'}]},options:{legend:{display:false},scales:{xAxes:[{ticks:{beginAtZero:true}}]}}}";
        $chart_p_url = "https://quickchart.io/chart?c=" . urlencode($chart_p_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('5. TOP 5 PROFESIONALES SOLICITADOS'), 0, 1, 'C');
        $pdf->Image($chart_p_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }

    // 6. Modalidad del Turno (Programado vs Sobre-turno)
    $sql_tipo = "SELECT IFNULL(NULLIF(t.tipo_turno, ''), 'Programado') as tipo, COUNT(*) as cantidad 
                 FROM turnos t 
                 INNER JOIN pacientes p ON t.paciente_id = p.id 
                 LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
                 WHERE $sql_where 
                 GROUP BY tipo ORDER BY cantidad DESC";
    $res_tipo = $conexion->query($sql_tipo);
    $labels_t = []; $data_t = [];
    if($res_tipo && $res_tipo->num_rows > 0) {
        while($r = $res_tipo->fetch_assoc()) {
            $lbl = str_replace(["'", '"'], '', $r['tipo']);
            $labels_t[] = "'" . $lbl . "'";
            $data_t[] = $r['cantidad'];
        }
        $chart_t_config = "{type:'pie',data:{labels:[" . implode(",", $labels_t) . "],datasets:[{data:[" . implode(",", $data_t) . "],backgroundColor:['#6366f1','#f43f5e']}]},options:{legend:{position:'right'}}}";
        $chart_t_url = "https://quickchart.io/chart?c=" . urlencode($chart_t_config) . "&w=500&h=250&bkg=white";
        
        $check_page_break($pdf, $graficos_agregados);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, convertir_texto('6. MODALIDAD DEL TURNO (PROG. / SOBRE-TURNO)'), 0, 1, 'C');
        $pdf->Image($chart_t_url, 40, $pdf->GetY() + 2, 130, 0, 'PNG');
        $pdf->Ln(75);
        $graficos_agregados++;
    }
}

$pdf->Output('I', 'Reporte_Turnos_Filtrados.pdf');
?>
