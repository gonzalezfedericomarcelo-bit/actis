<?php
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Inicialización de variables para todos los datos del PDF
$dni_ext = $nombre_ext = $apellido_ext = $servicio_ext = $fecha_ext = "";
$turno_nro_ext = $hora_ext = $hc_ext = $profesional_ext = $especialidad_ext = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['pdf_turno']) && $_FILES['pdf_turno']['error'] == UPLOAD_ERR_OK) {
    spl_autoload_register(function ($class) {
        $prefix = 'Smalot\\PdfParser\\'; $base_dir = __DIR__ . '/pdfparser/'; $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) { return; }
        $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) { require $file; }
    });

    if(file_exists('pdfparser/Parser.php')) {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($_FILES['pdf_turno']['tmp_name']);
        $text = preg_replace('/\s+/', ' ', $pdf->getText());

        // 1. Número de Turno
        if(preg_match('/Turno Nro\.?\s*:\s*(\d+)/i', $text, $m)) { $turno_nro_ext = $m[1]; }
        
        // 2. Fecha del Turno
        if(preg_match('/(\d{1,2})\s*de\s*([A-Za-zÁÉÍÓÚñÑ]+)\s*de\s*(\d{4})/i', $text, $m)) {
            $meses = ['Enero'=>'01','Febrero'=>'02','Marzo'=>'03','Abril'=>'04','Mayo'=>'05','Junio'=>'06','Julio'=>'07','Agosto'=>'08','Septiembre'=>'09','Octubre'=>'10','Noviembre'=>'11','Diciembre'=>'12'];
            $fecha_ext = $m[3]."-".($meses[ucfirst(strtolower($m[2]))] ?? '01')."-".str_pad($m[1], 2, "0", STR_PAD_LEFT);
        }

        // 3. Hora
        if(preg_match('/Hora\s*:\s*(\d{1,2}:\d{2})/i', $text, $m)) { $hora_ext = $m[1]; }

        // 4. Historia Clínica
        if(preg_match('/HC\s*:\s*(\d+)\s*-/i', $text, $m)) { $hc_ext = $m[1]; }

        // 5. Apellido y Nombre
        if(preg_match('/-\s*([A-ZÁÉÍÓÚÑñ\s]+),\s*([A-ZÁÉÍÓÚÑñ\s]+?)\s+Servicio/i', $text, $m)) {
            $apellido_ext = trim($m[1]);
            $nombre_ext = trim($m[2]);
        }

        // 6. DNI
        if(preg_match('/\b(\d{7,8})\b\s*Edad/i', $text, $m)) { 
            $dni_ext = $m[1]; 
        }

        // 7. Servicio
        if(preg_match('/Profesional\s+([A-ZÁÉÍÓÚÑñ\s]+?)\s+\d+\s+\d{7,8}/i', $text, $m)) {
            $servicio_ext = trim($m[1]);
        }

        // 8. Profesional
        if(preg_match('/Especialidad\s*:\s*(.*?)\s+(?:CONSULTA|Turno)/i', $text, $m)) {
            $profesional_ext = trim($m[1]);
        }

        // 9. Especialidad
        if (!empty($servicio_ext)) {
            $especialidad_ext = $servicio_ext;
        }

        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('PDF procesado. Verifique los datos extraídos.'); });</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('Librería PDF no encontrada'); });</script>";
    }
}
?>

