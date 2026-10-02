<?php
/**
 * descargas.php — Descarga de documentos por categoría
 * Acceso público desde QR del Tótem OSFA
 */
require_once 'includes/conexion.php';

// Cargar documentos activos agrupados por categoría
$docs = ['recetas' => [], 'odontologia' => [], 'otro' => []];
$res = $conexion->query("SELECT * FROM totem_documentos WHERE activo = 1 ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $cat = $row['categoria'] ?? 'otro';
        if (!isset($docs[$cat])) $cat = 'otro';
        $docs[$cat][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documentos — OSFA Posadas</title>
<meta name="description" content="Descargá tus recetas, órdenes y fichas odontológicas de OSFA desde tu celular.">
<link rel="icon" href="img/osfa.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Poppins', sans-serif;
        background: #f1f5f9;
        min-height: 100vh;
        color: #0f172a;
    }

    /* ── Header ────────────────────────────────── */
    .header {
        background: linear-gradient(135deg, #144973 0%, #1e6fa8 100%);
        padding: 32px 20px 28px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .header::before,
    .header::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,0.06);
    }
    .header::before { width: 200px; height: 200px; top: -60px; right: -50px; }
    .header::after  { width: 260px; height: 260px; bottom: -90px; left: -60px; }

    .header-logo {
        width: 56px; height: 56px;
        background: rgba(255,255,255,0.15);
        border-radius: 16px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 24px; color: white;
        margin-bottom: 14px;
        position: relative; z-index: 1;
    }
    .header h1 {
        font-size: 1.35rem; font-weight: 900; color: white;
        margin-bottom: 6px;
        position: relative; z-index: 1;
    }
    .header p {
        font-size: 0.82rem; color: rgba(255,255,255,0.7);
        position: relative; z-index: 1;
    }

    /* ── Contenedor ────────────────────────────── */
    .container {
        max-width: 540px;
        margin: 0 auto;
        padding: 22px 16px 56px;
    }

    /* ── Banner de aviso (único) ───────────────── */
    .aviso-banner {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 24px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border-left: 5px solid #f59e0b;
    }
    .aviso-banner-icon {
        width: 38px; height: 38px;
        background: #fef3c7;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; color: #d97706;
        flex-shrink: 0;
    }
    .aviso-banner-text {
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.6;
        font-weight: 500;
    }
    .aviso-banner-text strong {
        display: block;
        font-size: 0.85rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
    }

    /* ── Sección ───────────────────────────────── */
    .seccion {
        margin-bottom: 28px;
    }
    .seccion-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }
    .seccion-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .seccion-icon.recetas    { background: #dbeafe; color: #1d4ed8; }
    .seccion-icon.odonto     { background: #dcfce7; color: #15803d; }
    .seccion-icon.otro       { background: #f3e8ff; color: #7c3aed; }

    .seccion-titulo {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }
    .seccion-desc {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        margin-top: 1px;
    }

    /* ── Tarjeta de doc ────────────────────────── */
    .doc-card {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        border: 2px solid transparent;
        transition: all 0.18s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .doc-card:active,
    .doc-card:hover {
        border-color: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(37,99,235,0.1);
    }
    .doc-card-icon {
        width: 44px; height: 44px;
        background: #fef2f2;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; color: #ef4444;
        flex-shrink: 0;
    }
    .doc-card-text { flex: 1; min-width: 0; }
    .doc-card-text strong {
        display: block;
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .doc-card-text span {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 600;
    }
    .doc-card-arrow {
        font-size: 1rem;
        color: #2563eb;
        flex-shrink: 0;
    }

    /* ── Empty state ───────────────────────────── */
    .empty {
        text-align: center;
        padding: 36px 16px;
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .empty i { font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px; }
    .empty p { font-size: 0.85rem; color: #94a3b8; font-weight: 600; }

    /* ── Footer ────────────────────────────────── */
    .footer {
        text-align: center;
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 600;
        padding: 0 16px 24px;
    }
</style>
</head>
<body>

<div class="header">
    <div class="header-logo"><i class="fa-solid fa-file-arrow-down"></i></div>
    <h1>Documentos Descargables</h1>
    <p>OSFA — Delegación Posadas</p>
</div>

<div class="container">

    <!-- Aviso único centralizado -->
    <div class="aviso-banner">
        <div class="aviso-banner-icon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="aviso-banner-text">
            <strong>Recordá</strong>
            No es necesario imprimir recetas, bonos, órdenes médicas ni fichas odontológicas. Podés descargar los documentos desde esta página si los necesitás como respaldo.
        </div>
    </div>

    <?php
    $total = count($docs['recetas']) + count($docs['odontologia']) + count($docs['otro']);
    if ($total === 0): ?>
        <div class="empty">
            <i class="fa-solid fa-file-circle-xmark"></i>
            <p>No hay documentos disponibles por el momento.</p>
        </div>
    <?php else: ?>

        <?php if (!empty($docs['recetas'])): ?>
        <div class="seccion">
            <div class="seccion-header">
                <div class="seccion-icon recetas"><i class="fa-solid fa-prescription"></i></div>
                <div>
                    <div class="seccion-titulo">Recetas y Órdenes Médicas</div>
                    <div class="seccion-desc">Documentos médicos prescriptos por profesionales</div>
                </div>
            </div>
            <?php foreach ($docs['recetas'] as $doc): ?>
            <a href="uploads/documentos/<?php echo htmlspecialchars($doc['archivo']); ?>" target="_blank" class="doc-card">
                <div class="doc-card-icon"><i class="fa-solid fa-file-pdf"></i></div>
                <div class="doc-card-text">
                    <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                    <span><i class="fa-solid fa-download"></i> Tocar para descargar</span>
                </div>
                <div class="doc-card-arrow"><i class="fa-solid fa-arrow-down-to-line"></i></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($docs['odontologia'])): ?>
        <div class="seccion">
            <div class="seccion-header">
                <div class="seccion-icon odonto"><i class="fa-solid fa-tooth"></i></div>
                <div>
                    <div class="seccion-titulo">Odontología</div>
                    <div class="seccion-desc">Fichas y formularios del área odontológica</div>
                </div>
            </div>
            <?php foreach ($docs['odontologia'] as $doc): ?>
            <a href="uploads/documentos/<?php echo htmlspecialchars($doc['archivo']); ?>" target="_blank" class="doc-card">
                <div class="doc-card-icon"><i class="fa-solid fa-file-pdf"></i></div>
                <div class="doc-card-text">
                    <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                    <span><i class="fa-solid fa-download"></i> Tocar para descargar</span>
                </div>
                <div class="doc-card-arrow"><i class="fa-solid fa-arrow-down-to-line"></i></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($docs['otro'])): ?>
        <div class="seccion">
            <div class="seccion-header">
                <div class="seccion-icon otro"><i class="fa-solid fa-folder-open"></i></div>
                <div>
                    <div class="seccion-titulo">Otros Documentos</div>
                    <div class="seccion-desc">Formularios y documentos varios</div>
                </div>
            </div>
            <?php foreach ($docs['otro'] as $doc): ?>
            <a href="uploads/documentos/<?php echo htmlspecialchars($doc['archivo']); ?>" target="_blank" class="doc-card">
                <div class="doc-card-icon"><i class="fa-solid fa-file-pdf"></i></div>
                <div class="doc-card-text">
                    <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                    <span><i class="fa-solid fa-download"></i> Tocar para descargar</span>
                </div>
                <div class="doc-card-arrow"><i class="fa-solid fa-arrow-down-to-line"></i></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<div class="footer">
    ACTIS — Sistema Integral de Gestión OSFA &bull; <?php echo date('Y'); ?>
</div>

</body>
</html>
