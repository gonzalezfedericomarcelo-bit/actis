<?php
$configFile = 'config_modal.json';

// --- NUEVO: PUENTE ANTI-CACHÉ PARA TAMPERMONKEY ---
// Si Tampermonkey llama a este archivo con "?api=1", le escupe el JSON fresco sin caché
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    if (file_exists($configFile)) {
        echo file_get_contents($configFile);
    } else {
        echo json_encode(["activo" => true]);
    }
    exit;
}
// --------------------------------------------------

// 1. Si el archivo no existe, lo creamos
if (!file_exists($configFile)) {
    $defaultConfig = [
        "activo" => true,
        "html" => "<div style=\"font-size:60px; color:#10b981; margin-bottom:20px;\">🏆</div>\n<h2 style=\"margin:0 0 10px 0; color:#0f172a; font-family:Arial; font-size:24px;\">¡Buen trabajo, {{userName}}!</h2>\n<p style=\"color:#64748b; font-family:Arial; font-size:16px; margin:0 0 25px 0; line-height:1.5;\">El turno fue guardado en el sistema ACTIS correctamente.<br>Lo estás haciendo muy bien.</p>\n<button id=\"btn_cerrar_modal_actis\" style=\"background:#3b82f6; color:white; border:none; padding:12px 30px; border-radius:10px; font-weight:bold; font-size:16px; cursor:pointer; box-shadow:0 4px 14px rgba(59,130,246,0.4);\">Continuar</button>"
    ];
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
}

$mensaje = "";

// 2. Guardar los cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoEstado = isset($_POST['activo']) ? true : false;
    $nuevoHtml = $_POST['html'] ?? '';

    $nuevaConfig = [
        "activo" => $nuevoEstado,
        "html" => $nuevoHtml
    ];

    file_put_contents($configFile, json_encode($nuevaConfig, JSON_PRETTY_PRINT));
    $mensaje = "¡Configuración del modal guardada con éxito!";
}

// 3. Leer la configuración actual
$currentConfig = json_decode(file_get_contents($configFile), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración - Modal ACTIS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #334155; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #0f172a; }
        .alert { background: #dcfce7; color: #166534; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border-left: 5px solid #22c55e; }
        label { font-weight: bold; display: block; margin-bottom: 10px; }
        textarea { width: 100%; height: 250px; font-family: monospace; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; }
        .btn { background: #3b82f6; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #2563eb; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(26px); }
        .info { background: #e0f2fe; padding: 15px; border-radius: 5px; font-size: 14px; margin-top: 15px; color: #075985; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Panel de Control - Modal Tampermonkey</h1>
        <?php if($mensaje) echo "<div class='alert'>$mensaje</div>"; ?>
        
        <form method="POST">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                <label style="margin: 0; font-size: 18px;">¿Mostrar modal de felicitaciones?</label>
                <label class="switch">
                    <input type="checkbox" name="activo" <?= $currentConfig['activo'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <label for="html">Código HTML del Modal:</label>
            <textarea name="html" id="html"><?= htmlspecialchars($currentConfig['html']) ?></textarea>
            
            <div class="info">
                <strong>💡 Tip:</strong> Podés usar <code>{{userName}}</code> dentro del HTML para que el script lo reemplace automáticamente por el nombre del empleado.
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn">💾 Guardar Configuración</button>
            </div>
        </form>
    </div>
</body>
</html>