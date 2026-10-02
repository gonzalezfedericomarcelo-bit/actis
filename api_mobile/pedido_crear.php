<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';
// require_once '../logistica/envio_correo_hostinger.php'; // Si es necesario enviar correos

function generar_nuevo_numero_orden($pdo) {
    $año_actual = date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero_orden, '/', 1) AS UNSIGNED)) FROM log_pedidos_trabajo WHERE numero_orden LIKE :anio");
    $stmt->execute([':anio' => '%/' . $año_actual]);
    $max_correlativo = $stmt->fetchColumn();
    $nuevo_correlativo = $max_correlativo ? $max_correlativo + 1 : 1;
    return str_pad($nuevo_correlativo, 4, '0', STR_PAD_LEFT) . '/' . $año_actual;
}

try {
    // 1. Obtener datos JSON del POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("No se recibieron datos válidos.");
    }

    // 2. Extraer y limpiar datos
    $id_usuario_logueado = (int)($data['id_usuario'] ?? 0); // Esto debería venir del auth token o enviado por la app temporalmente
    $titulo_pedido_post = trim($data['titulo_pedido'] ?? '');
    $id_destino_post = (int)($data['id_destino_interno'] ?? 0);
    $id_area_post = (int)($data['id_area'] ?? 0);
    $prioridad_post = trim($data['prioridad'] ?? 'rutina');
    $fecha_requerida_post = empty($data['fecha_requerida']) ? null : $data['fecha_requerida'];
    $descripcion_sintomas_post = trim($data['descripcion_sintomas'] ?? '');
    $solicitante_real_nombre_post = trim($data['solicitante_real_nombre'] ?? '');
    $solicitante_telefono_post = trim($data['solicitante_telefono'] ?? '');
    $solicitante_email_post = trim($data['email_solicitante_externo'] ?? '');
    $firma_base64 = $data['firma_solicitante_base64'] ?? '';
    
    // Obtener información extra
    $nombre_area_seleccionada = 'N/A';
    if ($id_area_post > 0) {
        $stmt_area = $pdo->prepare("SELECT nombre FROM log_areas WHERE id_area = :id");
        $stmt_area->execute([':id' => $id_area_post]);
        $nombre_area_seleccionada = $stmt_area->fetchColumn() ?: 'N/A';
    }

    $es_firma_remota = false;
    if ($id_destino_post > 0) {
        $stmt_remota = $pdo->prepare("SELECT firma_remota FROM log_destinos_internos WHERE id_destino = :id");
        $stmt_remota->execute([':id' => $id_destino_post]);
        $es_firma_remota = ($stmt_remota->fetchColumn() == 1);
    }
    
    // Si viene la firma presencial, forzamos que no sea remota
    if (!empty($firma_base64)) {
        $es_firma_remota = false;
    }

    // Procesar firma
    $ruta_firma_solicitante_guardada = null;
    if (!empty($firma_base64)) {
        $upload_dir_firmas = '../logistica/uploads/firmas_pedidos/'; // Guardar donde la web espera
        if (!is_dir($upload_dir_firmas)) {
            mkdir($upload_dir_firmas, 0777, true);
        }

        // Remover cabecera "data:image/png;base64," si existe
        $partes = explode(',', $firma_base64);
        $encoded_image = (count($partes) > 1) ? $partes[1] : $partes[0];
        $decoded_image = base64_decode($encoded_image);

        if ($decoded_image !== false) {
            $nombre_solicitante_limpio = preg_replace("/[^a-zA-Z0-9]/", "", str_replace(" ", "_", $solicitante_real_nombre_post));
            $filename = 'solic_' . $nombre_solicitante_limpio . '_' . time() . '.png';
            $ruta_completa_firma = $upload_dir_firmas . $filename;
            if (file_put_contents($ruta_completa_firma, $decoded_image)) {
                $ruta_firma_solicitante_guardada = $ruta_completa_firma; 
            }
        }
    }

    $token_firma = null;
    $estado_final = 'pendiente_encargado';

    if ($es_firma_remota) {
        $token_firma = bin2hex(random_bytes(32));
        $estado_final = 'pendiente_firma_remota';
    }

    $pdo->beginTransaction();

    $numero_orden_generado = generar_nuevo_numero_orden($pdo);

    $sql_insert = "INSERT INTO log_pedidos_trabajo
                (numero_orden, titulo_pedido, id_solicitante, id_auxiliar, id_area, area_solicitante, id_destino_interno, prioridad, fecha_requerida, descripcion_sintomas, solicitante_real_nombre, solicitante_telefono, solicitante_email, token_firma, fecha_emision, estado_pedido, firma_solicitante_path)
            VALUES
                (:num_orden, :titulo_ped, :id_solic, :id_aux, :id_area, :area_nombre, :id_dest, :prio, :fecha_req, :descrip, :solic_real, :solic_tel, :email_solic, :token, NOW(), :estado, :firma_solic_path)";

    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        ':num_orden' => $numero_orden_generado,
        ':titulo_ped' => $titulo_pedido_post,
        ':id_solic' => $id_usuario_logueado,
        ':id_aux' => $id_usuario_logueado,
        ':id_area' => ($id_area_post > 0) ? $id_area_post : null,
        ':area_nombre' => $nombre_area_seleccionada,
        ':id_dest' => ($id_destino_post > 0) ? $id_destino_post : null,
        ':prio' => $prioridad_post,
        ':fecha_req' => $fecha_requerida_post,
        ':descrip' => $descripcion_sintomas_post,
        ':solic_real' => $solicitante_real_nombre_post,
        ':solic_tel' => empty($solicitante_telefono_post) ? null : $solicitante_telefono_post,
        ':email_solic' => $solicitante_email_post,
        ':token' => $token_firma,
        ':estado' => $estado_final,
        ':firma_solic_path' => $ruta_firma_solicitante_guardada
    ]);

    $id_nuevo_pedido = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Pedido de trabajo creado con éxito.',
        'numero_orden' => $numero_orden_generado,
        'id_pedido' => $id_nuevo_pedido
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al crear el pedido: ' . $e->getMessage()
    ]);
}
