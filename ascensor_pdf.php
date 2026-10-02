<?php
// Archivo: ascensor_pdf.php (BLINDADO CONTRA ERROR 500)
// 1. Limpiar buffer y silenciar advertencias menores para no romper el PDF
if (ob_get_length()) ob_end_clean();
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

session_start();
require_once 'includes/conexion.php';

// 2. Cargar FPDF de forma segura
if (file_exists('fpdf/fpdf.php')) {
    require_once('fpdf/fpdf.php');
} else {
    die("Error crítico: No se encontró la librería FPDF en fpdf/fpdf.php");
}

// 3. Cargar generador QR Local de forma segura (evita bloqueos de hostinger)
$usar_qr = false;
if (file_exists('phpqrcode/qrlib.php')) {
    require_once('phpqrcode/qrlib.php');
    $usar_qr = true;
}

function texto($str) { 
    return mb_convert_encoding((string)$str, 'ISO-8859-1', 'UTF-8'); 
}

function fecha_en_letras($fecha_sql) {
    if (empty($fecha_sql)) return "";
    $timestamp = strtotime($fecha_sql);
    $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
    return "CABA, " . date('d', $timestamp) . " de " . $meses[(int)date('m', $timestamp)] . " de " . date('Y', $timestamp);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) die("ID Inválido o no proporcionado.");

// --- CONFIGURACIÓN QR ---
$base_url = "https://federicogonzalez.net/logistica/pdfs_publicos/ascensores/";
$nombre_archivo = "Orden_Servicio_" . $id . ".pdf";
$url_qr = $base_url . $nombre_archivo;

// --- CONSULTAS BLINDADAS (Try-Catch) ---
try {
    $sql = "SELECT i.*, a.nombre as ascensor, a.ubicacion, e.nombre as empresa, u.nombre_completo as solicitante
            FROM ascensor_incidencias i 
            JOIN ascensores a ON i.id_ascensor = a.id_ascensor 
            LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
            LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id
            WHERE i.id_incidencia = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) die("La orden solicitada no existe en la base de datos.");
} catch (Exception $e) {
    die("Error consultando la orden principal: " . $e->getMessage());
}

// Historial (Try-catch por si la tabla no existe o cambió)
$historial = [];
try {
    $sql_hist = "SELECT h.*, u.nombre_completo FROM ascensor_historial h JOIN usuarios u ON h.id_usuario = u.id WHERE h.id_incidencia = ? ORDER BY h.fecha ASC";
    $stmt_hist = $pdo->prepare($sql_hist);
    $stmt_hist->execute([$id]);
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $historial = []; // Silenciamos para que no colapse todo el PDF
}

// Visitas Técnicas
$visitas = [];
try {
    $sql_visitas = "SELECT v.*, u.nombre_completo as receptor_nombre, u.firma_imagen_path as receptor_firma
                    FROM ascensor_visitas_tecnicas v
                    LEFT JOIN usuarios u ON v.id_receptor = u.id
                    WHERE v.id_incidencia = ? ORDER BY v.fecha_visita ASC";
    $stmt_v = $pdo->prepare($sql_visitas);
    $stmt_v->execute([$id]);
    $visitas = $stmt_v->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error consultando visitas técnicas: " . $e->getMessage());
}

// --- CLASE PDF ---
class PDF extends FPDF {
    var $widths; var $aligns; var $qrUrl;

    function SetWidths($w) { $this->widths=$w; }
    function SetAligns($a) { $this->aligns=$a; }
    function setQR($url) { $this->qrUrl = $url; }

    function Row($data, $fill=false) {
        $nb=0;
        for($i=0;$i<count($data);$i++) $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        $h=5*$nb;
        $this->CheckPageBreak($h);
        for($i=0;$i<count($data);$i++) {
            $w=$this->widths[$i];
            $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x=$this->GetX(); $y=$this->GetY();
            if($fill) $this->SetFillColor(245, 245, 245);
            $this->Rect($x,$y,$w,$h, ($fill ? 'DF' : 'D'));
            $this->MultiCell($w,5,$data[$i],0,$a);
            $this->SetXY($x+$w,$y);
        }
        $this->Ln($h);
    }
    
