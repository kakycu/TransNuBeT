# AGENTS.md

## Idioma

- **SIEMPRE contestar al usuario en español.**

## Entorno de desarrollo

- **USBWebServer:** `C:\Users\kakyc\Desktop\TRANSNUBET` (servidor Apache local; `http://localhost`, DocumentRoot `C:\Users\kakyc\Desktop\TRANSNUBET\root`, config en `Apache2\conf\httpd.conf`)
- Proyecto PHP: `servicios/nominas/` (ruta local: `C:\Users\kakyc\Desktop\TRANSNUBET\root\servicios\nominas`, URL: `http://localhost/servicios/nominas/`)
- MySQL local: `localhost`, usuario `root`, `frl8110kaky`, BD `TransNuBeT_nomina`
- **Binario MySQL:** `C:\Users\kakyc\Desktop\TRANSNUBET\mysql\bin\mysqld_usbwv8.exe`
- **NO hay cliente MySQL CLI** (`mysql.exe`) en esta instalación. El MCP de MySQL (`@benborla29/mcp-server-mysql`) tiene habilitadas las operaciones de escritura (`ALLOW_INSERT/UPDATE/DELETE/DDL_OPERATION=true` en `opencode.json`), por lo que se puede usar directamente para INSERT/UPDATE/DELETE y DDL; si el MCP no estuviera disponible, escribir con un script PHP con PDO ejecutado con `php.exe`.
- **PHP (CLI):** `C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe` (PHP 8.1.7)
- Credenciales de prueba: usuario `admin`, contraseña `password`

## Comandos útiles

- Validar sintaxis PHP: `& "C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe" -l <archivo>`
- Lint recursivo de un directorio:
  ```powershell
  Get-ChildItem -Path servicios\nominas -Recurse -Filter *.php | ForEach-Object { & "C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe" -l $_.FullName } 2>&1 | Select-String "No syntax errors" -NotMatch
  ```

## Pruebas en navegador

- Usar el **navegador integrado de OpenCode** (`tools.browser.*`) cuando esté disponible; si no, verificar con `curl.exe` / `Invoke-WebRequest` o un script PHP CLI. Ya **no** se usa Playwright MCP.
