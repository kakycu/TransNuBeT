# SSL / HTTPS en USBWebServer (https://localhost)

Notas de configuración del servidor Apache local (USBWebServer).
- DocumentRoot: `C:\Users\kakyc\Desktop\TRANSNUBET\root`
- Apache 2.4.54, binario: `Apache2\bin\httpd_usbwv8.exe`
- Servidor de producción remoto (InfinityFree) ya usa HTTPS por su cuenta; esto es SOLO para el servidor local.

> Estado: HTTPS habilitado con esquema CA + certificado de servidor.
> `http://localhost` y `https://localhost` funcionan y sirven el mismo sitio.

---

## IMPORTANTE: dónde se configuran los cambios

USBWebServer guarda su configuración maestra en `C:\Users\kakyc\Desktop\TRANSNUBET\settings`:

- `settings\httpd.conf` → **template maestro**. El panel `usbwebserver.exe` lo copia a
  `Apache2\conf\httpd.conf` sustituyendo los placeholders `{path}`, `{rootdir}`, `{port}`.
- **Si editas solo `Apache2\conf\httpd.conf`, el panel lo sobrescribe al arrancar** y pierdes los cambios.
- Para que SSL persista hay que editar **`settings\httpd.conf`** (y luego regenerar el conf real).

Los archivos de certificados se guardan en `Apache2\conf\`. Sí sobreviven (no los toca el panel).

---

## Qué se hizo (ya aplicado)

1. **Certificados generados** (en `C:\Users\kakyc\AppData\Local\Temp\opencode\ssl\` y copiados a `Apache2\conf\`):
   - `ca.crt` / `ca.key` → CA raíz, CN=`UnicornioSoftware`, válida 10 años.
   - `server.crt` / `server.key` / `server.csr` → certificado de servidor,
     CN=`UnicornioSOftwareDistributionCUBA`, emitido por la CA (`UnicornioSoftware`),
     SAN = `localhost` / `127.0.0.1`, EKU = servidor (TLS Web Server Authentication).

2. **`settings\httpd.conf`** (template maestro):
   - Habilitados `LoadModule socache_shmcb_module` y `LoadModule ssl_module`.
   - Añadido `Listen 443`.
   - Añadido al final: `Include conf/extra/httpd-ssl-local.conf`.

3. **`Apache2\conf\extra\httpd-ssl-local.conf`** (nuevo):
   - VirtualHost en `:443`, `ServerName localhost`, DocumentRoot al root real,
     `SSLEngine on` con `server.crt` / `server.key`.

4. **`Apache2\conf\httpd.conf`** regenerado desde el template editado (mismo contenido, placeholders resueltos).

5. Backups:
   - `settings\httpd.conf.bak` (template original)
   - `Apache2\conf\httpd.conf.sim` / `httpd.conf.bak` (conf original)
   - `Apache2\conf\server.crt.autofirmado.bak` y `server.key.autofirmado.bak` (cert viejos autofirmados)

---

## Cómo regenerar los certificados (si caducan o cambias de máquina)

Trabajo en un directorio temporal. Con el openssl de Apache:

```powershell
$openssl = "C:\Users\kakyc\Desktop\TRANSNUBET\Apache2\bin\openssl.exe"
```

1) **CA raíz** — crear `ca.cnf` con:
```
[ req ]
distinguished_name = req_dn
prompt = no
x509_extensions = v3_ca
[ req_dn ]
CN = UnicornioSoftware
O = UnicornioSoftware
OU = SecureWebPHP
[ v3_ca ]
basicConstraints = critical, CA:TRUE
keyUsage = critical, digitalSignature, keyEncipherment, keyCertSign, cRLSign
extendedKeyUsage = serverAuth, clientAuth, codeSigning, emailProtection, timeStamping, OCSPSigning
```
Generar:
```
& $openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -keyout ca.key -out ca.crt -config ca.cnf
```

2) **CSR del servidor** (el CN es el "Emitido para"):
```
& $openssl req -new -newkey rsa:2048 -nodes -keyout server.key -out server.csr -subj "/CN=UnicornioSOftwareDistributionCUBA/O=UnicornioSoftware/OU=SecureWebPHP(tm)" -config "C:\Users\kakyc\Desktop\TRANSNUBET\Apache2\conf\openssl.cnf"
```

3) **Firmar con la CA** — `server-ext.cnf` con SAN y EKU:
```
[ v3_server ]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names
[ alt_names ]
DNS.1 = localhost
DNS.2 = 127.0.0.1
IP.1 = 127.0.0.1
```
```
& $openssl x509 -req -in server.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.crt -days 3650 -extfile server-ext.cnf -extensions v3_server
```

4) **Copiar** `ca.crt`, `server.crt`, `server.key` a `Apache2\conf\` y reiniciar Apache.

> Evita caracteres no ASCII (como `°`) en los CN: dan problemas de codificación.

---

## Cómo reiniciar Apache / regenerar el conf desde el template

1. Cierra `usbwebserver.exe` si está abierto (o reinícialo para que aplique los cambios del template).
2. Si editaste `settings\httpd.conf`, el panel regenerará `Apache2\conf\httpd.conf` al arrancar.
   Para regenerarlo manualmente:
   ```powershell
   $c = Get-Content "C:\Users\kakyc\Desktop\TRANSNUBET\settings\httpd.conf" -Raw
   $c = $c.Replace("{path}","C:/Users/kakyc/Desktop/TRANSNUBET").Replace("{rootdir}","C:/Users/kakyc/Desktop/TRANSNUBET/root").Replace("{port}","80")
   [System.IO.File]::WriteAllText("C:\Users\kakyc\Desktop\TRANSNUBET\Apache2\conf\httpd.conf", $c, (New-Object System.Text.UTF8Encoding($false)))
   ```

---

## Cómo validar la configuración antes de arrancar

```
cd Apache2\bin
httpd_usbwv8.exe -t -f "C:\Users\kakyc\Desktop\TRANSNUBET\Apache2\conf\httpd.conf"
```
Debe mostrar `Syntax OK`.

---

## Cómo quitar la advertencia "No es seguro" del navegador

Se debe instalar la **CA** (`ca.crt`), NO el certificado de servidor, en el almacén de
*Entidades de certificación raíz de confianza*:

1. Win+R → `certmgr.msc` → Enter.
2. **Entidades de certificación raíz de confianza** → clic derecho →
   **Todas las tareas** → **Importar**.
3. Siguiente → Examinar → selecciona `Apache2\conf\ca.crt`
   (cambia el filtro a "Todos los archivos" si no lo ves) → Abrir.
4. Almacén: "Colocar todos los certificados en el siguiente almacén"
   (ya apunta a *Entidades de certificación raíz de confianza*) → Siguiente → Finalizar.
5. Confirma el aviso → Aceptar → cierra `certmgr.msc`.
6. Reinicia el navegador (cierra todas las ventanas) y entra a `https://localhost`.

