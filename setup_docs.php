<?php
require 'includes/conexion.php';

// Crear tabla totem_documentos
$conexion->query("CREATE TABLE IF NOT EXISTS totem_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insertar el widget si no existe ya
$res = $conexion->query("SELECT id FROM totem_botones WHERE identificador = 'widget_descargas'");
if ($res->num_rows == 0) {
    $conexion->query("INSERT INTO totem_botones (grupo, identificador, titulo, bajada, icono, color, accion_tipo, accion_data, orden, activo) 
                      VALUES ('widget', 'widget_descargas', 'Descargar Documentos', 'Recetas, Órdenes y Fichas. Toca aquí.', 'fa-file-pdf', '#ef4444', 'qr', 'https://federicogonzalez.net/actis/descargas.php', 30, 1)");
}

echo "Database updated successfully.\n";
?>
