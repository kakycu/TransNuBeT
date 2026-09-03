@echo off
title SISGESNOM :: EXPORTAR BD A INFINITYFREE
cd /d "%~dp0"
:: El script detecta Windows Terminal (UTF-8) y fija su encoding internamente.
powershell -NoProfile -ExecutionPolicy Bypass -File "ExportarBD_Infinity.ps1"

echo.
echo. Fin del asistente. Presione una tecla para cerrar...
pause >nul