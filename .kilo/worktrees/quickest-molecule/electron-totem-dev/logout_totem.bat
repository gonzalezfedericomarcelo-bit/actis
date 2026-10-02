@echo off
echo Cerrando ACTIS Totem...
taskkill /IM "ACTIS Totem.exe" /F 2>NUL
echo.
echo Borrando datos de sesion del Totem...
rmdir /S /Q "%APPDATA%\actis-totem"
echo.
echo Sesion borrada exitosamente.
echo Puede volver a abrir el Totem para probar el Auto-Login.
pause
