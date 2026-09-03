# TransNuBeT

Sistema de gestión de nóminas (PDL), junto con el sitio web institucional.

## Estructura del repositorio

```
root/
├── index.php              Sitio web institucional
├── privacidad.php         Página de privacidad
├── soporte.php            Página de soporte
├── terminos.php           Términos y condiciones
├── css/                   Estilos del sitio web
├── js/                    Scripts del sitio web
├── images/                Imágenes del sitio web
├── NOMINAS/               Sistema de gestión de nóminas
│   ├── index.php          Acceso al sistema
│   ├── login.php          Autenticación
│   ├── dashboard.php      Panel principal
│   ├── config.php         Configuración general y conexión PDO
│   ├── config/            Conexión de BD y migraciones automáticas
│   ├── modules/           Módulos funcionales
│   ├── ajax/              Endpoints AJAX
│   ├── includes/          Cabecera, sidebar, permisos y funciones
│   ├── css/               Estilos del sistema
│   ├── js/                Scripts del sistema
│   ├── assets/            Imágenes y recursos
│   └── InstalarBD.php     Instalador con el esquema completo embebido
```

## Módulos del sistema de nóminas

- **Dashboard** — Indicadores y resumen general.
- **Empleados** — Gestión de trabajadores (altas, bajas, historial salarial).
- **Nóminas** — Cálculo y emisión de nóminas por periodo y tipo de pago.
- **Submayor de Vacaciones** — Control de acumulados, consumos y pagos de vacaciones.
- **Reportes** — Informes y análisis.
- **Exportar Banco (BETA)** — Generación de archivos bancarios (BANDEC).
- **Clasificadores** — Áreas, centros de costo, categorías ocupacionales, escalas salariales, etc.
- **Configuración** — Parámetros generales, tasas, impuestos y vacaciones.
- **Usuarios** — Gestión de usuarios y roles con permisos.

## Requisitos

- PHP 8.1 o superior (probado con 8.1.7)
- MySQL 5.7+ (probado con 5.7.37)
- Servidor web (Apache/XAMPP/WAMP) o servidor embebido de PHP
- Extensión PDO MySQL
- Composer (para dependencias)

## Instalación

1. **Copiar el proyecto** a la raíz web del servidor (p. ej. `htdocs/`), de modo que el sistema quede accesible como `/nominas`.

2. **Instalar dependencias** dentro de `NOMINAS/`:
   ```bash
   composer install
   ```

3. **Crear la base de datos con el instalador**: abrir `http://localhost/nominas/InstalarBD.php`.
   El instalador comprueba los requisitos, crea la base de datos `transnubet_nomina` importando el
   esquema embebido y permite configurar los datos de la empresa, correo y credenciales de acceso.

4. **`NOMINAS/config.php` está excluido del repositorio** (contiene las credenciales de MySQL). El
   instalador lo crea y lo actualiza automáticamente con la conexión configurada. Si lo creas a mano:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'transnubet_nomina');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('BASE_URL', '/nominas');   // Ajustar si se instala en otra ruta
   ```

5. **Acceder** al sistema en `http://localhost/nominas` e iniciar sesión.

> Las migraciones de la tabla `clasif_usuarios` (recuperación de contraseña) y los parámetros de correo se aplican automáticamente al conectar.

## Usuario por defecto

El esquema incluye el usuario `admin` con contraseña por defecto `password`. Tras la primera instalación cambie la contraseña desde el módulo de Usuarios o el perfil.

## Notas

- Zona horaria configurada: `America/Havana`.
- Las tareas de respaldo/restauración de la BD se gestionan desde el panel de Configuración.
- En `NOMINAS/config/migraciones.php` se aplican migraciones idempotentes (columnas `reset_token`/`reset_expira`, parámetros de correo).