Al inspeccionar el certificado servido en `certmgr.msc` / navegador verás:
- **Emitido para:** `UnicornioSOftwareDistributionCUBA`
- **Emitido por:** `UnicornioSoftware`
- **Propósitos planteados / Propósito del servidor:** TLS Web Server Authentication (Permite una comunicación en Internet).

> Notas:
> - La importación aplica solo a tu usuario/Windows de esa máquina; no viaja con el proyecto.
> - La CA y el certificado duran 10 años.

---

## Login con Google (OAuth 2.0)

El login de nóminas (`nominas/login.php`) usa OAuth de Google. La URI de redirección se
construye dinámicamente según el host/protocolo (línea 173), por eso la URI debe estar
registrada en Google Cloud Console para que Google no la rechace.

### Orígenes de JavaScript autorizados (sin ruta: esquema://host:puerto)

Registrar en **APIs y servicios → Credenciales → Cliente OAuth 2.0** → "Orígenes de
JavaScript autorizados":

- `http://localhost`
- `https://localhost`
- `https://unicorniosoftware.gt.tc`

### URIs de redirección autorizadas (con ruta completa)

Registrar en **APIs y servicios → Credenciales → Cliente OAuth 2.0** → "URIs de
redireccionamiento autorizadas":

- `http://localhost/nominas/login.php?action=google_callback`
- `https://localhost/nominas/login.php?action=google_callback`
- `https://unicorniosoftware.gt.tc/nominas/login.php?action=google_callback`
- `https://www.unicorniosoftware.gt.tc/nominas/login.php?action=google_callback`

> Notas:
> - Las URIs deben coincidir **exactas, carácter por carácter**, con la que genera el
>   código (`nominas/login.php:173`). El puerto no se incluye (Google asume 80 http / 443 https).
> - Si la app OAuth está en estado "Probando", tu cuenta de Google debe estar en
>   "Usuarios de prueba" de la pantalla de consentimiento, si no Google da `access_denied`.
> - Tras editar, espera 1-2 min a que Google propague los cambios.
> - El `client_id` y `client_secret` se guardan en la tabla `configuracion_general`
>   (parámetros `google_client_id` / `google_client_secret`).
