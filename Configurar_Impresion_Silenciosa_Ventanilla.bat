@echo off
echo ========================================================
echo CONFIGURADOR DE IMPRESION SILENCIOSA - ACTIS VENTANILLA
echo ========================================================
echo.
echo Este script creara un acceso directo especial en el Escritorio
echo para abrir Google Chrome en Modo Kiosco de Impresion.
echo.
echo Esto permitira que los tickets y comprobantes de la Ventanilla
echo se impriman directamente en la impresora predeterminada 
echo sin mostrar el cuadro de dialogo de impresion de Chrome.
echo.
pause

set SCRIPT="%TEMP%\CrearAccesoDirectoChrome.vbs"

echo Set oWS = WScript.CreateObject("WScript.Shell") > %SCRIPT%
echo sLinkFile = oWS.SpecialFolders("Desktop") ^& "\ACTIS Ventanilla (Impresion Directa).lnk" >> %SCRIPT%
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> %SCRIPT%
echo oLink.TargetPath = "C:\Program Files\Google\Chrome\Application\chrome.exe" >> %SCRIPT%
echo oLink.Arguments = "--kiosk-printing https://validador.iosfa.gob.ar/ValidadorDni" >> %SCRIPT%
echo oLink.Description = "Abre ACTIS Ventanilla con impresion directa habilitada" >> %SCRIPT%
echo oLink.IconLocation = "C:\Program Files\Google\Chrome\Application\chrome.exe, 0" >> %SCRIPT%
echo oLink.Save >> %SCRIPT%

cscript /nologo %SCRIPT%
del %SCRIPT%

echo.
echo [EXITO] Se ha creado el acceso directo "ACTIS Ventanilla (Impresion Directa)" en el Escritorio.
echo.
echo NOTA IMPORTANTE:
echo Asegurate de que la impresora de tickets este configurada como 
echo la "Impresora Predeterminada" en Windows (Panel de Control - Dispositivos e Impresoras).
echo.
echo Presiona cualquier tecla para salir...
pause >nul