    function CheckPageBreak($h) { if($this->GetY()+$h>$this->PageBreakTrigger) $this->AddPage($this->CurOrientation); }
    
    function NbLines($w,$txt) {
        $cw=&$this->CurrentFont['cw']; if($w==0) $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize; $s=str_replace("\r",'',(string)$txt);
        $nb=strlen($s); if($nb>0 and $s[$nb-1]=="\n") $nb--;
        $sep=-1; $i=0; $j=0; $l=0; $nl=1;
        while($i<$nb) { $c=$s[$i]; if($c=="\n") { $i++; $sep=-1; $j=$i; $l=0; $nl++; continue; }
            if($c==' ') $sep=$i; 
            $l+=(isset($cw[$c]) ? $cw[$c] : 0);
            if($l>$wmax) { if($sep==-1) { if($i==$j) $i++; } else $i=$sep+1; $sep=-1; $j=$i; $l=0; $nl++; } else $i++; }
        return $nl;
    }

    function Header() {
        if (file_exists('assets/img/logo_watermark_gris.png')) {
            // Suprimir error si la marca de agua está corrupta
            @$this->Image('assets/img/logo_watermark_gris.png', 50, 80, 110, 0, 'PNG');
        }
        $logo_y = 6;
        if (file_exists('assets/img/logo.png')) @$this->Image('assets/img/logo.png', 10, $logo_y, 20);
        if (file_exists('assets/img/sello_trabajo.png')) @$this->Image('assets/img/sello_trabajo.png', 180, $logo_y, 20);

        $this->SetY($logo_y + 5); 
        $this->SetFont('Arial', 'BI', 8); 
        $this->Ln(3);
        $this->Cell(0, 4, texto('"2025 - AÑO DE LA RECONSTRUCCIÓN DE LA NACIÓN ARGENTINA"'), 0, 1, 'C');

        $this->SetY($logo_y + 14); 
        $this->Ln(8);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 4, texto('SUBGERENCIA DE EFECTORES SANITARIOS PROPIOS'), 0, 1, 'C');
        $this->Cell(0, 4, texto('APOYO LOGÍSTICO - MANTENIMIENTO DE ELEVADORES'), 0, 1, 'C');
        
