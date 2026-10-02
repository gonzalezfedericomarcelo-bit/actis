Add-Type -AssemblyName System.Drawing
$img = [System.Drawing.Image]::FromFile("C:\Users\HACKRO\.gemini\antigravity-ide\brain\604d106d-0ad7-4a7b-9c9e-0f95a356fd51\totem_security_icon_1787313417003.jpg")
$img.Save("C:\Users\HACKRO\Documents\GitHub\actis\electron-totem\icon.png", [System.Drawing.Imaging.ImageFormat]::Png)
$img.Dispose()
