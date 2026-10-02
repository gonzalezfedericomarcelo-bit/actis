<?php
/**
 * setup_docs_v2.php — Agrega campo categoria a totem_documentos
 */
require 'includes/conexion.php';
$conexion->query("ALTER TABLE totem_documentos ADD COLUMN IF NOT EXISTS categoria VARCHAR(30) NOT NULL DEFAULT 'otro'");
echo "OK: columna categoria agregada (o ya existía).";
