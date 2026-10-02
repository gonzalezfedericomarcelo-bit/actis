@echo off
set "ff_dir=%APPDATA%\Mozilla\Firefox\Profiles"
:: Busca el perfil activo (el primero que encuentre)
for /d %%d in (%ff_dir%\*.default-release) do set "perfil=%%d"

echo Configurando Firefox en: %perfil%

:: Deshabilitar bloqueo de autoplay (para sonido)
echo user_pref("media.autoplay.blocking_policy", 0); >> "%perfil%\prefs.js"
:: Deshabilitar notificaciones
echo user_pref("dom.webnotifications.enabled", false); >> "%perfil%\prefs.js"
:: Habilitar impresión silenciosa (evita ventana de diálogo)
echo user_pref("print.always_print_silent", true); >> "%perfil%\prefs.js"
echo user_pref("print.show_print_progress", false); >> "%perfil%\prefs.js"

echo Configuración aplicada. Reinicia Firefox.
pause
