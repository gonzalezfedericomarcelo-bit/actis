<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}
require_once 'includes/conexion.php';
require_once 'header.php';
?>
<div style="max-width: 800px; margin: 40px auto; text-align: center; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
    <h1 style="color: #0f172a; font-weight: 900; margin-bottom: 10px;"><i class="fa-solid fa-print" style="color: #2563eb;"></i> Centro de Pruebas de Impresión</h1>
    <p style="color: #64748b; font-size: 1.1rem; margin-bottom: 40px;">Verifique el diseño y formato de los tickets de ventanilla antes de imprimir.</p>
    
    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
        <button onclick="window.open('imprimir_ventanilla_validacion.php?dni=12345678&nombre=PACIENTE DE PRUEBA&afiliado=I12345678&token=A1B2C3', 'previewVal', 'width=350,height=600')" style="padding: 15px 30px; font-size: 1.1rem; background: #2563eb; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(37,99,235,0.3);"><i class="fa-solid fa-file-signature"></i> Previsualizar VALIDACIÓN (Ventanilla)</button>
        
        <button onclick="window.open('imprimir_ventanilla_asistencia.php?dni=12345678&nombre=PACIENTE DE PRUEBA&afiliado=I12345678&fuerza=FUERZAS%20ARMADAS&estado=ACTIVO', 'previewAsi', 'width=350,height=600')" style="padding: 15px 30px; font-size: 1.1rem; background: #10b981; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(16,185,129,0.3);"><i class="fa-solid fa-notes-medical"></i> Previsualizar ASISTENCIA (Ventanilla)</button>

        <button onclick="window.open('imprimir_ticket_iofa.php?dni=12345678&nombre=PACIENTE DE PRUEBA&afiliado=I12345678&codigo=TKM555&fuerza=FUERZAS%20ARMADAS&estado=ACTIVO', 'previewTotem', 'width=350,height=600')" style="padding: 15px 30px; font-size: 1.1rem; background: #8b5cf6; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(139,92,246,0.3);"><i class="fa-solid fa-print"></i> Previsualizar TÓTEM KIOSCO</button>
    </div>
</div>
<?php require_once 'footer.php'; ?>