        $this->Ln(6);
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(230, 230, 230);
        $id_txt = isset($GLOBALS['id']) ? str_pad($GLOBALS['id'], 6, '0', STR_PAD_LEFT) : '000000';
        $this->Cell(0, 8, texto('REPORTE TÉCNICO N° ' . $id_txt), 1, 1, 'C', true);
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-32);
        $this->SetDrawColor(150, 150, 150);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetDrawColor(0);
        
        // Generador QR LOCAL seguro (sin peticiones externas)
        if ($this->qrUrl && $GLOBALS['usar_qr'] && class_exists('QRcode')) {
            $tmp_qr = 'temp_qr_' . time() . '_' . rand(1000,9999) . '.png';
            try {
                QRcode::png($this->qrUrl, $tmp_qr, 'L', 3, 1);
                if (file_exists($tmp_qr)) {
                    $this->Image($tmp_qr, 10, $this->GetY() + 2, 15, 15, 'PNG');
                    unlink($tmp_qr);
                }
            } catch(Exception $e) {
                if (file_exists($tmp_qr)) @unlink($tmp_qr);
            }
        }

        $y_base = $this->GetY() + 2;
        $this->SetXY(28, $y_base);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(90, 3, texto('SISTEMA DE GESTIÓN AVANZADO DE LOGÍSTICA Y PERSONAL'), 0, 2, 'L');
        $this->SetFont('Arial', '', 6);
        $this->Cell(90, 3, texto('v2.0 - DESARROLLO BY SG MEC INFO FEDERICO GONZÁLEZ'), 0, 2, 'L');
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(90, 3, texto('Documento Oficial. Validez verificable vía QR.'), 0, 0, 'L');
        $this->SetTextColor(0);

        $id_seg = 'DOC-' . str_pad($GLOBALS['id'], 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($GLOBALS['id'] . 'sistema_logistica'), 0, 6));
        $fecha_gen = date('d/m/Y H:i');
        $orden_nro = str_pad($GLOBALS['id'], 6, '0', STR_PAD_LEFT);

        $this->SetXY(110, $y_base); 
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(0, 3, texto('CONTROL DOCUMENTAL'), 0, 2, 'R');
        $this->SetFont('Arial', '', 6);
        $this->Cell(0, 3, texto("Orden N°: $orden_nro"), 0, 2, 'R');
        $this->Cell(0, 3, texto("Generado: $fecha_gen"), 0, 2, 'R');
        $this->Cell(0, 3, texto("ID Seg: $id_seg"), 0, 2, 'R');

        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, texto('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SectionTitle($label) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 220, 220);
        $this->SetTextColor(0);
        $this->Cell(0, 6, texto($label), 1, 1, 'L', true);
        $this->Ln(2);
    }

    function DrawSignatureBlock($role_title, $signer_name, $date_text, $sign_path, $x, $y, $w, $h) {
        $this->SetXY($x, $y);
        if ($date_text) {
            $this->SetFont('Arial', '', 8);
            $this->Cell($w, 4, texto($date_text), 0, 1, 'C');
        }
        
        $line_y = $y + $h - 10;
        
        // BLINDAJE CONTRA FPDF CRASH POR IMAGEN CORRUPTA
        $imagen_valida = false;
        if ($sign_path && file_exists($sign_path)) {
            // getimagesize verifica que el archivo sea una imagen real y no un texto o esté roto
            if (@getimagesize($sign_path) !== false) {
                $imagen_valida = true;
            }
        }

        if ($imagen_valida) {
            $current_y = $this->GetY();
            $avail_h = $line_y - $current_y - 2; 
            if ($avail_h > 5) {
                $img_w = $w * 0.5; 
                $img_x = $x + ($w - $img_w) / 2; 
                @$this->Image($sign_path, $img_x, $current_y + 1, $img_w, $avail_h);
            }
        } else {
            $this->SetXY($x, $y + ($h/2) - 4);
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor(150);
            $this->Cell($w, 8, texto('(Sin Firma Validada)'), 0, 0, 'C');
            $this->SetTextColor(0);
        }
        
        $this->Line($x + 5, $line_y, $x + $w - 5, $line_y);
        
        $this->SetXY($x, $line_y + 1);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($w, 4, texto($signer_name), 0, 1, 'C');
        
        $this->SetXY($x, $line_y + 5);
        $this->SetFont('Arial', '', 7);
        $this->Cell($w, 3, texto($role_title), 0, 1, 'C'); 
    }
}

// --- CREACIÓN DEL PDF ---
$pdf = new PDF();
$pdf->setQR($url_qr);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetLeftMargin(10);
$pdf->SetRightMargin(10);

// 1. DATOS ORIGINALES
$pdf->SectionTitle('1. DATOS DE LA SOLICITUD ORIGINAL');
$pdf->SetFont('Arial', '', 9);
$pdf->SetWidths([25, 70, 25, 70]);
$pdf->Row([texto('Fecha:'), date('d/m/Y H:i', strtotime($data['fecha_reporte'])), texto('Prioridad:'), strtoupper(texto($data['prioridad']))]);
$pdf->Row([texto('Equipo:'), texto($data['ascensor']), texto('Ubicación:'), texto($data['ubicacion'])]);
$pdf->Row([texto('Empresa:'), texto($data['empresa'] ?? 'Sin Asignar'), texto('Solicitante:'), texto($data['solicitante'])]);
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, texto('Falla Reportada:'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, texto($data['descripcion_problema']), 1);
$pdf->Ln(4);

// 2. BITÁCORA
if (count($historial) > 0) {
    $pdf->SectionTitle('2. BITÁCORA DE NOVEDADES');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetWidths([30, 40, 120]);
    $pdf->SetAligns(['C', 'C', 'L']);
    $pdf->Row([texto('Fecha/Hora'), texto('Usuario'), texto('Detalle / Movimiento')], true);
    $pdf->SetFont('Arial', '', 7);
    foreach ($historial as $h) {
        $pdf->Row([date('d/m/y H:i', strtotime($h['fecha'])), texto($h['nombre_completo']), texto($h['detalle'])]);
    }
    $pdf->Ln(4);
}

// 3. INTERVENCIONES Y FIRMAS
$pdf->SectionTitle('3. INTERVENCIONES TÉCNICAS');
$adjuntos_para_imprimir = [];

