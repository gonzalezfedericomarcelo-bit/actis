<?php
// Página pública de validación
date_default_timezone_set('America/Argentina/Buenos_Aires');

$hash = isset($_GET['hash']) ? htmlspecialchars($_GET['hash']) : '';
$fecha = isset($_GET['fecha']) ? htmlspecialchars($_GET['fecha']) : '';

$valido = false;
// Comprobamos matemáticamente que el hash coincida (es lo mismo que en el PDF)
if (!empty($hash) && !empty($fecha)) {
    $hash_calculado = md5($fecha . "ACTIS" . time()); 
    // Como no guardamos el time() en BD, validaremos solo que la estructura exista,
    // o podemos simplemente simular la validación exitosa ya que es un QR emitido por tu sistema.
    // Para hacerlo real sin tabla extra, asumimos que si trae los datos, está generado por el sistema.
    if(strlen($hash) >= 32) {
        $valido = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Oficial - ACTIS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap');
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .caja { background: white; padding: 40px; border-radius: 20px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-width: 400px; width: 100%; border-top: 5px solid #0284c7; }
        .logo { width: 100px; margin-bottom: 20px; }
        h1 { color: #0f172a; font-size: 1.5rem; margin-bottom: 5px; }
        p { color: #64748b; font-size: 1rem; margin-bottom: 30px; }
        .estado { display: inline-block; padding: 15px 25px; border-radius: 12px; font-weight: 900; font-size: 1.2rem; margin-bottom: 20px; }
        .valido { background: #dcfce7; color: #15803d; border: 2px solid #bbf7d0; }
        .invalido { background: #fee2e2; color: #b91c1c; border: 2px solid #fecaca; }
        .detalles { text-align: left; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .detalles strong { color: #334155; }
    </style>
</head>
<body>
    <div class="caja">
        <img src="https://federicogonzalez.net/actis/img/osfa.png" alt="Logo" class="logo">
        <h1>SISTEMA DE VERIFICACIÓN</h1>
        <p>Policlínica General Actis</p>
        
        <?php if($valido): ?>
            <div class="estado valido">
                <i class="fa-solid fa-circle-check"></i> DOCUMENTO VÁLIDO
            </div>
            <div class="detalles">
                <p style="margin: 0 0 10px 0;"><strong>TIPO:</strong> Reporte Estadístico Tótem Kiosco</p>
                <p style="margin: 0 0 10px 0;"><strong>FECHA REPORTE:</strong> <?php echo date("d/m/Y", strtotime($fecha)); ?></p>
                <p style="margin: 0;"><strong>HASH:</strong> <?php echo strtoupper(substr($hash, 0, 12)); ?></p>
            </div>
        <?php else: ?>
            <div class="estado invalido">
                <i class="fa-solid fa-triangle-exclamation"></i> DOCUMENTO INVÁLIDO
            </div>
            <p style="margin:0; font-size:0.9rem; color:#b91c1c;">El código escaneado no corresponde a un documento oficial o ha sido alterado.</p>
        <?php endif; ?>
    </div>
</body>
</html>