<style>
    .tc-container { display: flex; flex-direction: column; gap: 30px; max-width: 1200px; margin: 0 auto; }
    
    /* Panel de pasos */
    .tc-step-header { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
    .tc-step-badge { width: 55px; height: 55px; background: linear-gradient(135deg, #2563eb, #1e40af); color: white; border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 1.8rem; font-weight: 900; box-shadow: 0 10px 20px rgba(37,99,235,0.3); }
    .tc-step-title { font-size: 1.8rem; font-weight: 900; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
    .tc-step-desc { font-size: 1rem; color: #64748b; font-weight: 500; margin: 5px 0 0 0; }

    /* Dropzone Avanzado */
    .tc-drop-zone { position: relative; border: 3px dashed #cbd5e1; border-radius: 24px; padding: 60px 30px; text-align: center; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; z-index: 1; }
    .tc-drop-zone::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.8), transparent); transform: skewX(-25deg); transition: 0.7s; z-index: -1; }
    .tc-drop-zone:hover { border-color: #3b82f6; background: #eff6ff; transform: translateY(-5px); box-shadow: 0 20px 40px rgba(37,99,235,0.12); }
    .tc-drop-zone:hover::before { left: 150%; }
    .tc-drop-icon { font-size: 5rem; color: #94a3b8; transition: all 0.3s ease; margin-bottom: 20px; display: block; }
    .tc-drop-zone:hover .tc-drop-icon { color: #3b82f6; transform: scale(1.1); }
    .tc-drop-text { font-size: 1.4rem; font-weight: 800; color: #1e293b; display: block; margin-bottom: 10px; }
    .tc-drop-subtext { font-size: 0.95rem; color: #64748b; font-weight: 500; }

    /* Títulos de sección */
    .tc-section-title { font-size: 1.1rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 1px; margin: 30px 0 20px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }

    /* Grilla de Formulario Compleja */
    .tc-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
    
    /* INPUTS BLINDADOS CON ESTILO MODERNO */
    .tc-input-wrapper { display: flex; flex-direction: column; gap: 8px; width: 100%; }
    .tc-input-wrapper label { font-size: 0.9rem; font-weight: 700; color: #475569; margin-left: 5px; }
    
    .tc-input-container { position: relative; width: 100%; }
    .tc-input-container i { position: absolute; top: 50%; transform: translateY(-50%); left: 16px; color: #94a3b8; font-size: 1.2rem; z-index: 2; transition: color 0.3s ease; }
    
    .tc-input-container input {
        width: 100%;
        height: 55px;
        border-radius: 14px;
        padding: 10px 15px 10px 50px;
        border: 2px solid #cbd5e1;
        background: #f8fafc;
        font-size: 1.05rem;
        font-weight: 600;
        color: #0f172a;
        box-sizing: border-box;
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
    }
    .tc-input-container input:focus {
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
    }
    .tc-input-container input:focus + i { color: #2563eb; }
</style>

<div class="tc-container">
    
    <div class="tarjeta glass-panel" style="padding: 40px;">
        <div class="tc-step-header">
            <div class="tc-step-badge">1</div>
            <div>
                <h2 class="tc-step-title">Motor de Importación Inteligente</h2>
                <p class="tc-step-desc">Procesamiento automatizado de comprobantes PDF del sistema central.</p>
            </div>
        </div>

        <form action="turnos_crear.php" method="POST" enctype="multipart/form-data" id="form-pdf">
            <div class="tc-drop-zone" onclick="document.getElementById('pdf_turno').click()">
                <i class="fa-solid fa-file-pdf tc-drop-icon"></i>
                <span class="tc-drop-text">Arrastre el documento PDF o haga clic para explorar</span>
                <span class="tc-drop-subtext">El algoritmo extraerá automáticamente la identidad y la filiación médica.</span>
                <input type="file" name="pdf_turno" id="pdf_turno" accept=".pdf" required style="display:none;" onchange="document.getElementById('form-pdf').submit();">
            </div>
        </form>
    </div>

    <div class="tarjeta glass-panel" style="padding: 40px;">
        <div class="tc-step-header">
            <div class="tc-step-badge" style="background: linear-gradient(135deg, #10b981, #047857);">2</div>
            <div>
                <h2 class="tc-step-title">Verificación y Confirmación de Datos</h2>
                <p class="tc-step-desc">Valide la información extraída antes de inyectarla en la base de datos.</p>
            </div>
        </div>

        <form action="turnos_procesar.php" method="POST">
            
            <div class="tc-section-title"><i class="fa-solid fa-address-card" style="color: #3b82f6;"></i> Identidad del Paciente</div>
            <div class="tc-form-grid">
                <div class="tc-input-wrapper">
                    <label for="dni">Documento (DNI)</label>
                    <div class="tc-input-container">
                        <input type="text" name="dni" id="dni" value="<?php echo htmlspecialchars($dni_ext); ?>" required>
                        <i class="fa-regular fa-id-card"></i>
                    </div>
                </div>
                
                <div class="tc-input-wrapper">
                    <label for="hc">Nro. Historia Clínica</label>
                    <div class="tc-input-container">
                        <input type="text" name="hc" id="hc" value="<?php echo htmlspecialchars($hc_ext); ?>">
                        <i class="fa-solid fa-file-medical"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="apellido">Apellido Paterno/Materno</label>
                    <div class="tc-input-container">
                        <input type="text" name="apellido" id="apellido" value="<?php echo htmlspecialchars($apellido_ext); ?>" required>
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="nombre">Nombre Completo</label>
                    <div class="tc-input-container">
                        <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($nombre_ext); ?>" required>
                        <i class="fa-regular fa-user"></i>
                    </div>
                </div>
            </div>

            <div class="tc-section-title"><i class="fa-solid fa-stethoscope" style="color: #f59e0b;"></i> Detalles Clínicos y Asignación</div>
            <div class="tc-form-grid">
                <div class="tc-input-wrapper">
                    <label for="turno_nro">Nro. Turno Referencia</label>
                    <div class="tc-input-container">
                        <input type="text" name="turno_nro" id="turno_nro" value="<?php echo htmlspecialchars($turno_nro_ext); ?>">
                        <i class="fa-solid fa-hashtag"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="fecha_turno">Fecha Programada</label>
                    <div class="tc-input-container">
                        <input type="date" name="fecha_turno" id="fecha_turno" value="<?php echo $fecha_ext; ?>" required>
                        <i class="fa-regular fa-calendar-check"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="hora_turno">Horario (HH:MM)</label>
                    <div class="tc-input-container">
                        <input type="time" name="hora_turno" id="hora_turno" value="<?php echo htmlspecialchars($hora_ext); ?>">
                        <i class="fa-regular fa-clock"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="profesional">Médico Asignado</label>
                    <div class="tc-input-container">
                        <input type="text" name="profesional" id="profesional" value="<?php echo htmlspecialchars($profesional_ext); ?>">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="servicio">Servicio Principal</label>
                    <div class="tc-input-container">
                        <input type="text" name="servicio" id="servicio" value="<?php echo htmlspecialchars($servicio_ext); ?>" required>
                        <i class="fa-solid fa-briefcase-medical"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="especialidad">Especialidad Derivada</label>
                    <div class="tc-input-container">
                        <input type="text" name="especialidad" id="especialidad" value="<?php echo htmlspecialchars($especialidad_ext); ?>">
                        <i class="fa-solid fa-notes-medical"></i>
                    </div>
                </div>
            </div>

            <div class="tc-section-title"><i class="fa-solid fa-satellite-dish" style="color: #10b981;"></i> Vías de Notificación</div>
            <div class="tc-form-grid" style="margin-bottom: 40px;">
                <div class="tc-input-wrapper">
                    <label for="email">Correo Electrónico Válido</label>
                    <div class="tc-input-container">
                        <input type="email" name="email" id="email">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>

                <div class="tc-input-wrapper">
                    <label for="whatsapp">Móvil / WhatsApp (+54)</label>
                    <div class="tc-input-container">
                        <input type="tel" name="whatsapp" id="whatsapp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; font-size: 1.5rem; font-weight: 900; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 16px; padding: 25px; box-shadow: 0 15px 35px rgba(16,185,129,0.3); transition: all 0.3s; text-transform: uppercase; letter-spacing: 1px; cursor: pointer;">
                <i class="fa-solid fa-microchip" style="margin-right: 12px;"></i> Procesar e Ingresar al Sistema
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>