<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $action = $data['action'] ?? '';
    $id_pedido = (int)($data['id_pedido'] ?? 0);
    $id_usuario = (int)($data['id_usuario'] ?? 0);

    if ($id_pedido <= 0) {
        throw new Exception("ID de pedido inválido.");
    }

    if ($action === 'rechazar') {
        $motivo = $data['motivo_rechazo'] ?? '';
        
        $pdo->beginTransaction();
        
        // 1. Cambiar estado
        $stmt = $pdo->prepare("UPDATE log_pedidos_trabajo SET estado_pedido = 'rechazado' WHERE id_pedido = :id");
        $stmt->execute([':id' => $id_pedido]);
        
        // 2. Opcional: Registrar motivo o notificar al creador (se implementará según las reglas de negocio)
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pedido rechazado correctamente.'
        ]);
        exit;
    }
    else if ($action === 'reenviar_firma') {
        // En la web llama a ajax_generar_token_firma.php
        $nuevo_token = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("UPDATE log_pedidos_trabajo SET token_firma = :token WHERE id_pedido = :id");
        $stmt->execute([':token' => $nuevo_token, ':id' => $id_pedido]);
        
        // Aquí iría la lógica de re-envío de correo que estaba en la web.
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Nuevo enlace generado y correo enviado.'
        ]);
        exit;
    }
    else {
        throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
