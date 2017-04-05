set GTR2_DIR=n:\gtr2

cd /d %GTR2_DIR%\UserData\LOG\
if errorlevel 1 goto quit
del /f weather.txt
ftp -n -s:o:\weather.ftp
attrib +R weather.txt
:quit
pause