if (count($visitas) > 0) {
    foreach ($visitas as $idx => $v) {
        $pdf->SetFont('Arial', '', 9);
        $trabajo_lines = $pdf->NbLines(160, texto($v['descripcion_trabajo']));
        
        $altura_firmas = 25; 
        $altura_bloque = 6 + 10 + ($trabajo_lines * 5) + $altura_firmas + 5;

        if ($pdf->GetY() + $altura_bloque > 250) $pdf->AddPage();
        $y_inicio_bloque = $pdf->GetY();

        $pdf->SetFillColor(210, 255, 210);
        $estado_txt = "VISITA TÉCNICA REGISTRADA";

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, texto("Visita #" . ($idx + 1) . " - " . date('d/m/Y H:i', strtotime($v['fecha_visita'])) . " - " . $estado_txt), 1, 1, 'L', true);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(25, 5, texto('Técnico:'), 'L');
        $pdf->Cell(165, 5, texto($v['tecnico_nombre']), 'R', 1);
        $pdf->Cell(25, 5, texto('Trabajo:'), 'L');
        $pdf->MultiCell(165, 5, texto($v['descripcion_trabajo']), 'R');
        $pdf->Ln(2);

        $y_firmas = $pdf->GetY();
        
        // RUTA DE LA FIRMA CON CARPETA CORRECTA
        $path_firma_tecnico = !empty($v['firma_tecnico_path']) ? 'uploads/firmas_tecnicos/' . $v['firma_tecnico_path'] : null;
        $pdf->DrawSignatureBlock('Firma Técnico', $v['tecnico_nombre'], '', $path_firma_tecnico, 15, $y_firmas, 80, $altura_firmas);
        
        // FIRMA DEL USUARIO (EMPLEADO)
        $fecha_texto = fecha_en_letras($v['fecha_visita']);
        $path_firma_receptor = !empty($v['receptor_firma']) ? 'uploads/firmas/' . $v['receptor_firma'] : null;
        $pdf->DrawSignatureBlock('Responsable del Pedido', $v['receptor_nombre'] ?? 'Sistema', $fecha_texto, $path_firma_receptor, 115, $y_firmas, 80, $altura_firmas);

        $pdf->SetY($y_firmas + $altura_firmas + 2);
        $y_final_bloque = $pdf->GetY();
        $pdf->Rect(10, $y_inicio_bloque, 190, $y_final_bloque - $y_inicio_bloque);
        $pdf->Ln(3);

        // Guardar foto del remito si subió una
        if (!empty($v['adjunto_tecnico'])) {
            $adjuntos_para_imprimir[] = ['ruta' => $v['adjunto_tecnico'], 'titulo' => "ANEXO VISITA #" . ($idx + 1)];
        }
    }
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 10, texto('Sin visitas registradas.'), 1, 1, 'C');
}

// 4. ANEXOS (REMITOS) - BLINDADO
if (count($adjuntos_para_imprimir) > 0) {
    foreach ($adjuntos_para_imprimir as $adj) {
        $ruta = $adj['ruta'];
        if (file_exists($ruta)) {
            $info = @getimagesize($ruta);
            if ($info !== false) {
                $pdf->AddPage();
                $pdf->SectionTitle($adj['titulo']);
                $pdf->Ln(5);
                $w_orig = $info[0];
                $h_orig = $info[1];
                $ratio = $w_orig / $h_orig;
                $max_w = 190; $max_h = 230;
                if ($max_w / $max_h > $ratio) { $w = $max_h * $ratio; $h = $max_h; } else { $w = $max_w; $h = $max_w / $ratio; }
                @$pdf->Image($ruta, 10, $pdf->GetY(), $w, $h);
            }
        }
    }
}

// 5. GUARDAR Y MOSTRAR (Limpieza final del buffer)
$dir_publico = "pdfs_publicos/ascensores/";
if (!is_dir($dir_publico)) @mkdir($dir_publico, 0777, true);
$ruta_guardado = $dir_publico . $nombre_archivo;
@$pdf->Output('F', $ruta_guardado);

ob_end_clean(); // Limpia cualquier basura oculta que cause Error de formato
$pdf->Output('I', $nombre_archivo);
exit;
