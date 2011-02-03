rmdir /q /s "SRou Installer Scripts"
mkdir "SRou Installer Scripts" || goto die

svn export --force "E:\{Addon Installers}\Distribution" "SRou Installer Scripts" || goto die
svn export --force --depth files "E:\{Addon Installers}" "SRou Installer Scripts" || goto die

del "SRou Installer Scripts\*.nsi" || goto die

del "SRou Installer Scripts.zip"
zip -9rm "SRou Installer Scripts.zip" "SRou Installer Scripts"

:die
pause