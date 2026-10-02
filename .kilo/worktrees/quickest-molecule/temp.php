<?php
require 'includes/conexion.php';
$conexion->query("INSERT INTO totem_botones (grupo, identificador, titulo, bajada, icono, color, accion_tipo, accion_data, orden, activo) VALUES ('widget', 'proximamente', 'Próximamente', 'Nuevo servicio en desarrollo', 'fa-star', '#8b5cf6', 'modal', '', 30, 1)");
echo "Inserted";
?>
