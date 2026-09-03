# AGENTS.md

## Idioma

- **SIEMPRE contestar al usuario en español.**

## Entorno de desarrollo

- **USBWebServer:** `C:\Users\kakyc\Desktop\TRANSNUBET` (servidor Apache local; `http://localhost`, DocumentRoot `C:\Users\kakyc\Desktop\TRANSNUBET\root`, config en `Apache2\conf\httpd.conf`)
- Proyecto PHP: `NOMINAS/` (raíz web: `C:\Users\kakyc\Desktop\TRANSNUBET\root\NOMINAS`)
- MySQL local: `localhost`, usuario `root`, `frl8110kaky`, BD `TransNuBeT_nomina`
- **Binario MySQL:** `C:\Users\kakyc\Desktop\TRANSNUBET\mysql\bin\mysqld_usbwv8.exe`
- **PHP (CLI):** `C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe` (PHP 8.1.7)
- Credenciales de prueba: usuario `admin`, contraseña `password`

## Comandos útiles

- Validar sintaxis PHP: `& "C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe" -l <archivo>`
- Lint recursivo de un directorio:
  ```powershell
  Get-ChildItem -Path NOMINAS -Recurse -Filter *.php | ForEach-Object { & "C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe" -l $_.FullName } 2>&1 | Select-String "No syntax errors" -NotMatch
  ```
