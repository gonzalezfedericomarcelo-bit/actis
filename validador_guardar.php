<?php
require_once 'includes/conexion.php';
require_once 'envio_correo.php';
require_once 'includes/header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id']) && isset($_POST['token_iofa'])) {
    $turno_id = $conexion->real_escape_string($_POST['turno_id']);
    $token_iofa = $conexion->real_escape_string($_POST['token_iofa']);
    $usuario_validador_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

    // 1. DESTRUIR EL CANDADO DE DUPLICADOS EN LA BASE DE DATOS
    try {
        $conexion->query("ALTER TABLE turnos DROP INDEX unique_token_iofa");
    } catch (Exception $e) { 
        // Si el índice ya fue borrado, simplemente continúa sin detenerse
    }

    // 2. ACTUALIZAR EL TURNO Y PERMITIR TOKENS REPETIDOS
    try {
        $sql_update = "UPDATE turnos SET token_iofa = '$token_iofa', estado = 'Autorizado', usuario_validador_id = $usuario_validador_id, validado_el = NOW() WHERE id = $turno_id";
        $conexion->query($sql_update);

        $sql_datos = "SELECT t.*, p.nombre, p.apellido, p.dni, p.email, p.whatsapp FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $turno_id";
        $res = $conexion->query($sql_datos);
        
        if($res->num_rows > 0){
            $datos = $res->fetch_assoc();

            $ruta_base = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $url_publica = $ruta_base . "/ticket_publico.php?id=" . $turno_id;
            $url_qr_nuevo = "https://quickchart.io/qr?text=" . urlencode($url_publica) . "&size=250&margin=2";

            // Enviar Correo Electrónico
            if (!empty($datos['email'])) {
                $asunto = "Turno Autorizado - Policlínica General ACTIS";
                $cuerpo = "
                <div style='font-family: Arial, sans-serif; background-color: #f1f5f9; padding: 30px; text-align: center;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                        <div style='background-color: #2563eb; color: white; padding: 25px;'>
                            <h1 style='margin: 0; font-size: 24px; letter-spacing: 1px;'>ACTIS <span style='color: #93c5fd;'>PRO</span></h1>
                            <p style='margin: 5px 0 0 0; font-size: 16px; opacity: 0.9;'>Certificado de Autorización</p>
                        </div>
                        <div style='padding: 30px; color: #1e293b; text-align: left;'>
                            <h2 style='margin-top: 0; color: #0f172a;'>¡Hola, ".$datos['nombre']."!</h2>
                            <p style='font-size: 16px; line-height: 1.5;'>Tu turno ha sido <strong>AUTORIZADO</strong> exitosamente por IOFA. Ya estás listo para tu atención.</p>
                            <div style='background-color: #f8fafc; border-left: 4px solid #10b981; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                                <p style='margin: 5px 0; font-size: 15px;'><strong>Servicio:</strong> ".$datos['servicio']."</p>
                                <p style='margin: 5px 0; font-size: 15px;'><strong>Fecha:</strong> ".date("d/m/Y", strtotime($datos['fecha_turno']))."</p>
                                <p style='margin: 5px 0; font-size: 18px; color: #2563eb;'><strong>Token IOFA:</strong> ".$token_iofa."</p>
                            </div>
                            <div style='text-align: center; margin-top: 30px;'>
                                <p style='font-size: 14px; color: #64748b; margin-bottom: 15px;'>Presenta este código en la recepción de la clínica:</p>
                                <img src='$url_qr_nuevo' alt='Código QR' style='border: 2px solid #e2e8f0; padding: 10px; border-radius: 10px; background: white;'>
                            </div>
                        </div>
                    </div>
                </div>";
                enviarCorreoNativo($datos['email'], $asunto, $cuerpo);
            }

            $texto_wa = "Hola ".$datos['nombre']."! Tu turno en ACTIS para ".$datos['servicio']." está AUTORIZADO. Tu Token IOFA es: $token_iofa. Tu certificado: $url_publica";
            $link_wa = "https://wa.me/" . preg_replace('/[^0-9]/', '', $datos['whatsapp']) . "?text=" . urlencode($texto_wa);

            echo "<div class='tarjeta glass-panel' style='text-align: center;'>";
            echo "<i class='fa-solid fa-circle-check' style='font-size: 5rem; color: var(--color-exito); margin-bottom: 20px;'></i>";
            echo "<h2 style='font-size: 2rem; font-weight: 700;'>¡Turno Autorizado Correctamente!</h2>";
            echo "<p style='font-size: 1.2rem; margin-bottom: 20px;'>El token IOFA <strong>$token_iofa</strong> ha sido registrado.</p>";
            
            // BOTONES Y APERTURA DE TICKET 80MM
            echo "<div style='display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;'>";
            echo "<a href='imprimir_ticket_iofa.php?id=$turno_id' target='_blank' class='btn btn-primario'><i class='fa-solid fa-print'></i> Imprimir Ticket Manual</a>";
            if (!empty($datos['whatsapp'])) { echo "<a href='$link_wa' target='_blank' class='btn' style='background: #25D366; color: white;'><i class='fa-brands fa-whatsapp' style='font-size: 1.2rem;'></i> Enviar WhatsApp</a>"; }
            echo "<a href='validador_listar.php' class='btn btn-secundario' style='background: #64748b; color: white;'><i class='fa-solid fa-arrow-left'></i> Volver a la Lista</a>";
            echo "</div></div>";
            
            // Este script abre automáticamente la ventana de tu ticketera al terminar de guardar
            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() { 
                        mostrarExito('Autorización Guardada y Notificada'); 
                        window.open('imprimir_ticket_iofa.php?id=$turno_id', '_blank');
                    });
                  </script>";
        }
    } catch (Exception $e) {
        echo "<div class='tarjeta glass-panel' style='text-align: center;'><h2 style='color: var(--color-peligro);'>Error BD</h2><p>".$e->getMessage()."</p><a href='validador_listar.php' class='btn btn-primario'>Volver</a></div>";
    }
} else {
    header("Location: validador_inicio.php");
}
require_once 'includes/footer.php';
?>