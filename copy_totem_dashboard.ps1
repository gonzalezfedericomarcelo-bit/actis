Write-Host "=== Copiando Dashboard Totem a ACTIS ==="
# Paths - adjust if your repository roots differ
$logisticaRoot = "C:\\Users\\HACKRO\\Documents\\GitHub\\logistica"
$actisRoot    = "C:\\Users\\HACKRO\\Documents\\GitHub\\actis"

# Archivos a copiar
$files = @(
    "dashboard_totem.php",
    "admin_totem.php"   # opcional, ya está en ACTIS pero lo forzamos por seguridad
)

foreach ($file in $files) {
    $src = Join-Path $logisticaRoot $file
    $dst = Join-Path $actisRoot $file
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $dst -Force
        Write-Host "[OK] Copiado $file"
    } else {
        Write-Warning "[WARN] No se encontró $file en $logisticaRoot"
    }
}

# Copiar carpeta completa del carrusel
$srcCarrusel = Join-Path $logisticaRoot "carrusel_totem"
$dstCarrusel = Join-Path $actisRoot "carrusel_totem"
if (Test-Path $srcCarrusel) {
    # -Recurse copia todo el contenido, -Force sobrescribe archivos existentes
    Copy-Item -Path $srcCarrusel -Destination $dstCarrusel -Recurse -Force
    Write-Host "[OK] Carpeta carrusel_totem copiada"
} else {
    Write-Warning "[WARN] Carpeta carrusel_totem no encontrada en $logisticaRoot"
}

Write-Host "=== Proceso completado ==="
