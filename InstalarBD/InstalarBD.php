<?php
// ============================================================
// InstalarBD_SisgesNom.php
// ------------------------------------------------------------
// Instalador web del Sistema de Gestión de Nóminas
// Crea la base de datos, importa el esquema embebido y actualiza
// la configuración de conexión en config.php.
//
// Generado el 13-08-2025. Eliminar tras la instalación.
// ============================================================
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('America/Havana');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- Constantes del instalador ----------
define('INSTALLER_VERSION', '1.0.2');
define('SYS_NAME', 'Sistema de Gestión de Nóminas');
define('SYS_NAME_SHORT', 'SisGesNom');
define('SYS_DBNAME', 'SisGesNominas');
define('APP_DIR', dirname(__DIR__) . '/nominas');
define('SYS_CONFIG_FILE', APP_DIR . '/config.php');
define('INSTALLER_SELF', basename(__FILE__));
define('SYS_TABLES', 20);
// Registro de actividad en archivo externo junto al instalador
define('INSTALLER_LOG_FILE', __DIR__ . '/instalarBD.log');

// Ruta web base de la aplicación (ej: /nominas o /)
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

// ---------- Registro de actividad ----------
function logMessage(string $msg): void {
    $line = '[' . date('H:i:s') . '] ' . $msg;
    if (!isset($_SESSION['install_log'])) {
        $_SESSION['install_log'] = [];
    }
    $_SESSION['install_log'][] = $line;
    @file_put_contents(INSTALLER_LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function getLog(): string {
    $content = @file_get_contents(INSTALLER_LOG_FILE);
    if ($content !== false) {
        return rtrim($content, "\r\n");
    }
    return implode("\n", $_SESSION['install_log'] ?? []);
}

// Registro inicial de despliegue (una sola vez por sesión)
if (empty($_SESSION['deploy_started'])) {
    $_SESSION['deploy_started'] = 1;
    logMessage('Comenzando proceso de despliegue.');
}

// ---------- Esquema embebido de la base de datos ----------
$sqlSisGesNom = <<<'SQL'
-- phpMyAdmin SQL Dump
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Versión del servidor: 5.7.37
-- Versión de PHP: 8.1.7

-- Exportaciones Nóminas SisGesNom --
-- Salva SQL de las Bases de Datos del Sistema de Nóminas SisGesNom
-- Webmaster: Franklin Ramos Lamadrid (kaky°)®
-- Email: kakycu@gmail.com
-- Copyright © 2025 - 2026.
-- ------------------------------------------------------------------------------
-- phpMyAdmin Volcado de Datos SQL.
-- version cliente: mysqlnd 8.1.7
-- https://www.unicornio.com/sisgesnom/
-- https://nominas.unicornio.cu/
-- ------------------------------------------------------------------------------
-- Nombre del Servidor: localhost
-- Dirección del Servidor: 127.0.0.1
-- Tiempo de generación: 13-08-2026 a las 01:13:16 AM

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


--
-- Base de datos: `SisGesNom_nomina`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `codigo` int(11) NOT NULL,
  `nombre_area` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `codigo`, `nombre_area`, `descripcion`, `activo`, `created_at`) VALUES
(1, 100, 'Administración', 'Dirección y gestión administrativa', 1, '2026-05-09 03:21:27'),
(2, 200, 'Operaciones', 'Área de operaciones y producción', 1, '2026-05-09 03:21:27'),
(3, 300, 'Recursos Humanos', 'Gestión de personal y nómina', 1, '2026-05-09 03:21:27'),
(4, 400, 'Economía, Contabilidad y Finanzas', 'Contabilidad y finanzas', 1, '2026-05-09 03:21:27'),
(5, 500, 'Tecnología', 'Soporte técnico y sistemas', 1, '2026-05-09 03:21:27'),
(6, 600, 'Comercial', 'Ventas y comercialización', 1, '2026-05-09 03:21:27'),
(7, 700, 'Mantenimiento', 'Mantenimiento general', 1, '2026-05-09 03:21:27'),
(8, 800, 'Seguridad y Protección', 'Costos de Veladores y Serenos', 1, '2026-06-11 13:35:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos_plantilla`
--

CREATE TABLE `cargos_plantilla` (
  `id` int(11) NOT NULL,
  `organo_grupo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'DIRECCIÓN, TÉCNICO PRODUCTIVO, etc.',
  `nombre_cargo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_ocupacional_id` int(11) NOT NULL,
  `nivel_preparacion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NS, NMS, NM, etc.',
  `escala_salarial_id` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

--
-- Volcado de datos para la tabla `cargos_plantilla`
--

INSERT INTO `cargos_plantilla` (`id`, `organo_grupo`, `nombre_cargo`, `categoria_ocupacional_id`, `nivel_preparacion`, `escala_salarial_id`, `activo`, `created_at`) VALUES
(1, 'DIRECCIÓN', 'Jefe/Director de Proyecto', 4, 'NMS', 32, 1, '2026-06-01 04:28:49'),
(2, 'DIRECCIÓN', 'Asesor “B” Jurídico', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(3, 'DIRECCIÓN', 'Especialista “C” en Gestión Económica', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(4, 'DIRECCIÓN', 'Especialista “C” en Gestión Comercial.', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(5, 'DIRECCIÓN', 'Especialista “C” en Gestión de los Recursos Humanos', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(6, 'DIRECCIÓN', 'Especialista “C” en Gestión Documental', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(7, 'DIRECCIÓN', 'Especialista “C” en Ciencias Informáticas', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(8, 'DIRECCIÓN', 'Promotora Cultural', 2, 'NMS', 27, 1, '2026-06-01 04:28:49'),
(9, 'GRUPO TÉCNICO PRODUCTIVO', 'Especialista “A” en Obras de Arquitectura e Industriales', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(10, 'GRUPO TÉCNICO PRODUCTIVO', 'Especialista “A” en Obras de Ingeniería', 2, 'NS', 31, 1, '2026-06-01 04:28:49'),
(11, 'GRUPO TÉCNICO PRODUCTIVO', 'Especialista “C” de Proyectos de Ingeniería', 2, 'NS', 30, 1, '2026-06-01 04:28:49'),
(12, 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'Tecnólogo “B” de Mantenimiento Mecánico', 1, 'NMS', 28, 1, '2026-06-01 04:28:49'),
(13, 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'Operario General de Mantenimiento (Jefe Brigada)', 1, 'NMS', 27, 1, '2026-06-01 04:28:49'),
(14, 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'Operario General de Mantenimiento', 1, 'NMS C/HAB', 26, 1, '2026-06-01 04:28:49'),
(15, 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'Ayudante', 1, 'NM', 21, 1, '2026-06-01 04:28:49'),
(16, 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'Chofer “C”', 1, 'NM LIC.COND.', 27, 1, '2026-06-01 04:28:49'),
(17, 'INDIRECTOS A LA PRODUCCIÓN', 'Especialista “C” Gestión de la Calidad', 2, 'NS', 28, 1, '2026-06-01 04:28:49'),
(18, 'INDIRECTOS A LA PRODUCCIÓN', 'Balancista Distribuidor', 2, 'NMS', 28, 1, '2026-06-01 04:28:49'),
(19, 'INDIRECTOS A LA PRODUCCIÓN', 'Encargado de Almacén', 5, 'NMS', 27, 1, '2026-06-01 04:28:49'),
(20, 'INDIRECTOS A LA PRODUCCIÓN', 'Sereno', 5, 'NMS', 22, 1, '2026-06-01 04:28:49'),
(21, 'INDIRECTOS A LA PRODUCCIÓN', 'Dependiente', 5, 'NMS', 26, 1, '2026-06-01 04:28:49'),
(22, 'INDIRECTOS A LA PRODUCCIÓN', 'Elaborador', 5, 'NMS', 26, 1, '2026-06-01 04:28:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_ocupacionales`
--

CREATE TABLE `categorias_ocupacionales` (
  `id` int(11) NOT NULL,
  `codigo` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `factor_incidencia` decimal(5,4) DEFAULT '1.0000',
  `orden` int(11) DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_ocupacionales`
--

INSERT INTO `categorias_ocupacionales` (`id`, `codigo`, `nombre`, `descripcion`, `factor_incidencia`, `orden`, `activo`, `created_at`) VALUES
(1, 'O', 'Operario', 'Trabajador de producción y operaciones', '1.0000', 1, 1, '2026-05-09 03:21:27'),
(2, 'T', 'Técnico', 'Personal técnico especializado', '1.2000', 2, 1, '2026-05-09 03:21:27'),
(3, 'A', 'Administrativo', 'Personal administrativo y de oficina', '1.1500', 3, 1, '2026-05-09 03:21:27'),
(4, 'D', 'Directivo', 'Personal de dirección y gestión', '1.5000', 4, 1, '2026-05-09 03:21:27'),
(5, 'S', 'Servicios', 'Personal de servicios generales', '0.9000', 5, 1, '2026-05-09 03:21:27'),
(6, 'C', 'Cuadros', 'Personal con funciones de dirección y supervisión', '1.3500', 6, 1, '2026-05-09 03:21:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `centros_costo`
--

CREATE TABLE `centros_costo` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_nomina`
--

CREATE TABLE `cierres_nomina` (
  `id` int(11) NOT NULL,
  `periodo_desde` date NOT NULL,
  `periodo_hasta` date NOT NULL,
  `tipo_nomina` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'automatica',
  `numero_nomina` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cierre` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_cierre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_trabajadores` int(11) DEFAULT NULL,
  `total_devengado` decimal(12,2) DEFAULT NULL,
  `total_deducciones` decimal(12,2) DEFAULT NULL,
  `total_neto` decimal(12,2) DEFAULT NULL,
  `total_contribucion` decimal(12,2) DEFAULT NULL,
  `total_vacaciones_pagadas` decimal(12,2) DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('borrador','cerrado','pagado') COLLATE utf8mb4_unicode_ci DEFAULT 'cerrado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clasif_rol`
--

CREATE TABLE `clasif_rol` (
  `id` int(11) NOT NULL,
  `codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clasif_rol`
--

INSERT INTO `clasif_rol` (`id`, `codigo`, `descripcion`) VALUES
(1, 'Admin', 'Administrador del Sistema'),
(2, 'Visor', 'Visualizador'),
(3, 'Editor', 'Contador / Editor'),
(4, 'Super', 'Supervisor General'),
(5, 'Soft', 'Programador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clasif_usuarios`
--

CREATE TABLE `clasif_usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_ci` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion_particular` text COLLATE utf8mb4_unicode_ci,
  `telefono_contacto` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `rol_id` int(11) DEFAULT NULL,
  `foto` longtext COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clasif_usuarios`
--

INSERT INTO `clasif_usuarios` (`id`, `nombre`, `apellidos`, `no_ci`, `direccion_particular`, `telefono_contacto`, `fecha_registro`, `rol_id`, `foto`, `usuario`, `password`, `email`, `activo`, `fecha_actualizacion`, `reset_token`, `reset_expira`) VALUES
(1, 'Franklin', 'Ramos Lamadrid', '81103016525', 'A. Arango No. 137. Nuevitas, Camagüey', '+5359860773', '2020-11-28 19:48:46', 1, 'assets/imagenes/usuarios/user_1773321151_69b2bbbf55ff3.jpg', 'admin', '$2y$10$ylnrJw8ZEDOdjoFBuc1pNuFcpthaAaAv3MuIHcqsgFks09zb216NO', 'kakycu@gmail.com', 1, '2026-08-09 17:31:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_general`
--

CREATE TABLE `configuracion_general` (
  `id` int(11) NOT NULL,
  `parametro` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_dato` enum('entero','decimal','texto','fecha','booleano') COLLATE utf8mb4_unicode_ci DEFAULT 'texto',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_modificacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_modifico` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_general`
--

INSERT INTO `configuracion_general` (`id`, `parametro`, `valor`, `tipo_dato`, `descripcion`, `fecha_modificacion`, `usuario_modifico`) VALUES
(1, 'horas_mensuales', '192', 'entero', 'Cantidad de horas laborables por mes (24 días x 8 horas)', NULL, NULL),
(2, 'dias_mensuales', '24', 'entero', 'Cantidad de días laborables por mes', NULL, NULL),
(3, 'horas_jornada_diaria', '8', 'entero', 'Horas por jornada laboral diaria', NULL, NULL),
(4, 'tasa_contribucion_especial', '5', 'decimal', 'Porcentaje de contribución especial a la seguridad social', NULL, NULL),
(5, 'salario_minimo', '3100', 'decimal', 'Salario mínimo mensual según Gaceta Oficial', NULL, NULL),
(6, 'nombre_empresa', '', 'texto', 'Nombre de la entidad', NULL, NULL),
(7, 'direccion_empresa', '', 'texto', 'Dirección de la entidad', NULL, NULL),
(8, 'reeup_empresa', '', 'texto', 'Código REEUP de la empresa', NULL, NULL),
(9, 'jefe_proyecto', '', 'texto', 'Nombre del Jefe de Proyecto', NULL, NULL),
(10, 'especialista_gestion', '', 'texto', 'Nombre del Especialista en Gestión Económica', NULL, NULL),
(11, 'especialista_gestionRRHH', '', 'texto', 'Nombre del Especialista en Gestión de RRHH', NULL, NULL),
(12, 'recargo_nocturno', '1.25', 'decimal', 'Recargo Nocturnidad', NULL, NULL),
(13, 'intendente', '', 'texto', 'Nombre del Intendente que aprueba la plantilla', NULL, NULL),
(14, 'nit_empresa', '', 'texto', 'NIT de la empresa', NULL, NULL),
(15, 'mail_activo', '0', 'texto', NULL, NULL, NULL),
(16, 'mail_proveedor', 'custom', 'texto', NULL, NULL, NULL),
(17, 'mail_host', '', 'texto', NULL, NULL, NULL),
(18, 'mail_port', '587', 'texto', NULL, NULL, NULL),
(19, 'mail_encryption', 'tls', 'texto', NULL, NULL, NULL),
(20, 'mail_usuario', '', 'texto', NULL, NULL, NULL),
(21, 'mail_password', '', 'texto', NULL, NULL, NULL),
(22, 'mail_from', '', 'texto', NULL, NULL, NULL),
(23, 'mail_from_name', '', 'texto', NULL, NULL, NULL),
(24, 'slogan', '', 'texto', 'Eslogan de la entidad', NULL, NULL),
(25, 'telefono_empresa', '', 'texto', 'Teléfono de contacto de la entidad', NULL, NULL),
(26, 'email_empresa', '', 'texto', 'Correo de contacto de la entidad', NULL, NULL),
(27, 'tarifa_nocturnidad_temprana', '0.60', 'decimal', 'Tarifa fija Nt 7-23h ($/h) - Res. 15/2026 MTSS', NULL, NULL),
(28, 'tarifa_nocturnidad_tardia', '1.15', 'decimal', 'Tarifa fija Nt 23-7h ($/h) - Res. 15/2026 MTSS', NULL, NULL),
(29, 'telefono_soporte', '', 'texto', 'Telefono de soporte técnico', NULL, NULL),
(30, 'email_soporte', '', 'texto', 'Correo de contacto de soporte técnico', NULL, NULL),
(31, 'google_client_id', '', 'texto', 'Client ID de la app OAuth de Google para el login con Google', NULL, NULL),
(32, 'google_client_secret', '', 'texto', 'Client Secret de la app OAuth de Google para el login con Google', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_rangos_impuesto`
--

CREATE TABLE `configuracion_rangos_impuesto` (
  `id` int(11) NOT NULL,
  `desde` decimal(12,2) NOT NULL,
  `hasta` decimal(12,2) DEFAULT NULL,
  `tasa` decimal(5,4) NOT NULL,
  `monto_fijo` decimal(12,2) DEFAULT '0.00',
  `fecha_vigencia` date NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_rangos_impuesto`
--

INSERT INTO `configuracion_rangos_impuesto` (`id`, `desde`, `hasta`, `tasa`, `monto_fijo`, `fecha_vigencia`, `descripcion`) VALUES
(1, '0.00', '3260.00', '0.0000', '0.00', '2024-01-01', 'Rango Exento'),
(2, '3260.01', '9510.00', '0.0300', '0.00', '2024-01-01', '1er Rango'),
(3, '9510.01', '15000.00', '0.0500', '0.00', '2024-01-01', '2do Rango'),
(4, '15000.01', '20000.00', '0.0750', '0.00', '2024-01-01', '3er Rango'),
(5, '20000.01', '25000.00', '0.1000', '0.00', '2024-01-01', '4to Rango'),
(6, '25000.01', '30000.00', '0.1500', '0.00', '2024-01-01', '5to Rango'),
(7, '30000.01', NULL, '0.2000', '0.00', '2024-01-01', '6to Rango');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_tasas`
--

CREATE TABLE `configuracion_tasas` (
  `id` int(11) NOT NULL,
  `nombre_tasa` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fecha_vigencia` date NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_tasas`
--

INSERT INTO `configuracion_tasas` (`id`, `nombre_tasa`, `valor`, `fecha_vigencia`, `descripcion`) VALUES
(1, 'contribucion_especial', '5.00', '2024-01-01', 'Contribución especial a la seguridad social (5% sobre salario devengado)');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_vacaciones`
--

CREATE TABLE `configuracion_vacaciones` (
  `id` int(11) NOT NULL,
  `dias_por_mes` decimal(5,2) NOT NULL DEFAULT '2.18',
  `factor_calculo` decimal(5,4) NOT NULL DEFAULT '0.0909',
  `meses_requeridos` int(11) NOT NULL DEFAULT '11',
  `fecha_vigencia` date NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_vacaciones`
--

INSERT INTO `configuracion_vacaciones` (`id`, `dias_por_mes`, `factor_calculo`, `meses_requeridos`, `fecha_vigencia`, `activo`, `created_at`) VALUES
(1, '2.18', '0.0909', 11, '2024-01-01', 1, '2026-05-09 03:21:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escalas_salariales`
--

CREATE TABLE `escalas_salariales` (
  `id` int(11) NOT NULL,
  `escala_numero` int(11) NOT NULL,
  `salario_mensual` decimal(12,2) NOT NULL,
  `salario_hora_ordinaria` decimal(10,4) GENERATED ALWAYS AS ((`salario_mensual` / 192)) STORED,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_vigencia` date NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `escalas_salariales`
--

INSERT INTO `escalas_salariales` (`id`, `escala_numero`, `salario_mensual`, `descripcion`, `fecha_vigencia`, `activo`, `created_at`) VALUES
(1, 1, '2100.00', 'I', '2024-01-01', 1, '2026-05-09 03:21:27'),
(2, 2, '2200.00', 'II', '2024-01-01', 1, '2026-05-09 03:21:27'),
(3, 3, '2300.00', 'III', '2024-01-01', 1, '2026-05-09 03:21:27'),
(4, 4, '2420.00', 'IV', '2024-01-01', 1, '2026-05-09 03:21:27'),
(5, 5, '2540.00', 'V', '2024-01-01', 1, '2026-05-09 03:21:27'),
(6, 6, '2660.00', 'VI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(7, 7, '2810.00', 'VII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(8, 8, '2960.00', 'VIII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(9, 9, '3110.00', 'IX', '2024-01-01', 1, '2026-05-09 03:21:27'),
(10, 10, '3260.00', 'X', '2024-01-01', 1, '2026-05-09 03:21:27'),
(11, 11, '3410.00', 'XI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(12, 12, '3610.00', 'XII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(13, 13, '3810.00', 'XIII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(14, 14, '4010.00', 'XIV', '2024-01-01', 1, '2026-05-09 03:21:27'),
(15, 15, '4210.00', 'XV', '2024-01-01', 1, '2026-05-09 03:21:27'),
(16, 16, '4410.00', 'XVI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(17, 17, '4610.00', 'XVII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(18, 18, '4810.00', 'XVIII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(19, 19, '5060.00', 'XIX', '2024-01-01', 1, '2026-05-09 03:21:27'),
(20, 20, '5310.00', 'XX', '2024-01-01', 1, '2026-05-09 03:21:27'),
(21, 21, '5560.00', 'XXI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(22, 22, '5810.00', 'XXII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(23, 23, '6060.00', 'XXIII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(24, 24, '6310.00', 'XXIV', '2024-01-01', 1, '2026-05-09 03:21:27'),
(25, 25, '6610.00', 'XXV', '2024-01-01', 1, '2026-05-09 03:21:27'),
(26, 26, '6960.00', 'XXVI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(27, 27, '7310.00', 'XXVII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(28, 28, '7660.00', 'XXVIII', '2024-01-01', 1, '2026-05-09 03:21:27'),
(29, 29, '8010.00', 'XXIX', '2024-01-01', 1, '2026-05-09 03:21:27'),
(30, 30, '8510.00', 'XXX', '2024-01-01', 1, '2026-05-09 03:21:27'),
(31, 31, '9010.00', 'XXXI', '2024-01-01', 1, '2026-05-09 03:21:27'),
(32, 32, '9510.00', 'XXXII', '2024-01-01', 1, '2026-05-09 03:21:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_salarios`
--

CREATE TABLE `historial_salarios` (
  `id` int(11) NOT NULL,
  `trabajador_id` int(11) NOT NULL,
  `escala_anterior_id` int(11) DEFAULT NULL,
  `escala_nueva_id` int(11) DEFAULT NULL,
  `fecha_cambio` date NOT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_registra` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `montos_distrib`
--

CREATE TABLE `montos_distrib` (
  `id` int(11) NOT NULL,
  `mes` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `anio` int(11) NOT NULL,
  `importe_dis` decimal(12,2) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `motivos_baja`
--

CREATE TABLE `motivos_baja` (
  `id` int(11) NOT NULL,
  `codigo` int(11) NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `base_legal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `motivos_baja`
--

INSERT INTO `motivos_baja` (`id`, `codigo`, `categoria`, `nombre`, `descripcion`, `base_legal`, `activo`) VALUES
(1, 1, 'Iniciativa del Trabajador', 'Renuncia voluntaria (aviso previo cumplido)', 'El trabajador decide terminar la relación laboral por decisión propia, cumpliendo el plazo de aviso previo establecido (hasta 30 días hábiles para contratos indeterminados o 4 meses para cargos técnicos de nivel superior) [citation:1][citation:6]', 'Artículo 45 Ley 116', 1),
(2, 2, 'Iniciativa del Trabajador', 'Renuncia sin aviso previo (abandono)', 'El trabajador abandona sus labores sin cumplir el término de aviso previo. Se considera ausencia injustificada y violación de la disciplina laboral [citation:6]', 'Artículo 45 Ley 116', 1),
(3, 3, 'Iniciativa del Trabajador', 'Traslado por cambio de residencia', 'Traslado del trabajador a otra provincia o municipio por cambio de domicilio particular que imposibilita continuar la relación laboral', 'Jurisprudencia', 1),
(4, 4, 'Iniciativa del Trabajador', 'Motivos personales o familiares', 'Renuncia por atender situaciones personales o familiares que requieren tiempo completo (cuidado de familiar enfermo, etc.)', 'Artículo 54 Ley 116', 1),
(5, 5, 'Iniciativa del Trabajador', 'Mejor oferta laboral (sector no estatal)', 'El trabajador renuncia para incorporarse a una MIPYME, al TCP (trabajo por cuenta propia) o a otra entidad con mejores condiciones', 'Artículo 45 Ley 116', 1),
(6, 6, 'Iniciativa del Trabajador', 'Cese del Trabajo por Cuenta Propia', 'El trabajador se dedica de forma permanente al TCP y solicita la baja de su empleo asalariado', 'Ley 116', 1),
(7, 7, 'Iniciativa del Trabajador', 'Incompatibilidad de horarios', 'Imposibilidad de cumplir la jornada laboral por estudios, segundo empleo u otras actividades incompatibles', 'Jurisprudencia', 1),
(8, 8, 'Iniciativa del Trabajador', 'Rechazo de reubicación laboral', 'El trabajador declinó injustificadamente la oferta de empleo propuesta dentro o fuera de la entidad, acorde a sus capacidades [citation:6]', 'Artículo 49 inciso c Ley 116', 1),
(9, 9, 'Iniciativa del Trabajador', 'No reincorporación tras licencia', 'El trabajador no se reintegra al vencimiento de la licencia no retribuida concedida por el empleador [citation:6]', 'Artículo 49 inciso g Ley 116', 1),
(10, 10, 'Iniciativa del Trabajador', 'Disconformidad con condiciones de trabajo', 'Renuncia fundamentada en cambios sustanciales de las condiciones laborales no aceptados por el trabajador (traslado, cambio de funciones, etc.)', 'Ley 116', 1),
(11, 11, 'Iniciativa del Empleador', 'Pérdida de idoneidad demostrada', 'El trabajador pierde las capacidades o competencias necesarias para desempeñar su cargo, debidamente demostrada mediante evaluación [citation:6]', 'Artículo 49 inciso a Ley 116', 1),
(12, 12, 'Iniciativa del Empleador', 'Disponibilidad por reestructuración', 'Trabajador declarado disponible por reestructuración, reorganización o cambios tecnológicos, sin posibilidad de reubicación definitiva en la entidad [citation:6][citation:8]', 'Artículo 49 inciso b Ley 116', 1),
(13, 13, 'Iniciativa del Empleador', 'Falta grave o violación de la disciplina', 'Separación definitiva por inobservancia grave de las normas de disciplina establecidas en la legislación o reglamentos internos [citation:6]', 'Artículo 49 inciso d Ley 116', 1),
(14, 14, 'Iniciativa del Empleador', 'Despido por ausencias injustificadas', 'Acumulación de ausencias reiteradas sin justificación que afectan el proceso productivo', 'Artículo 60 Ley 116', 1),
(15, 15, 'Iniciativa del Empleador', 'Despido por incumplimiento reiterado', 'Incumplimiento reiterado de las obligaciones laborales sin justa causa', 'Artículo 60 Ley 116', 1),
(16, 16, 'Iniciativa del Empleador', 'Despido por comisión de delito', 'Comisión de delito en conexión con el trabajo, debidamente acreditado por sentencia firme o investigación', 'Artículo 60 Ley 116', 1),
(17, 17, 'Iniciativa del Empleador', 'Despido por conducta inmoral', 'Conducta inmoral grave que afecta al colectivo laboral o al prestigio de la entidad', 'Artículo 60 Ley 116', 1),
(18, 18, 'Iniciativa del Empleador', 'Revelación de secretos o información confidencial', 'Revelación intencional de secretos, información confidencial o datos protegidos de la entidad', 'Artículo 60 Ley 116', 1),
(19, 19, 'Iniciativa del Empleador', 'Despido improcedente (injustificado)', 'El empleador termina la relación sin causa legal válida o sin seguir el procedimiento establecido (esto genera derecho a indemnización para el trabajador) [citation:8]', 'Artículo 61 Ley 116', 1),
(20, 20, 'Iniciativa del Empleador', 'Por no superar el período de prueba', 'El trabajador no demuestra las capacidades requeridas durante el período de prueba establecido en el contrato', 'Ley 116', 1),
(21, 21, 'Iniciativa del Empleador', 'Pérdida de requisitos para el cargo', 'El trabajador pierde algún requisito indispensable para el cargo (licencia, certificación, titulación, etc.)', 'Ley 116', 1),
(22, 22, 'Iniciativa del Empleador', 'No reincorporación tras licencia de maternidad', 'La trabajadora o trabajador no se reintegra al vencimiento de la licencia de maternidad o prestación social [citation:6]', 'Artículo 49 inciso e Ley 116', 1),
(23, 23, 'Iniciativa del Empleador', 'Invalidez parcial sin reubicación', 'Trabajador declarado con invalidez parcial que no acepta, sin causa justificada, la oferta de empleo acorde a su capacidad [citation:6]', 'Artículo 49 inciso c Ley 116', 1),
(24, 36, 'Causas de Salud', 'Jubilación por edad', 'Jubilación ordinaria por cumplir la edad legal establecida (60 años para hombres, 55 para mujeres, según corresponda) [citation:1][citation:6]', 'Artículo 45 Ley 116', 1),
(25, 37, 'Causas de Salud', 'Jubilación por años de servicio', 'Jubilación anticipada por cumplir los años de servicio requeridos en actividades especiales (docencia, salud, etc.)', 'Ley de Seguridad Social', 1),
(26, 38, 'Causas de Salud', 'Incapacidad permanente total', 'Incapacidad permanente total para el trabajo, certificada por el Consejo de Valoración de la Seguridad Social', 'Ley de Seguridad Social', 1),
(27, 39, 'Causas de Salud', 'Invalidez permanente parcial con desempleo', 'Invalidez parcial que impide la reubicación laboral acorde a la capacidad residual del trabajador', 'Artículo 49 Ley 116', 1),
(28, 40, 'Causas de Salud', 'Enfermedad prolongada (sin posibilidad de reintegro)', 'Enfermedad de larga duración que supera los plazos de incapacidad temporal sin expectativas de reintegro laboral', 'Ley de Seguridad Social', 1),
(29, 41, 'Causas de Salud', 'Fallecimiento del trabajador', 'Muerte del trabajador. Los derechos laborales pendientes se transmiten a sus familiares con derecho a pensión o herederos [citation:1]', 'Artículo 45 Ley 116', 1),
(30, 42, 'Causas de Salud', 'Accidente laboral con incapacidad', 'Accidente de trabajo que provoca una incapacidad permanente que impide la continuidad laboral', 'Ley de Seguridad Social', 1),
(31, 46, 'Sanciones Penales', 'Privación de libertad mayor a 6 meses', 'Trabajador condenado a sanción de privación de libertad por sentencia firme que excede de seis meses, si el empleador decide la terminación [citation:6]', 'Artículo 49 inciso f Ley 116', 1),
(32, 47, 'Sanciones Penales', 'Privación de libertad menor a 6 meses', 'Trabajador condenado a privación de libertad por menos de seis meses (contrato indeterminado); la relación se suspende y puede reintegrarse al cumplir la condena [citation:1]', 'Artículo 56 Ley 116', 1),
(33, 48, 'Sanciones Penales', 'Prisión provisional (proceso penal)', 'Trabajador en prisión provisional por proceso penal ajeno al trabajo; la relación laboral se suspende. Si es absuelto, tiene derecho a reintegro y salarios caídos [citation:1]', 'Anteproyecto Código de Trabajo', 1),
(34, 49, 'Sanciones Penales', 'Pérdida de derechos políticos', 'Trabajador inhabilitado para el ejercicio de funciones públicas o políticas por sentencia judicial firme', 'Ley 116', 1),
(35, 56, 'Causas Forzosas', 'Extinción de la entidad', 'La entidad empleadora se extingue, disuelve o cierra, sin que exista otra que se subrogue en su lugar [citation:6]', 'Artículo 45 Ley 116', 1),
(36, 57, 'Causas Forzosas', 'Falta de combustible o energía sostenida', 'Paralización prolongada de actividades por crisis energética sin posibilidad de garantizar la reubicación o el salario [citation:7]', 'Artículo 57 Ley 116', 1),
(37, 58, 'Causas Forzosas', 'Desastre natural o sanitario', 'Fenómenos naturales, epidemias o situaciones sanitarias que imposibilitan la continuidad de la operación [citation:1][citation:7]', 'Artículo 57 Ley 116', 1),
(38, 59, 'Causas Forzosas', 'Falta de materiales o piezas sostenida', 'Paralización prolongada por rotura de equipos o falta de piezas de repuesto, materias primas o materiales esenciales [citation:7]', 'Artículo 57 Ley 116', 1),
(39, 60, 'Causas Forzosas', 'Vencimiento de contrato por tiempo determinado', 'Finalización del período pactado en contrato por tiempo determinado o conclusión de la obra o servicio contratado [citation:6]', 'Artículo 45 Ley 116', 1),
(40, 61, 'Causas Forzosas', 'Cumplimiento del Servicio Militar Activo', 'Trabajador llamado al Servicio Militar Activo (SMA) por el tiempo requerido. La relación laboral se suspende con derecho a reintegro [citation:1]', 'Artículo 56 Ley 116', 1),
(41, 62, 'Causas Forzosas', 'Movilización por defensa o seguridad nacional', 'Movilización por interés de la defensa y seguridad nacional, con derecho a reintegro al cese de la movilización [citation:1]', 'Artículo 56 Ley 116', 1),
(42, 63, 'Causas Forzosas', 'Desempeño de cargo público electo', 'Trabajador electo para desempeñar cargos en órganos del Poder Popular, organizaciones políticas o de masas a tiempo completo [citation:1]', 'Artículo 56 Ley 116', 1),
(43, 71, 'Acuerdo entre Partes', 'Mutuo acuerdo con indemnización', 'Terminación de común acuerdo entre empleador y trabajador, estableciéndose una compensación económica pactada', 'Artículo 45 Ley 116', 1),
(44, 72, 'Acuerdo entre Partes', 'Sustitución de contrato', 'Terminación del contrato actual para suscribir una nueva modalidad contractual (ej: de temporal a indeterminado)', 'Ley 116', 1),
(45, 73, 'Acuerdo entre Partes', 'Traslado a otra entidad (movimiento de personal)', 'Traslado por acuerdo entre entidades del sector estatal, con subrogación de derechos', 'Ley 116', 1),
(46, 74, 'Acuerdo entre Partes', 'Mutuo acuerdo sin indemnización', 'Terminación de mutuo acuerdo sin contraprestación económica, generalmente por mutuo beneficio (ej: trabajador desea dedicarse al TCP)', 'Ley 116', 1),
(47, 81, 'Situaciones Especiales', 'Terminación por maternidad sin reintegro', 'La trabajadora no se reintegra al vencimiento de la licencia de maternidad o prestación social, sin causa justificada [citation:6]', 'Artículo 49 inciso e Ley 116', 1),
(48, 82, 'Situaciones Especiales', 'Expatriación o misión internacional', 'Trabajador seleccionado para misión internacional (colaboración, contrato en el exterior) con término del contrato doméstico', 'Ley 116', 1),
(49, 83, 'Situaciones Especiales', 'Traslado definitivo por necesidad del servicio', 'Traslado definitivo del trabajador a otra provincia o municipio por necesidades del servicio (sector educación, salud, etc.)', 'Ley 116', 1),
(50, 84, 'Situaciones Especiales', 'Excedencia sin derecho a reingreso', 'Trabajador que toma excedencia por estudios o cuidado de familiar y no solicita el reingreso en el plazo establecido', 'Ley 116', 1),
(51, 85, 'Situaciones Especiales', 'Despido por represalia sindical (improcedente)', 'Despido motivado por actividad sindical o reclamación del trabajador. Genera derecho a reincorporación o indemnización agravada [citation:8]', 'Ley 116', 1),
(52, 91, 'Otros', 'Error administrativo (alta duplicada o incorrecta)', 'Registro dado de baja por corrección administrativa. No implica cese real', 'Procedimiento administrativo', 1),
(53, 92, 'Otros', 'Pase a otra forma de gestión no laboral', 'Trabajador que pasa a ser socio de cooperativa, TCP sin relación laboral de dependencia, u otra figura no asalariada', 'Ley 116', 1),
(54, 93, 'Otros', 'Cierre de MIPYME o TCP empleador', 'El empleador (MIPYME o TCP) cierra su negocio y no puede continuar la relación laboral', 'Ley 116', 1),
(55, 94, 'Otros', 'Imposibilidad de garantizar salario mínimo', 'El empleador no puede garantizar el pago del salario mínimo establecido por Gaceta Oficial por causas económicas justificadas', 'Ley 116', 1),
(56, 95, 'Otros', 'No se presentó a tomar posesión', 'Trabajador seleccionado que no se presentó a tomar posesión del cargo en la fecha indicada', 'Ley 116', 1),
(57, 96, 'Otros', 'Caducidad de la oferta de empleo', 'La oferta de empleo aceptada caduca por falta de presentación de documentos o requisitos en el plazo establecido', 'Ley 116', 1),
(58, 97, 'Otros', 'Otros Motivos no Especificados', 'Otros Motivos no Especificados', '--', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nominas`
--

CREATE TABLE `nominas` (
  `id` int(11) NOT NULL,
  `trabajador_id` int(11) NOT NULL,
  `periodo_desde` date NOT NULL,
  `periodo_hasta` date NOT NULL,
  `horas_laboradas` int(11) DEFAULT '192',
  `horas_nocturnas` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe_horas_nocturnas` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dias_feriados` decimal(5,2) NOT NULL DEFAULT '0.00',
  `importe_dias_feriados` decimal(12,2) NOT NULL DEFAULT '0.00',
  `horas_extras` int(11) DEFAULT '0',
  `horas_extras_dobles` int(11) DEFAULT '0',
  `importe_salario_laboral` decimal(12,2) DEFAULT '0.00',
  `importe_dias_feriado` decimal(12,2) DEFAULT '0.00',
  `pago_resultado` decimal(12,2) DEFAULT '0.00',
  `otros_salarios` decimal(12,2) DEFAULT '0.00',
  `total_salario_devengado` decimal(12,2) DEFAULT '0.00',
  `descuentos` decimal(12,2) DEFAULT '0.00',
  `contribucion_especial` decimal(12,2) DEFAULT '0.00',
  `ingresos_personales` decimal(12,2) DEFAULT '0.00',
  `otras_deducciones` decimal(12,2) DEFAULT '0.00',
  `total_deducciones` decimal(12,2) DEFAULT '0.00',
  `dias_vacaciones_tomados` decimal(5,2) DEFAULT '0.00',
  `dias_restantes` decimal(10,2) DEFAULT '0.00',
  `vacaciones_acumuladas_mes` decimal(10,2) DEFAULT '0.00',
  `importe_vacaciones_acumulado_mes` decimal(12,2) DEFAULT '0.00',
  `total_vacaciones_acumuladas` decimal(10,2) DEFAULT '0.00',
  `total_importe_vacaciones_acumuladas` decimal(12,2) DEFAULT '0.00',
  `importe_vacaciones` decimal(12,2) DEFAULT '0.00',
  `importe_neto` decimal(12,2) DEFAULT '0.00',
  `fecha_contab` date DEFAULT NULL,
  `tipo_nomina` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'automatica',
  `numero_nomina` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('borrador','procesado','pagado','cerrado','contabilizado') COLLATE utf8mb4_unicode_ci DEFAULT 'borrador',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_descuento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'total_rangos',
  `horas_nocturnas_tempranas` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe_nocturnas_tempranas` decimal(12,2) NOT NULL DEFAULT '0.00',
  `horas_nocturnas_tardias` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe_nocturnas_tardias` decimal(12,2) NOT NULL DEFAULT '0.00',
  `horas_doble_turno` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe_doble_turno` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secuencias_nominas`
--

CREATE TABLE `secuencias_nominas` (
  `id` int(11) NOT NULL,
  `tipo_nomina` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ultimo_numero` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `submayor_vacaciones`
--

CREATE TABLE `submayor_vacaciones` (
  `id` int(11) NOT NULL,
  `trabajador_id` int(11) NOT NULL,
  `periodo_desde` date NOT NULL,
  `periodo_hasta` date NOT NULL,
  `tipo_movimiento` enum('acumulacion','disfrute','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias` decimal(10,2) NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `nomina_id` int(11) DEFAULT NULL,
  `tipo_nomina` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_registro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subsistemas`
--

CREATE TABLE `subsistemas` (
  `id` int(11) NOT NULL,
  `codigo` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Activo, 0=Inactivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `subsistemas`
--

INSERT INTO `subsistemas` (`id`, `codigo`, `nombre`, `estado`) VALUES
(1, '0001', 'Nóminas', 1),
(2, '0002', 'Facturación', 0),
(3, '0003', 'Costos', 0),
(4, '0004', 'Inventarios', 0),
(5, '0005', 'Administración', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajadores`
--

CREATE TABLE `trabajadores` (
  `id` int(11) NOT NULL,
  `codigo` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primer_apellido` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `segundo_apellido` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_completo` varchar(150) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`nombres`,' ',`primer_apellido`,' ',ifnull(`segundo_apellido`,''))) STORED,
  `direccion_particular` text COLLATE utf8mb4_unicode_ci,
  `telefono_contacto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `centro_costo_id` int(11) DEFAULT NULL,
  `categoria_ocupacional_id` int(11) DEFAULT NULL,
  `cargo_id` int(11) DEFAULT NULL,
  `escala_salarial_id` int(11) DEFAULT NULL,
  `fecha_alta` date NOT NULL,
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` int(11) DEFAULT NULL,
  `vacaciones_acumuladas` decimal(10,2) DEFAULT '0.00',
  `tipo_contrato` enum('Determinado','Indeterminado','A Prueba') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Indeterminado',
  `activo` tinyint(1) DEFAULT '1',
  `no_acumular_vacaciones` tinyint(1) DEFAULT '0',
  `foto_ruta` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuentabanc` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

--
-- Índices para tablas volcadas
--

--
-- Índices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de la tabla `cargos_plantilla`
--
ALTER TABLE `cargos_plantilla`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cargos_categoria` (`categoria_ocupacional_id`),
  ADD KEY `fk_cargos_escala` (`escala_salarial_id`);

--
-- Índices de la tabla `categorias_ocupacionales`
--
ALTER TABLE `categorias_ocupacionales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de la tabla `centros_costo`
--
ALTER TABLE `centros_costo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD UNIQUE KEY `codigo_2` (`codigo`);

--
-- Índices de la tabla `cierres_nomina`
--
ALTER TABLE `cierres_nomina`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_periodo_tipo_lote` (`periodo_desde`,`periodo_hasta`,`tipo_nomina`,`numero_nomina`),
  ADD KEY `idx_cierre_tipo` (`tipo_nomina`);

--
-- Índices de la tabla `clasif_rol`
--
ALTER TABLE `clasif_rol`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de la tabla `clasif_usuarios`
--
ALTER TABLE `clasif_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_ci` (`no_ci`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Índices de la tabla `configuracion_general`
--
ALTER TABLE `configuracion_general`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parametro` (`parametro`);

--
-- Índices de la tabla `configuracion_rangos_impuesto`
--
ALTER TABLE `configuracion_rangos_impuesto`
  ADD PRIMARY KEY (`id`);

--
-- Índices de la tabla `configuracion_tasas`
--
ALTER TABLE `configuracion_tasas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tasa_fecha` (`nombre_tasa`,`fecha_vigencia`);

--
-- Índices de la tabla `configuracion_vacaciones`
--
ALTER TABLE `configuracion_vacaciones`
  ADD PRIMARY KEY (`id`);

--
-- Índices de la tabla `escalas_salariales`
--
ALTER TABLE `escalas_salariales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `escala_numero` (`escala_numero`);

--
-- Índices de la tabla `historial_salarios`
--
ALTER TABLE `historial_salarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trabajador_id` (`trabajador_id`),
  ADD KEY `escala_anterior_id` (`escala_anterior_id`),
  ADD KEY `escala_nueva_id` (`escala_nueva_id`);

--
-- Índices de la tabla `montos_distrib`
--
ALTER TABLE `montos_distrib`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mes_anio` (`mes`,`anio`);

--
-- Índices de la tabla `motivos_baja`
--
ALTER TABLE `motivos_baja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de la tabla `nominas`
--
ALTER TABLE `nominas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_por_tipo_periodo` (`trabajador_id`,`periodo_desde`,`periodo_hasta`,`tipo_nomina`,`numero_nomina`);

--
-- Índices de la tabla `secuencias_nominas`
--
ALTER TABLE `secuencias_nominas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_nomina` (`tipo_nomina`);

--
-- Índices de la tabla `submayor_vacaciones`
--
ALTER TABLE `submayor_vacaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trabajador` (`trabajador_id`),
  ADD KEY `idx_periodo` (`periodo_desde`,`periodo_hasta`),
  ADD KEY `idx_nomina` (`nomina_id`);

--
-- Índices de la tabla `subsistemas`
--
ALTER TABLE `subsistemas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `categoria_ocupacional_id` (`categoria_ocupacional_id`),
  ADD KEY `escala_salarial_id` (`escala_salarial_id`),
  ADD KEY `fk_trabajadores_motivo_baja` (`motivo_baja`),
  ADD KEY `centro_costo_id` (`centro_costo_id`),
  ADD KEY `fk_trabajadores_cargo_plantilla` (`cargo_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `cargos_plantilla`
--
ALTER TABLE `cargos_plantilla`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `categorias_ocupacionales`
--
ALTER TABLE `categorias_ocupacionales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `centros_costo`
--
ALTER TABLE `centros_costo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `cierres_nomina`
--
ALTER TABLE `cierres_nomina`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `clasif_rol`
--
ALTER TABLE `clasif_rol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `clasif_usuarios`
--
ALTER TABLE `clasif_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `configuracion_general`
--
ALTER TABLE `configuracion_general`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `configuracion_rangos_impuesto`
--
ALTER TABLE `configuracion_rangos_impuesto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `configuracion_tasas`
--
ALTER TABLE `configuracion_tasas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `configuracion_vacaciones`
--
ALTER TABLE `configuracion_vacaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `escalas_salariales`
--
ALTER TABLE `escalas_salariales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `historial_salarios`
--
ALTER TABLE `historial_salarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `montos_distrib`
--
ALTER TABLE `montos_distrib`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `motivos_baja`
--
ALTER TABLE `motivos_baja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de la tabla `nominas`
--
ALTER TABLE `nominas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `secuencias_nominas`
--
ALTER TABLE `secuencias_nominas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `submayor_vacaciones`
--
ALTER TABLE `submayor_vacaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `subsistemas`
--
ALTER TABLE `subsistemas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cargos_plantilla`
--
ALTER TABLE `cargos_plantilla`
  ADD CONSTRAINT `fk_cargos_categoria` FOREIGN KEY (`categoria_ocupacional_id`) REFERENCES `categorias_ocupacionales` (`id`),
  ADD CONSTRAINT `fk_cargos_escala` FOREIGN KEY (`escala_salarial_id`) REFERENCES `escalas_salariales` (`id`);

--
-- Filtros para la tabla `clasif_usuarios`
--
ALTER TABLE `clasif_usuarios`
  ADD CONSTRAINT `clasif_usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `clasif_rol` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_salarios`
--
ALTER TABLE `historial_salarios`
  ADD CONSTRAINT `historial_salarios_ibfk_1` FOREIGN KEY (`trabajador_id`) REFERENCES `trabajadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_salarios_ibfk_2` FOREIGN KEY (`escala_anterior_id`) REFERENCES `escalas_salariales` (`id`),
  ADD CONSTRAINT `historial_salarios_ibfk_3` FOREIGN KEY (`escala_nueva_id`) REFERENCES `escalas_salariales` (`id`);

--
-- Filtros para la tabla `submayor_vacaciones`
--
ALTER TABLE `submayor_vacaciones`
  ADD CONSTRAINT `fk_submayor_trabajador` FOREIGN KEY (`trabajador_id`) REFERENCES `trabajadores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  ADD CONSTRAINT `fk_trabajadores_cargo_plantilla` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_plantilla` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trabajadores_motivo_baja` FOREIGN KEY (`motivo_baja`) REFERENCES `motivos_baja` (`codigo`),
  ADD CONSTRAINT `trabajadores_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trabajadores_ibfk_2` FOREIGN KEY (`categoria_ocupacional_id`) REFERENCES `categorias_ocupacionales` (`id`),
  ADD CONSTRAINT `trabajadores_ibfk_3` FOREIGN KEY (`escala_salarial_id`) REFERENCES `escalas_salariales` (`id`),
  ADD CONSTRAINT `trabajadores_ibfk_centro_costo` FOREIGN KEY (`centro_costo_id`) REFERENCES `centros_costo` (`id`) ON DELETE SET NULL;

COMMIT;


SQL;

// ---------- Utilidades ----------
function jsonResponse(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $msg): void {
    logMessage('ERROR: ' . $msg);
    jsonResponse(['success' => false, 'message' => $msg, 'log' => getLog()]);
}

function mysqlErrorEs($error): string {
    $error = (string)$error;
    $map = [
        'Access denied.*using password: YES'    => 'Contraseña incorrecta de la base de datos.',
        'Access denied'                         => 'Acceso denegado para el usuario indicado (compruebe usuario y contraseña).',
        'Unknown database'                      => 'La base de datos especificada no existe.',
        'Can\'t connect'                        => 'No se pudo conectar al servidor MySQL (¿está en ejecución? ¿el host/puerto son correctos?).',
        'Connection refused'                    => 'Conexión rechazada por el servidor MySQL (host o puerto incorrectos).',
        'Connection timed out'                  => 'Tiempo de espera de conexión agotado (revise host y puerto).',
        'Unknown host'                          => 'Host de MySQL desconocido o inaccesible.',
        'Table .* doesn\'t exist'               => 'Una de las tablas del esquema no existe.',
        'Duplicate entry'                       => 'Entrada duplicada (dato repetido en una tabla).',
        'Table .* already exists'               => 'Una de las tablas ya existe.',
        'permission denied'                     => 'Permiso denegado en MySQL.',
        'Cannot drop database'                  => 'No se pudo eliminar la base de datos (compruebe permisos).',
    ];
    foreach ($map as $pat => $esp) {
        if (preg_match('~' . $pat . '~i', $error)) {
            return $esp;
        }
    }
    return $error;
}

function connectDb(string $host, string $user, string $pass, int $port = 3306, ?string $dbname = null): mysqli {
    $m = @new mysqli($host, (string)$user, (string)$pass, $dbname, $port);
    if ($m->connect_errno) {
        $detalle = mysqlErrorEs($m->connect_error);
        throw new RuntimeException('No se pudo conectar a MySQL: ' . $detalle);
    }
    $m->set_charset('utf8mb4');
    return $m;
}

/**
 * Devuelve el nombre del motor (MariaDB o MySQL) y su versión legible.
 */
function dbServerName(mysqli $m): string {
    $info = (string)$m->server_info;
    $ver = '';
    try {
        $r = $m->query('SELECT VERSION() AS v');
        if ($r && $row = $r->fetch_assoc()) { $ver = (string)$row['v']; }
    } catch (\Throwable $e) {}
    if ($ver === '') { $ver = $info; }
    if (stripos($info, 'MariaDB') !== false || stripos($ver, 'MariaDB') !== false) {
        return 'MariaDB ' . trim(preg_replace('/^5\.5\.5-/', '', $ver));
    }
    return 'MySQL ' . trim($ver);
}

/**
 * Devuelve la versión de phpMyAdmin si está instalado, o '' si no se encuentra.
 */
function phpMyAdminVersion(): string {
    $candidates = [
        __DIR__ . '/../../phpmyadmin',
        __DIR__ . '/../phpmyadmin',
        __DIR__ . '/phpmyadmin',
        dirname($_SERVER['SCRIPT_FILENAME'] ?? __DIR__) . '/../../phpmyadmin',
    ];
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($docRoot !== '') {
        $candidates[] = dirname($docRoot) . '/phpmyadmin';
        $candidates[] = $docRoot . '/phpmyadmin';
    }
    foreach ($candidates as $dir) {
        $versionFile = rtrim($dir, '/\\') . '/libraries/classes/Version.php';
        if (!is_file($versionFile)) { continue; }
        $content = @file_get_contents($versionFile);
        if ($content === false) { continue; }
        if (preg_match('/public const VERSION\s*=\s*\'([^\']+)\'\s*\.\s*VERSION_SUFFIX;/', $content, $mm)) {
            return $mm[1];
        }
        if (preg_match('/public const VERSION\s*=\s*\'([^\']+)\';/', $content, $mm)) {
            return $mm[1];
        }
    }
    return '';
}

function credsFromSessionOrPost(array $post): array {
    $s = $_SESSION['db_creds'] ?? [];
    return [
        'host'   => $post['db_host'] ?? $s['host'] ?? 'localhost',
        'port'   => (int)($post['db_port'] ?? $s['port'] ?? 3306),
        'user'   => $post['db_user'] ?? $s['user'] ?? 'root',
        'pass'   => (string)($post['db_pass'] ?? $s['pass'] ?? ''),
        'dbname' => $post['db_name'] ?? $s['dbname'] ?? SYS_DBNAME,
    ];
}

/**
 * Inserta o actualiza un parámetro en configuracion_general.
 */
function setConfigParam(mysqli $m, string $parametro, string $valor, string $tipo = 'texto'): void {
    $sel = $m->prepare("SELECT id FROM configuracion_general WHERE parametro = ?");
    $sel->bind_param('s', $parametro);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows > 0) {
        $upd = $m->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
        $upd->bind_param('ss', $valor, $parametro);
        $upd->execute();
    } else {
        $upd2 = $m->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion) VALUES (?, ?, ?, ?)");
        $desc = ucwords(str_replace('_', ' ', $parametro));
        $upd2->bind_param('ssss', $parametro, $valor, $tipo, $desc);
        $upd2->execute();
    }
}

/**
 * Actualiza las constantes de conexión en config.php.
 */
function updateConfigFile(string $dbname, string $host, string $user, string $pass): void {
    if (!is_file(SYS_CONFIG_FILE)) {
        throw new RuntimeException('No se encontró el archivo de configuración ' . SYS_CONFIG_FILE);
    }
    $content = file_get_contents(SYS_CONFIG_FILE);
    $replace = [
        'DB_HOST' => ["/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '" . addslashes($host) . "');"],
        'DB_NAME' => ["/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '" . addslashes($dbname) . "');"],
        'DB_USER' => ["/define\('DB_USER',\s*'[^']*'\);/", "define('DB_USER', '" . addslashes($user) . "');"],
        'DB_PASS' => ["/define\('DB_PASS',\s*'[^']*'\);/", "define('DB_PASS', '" . addslashes($pass) . "');"],
    ];
    $newContent = $content;
    foreach ($replace as $name => [$pattern, $replacement]) {
        $newContent = preg_replace($pattern, $replacement, $newContent, 1, $count);
        if (!$count) {
            throw new RuntimeException("No se pudo localizar la constante $name en config.php");
        }
    }
    if (file_put_contents(SYS_CONFIG_FILE, $newContent) === false) {
        throw new RuntimeException('No se pudo escribir en ' . SYS_CONFIG_FILE);
    }
}

// ============================================================
// MANEJADORES (AJAX)
// ============================================================
if (($_POST['action'] ?? '') !== '') {
    $action = (string)$_POST['action'];

    try {
        switch ($action) {
            case 'check_requirements':
                checkRequirementsHandler();
                break;
            case 'test_connection':
                testConnectionHandler();
                break;
            case 'create_config':
                createConfigHandler();
                break;
            case 'create_database':
                createDatabaseHandler();
                break;
            case 'upload_salva':
                uploadSalvaHandler();
                break;
            case 'import_salva_batch':
                importSalvaBatchHandler();
                break;
            case 'save_company_config':
                saveCompanyConfigHandler();
                break;
            case 'save_admin':
                saveAdminHandler();
                break;
            case 'save_config':
                saveConfigHandler();
                break;
            case 'delete_installer':
                deleteInstallerHandler();
                break;
            default:
                jsonError('Acción desconocida: ' . $action);
        }
    } catch (Throwable $e) {
        jsonError($e->getMessage());
    }
}

function checkRequirementsHandler(): void {
    global $appBase;
    $req = [];

    $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
    $req[] = ['name' => 'PHP >= 8.1', 'ok' => $phpOk, 'value' => PHP_VERSION];

    foreach (['mysqli', 'pdo_mysql', 'json', 'gd', 'fileinfo', 'mbstring', 'openssl'] as $ext) {
        $loaded = extension_loaded($ext);
        $req[] = ['name' => 'Extensión ' . $ext, 'ok' => $loaded, 'value' => $loaded ? 'Cargada' : 'Falta'];
    }

    $req[] = ['name' => 'Sesiones PHP', 'ok' => function_exists('session_start'), 'value' => 'Disponible'];
    $req[] = ['name' => 'Permisos de escritura CARPETA NOMINAS', 'ok' => is_writable(APP_DIR), 'value' => is_writable(APP_DIR) ? 'Sí' : 'No'];
    $configErr = ensureConfigFile();
    $configOk = ($configErr === '');
    $req[] = [
        'name' => 'Archivo config.php',
        'ok' => $configOk,
        'value' => $configOk ? 'Existe' : 'Falta',
        'desc' => $configOk
            ? 'Archivo necesario y fundamental.'
            : ($configErr !== '' ? $configErr : 'Archivo necesario y fundamental. Intente de nuevo.')
    ];
    $req[] = ['name' => 'Vendor (PHPMailer)', 'ok' => is_dir(APP_DIR . '/vendor'), 'value' => is_dir(APP_DIR . '/vendor') ? 'Existe' : 'Falta'];

    // Detección de phpMyAdmin (solo informativa, no bloquea)
    $pmaVer = phpMyAdminVersion();
    $req[] = [
        'name' => 'phpMyAdmin',
        'ok' => $pmaVer !== '',
        'value' => $pmaVer !== '' ? 'Detectado (' . $pmaVer . ')' : 'No instalado',
        'desc' => $pmaVer !== '' ? 'Instalado junto al proyecto.' : 'Opcional, no impide continuar.',
        'opcional' => true,
    ];

    // Chequeo REAL de conexión a MySQL (servidor encendido/apagado)
    $s = $_SESSION['db_creds'] ?? [];
    $dbHost = $_POST['db_host'] ?? $s['host'] ?? (defined('DB_HOST') ? DB_HOST : 'localhost');
    $dbPort = (int)($_POST['db_port'] ?? $s['port'] ?? 3306);
    $dbUser = (string)($_POST['db_user'] ?? $s['user'] ?? (defined('DB_USER') ? DB_USER : 'root'));
    $dbPass = (string)($_POST['db_pass'] ?? $s['pass'] ?? (defined('DB_PASS') ? (string)DB_PASS : ''));
    $mysqlOk = false;
    $mysqlValue = 'No probado';
    $mysqlUp = false;
    $fp = @fsockopen($dbHost, $dbPort, $errno, $errstr, 2);
    if ($fp) {
        $mysqlUp = true;
        fclose($fp);
        try {
            $m = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);
            if ($m && !$m->connect_errno) {
                $mysqlOk = true;
                $mysqlValue = 'Conectado (' . dbServerName($m) . ')';
                $m->close();
            } else {
                $mysqlValue = 'Servidor activo, error de acceso: ' . ($m ? $m->connect_error : 'desconocido');
                if ($m) { @$m->close(); }
            }
        } catch (\Throwable $e) {
            $mysqlValue = 'Servidor activo, error de acceso: ' . $e->getMessage();
        }
    } else {
        $mysqlValue = 'Servidor MySQL apagado o inaccesible (' . ($errstr ?: 'timeout') . ')';
    }
    $req[] = [
        'name' => 'Servidor MySQL / MariaDB',
        'ok' => $mysqlOk,
        'value' => $mysqlValue,
        // Si falla (apagado O error de acceso), permitir corregir host/puerto/usuario aquí mismo y probar
        'showAuth' => !$mysqlOk,
        'desc' => (!$mysqlOk && !$mysqlUp)
            ? 'Escriba el host y el puerto correctos (y credenciales si procede) y pruebe la conexión aquí mismo.'
            : '',
        'auth' => [
            'host' => $dbHost,
            'port' => $dbPort,
            'user' => $dbUser,
            'pass' => $dbPass,
        ],
    ];

    $allOk = true;
    $allOk = true;                    // todo cumple
    $soloOpcionalesPendientes = true; // lo único que falla es opcional
    foreach ($req as $r) {
        if (!$r['ok']) {
            $allOk = false;
            if (empty($r['opcional'])) {
                $soloOpcionalesPendientes = false;
            }
        }
    }

    logMessage('Verificación de requisitos completada (' . ($allOk ? 'todo OK' : ($soloOpcionalesPendientes ? 'solo opcionales pendientes' : 'con pendientes')) . ').');
    jsonResponse([
        'success' => true,
        'data' => [
            'requirements' => $req,
            'allOk' => $allOk,
            'soloOpcionalPendiente' => (!$allOk && $soloOpcionalesPendientes),
            'systemName' => SYS_NAME,
            'systemVersion' => INSTALLER_VERSION,
            'phpVersion' => PHP_VERSION,
            'dbName' => SYS_DBNAME,
            'urlBase' => $appBase,
            'tables' => SYS_TABLES,
        ],
        'log' => getLog(),
    ]);
}

function createConfigHandler(): void {
    if (is_file(SYS_CONFIG_FILE)) {
        jsonResponse(['success' => true, 'message' => 'config.php ya existe.', 'log' => getLog()]);
        return;
    }
    $error = ensureConfigFile();
    if ($error !== '') {
        jsonResponse(['success' => false, 'message' => $error, 'log' => getLog()]);
        return;
    }
    logMessage('config.php creado desde la plantilla.');
    jsonResponse(['success' => true, 'message' => 'config.php creado correctamente.', 'log' => getLog()]);
}

/**
 * Crea config.php a partir de la plantilla si no existe.
 * Devuelve '' si ya existe o se creó correctamente, o un mensaje de error.
 */
function ensureConfigFile(): string {
    if (is_file(SYS_CONFIG_FILE)) {
        return '';
    }
    $content = <<<'CFG'
<?php
// config.php - Configuración central de la BASE DE DATOS.
// Este es el ÚNICO archivo donde se guardan el nombre de la base de datos y
// la contraseña de MySQL. El instalador actualiza estas constantes
// automáticamente al configurar la conexión.
//
// La conexión PDO, la carga de configuración desde la BD y las funciones del
// sistema están en config/database.php (lo incluyen todas las páginas).

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'SisGesNom_nomina');
define('DB_USER', 'root');
define('DB_PASS', '');          // Contraseña de MySQL (puede estar vacía)
define('DB_CHARSET', 'utf8mb4');

// ============================================
// VERSIÓN DEL SISTEMA
// ============================================
define('SITE_VERSION', 'v2.0.1');

// ============================================
// RUTA BASE
// ============================================
define('BASE_URL', '/nominas');
define('BASE_PATH', __DIR__);
define('ASSETS_PATH', BASE_PATH . '/assets');

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}
CFG;
    if (@file_put_contents(SYS_CONFIG_FILE, $content) === false) {
        return 'No se pudo crear config.php. Verifique los permisos de escritura en NOMINAS.';
    }
    logMessage('config.php creado automáticamente desde la plantilla.');
    return '';
}

function testConnectionHandler(): void {
    $c = credsFromSessionOrPost($_POST);
    logMessage('Probando conexión a MySQL en ' . $c['host'] . ':' . $c['port'] . ' (usuario: ' . $c['user'] . ')...');
    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port']);
    $server = dbServerName($m);
    $info = [
        'host' => $m->server_info ?? '',
        'version' => $m->server_version ?? 0,
    ];
    $m->close();
    $_SESSION['db_creds'] = $c;
    logMessage('Conexión exitosa (' . $server . ').');
    jsonResponse(['success' => true, 'message' => 'Conexión exitosa a ' . $server . '.', 'server' => $info['version'], 'log' => getLog()]);
}

function createDatabaseHandler(): void {
    global $appBase;
    $c = credsFromSessionOrPost($_POST);
    $recreate = (($_POST['db_recreate'] ?? '0') === '1');

    logMessage('Creando base de datos ' . $c['dbname'] . '...');
    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port']);

    $db = $m->real_escape_string($c['dbname']);
    $exists = $m->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$db'");

    if ($exists && $exists->num_rows > 0) {
        $tables = $m->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db'");
        $n = (int)($tables ? $tables->fetch_assoc()['n'] : 0);
        if (!$recreate) {
            if ($n > 0) {
                $m->close();
                jsonError("La base de datos '$db' ya existe con $n tabla(s). Para reiniciarla marque la opción \"Reiniciar BD\".");
            }
            logMessage("La base de datos '$db' existe pero está vacía; se usará.");
        } else {
            logMessage("Reiniciando: se eliminará la base de datos '$db' existente.");
            $m->query("DROP DATABASE IF EXISTS `$db`");
        }
    }

    $m->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($m->errno) {
        $m->close();
        jsonError('Error al crear la base de datos: ' . $m->error);
    }
    $m->select_db($c['dbname']);
    $m->set_charset('utf8mb4');

    logMessage('Importando esquema (tablas y datos iniciales)...');
    global $sqlSisGesNom;
    $queries = 0;
    $m->query('SET FOREIGN_KEY_CHECKS=0');
    if ($m->multi_query($sqlSisGesNom)) {
        do {
            if ($res = $m->store_result()) {
                $res->free();
            }
            $queries++;
        } while ($m->more_results() && $m->next_result());
    }
    if ($m->errno) {
        $err = $m->error;
        $m->query('SET FOREIGN_KEY_CHECKS=1');
        $m->close();
        jsonError('Error al importar el esquema: ' . $err);
    }
    $m->query('SET FOREIGN_KEY_CHECKS=1');

    logMessage('Vaciando tablas operativas (trabajadores, nóminas, etc.)...');
    $tablasVaciar = ['areas', 'centros_costo', 'cierres_nomina', 'historial_salarios', 'montos_distrib', 'nominas', 'secuencias_nominas', 'submayor_vacaciones', 'trabajadores'];
    $m->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tablasVaciar as $t) {
        if (!$m->query("TRUNCATE TABLE `$t`")) {
            $err = $m->error;
            $m->query('SET FOREIGN_KEY_CHECKS=1');
            $m->close();
            jsonError("Error al vaciar la tabla $t: " . $err);
        }
    }
    $m->query('SET FOREIGN_KEY_CHECKS=1');

    updateConfigFile($c['dbname'], $c['host'], $c['user'], $c['pass']);

    $_SESSION['db_creds'] = $c;
    $m->close();

    logMessage("Base de datos '$db' creada e importada correctamente.");
    logMessage('config.php actualizado con las credenciales de conexión.');
    jsonResponse([
        'success' => true,
        'message' => 'Base de datos creada e importada. config.php actualizado.',
        'urlBase' => $appBase,
        'dbname' => $c['dbname'],
        'log' => getLog(),
    ]);
}

function uploadSalvaHandler(): void {
    if (empty($_FILES['salva'])) {
        jsonError('No se recibió ningún archivo.');
    }
    $f = $_FILES['salva'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        jsonError('Error al subir el archivo (código ' . $f['error'] . ').');
    }
    if ($f['size'] > 300 * 1024 * 1024) {
        jsonError('El archivo es demasiado grande. Máximo 300MB.');
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $sql = '';
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($f['tmp_name']) !== true) {
            jsonError('No se pudo abrir el archivo ZIP.');
        }
        $extractPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'salva_' . time() . '_' . uniqid();
        if (!mkdir($extractPath, 0755, true)) {
            $zip->close();
            jsonError('No se pudo crear el directorio temporal.');
        }
        $zip->extractTo($extractPath);
        $zip->close();
        $sqlFiles = glob($extractPath . DIRECTORY_SEPARATOR . '*.sql');
        if (empty($sqlFiles)) {
            array_map('unlink', glob("$extractPath/*"));
            rmdir($extractPath);
            jsonError('El archivo ZIP no contiene ningún archivo .sql');
        }
        $sql = @file_get_contents($sqlFiles[0]);
        array_map('unlink', glob("$extractPath/*"));
        rmdir($extractPath);
    } elseif ($ext === 'sql') {
        $sql = @file_get_contents($f['tmp_name']);
    } else {
        jsonError('Formato no soportado. Solo se permiten archivos .sql o .zip');
    }
    if ($sql === false || trim($sql) === '') {
        jsonError('El archivo está vacío o no se pudo leer.');
    }

    $dbname = detectDbNameFromSql($sql);
    logMessage('Salva recibida: ' . $f['name'] . ($dbname ? " (base de datos detectada: $dbname)" : ''));

    $queries = splitSQLQueries($sql);
    if (empty($queries)) {
        jsonError('No se encontraron consultas SQL en el archivo.');
    }

    $token = bin2hex(random_bytes(8));
    $qFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'salva_' . $token . '.queries.json';
    if (file_put_contents($qFile, json_encode($queries)) === false) {
        jsonError('No se pudo preparar la salva en el servidor.');
    }

    $_SESSION['salva_state'] = [
        'token'  => $token,
        'index'  => 0,
        'total'  => count($queries),
        'dbname' => $dbname,
        'errors' => [],
        'orig'   => $f['name'],
    ];

    jsonResponse([
        'success' => true,
        'message' => 'Salva recibida. Iniciando importación (' . count($queries) . ' consultas)...',
        'total'   => count($queries),
        'dbname'  => $dbname,
        'log'     => getLog(),
    ]);
}

function importSalvaBatchHandler(): void {
    $st = $_SESSION['salva_state'] ?? null;
    if (!$st) {
        jsonError('La sesión de importación caducó. Seleccione de nuevo el archivo de salva.');
    }
    $qFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'salva_' . $st['token'] . '.queries.json';
    $queries = json_decode(@file_get_contents($qFile), true);
    if (!is_array($queries) || empty($queries)) {
        jsonError('No se pudo leer la salva preparada en el servidor.');
    }

    $c = credsFromSessionOrPost($_POST);
    $dbname = $st['dbname'] ?: $c['dbname'];

    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port']);

    $isFirstBatch = ((int)$st['index']) === 0;
    $firstQuery = trim($queries[0] ?? '');
    $sqlCreatesDb = (bool)preg_match('~^\s*(DROP\s+DATABASE|CREATE\s+DATABASE|USE)\b~i', $firstQuery);

    if ($isFirstBatch && !$sqlCreatesDb) {
        $db = $m->real_escape_string($dbname);
        $m->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if ($m->errno) {
            $m->close();
            jsonError('Error al crear la base de datos: ' . $m->error);
        }
        $m->select_db($dbname);
    } elseif (!$isFirstBatch) {
        $m->select_db($dbname);
    }
    $m->set_charset('utf8mb4');

    $m->query('SET FOREIGN_KEY_CHECKS=0');
    $m->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    $batchSize = 8;
    $start = (int)$st['index'];
    $end = min($start + $batchSize, (int)$st['total']);
    $errors = $st['errors'] ?? [];

    for ($i = $start; $i < $end; $i++) {
        $q = trim($queries[$i]);
        if ($q === '') {
            continue;
        }
        try {
            if ($m->query($q)) {
                continue;
            }
            $err = $m->error;
            if (count($errors) < 10) {
                $errors[] = 'Error SQL: ' . $err . ' | ' . substr($q, 0, 120);
            }
            if (stripos($err, 'packet') !== false || stripos($err, 'gone away') !== false) {
                $errors[] = 'CRÍTICO: Paquete demasiado grande. Se requiere aumentar max_allowed_packet en el servidor MySQL.';
                break;
            }
        } catch (Throwable $e) {
            if (count($errors) < 10) {
                $errors[] = 'Error SQL: ' . $e->getMessage() . ' | ' . substr($q, 0, 120);
            }
        }
    }

    $_SESSION['salva_state']['index'] = $end;
    $_SESSION['salva_state']['errors'] = $errors;

    $done = $end >= (int)$st['total'];

    if (!$done) {
        $m->query('SET FOREIGN_KEY_CHECKS=1');
        $m->close();
        jsonResponse([
            'success'   => true,
            'done'      => false,
            'processed' => $end,
            'total'     => (int)$st['total'],
            'log'       => getLog(),
        ]);
    }

    $m->query('SET FOREIGN_KEY_CHECKS=1');

    $empresa = [];
    try {
        $empresa = readEmpresaFromDb($m);
    } catch (Throwable $e) {
        // La salva puede no contener la tabla configuracion_general; se ignora.
    }
    $m->close();

    @unlink($qFile);
    unset($_SESSION['salva_state']);

    updateConfigFile($dbname, $c['host'], $c['user'], $c['pass']);
    $_SESSION['db_creds'] = $c;
    $_SESSION['db_creds']['dbname'] = $dbname;

    $failed = count($errors);
    $successful = (int)$st['total'] - $failed;
    logMessage("Salva importada: $successful consultas ejecutadas, $failed fallaron. config.php actualizado.");

    jsonResponse([
        'success'   => ($failed === 0 || $successful > 0),
        'done'      => true,
        'processed' => (int)$st['total'],
        'total'     => (int)$st['total'],
        'message'   => 'Salva importada: ' . $successful . ' consultas ejecutadas' . ($failed > 0 ? ', ' . $failed . ' fallaron.' : '. config.php actualizado.'),
        'errors'    => $errors,
        'dbname'    => $dbname,
        'empresa'   => $empresa,
        'log'       => getLog(),
    ]);
}

function detectDbNameFromSql(string $sql): ?string {
    if (preg_match('/^\s*CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_\-]+)`?/im', $sql, $m1)) {
        return $m1[1];
    }
    if (preg_match('/^\s*USE\s+`?([A-Za-z0-9_\-]+)`?/im', $sql, $m2)) {
        return $m2[1];
    }
    if (preg_match('/CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_\-]+)`?/i', $sql, $m3)) {
        return $m3[1];
    }
    if (preg_match('/USE\s+`?([A-Za-z0-9_\-]+)`?/i', $sql, $m4)) {
        return $m4[1];
    }
    return null;
}

function readEmpresaFromDb(mysqli $m): array {
    $keys = [
        'nombre_empresa', 'direccion_empresa', 'reeup_empresa', 'nit_empresa', 'slogan',
        'telefono_empresa', 'email_empresa', 'jefe_proyecto', 'especialista_gestion',
        'especialista_gestionRRHH', 'intendente', 'telefono_soporte', 'email_soporte',
    ];
    $res = $m->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('" . implode("','", array_map(function ($k) use ($m) { return $m->real_escape_string($k); }, $keys)) . "')");
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[$r['parametro']] = $r['valor'];
        }
    }
    return $out;
}

function splitSQLQueries(string $sql): array {
    $sql = removeSQLComments($sql);
    $delimiter = ';';
    $queries = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        if (preg_match('/^\s*DELIMITER\s+([^\s]+)/i', substr($sql, $i), $matches)) {
            $delimiter = $matches[1];
            $i += strlen($matches[0]);
            continue;
        }

        $char = $sql[$i];

        if ($escaped) {
            $current .= $char;
            $escaped = false;
            $i++;
            continue;
        }

        if ($char === '\\') {
            $current .= $char;
            $escaped = true;
            $i++;
            continue;
        }

        if ($char === "'" || $char === '"') {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            $current .= $char;
            $i++;
            continue;
        }

        $current .= $char;

        if (!$inString && substr($current, -strlen($delimiter)) === $delimiter) {
            $trimmed = trim(substr($current, 0, -strlen($delimiter)));
            if ($trimmed !== '') {
                $queries[] = $trimmed;
            }
            $current = '';
        }

        $i++;
    }

    $remaining = trim($current);
    if ($remaining !== '') {
        $queries[] = $remaining;
    }

    return $queries;
}

function removeSQLComments(string $sql): string {
    $result = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $inLineComment = false;
    $inBlockComment = false;
    $i = 0;

    while ($i < $len) {
        $char = $sql[$i];

        if ($escaped) {
            $escaped = false;
            $i++;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            $i++;
            continue;
        }

        if (($char === "'" || $char === '"') && !$inLineComment && !$inBlockComment) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            $result .= $char;
            $i++;
            continue;
        }

        if ($inString) {
            $result .= $char;
            $i++;
            continue;
        }

        if (!$inBlockComment && !$inLineComment && $char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            $inLineComment = true;
            $i += 2;
            continue;
        }

        if (!$inBlockComment && !$inLineComment && $char === '#') {
            $inLineComment = true;
            $i++;
            continue;
        }

        if (!$inBlockComment && !$inLineComment && $char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $inBlockComment = true;
            $i += 2;
            continue;
        }

        if ($inBlockComment && $char === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
            $inBlockComment = false;
            $i += 2;
            continue;
        }

        if ($inLineComment && ($char === "\n" || $char === "\r")) {
            $inLineComment = false;
            $result .= $char;
            $i++;
            continue;
        }

        if ($inLineComment || $inBlockComment) {
            $i++;
            continue;
        }

        $result .= $char;
        $i++;
    }

    return $result;
}

function saveCompanyConfigHandler(): void {
    $c = credsFromSessionOrPost($_POST);
    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port'], $c['dbname']);

    $company = [
        'nombre_empresa'        => trim($_POST['nombre_empresa'] ?? ''),
        'direccion_empresa'     => trim($_POST['direccion_empresa'] ?? ''),
        'reeup_empresa'         => trim($_POST['reeup_empresa'] ?? ''),
        'nit_empresa'           => trim($_POST['nit_empresa'] ?? ''),
        'slogan'                => trim($_POST['slogan'] ?? ''),
        'telefono_empresa'      => trim($_POST['telefono_empresa'] ?? ''),
        'telefono_soporte'     => trim($_POST['telefono_soporte'] ?? ''),
        'email_soporte'       => trim($_POST['email_soporte'] ?? ''),
        'email_empresa'         => trim($_POST['email_empresa'] ?? ''),
        'jefe_proyecto'         => trim($_POST['jefe_proyecto'] ?? ''),
        'especialista_gestion'  => trim($_POST['especialista_gestion'] ?? ''),
        'especialista_gestionRRHH' => trim($_POST['especialista_gestionRRHH'] ?? ''),
        'intendente'            => trim($_POST['intendente'] ?? ''),
    ];
    foreach ($company as $param => $valor) {
        setConfigParam($m, $param, $valor, 'texto');
    }
    $m->close();

    logMessage('Datos de la empresa guardados en configuracion_general.');
    jsonResponse(['success' => true, 'message' => 'Datos de la empresa guardados.', 'log' => getLog()]);
}

function saveAdminHandler(): void {
    $c = credsFromSessionOrPost($_POST);
    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port'], $c['dbname']);

    $usuario = trim($_POST['usuario'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($usuario === '' || strlen($usuario) < 3) {
        $m->close();
        jsonError('El nombre de usuario debe tener al menos 3 caracteres.');
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $usuario)) {
        $m->close();
        jsonError('El nombre de usuario solo puede contener letras, números, punto, guion y subrayado.');
    }
    if (strlen($password) < 6) {
        $m->close();
        jsonError('La contraseña debe tener al menos 6 caracteres.');
    }

    $sel = $m->prepare("SELECT id FROM clasif_usuarios WHERE usuario = ?");
    $sel->bind_param('s', $usuario);
    $sel->execute();
    if ($sel->get_result()->num_rows > 0) {
        $m->close();
        jsonError("El usuario '$usuario' ya existe. Elija otro nombre de usuario.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $nombre = 'Administrador';
    $apellidos = 'del Sistema';
    $noCi = '00000000000';
    $rol = 1;
    $activo = 1;

    $m->query("DELETE FROM clasif_usuarios WHERE usuario = 'admin'");
    if ($m->errno) {
        $err = $m->error;
        $m->close();
        jsonError('Error al reemplazar el usuario admin: ' . $err);
    }

    $ins = $m->prepare("INSERT INTO clasif_usuarios (nombre, apellidos, no_ci, rol_id, usuario, password, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param('sssissi', $nombre, $apellidos, $noCi, $rol, $usuario, $hash, $activo);
    $ins->execute();
    if ($m->errno) {
        $err = $m->error;
        $m->close();
        jsonError('Error al crear el usuario administrador: ' . $err);
    }
    $m->close();

    logMessage("Usuario administrador '$usuario' creado correctamente (el admin embebido fue reemplazado).");
    jsonResponse(['success' => true, 'message' => 'Usuario administrador creado.', 'log' => getLog()]);
}

function saveConfigHandler(): void {
    $c = credsFromSessionOrPost($_POST);
    $m = connectDb($c['host'], $c['user'], $c['pass'], $c['port'], $c['dbname']);

    $params = [
        'mail_activo'     => (($_POST['mail_activo'] ?? '0') === '1') ? '1' : '0',
        'mail_proveedor'  => trim($_POST['mail_proveedor'] ?? ''),
        'mail_host'       => trim($_POST['mail_host'] ?? ''),
        'mail_port'       => trim($_POST['mail_port'] ?? '587'),
        'mail_encryption' => trim($_POST['mail_encryption'] ?? 'tls'),
        'mail_usuario'    => trim($_POST['mail_usuario'] ?? ''),
        'mail_password'   => (string)($_POST['mail_password'] ?? ''),
        'mail_from'       => trim($_POST['mail_from'] ?? ''),
        'mail_from_name'  => trim($_POST['mail_from_name'] ?? ''),
    ];
    foreach ($params as $param => $valor) {
        setConfigParam($m, $param, $valor, 'texto');
    }
    $m->close();

    logMessage('Configuración del sistema (correo) guardada.');
    jsonResponse(['success' => true, 'message' => 'Configuración del sistema guardada.', 'log' => getLog()]);
}

function deleteInstallerHandler(): void {
    $self = realpath(__FILE__) ?: __FILE__;
    $backup = __DIR__ . '/InstalarBD_SisGesNom.instalado.php';
    if (is_file($backup)) {
        @unlink($backup);
    }
    if (!@copy($self, $backup)) {
        jsonError('No se pudo crear la copia de seguridad del instalador (permisos de escritura).');
    }
    logMessage('Copia de seguridad creada: ' . basename($backup));
    // El archivo esta en ejecucion: responder primero y eliminarlo al final de la peticion.
    ignore_user_abort(true);
    register_shutdown_function(function () use ($self) {
        @unlink($self);
    });
    jsonResponse(['success' => true, 'message' => 'Instalador eliminado correctamente.', 'log' => getLog()]);
}

// ============================================================
// INTERFAZ WEB
// ============================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>Instalador - <?php echo SYS_NAME_SHORT; ?></title>
<link rel="icon" type="image/png" href="../images/favicon.png">
<link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
<style>:root {
    --primary: #14b8a6;
    --primary-dark: #0d9488;
    --accent: #2dd4bf;
    --bg: #020617;
    --card: #0f172a;
    --panel: #1e293b;
    --border: #334155;
    --text: #e2e8f0;
    --muted: #94a3b8;
    --ok: #22c55e;
    --warn: #f59e0b;
    --fail: #ef4444;
    --radius: 12px;
    --shadow: 0 10px 30px rgba(0, 0, 0, .5);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
    min-height: 100vh;
    color: var(--text);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 28px 16px 60px;
}
html { height: 100%; }
body { margin: 0; }
.bg {
    animation: slide 3s ease-in-out infinite alternate;
    background-image: linear-gradient(-60deg, #6c3 50%, #09f 50%);
    bottom: 0;
    left: -50%;
    opacity: 0.5;
    position: fixed;
    right: -50%;
    top: 0;
    z-index: -1;
}
.bg2 {
    animation-direction: alternate-reverse;
    animation-duration: 4s;
}
.bg3 {
    animation-duration: 5s;
}
@keyframes slide {
    0% { transform: translateX(-25%); }
    100% { transform: translateX(25%); }
}
.container { width: 100%; max-width: 920px; }
header.top { text-align: center; color: #fff; margin-bottom: 22px; }
header.top .logo { font-size: 34px; font-weight: 800; letter-spacing: .5px; }
header.top .logo span { color: #5eead4; }
header.top .headline {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}
header.top .sub { opacity: .85; font-size: 14px; }
header.top .badges { font-size: 12px; }
header.top .badges span {
    background: rgba(255,255,255,.12);
    color: #cbd5e1;
    padding: 4px 12px;
    border-radius: 20px;
    margin: 0 4px;
    display: inline-block;
}
.wizard { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.wizard-titlebar { border-radius: var(--radius) var(--radius) 0 0; }
.steps-nav {
    display: flex;
    background: linear-gradient(135deg, #134e4a 0%, #0f766e 100%);
    overflow-x: auto;
}
.step-item {
    flex: 1;
    min-width: 110px;
    text-align: center;
    padding: 14px 8px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all .2s;
    user-select: none;
}
.step-item .num {
    width: 26px; height: 26px;
    margin: 0 auto 6px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .18);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 26px;
    transition: all .2s;
}
.step-item .lbl { font-size: 11px; font-weight: 600; color: rgba(255, 255, 255, .75); }
.step-item.active { background: rgba(255, 255, 255, .14); border-bottom-color: #5eead4; }
.step-item.active .num { background: #fff; color: #0f766e; }
.step-item.active .lbl { color: #fff; font-weight: 700; }
.step-item.done .num { background: #34d399; color: #022c22; }
.step-item.done .lbl { color: #99f6e4; }
.step-item.locked { cursor: not-allowed; opacity: .55; }
.step-item.locked .num { background: rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .5); }
.step-item.locked .lbl { color: rgba(255, 255, 255, .5); }
.step-body { padding: 24px 28px 26px; }
.step-panel { display: none; animation: fadeIn .3s ease; }
.step-panel.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
h2.panel-title { font-size: 20px; color: var(--text); margin-bottom: 6px; }
p.panel-desc { color: var(--muted); font-size: 13.5px; margin-bottom: 18px; }
.card-info {
    background: #042f2e;
    border: 1px solid #115e59;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #99f6e4;
    margin-bottom: 16px;
}
.card-info b { color: #5eead4; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-grid.grid-empresa { grid-template-columns: repeat(6, 1fr); }
.form-grid.grid-empresa .form-group.col-2 { grid-column: span 2; }
.form-grid.grid-empresa .form-group.col-3 { grid-column: span 3; }
.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 12.5px; font-weight: 600; color: #cbd5e1; margin-bottom: 5px; }
.form-group label .req { color: var(--fail); }
.form-control {
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: var(--panel);
    color: var(--text);
    transition: border-color .2s, box-shadow .2s;
    width: 100%;
}
.form-control::placeholder { color: #64748b; }
.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(20,184,166,.18);
}
select.form-control { cursor: pointer; }
select.form-control option { background: #1e293b; color: #e2e8f0; }
.password-wrapper { position: relative; }
.password-wrapper .form-control { padding-right: 42px !important; }
.password-toggle {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: rgba(255, 255, 255, .5);
    padding: 6px 8px; cursor: pointer; z-index: 5;
}
.password-toggle:hover { color: #22d3ee; }
.check-row { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #cbd5e1; }
.check-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
.actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
.btn {
    padding: 10px 22px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: var(--primary); color: #022c22; }
.btn-primary:hover:not(:disabled) { background: var(--primary-dark); }
.btn-secondary { background: #334155; color: #e2e8f0; }
.btn-secondary:hover:not(:disabled) { background: #475569; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: #cbd5e1; }
.btn-outline:hover:not(:disabled) { border-color: var(--primary); color: var(--accent); }
.btn-salva { background: linear-gradient(135deg, #f59e0b, #d97706); color: #1c1917; font-weight: 700; }
.btn-salva:hover:not(:disabled) { filter: brightness(1.1); }
.salva-row { display: flex; align-items: center; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
.salva-hint { font-size: 12px; color: var(--muted); }
.btn-danger { background: var(--fail); color: #fff; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-success { background: var(--ok); color: #022c22; }
.btn-success:hover:not(:disabled) { background: #16a34a; }
.btn-warning { background: var(--warn); color: #1c1917; }
.btn-warning:hover:not(:disabled) { background: #d97706; }
.btn-sm { padding: 6px 12px; font-size: 12.5px; }
#loader { display: none; text-align: center; padding: 40px 0; }
#loader .spin {
    width: 42px; height: 42px;
    border: 4px solid #334155;
    border-top-color: var(--primary);
    border-radius: 50%;
    margin: 0 auto 12px;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
#loader p { color: var(--muted); font-size: 13px; }
.alert { display: none; position: relative; border-radius: 8px; padding: 12px 14px; padding-right: 34px; font-size: 13.5px; margin-bottom: 14px; }
.alert.show { display: block; }
.alert.success { background: #052e16; border: 1px solid #14532d; color: #86efac; }
.alert.error { background: #450a0a; border: 1px solid #7f1d1d; color: #fca5a5; }
.alert .pbar { height: 10px; background: rgba(255,255,255,.18); border-radius: 6px; overflow: hidden; margin-top: 9px; }
.alert .pbar-fill { height: 100%; width: 0; background: linear-gradient(90deg, #22c55e, #3b82f6); border-radius: 6px; transition: width .2s ease; }
.alert .spin-icon { margin-right: 8px; color: var(--accent); animation: spin 1s linear infinite; }
.alert.warning { background: #451a03; border: 1px solid #78350f; color: #fcd34d; }
.alert .alert-close { position: absolute; top: 9px; right: 9px; background: none; border: none; color: inherit; opacity: .65; cursor: pointer; font-size: 14px; line-height: 1; padding: 2px 4px; border-radius: 4px; }
.alert .alert-close:hover { opacity: 1; background: rgba(255, 255, 255, .15); }
.req-list {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.req-list li {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 13.5px;
    background: var(--card);
    box-shadow: var(--shadow);
    position: relative;
    transition: transform .2s, border-color .2s;
}
.req-list li:hover { transform: translateY(-2px); }
.req-list li .req-head {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}
.req-list li .icon {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    border-radius: 10px;
    flex-shrink: 0;
}
.req-list li.ok { border-color: #14532d; background: #052e16; }
.req-list li.ok .icon { background: rgba(34, 197, 94, .15); }
.req-list li.fail { border-color: #7f1d1d; background: #450a0a; }
.req-list li.fail .icon { background: rgba(239, 68, 68, .18); }
.req-list li .rname { font-weight: 700; color: var(--text); font-size: 13.5px; line-height: 1.3; }
.req-list li .val {
    color: var(--muted);
    font-size: 12.5px;
    margin-top: auto;
    background: rgba(255, 255, 255, .05);
    padding: 4px 10px;
    border-radius: 6px;
    width: 100%;
    word-break: break-word;
}
.req-list li .val b { color: #cbd5e1; font-weight: 600; }
.req-list li .rdesc {
    color: #fca5a5;
    font-size: 12px;
    line-height: 1.4;
    background: rgba(239, 68, 68, .12);
    padding: 4px 10px;
    border-radius: 6px;
    width: 100%;
    word-break: break-word;
}
.req-list li.ok .rdesc { color: #86efac; background: rgba(34, 197, 94, .12); }
.req-list li .mysql-auth {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 2px;
}
.req-list li .mysql-auth .auth-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 2px;
}
.req-list li .mysql-auth .auth-msg { font-size: 12px; line-height: 1.4; display: none; }
.req-list li .mysql-auth .auth-msg.ok { display: block; color: #86efac; }
.req-list li .mysql-auth .auth-msg.err { display: block; color: #fca5a5; }
.req-list li .mysql-auth .form-control {
    width: 100%;
    padding: 6px 10px;
    font-size: 12.5px;
    border: 1px solid #334155;
    border-radius: 6px;
    background: #0f172a;
    color: var(--text);
}
.req-list li .mysql-auth .form-control:focus { outline: none; border-color: #22d3ee; }
@media (max-width: 820px) {
    .req-list { grid-template-columns: 1fr 1fr; }
    .form-grid, .form-grid.grid-empresa { grid-template-columns: 1fr 1fr; }
    .summary { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .req-list { grid-template-columns: 1fr; }
    .form-grid, .form-grid.grid-empresa { grid-template-columns: 1fr; }
    .form-grid.grid-empresa .form-group.col-2,
    .form-grid.grid-empresa .form-group.col-3 { grid-column: 1 / -1; }
    .summary { grid-template-columns: 1fr; }
    .step-body { padding: 18px 14px 20px; }
    .step-item { min-width: 88px; padding: 12px 6px; }
    .step-item .lbl { font-size: 10px; }
    .actions { flex-direction: column-reverse; gap: 10px; }
    .actions .btn { width: 100%; justify-content: center; }
    .salva-row { flex-direction: column; align-items: stretch; }
    /* Modal: evitar que los botones queden cortados fuera de la pantalla */
    .modal-overlay { justify-content: flex-start; padding: 14px 12px; }
    .modal-logo { height: 150px; }
    .modal-heading .hh { font-size: 20px; letter-spacing: 1px; }
    .modal-heading .ss { font-size: 12.5px; }
    .modal-body { padding: 18px 16px 20px; }
    .modal-box .actions { flex-direction: column; }
    .modal-box .actions .btn { width: 100%; }
}
.summary { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
.summary .item {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px 14px;
    background: var(--panel);
}
.summary .item.full { grid-column: 1 / -1; }
.summary .item .k { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 3px; }
.summary .item .v { font-size: 14px; font-weight: 600; color: var(--text); word-break: break-word; }
.log-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding: 9px 12px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
    font-size: 12.5px;
    font-weight: 600;
    color: #cbd5e1;
    transition: all .2s;
}
.log-toggle:hover { border-color: var(--primary); color: var(--accent); }
.log-toggle .chev { transition: transform .2s ease; color: var(--accent); font-size: 13px; }
.log-toggle.open { border-color: var(--primary); color: var(--accent); }
.log-toggle.open .chev { transform: rotate(90deg); }
.btn-log-clear {
    float: right; background: none; border: 1px solid rgba(255,255,255,0.15); color: #94a3b8;
    border-radius: 6px; padding: 2px 8px; font-size: 12px; cursor: pointer; transition: all .2s;
}
.btn-log-clear:hover { color: #e74c3c; border-color: #e74c3c; background: rgba(231,76,60,0.1); }
.log-box {
    background: #020617;
    color: #7dd3fc;
    font-family: Consolas, "Courier New", monospace;
    font-size: 11.5px;
    line-height: 1.6;
    border-radius: 8px;
    padding: 12px 14px;
    max-height: 180px;
    overflow-y: auto;
    margin-top: 6px;
    white-space: pre-wrap;
    border: 1px solid #1e293b;
}
.log-box.collapsed { display: none !important; }
.center { text-align: center; }
.section-title {
    font-size: 12.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    margin-bottom: 12px;
}
.links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 4px; }
.links-grid .btn { width: 100%; }
.mt { margin-top: 14px; }
.mb { margin-bottom: 14px; }
.small { font-size: 12.5px; color: var(--muted); }
footer {
    text-align: center;
    color: rgba(255,255,255,.92);
    font-size: 12.5px;
    margin-top: 22px;
    padding: 14px 18px;
    border-radius: var(--radius);
    background: linear-gradient(-60deg, rgba(34,197,94,.18) 50%, rgba(14,165,233,.18) 50%);
    background-size: 200% 200%;
    animation: footerSlide 6s ease-in-out infinite alternate;
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: var(--shadow);
    letter-spacing: .3px;
    line-height: 1.6;
}
footer .fn {
    font-weight: 600;
    font-size: 13.5px;
    color: #fff;
}
@keyframes footerSlide {
    0% { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
}
a.link { color: var(--accent); }
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, .72);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.modal-heading {
    text-align: center;
    margin-bottom: 14px;
}
.modal-heading .hh {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #fff;
    text-transform: uppercase;
}
.modal-heading .hh span { color: #5eead4; }
.modal-heading .ss {
    font-size: 14px;
    color: #cbd5e1;
    margin-top: 4px;
    font-weight: 600;
}
.modal-heading .ss-copyright {
    font-size: 11.5px;
    color: rgba(255,255,255,.6);
    margin-top: 4px;
    font-weight: 600;
    letter-spacing: .3px;
}
.modal-heading .modal-logo {
    margin-top: 10px;
    height: 272px;
    max-width: 420px;
    object-fit: contain;
    border-radius: 8px;
    filter: drop-shadow(0 2px 6px rgba(0, 0, 0, .4));
}
.modal-box {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    max-width: 480px;
    width: 100%;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.modal-titlebar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
    border-bottom: 1px solid rgba(255, 255, 255, .1);
    text-align: left;
}
.modal-titlebar .tt-icon {
    font-size: 18px;
    color: #5eead4;
}
.modal-titlebar .tt-text {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .3px;
    flex: 1;
}
.modal-body {
    padding: 24px 28px 26px;
}
.modal-close {
    position: static;
    width: 30px;
    height: 30px;
    border: none;
    background: rgba(255, 255, 255, .12);
    color: #e2e8f0;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    border-radius: 8px;
    transition: all .2s;
}
.modal-close:hover { background: #ef4444; color: #fff; }
.modal-box h3 { font-size: 20px; color: var(--text); margin-bottom: 8px; }
.modal-box p { font-size: 13.5px; color: var(--muted); margin-bottom: 18px; }
.modal-box .welcome-title { font-size: 17px; font-weight: 700; color: var(--text); line-height: 1.35; margin-bottom: 8px; }
.modal-box .welcome-sub { font-size: 14px; color: var(--muted); line-height: 1.55; margin-bottom: 16px; }
.modal-box .form-control { text-align: center; margin-bottom: 18px; }
.modal-box .actions { display: flex; gap: 10px; justify-content: center; margin: 0; padding: 0; border: none; }
.modal-box.modal-wide { max-width: 560px; }
.admin-body { text-align: left; }
.admin-field { margin-bottom: 16px; }
.admin-field label { display: block; font-size: 12.5px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
.admin-field .form-control { text-align: left; margin-bottom: 0; }
.strength-meter { display: flex; gap: 6px; margin-top: 10px; }
.strength-meter span { flex: 1; height: 6px; border-radius: 4px; background: rgba(255, 255, 255, .12); transition: background .2s; }
.strength-label { font-size: 12px; font-weight: 600; margin-top: 6px; min-height: 16px; }
.admin-msg { display: none; padding: 9px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 14px; text-align: left; }
.admin-msg.error { background: rgba(239, 68, 68, .12); color: #fca5a5; border: 1px solid rgba(239, 68, 68, .35); }
.admin-msg.success { background: rgba(34, 197, 94, .12); color: #86efac; border: 1px solid rgba(34, 197, 94, .35); }
.admin-options { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
.admin-opt { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; background: rgba(255, 255, 255, .03); cursor: pointer; transition: all .2s; }
.admin-opt.selected { border-color: #0f766e; background: rgba(15, 118, 110, .12); box-shadow: 0 0 0 1px #0f766e; }
.admin-opt input { position: absolute; opacity: 0; }
.admin-opt i { font-size: 22px; color: #5eead4; }
.admin-opt b { font-size: 14px; color: var(--text); display: block; }
.admin-opt span { font-size: 12.5px; color: var(--muted); display: block; margin-top: 4px; line-height: 1.4; }
@property --retro-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}
@keyframes retroSpin { to { --retro-angle: 90deg; } }
.swal-retro-bg {
    --retro-angle: 0deg;
    background-image:
        linear-gradient(rgba(2, 6, 23, .28), rgba(2, 6, 23, .28)),
        repeating-conic-gradient(from var(--retro-angle),
            rgba(248, 113, 113, .55) 0deg 90deg,
            rgba(203, 213, 225, .38) 90deg 180deg,
            rgba(248, 113, 113, .55) 180deg 270deg,
            rgba(203, 213, 225, .38) 270deg 360deg) !important;
    background-size: 72px 72px !important;
    background-repeat: repeat !important;
    animation: retroSpin 3s linear infinite;
}
</style>
<style>:root{--swal2-outline: 0 0 0 3px rgba(100, 150, 200, 0.5);--swal2-container-padding: 0.625em;--swal2-backdrop: rgba(0, 0, 0, 0.4);--swal2-backdrop-transition: background-color 0.15s;--swal2-width: 32em;--swal2-padding: 0 0 1.25em;--swal2-border: none;--swal2-border-radius: 0.3125rem;--swal2-background: white;--swal2-color: #545454;--swal2-show-animation: swal2-show 0.3s;--swal2-hide-animation: swal2-hide 0.15s forwards;--swal2-icon-zoom: 1;--swal2-icon-animations: true;--swal2-title-padding: 0.8em 1em 0;--swal2-html-container-padding: 1em 1.6em 0.3em;--swal2-input-border: 1px solid #d9d9d9;--swal2-input-border-radius: 0.1875em;--swal2-input-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px transparent;--swal2-input-background: transparent;--swal2-input-transition: border-color 0.2s, box-shadow 0.2s;--swal2-input-hover-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px transparent;--swal2-input-focus-border: 1px solid #b4dbed;--swal2-input-focus-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px rgba(100, 150, 200, 0.5);--swal2-progress-step-background: #add8e6;--swal2-validation-message-background: #f0f0f0;--swal2-validation-message-color: #666;--swal2-footer-border-color: #eee;--swal2-footer-background: transparent;--swal2-footer-color: inherit;--swal2-timer-progress-bar-background: rgba(0, 0, 0, 0.3);--swal2-close-button-position: initial;--swal2-close-button-inset: auto;--swal2-close-button-font-size: 2.5em;--swal2-close-button-color: #ccc;--swal2-close-button-transition: color 0.2s, box-shadow 0.2s;--swal2-close-button-outline: initial;--swal2-close-button-box-shadow: inset 0 0 0 3px transparent;--swal2-close-button-focus-box-shadow: inset var(--swal2-outline);--swal2-close-button-hover-transform: none;--swal2-actions-justify-content: center;--swal2-actions-width: auto;--swal2-actions-margin: 1.25em auto 0;--swal2-actions-padding: 0;--swal2-actions-border-radius: 0;--swal2-actions-background: transparent;--swal2-action-button-transition: background-color 0.2s, box-shadow 0.2s;--swal2-action-button-hover: black 10%;--swal2-action-button-active: black 10%;--swal2-confirm-button-box-shadow: none;--swal2-confirm-button-border-radius: 0.25em;--swal2-confirm-button-background-color: #7066e0;--swal2-confirm-button-color: #fff;--swal2-deny-button-box-shadow: none;--swal2-deny-button-border-radius: 0.25em;--swal2-deny-button-background-color: #dc3741;--swal2-deny-button-color: #fff;--swal2-cancel-button-box-shadow: none;--swal2-cancel-button-border-radius: 0.25em;--swal2-cancel-button-background-color: #6e7881;--swal2-cancel-button-color: #fff;--swal2-toast-show-animation: swal2-toast-show 0.5s;--swal2-toast-hide-animation: swal2-toast-hide 0.1s forwards;--swal2-toast-border: none;--swal2-toast-box-shadow: 0 0 1px hsl(0deg 0% 0% / 0.075), 0 1px 2px hsl(0deg 0% 0% / 0.075), 1px 2px 4px hsl(0deg 0% 0% / 0.075), 1px 3px 8px hsl(0deg 0% 0% / 0.075), 2px 4px 16px hsl(0deg 0% 0% / 0.075)}[data-swal2-theme=dark]{--swal2-dark-theme-black: #19191a;--swal2-dark-theme-white: #e1e1e1;--swal2-background: var(--swal2-dark-theme-black);--swal2-color: var(--swal2-dark-theme-white);--swal2-footer-border-color: #555;--swal2-input-background: color-mix(in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10%);--swal2-validation-message-background: color-mix( in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10% );--swal2-validation-message-color: var(--swal2-dark-theme-white);--swal2-timer-progress-bar-background: rgba(255, 255, 255, 0.7)}@media(prefers-color-scheme: dark){[data-swal2-theme=auto]{--swal2-dark-theme-black: #19191a;--swal2-dark-theme-white: #e1e1e1;--swal2-background: var(--swal2-dark-theme-black);--swal2-color: var(--swal2-dark-theme-white);--swal2-footer-border-color: #555;--swal2-input-background: color-mix(in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10%);--swal2-validation-message-background: color-mix( in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10% );--swal2-validation-message-color: var(--swal2-dark-theme-white);--swal2-timer-progress-bar-background: rgba(255, 255, 255, 0.7)}}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown){overflow:hidden}body.swal2-height-auto{height:auto !important}body.swal2-no-backdrop .swal2-container{background-color:rgba(0,0,0,0) !important;pointer-events:none}body.swal2-no-backdrop .swal2-container .swal2-popup{pointer-events:all}body.swal2-no-backdrop .swal2-container .swal2-modal{box-shadow:0 0 10px var(--swal2-backdrop)}body.swal2-toast-shown .swal2-container{box-sizing:border-box;width:360px;max-width:100%;background-color:rgba(0,0,0,0);pointer-events:none}body.swal2-toast-shown .swal2-container.swal2-top{inset:0 auto auto 50%;transform:translateX(-50%)}body.swal2-toast-shown .swal2-container.swal2-top-end,body.swal2-toast-shown .swal2-container.swal2-top-right{inset:0 0 auto auto}body.swal2-toast-shown .swal2-container.swal2-top-start,body.swal2-toast-shown .swal2-container.swal2-top-left{inset:0 auto auto 0}body.swal2-toast-shown .swal2-container.swal2-center-start,body.swal2-toast-shown .swal2-container.swal2-center-left{inset:50% auto auto 0;transform:translateY(-50%)}body.swal2-toast-shown .swal2-container.swal2-center{inset:50% auto auto 50%;transform:translate(-50%, -50%)}body.swal2-toast-shown .swal2-container.swal2-center-end,body.swal2-toast-shown .swal2-container.swal2-center-right{inset:50% 0 auto auto;transform:translateY(-50%)}body.swal2-toast-shown .swal2-container.swal2-bottom-start,body.swal2-toast-shown .swal2-container.swal2-bottom-left{inset:auto auto 0 0}body.swal2-toast-shown .swal2-container.swal2-bottom{inset:auto auto 0 50%;transform:translateX(-50%)}body.swal2-toast-shown .swal2-container.swal2-bottom-end,body.swal2-toast-shown .swal2-container.swal2-bottom-right{inset:auto 0 0 auto}@media print{body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown){overflow-y:scroll !important}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown)>[aria-hidden=true]{display:none}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown) .swal2-container{position:static !important}}div:where(.swal2-container){display:grid;position:fixed;z-index:1060;inset:0;box-sizing:border-box;grid-template-areas:"top-start     top            top-end" "center-start  center         center-end" "bottom-start  bottom-center  bottom-end";grid-template-rows:minmax(min-content, auto) minmax(min-content, auto) minmax(min-content, auto);height:100%;padding:var(--swal2-container-padding);overflow-x:hidden;transition:var(--swal2-backdrop-transition);-webkit-overflow-scrolling:touch}div:where(.swal2-container).swal2-backdrop-show,div:where(.swal2-container).swal2-noanimation{background:var(--swal2-backdrop)}div:where(.swal2-container).swal2-backdrop-hide{background:rgba(0,0,0,0) !important}div:where(.swal2-container).swal2-top-start,div:where(.swal2-container).swal2-center-start,div:where(.swal2-container).swal2-bottom-start{grid-template-columns:minmax(0, 1fr) auto auto}div:where(.swal2-container).swal2-top,div:where(.swal2-container).swal2-center,div:where(.swal2-container).swal2-bottom{grid-template-columns:auto minmax(0, 1fr) auto}div:where(.swal2-container).swal2-top-end,div:where(.swal2-container).swal2-center-end,div:where(.swal2-container).swal2-bottom-end{grid-template-columns:auto auto minmax(0, 1fr)}div:where(.swal2-container).swal2-top-start>.swal2-popup{align-self:start}div:where(.swal2-container).swal2-top>.swal2-popup{grid-column:2;place-self:start center}div:where(.swal2-container).swal2-top-end>.swal2-popup,div:where(.swal2-container).swal2-top-right>.swal2-popup{grid-column:3;place-self:start end}div:where(.swal2-container).swal2-center-start>.swal2-popup,div:where(.swal2-container).swal2-center-left>.swal2-popup{grid-row:2;align-self:center}div:where(.swal2-container).swal2-center>.swal2-popup{grid-column:2;grid-row:2;place-self:center center}div:where(.swal2-container).swal2-center-end>.swal2-popup,div:where(.swal2-container).swal2-center-right>.swal2-popup{grid-column:3;grid-row:2;place-self:center end}div:where(.swal2-container).swal2-bottom-start>.swal2-popup,div:where(.swal2-container).swal2-bottom-left>.swal2-popup{grid-column:1;grid-row:3;align-self:end}div:where(.swal2-container).swal2-bottom>.swal2-popup{grid-column:2;grid-row:3;place-self:end center}div:where(.swal2-container).swal2-bottom-end>.swal2-popup,div:where(.swal2-container).swal2-bottom-right>.swal2-popup{grid-column:3;grid-row:3;place-self:end end}div:where(.swal2-container).swal2-grow-row>.swal2-popup,div:where(.swal2-container).swal2-grow-fullscreen>.swal2-popup{grid-column:1/4;width:100%}div:where(.swal2-container).swal2-grow-column>.swal2-popup,div:where(.swal2-container).swal2-grow-fullscreen>.swal2-popup{grid-row:1/4;align-self:stretch}div:where(.swal2-container).swal2-no-transition{transition:none !important}div:where(.swal2-container)[popover]{width:auto;border:0}div:where(.swal2-container) div:where(.swal2-popup){display:none;position:relative;box-sizing:border-box;grid-template-columns:minmax(0, 100%);width:var(--swal2-width);max-width:100%;padding:var(--swal2-padding);border:var(--swal2-border);border-radius:var(--swal2-border-radius);background:var(--swal2-background);color:var(--swal2-color);font-family:inherit;font-size:1rem;container-name:swal2-popup}div:where(.swal2-container) div:where(.swal2-popup):focus{outline:none}div:where(.swal2-container) div:where(.swal2-popup).swal2-loading{overflow-y:hidden}div:where(.swal2-container) div:where(.swal2-popup).swal2-draggable{cursor:grab}div:where(.swal2-container) div:where(.swal2-popup).swal2-draggable div:where(.swal2-icon){cursor:grab}div:where(.swal2-container) div:where(.swal2-popup).swal2-dragging{cursor:grabbing}div:where(.swal2-container) div:where(.swal2-popup).swal2-dragging div:where(.swal2-icon){cursor:grabbing}div:where(.swal2-container) h2:where(.swal2-title){position:relative;max-width:100%;margin:0;padding:var(--swal2-title-padding);color:inherit;font-size:1.875em;font-weight:600;text-align:center;text-transform:none;overflow-wrap:break-word;cursor:initial}div:where(.swal2-container) div:where(.swal2-actions){display:flex;z-index:1;box-sizing:border-box;flex-wrap:wrap;align-items:center;justify-content:var(--swal2-actions-justify-content);width:var(--swal2-actions-width);margin:var(--swal2-actions-margin);padding:var(--swal2-actions-padding);border-radius:var(--swal2-actions-border-radius);background:var(--swal2-actions-background)}div:where(.swal2-container) div:where(.swal2-loader){display:none;align-items:center;justify-content:center;width:2.2em;height:2.2em;margin:0 1.875em;animation:swal2-rotate-loading 1.5s linear 0s infinite normal;border-width:.25em;border-style:solid;border-radius:100%;border-color:#2778c4 rgba(0,0,0,0) #2778c4 rgba(0,0,0,0)}div:where(.swal2-container) button:where(.swal2-styled){margin:.3125em;padding:.625em 1.1em;transition:var(--swal2-action-button-transition);border:none;box-shadow:0 0 0 3px rgba(0,0,0,0);font-weight:500}div:where(.swal2-container) button:where(.swal2-styled):not([disabled]){cursor:pointer}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm){border-radius:var(--swal2-confirm-button-border-radius);background:initial;background-color:var(--swal2-confirm-button-background-color);box-shadow:var(--swal2-confirm-button-box-shadow);color:var(--swal2-confirm-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm):hover{background-color:color-mix(in srgb, var(--swal2-confirm-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm):active{background-color:color-mix(in srgb, var(--swal2-confirm-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny){border-radius:var(--swal2-deny-button-border-radius);background:initial;background-color:var(--swal2-deny-button-background-color);box-shadow:var(--swal2-deny-button-box-shadow);color:var(--swal2-deny-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny):hover{background-color:color-mix(in srgb, var(--swal2-deny-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny):active{background-color:color-mix(in srgb, var(--swal2-deny-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel){border-radius:var(--swal2-cancel-button-border-radius);background:initial;background-color:var(--swal2-cancel-button-background-color);box-shadow:var(--swal2-cancel-button-box-shadow);color:var(--swal2-cancel-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel):hover{background-color:color-mix(in srgb, var(--swal2-cancel-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel):active{background-color:color-mix(in srgb, var(--swal2-cancel-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):focus-visible{outline:none;box-shadow:var(--swal2-action-button-focus-box-shadow)}div:where(.swal2-container) button:where(.swal2-styled)[disabled]:not(.swal2-loading){opacity:.4}div:where(.swal2-container) button:where(.swal2-styled)::-moz-focus-inner{border:0}div:where(.swal2-container) div:where(.swal2-footer){margin:1em 0 0;padding:1em 1em 0;border-top:1px solid var(--swal2-footer-border-color);background:var(--swal2-footer-background);color:var(--swal2-footer-color);font-size:1em;text-align:center;cursor:initial}div:where(.swal2-container) .swal2-timer-progress-bar-container{position:absolute;right:0;bottom:0;left:0;grid-column:auto !important;overflow:hidden;border-bottom-right-radius:var(--swal2-border-radius);border-bottom-left-radius:var(--swal2-border-radius)}div:where(.swal2-container) div:where(.swal2-timer-progress-bar){width:100%;height:.25em;background:var(--swal2-timer-progress-bar-background)}div:where(.swal2-container) img:where(.swal2-image){max-width:100%;margin:2em auto 1em;cursor:initial}div:where(.swal2-container) button:where(.swal2-close){position:var(--swal2-close-button-position);inset:var(--swal2-close-button-inset);z-index:2;align-items:center;justify-content:center;width:1.2em;height:1.2em;margin-top:0;margin-right:0;margin-bottom:-1.2em;padding:0;overflow:hidden;transition:var(--swal2-close-button-transition);border:none;border-radius:var(--swal2-border-radius);outline:var(--swal2-close-button-outline);background:rgba(0,0,0,0);color:var(--swal2-close-button-color);font-family:monospace;font-size:var(--swal2-close-button-font-size);cursor:pointer;justify-self:end}div:where(.swal2-container) button:where(.swal2-close):hover{transform:var(--swal2-close-button-hover-transform);background:rgba(0,0,0,0);color:#f27474}div:where(.swal2-container) button:where(.swal2-close):focus-visible{outline:none;box-shadow:var(--swal2-close-button-focus-box-shadow)}div:where(.swal2-container) button:where(.swal2-close)::-moz-focus-inner{border:0}div:where(.swal2-container) div:where(.swal2-html-container){z-index:1;justify-content:center;margin:0;padding:var(--swal2-html-container-padding);overflow:auto;color:inherit;font-size:1.125em;font-weight:normal;line-height:normal;text-align:center;overflow-wrap:break-word;word-break:break-word;cursor:initial}div:where(.swal2-container) input:where(.swal2-input),div:where(.swal2-container) input:where(.swal2-file),div:where(.swal2-container) textarea:where(.swal2-textarea),div:where(.swal2-container) select:where(.swal2-select),div:where(.swal2-container) div:where(.swal2-radio),div:where(.swal2-container) label:where(.swal2-checkbox){margin:1em 2em 3px}div:where(.swal2-container) input:where(.swal2-input),div:where(.swal2-container) input:where(.swal2-file),div:where(.swal2-container) textarea:where(.swal2-textarea){box-sizing:border-box;width:auto;transition:var(--swal2-input-transition);border:var(--swal2-input-border);border-radius:var(--swal2-input-border-radius);background:var(--swal2-input-background);box-shadow:var(--swal2-input-box-shadow);color:inherit;font-size:1.125em}div:where(.swal2-container) input:where(.swal2-input).swal2-inputerror,div:where(.swal2-container) input:where(.swal2-file).swal2-inputerror,div:where(.swal2-container) textarea:where(.swal2-textarea).swal2-inputerror{border-color:#f27474 !important;box-shadow:0 0 2px #f27474 !important}div:where(.swal2-container) input:where(.swal2-input):hover,div:where(.swal2-container) input:where(.swal2-file):hover,div:where(.swal2-container) textarea:where(.swal2-textarea):hover{box-shadow:var(--swal2-input-hover-box-shadow)}div:where(.swal2-container) input:where(.swal2-input):focus,div:where(.swal2-container) input:where(.swal2-file):focus,div:where(.swal2-container) textarea:where(.swal2-textarea):focus{border:var(--swal2-input-focus-border);outline:none;box-shadow:var(--swal2-input-focus-box-shadow)}div:where(.swal2-container) input:where(.swal2-input)::placeholder,div:where(.swal2-container) input:where(.swal2-file)::placeholder,div:where(.swal2-container) textarea:where(.swal2-textarea)::placeholder{color:#ccc}div:where(.swal2-container) .swal2-range{margin:1em 2em 3px;background:var(--swal2-background)}div:where(.swal2-container) .swal2-range input{width:80%}div:where(.swal2-container) .swal2-range output{width:20%;color:inherit;font-weight:600;text-align:center}div:where(.swal2-container) .swal2-range input,div:where(.swal2-container) .swal2-range output{height:2.625em;padding:0;font-size:1.125em;line-height:2.625em}div:where(.swal2-container) .swal2-input{height:2.625em;padding:0 .75em}div:where(.swal2-container) .swal2-file{width:75%;margin-right:auto;margin-left:auto;background:var(--swal2-input-background);font-size:1.125em}div:where(.swal2-container) .swal2-textarea{height:6.75em;padding:.75em}div:where(.swal2-container) .swal2-select{min-width:50%;max-width:100%;padding:.375em .625em;background:var(--swal2-input-background);color:inherit;font-size:1.125em}div:where(.swal2-container) .swal2-radio,div:where(.swal2-container) .swal2-checkbox{align-items:center;justify-content:center;background:var(--swal2-background);color:inherit}div:where(.swal2-container) .swal2-radio label,div:where(.swal2-container) .swal2-checkbox label{margin:0 .6em;font-size:1.125em}div:where(.swal2-container) .swal2-radio input,div:where(.swal2-container) .swal2-checkbox input{flex-shrink:0;margin:0 .4em}div:where(.swal2-container) label:where(.swal2-input-label){display:flex;justify-content:center;margin:1em auto 0}div:where(.swal2-container) div:where(.swal2-validation-message){align-items:center;justify-content:center;margin:1em 0 0;padding:.625em;overflow:hidden;background:var(--swal2-validation-message-background);color:var(--swal2-validation-message-color);font-size:1em;font-weight:300}div:where(.swal2-container) div:where(.swal2-validation-message)::before{content:"!";display:inline-block;width:1.5em;min-width:1.5em;height:1.5em;margin:0 .625em;border-radius:50%;background-color:#f27474;color:#fff;font-weight:600;line-height:1.5em;text-align:center}div:where(.swal2-container) .swal2-progress-steps{flex-wrap:wrap;align-items:center;max-width:100%;margin:1.25em auto;padding:0;background:rgba(0,0,0,0);font-weight:600}div:where(.swal2-container) .swal2-progress-steps li{display:inline-block;position:relative}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step{z-index:20;flex-shrink:0;width:2em;height:2em;border-radius:2em;background:#2778c4;color:#fff;line-height:2em;text-align:center}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step{background:#2778c4}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step~.swal2-progress-step{background:var(--swal2-progress-step-background);color:#fff}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step~.swal2-progress-step-line{background:var(--swal2-progress-step-background)}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step-line{z-index:10;flex-shrink:0;width:2.5em;height:.4em;margin:0 -1px;background:#2778c4}div:where(.swal2-icon){position:relative;box-sizing:content-box;justify-content:center;width:5em;height:5em;margin:2.5em auto .6em;zoom:var(--swal2-icon-zoom);border:.25em solid rgba(0,0,0,0);border-radius:50%;border-color:#000;font-family:inherit;line-height:5em;cursor:default;user-select:none}div:where(.swal2-icon) .swal2-icon-content{display:flex;align-items:center;font-size:3.75em}div:where(.swal2-icon).swal2-error{border-color:#f27474;color:#f27474}div:where(.swal2-icon).swal2-error .swal2-x-mark{position:relative;flex-grow:1}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line]{display:block;position:absolute;top:2.3125em;width:2.9375em;height:.3125em;border-radius:.125em;background-color:#f27474}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line][class$=left]{left:1.0625em;transform:rotate(45deg)}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line][class$=right]{right:1em;transform:rotate(-45deg)}@container swal2-popup style(--swal2-icon-animations:true){div:where(.swal2-icon).swal2-error.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-error.swal2-icon-show .swal2-x-mark{animation:swal2-animate-error-x-mark .5s}}div:where(.swal2-icon).swal2-warning{border-color:#f8bb86;color:#f8bb86}@container swal2-popup style(--swal2-icon-animations:true){div:where(.swal2-icon).swal2-warning.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-warning.swal2-icon-show .swal2-icon-content{animation:swal2-animate-i-mark .5s}}div:where(.swal2-icon).swal2-info{border-color:#3fc3ee;color:#3fc3ee}@container swal2-popup style(--swal2-icon-animations:true){div:where(.swal2-icon).swal2-info.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-info.swal2-icon-show .swal2-icon-content{animation:swal2-animate-i-mark .8s}}div:where(.swal2-icon).swal2-question{border-color:#87adbd;color:#87adbd}@container swal2-popup style(--swal2-icon-animations:true){div:where(.swal2-icon).swal2-question.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-question.swal2-icon-show .swal2-icon-content{animation:swal2-animate-question-mark .8s}}div:where(.swal2-icon).swal2-success{border-color:#a5dc86;color:#a5dc86}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line]{position:absolute;width:3.75em;height:7.5em;border-radius:50%}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line][class$=left]{top:-0.4375em;left:-2.0635em;transform:rotate(-45deg);transform-origin:3.75em 3.75em;border-radius:7.5em 0 0 7.5em}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line][class$=right]{top:-0.6875em;left:1.875em;transform:rotate(-45deg);transform-origin:0 3.75em;border-radius:0 7.5em 7.5em 0}div:where(.swal2-icon).swal2-success .swal2-success-ring{position:absolute;z-index:2;top:-0.25em;left:-0.25em;box-sizing:content-box;width:100%;height:100%;border:.25em solid rgba(165,220,134,.3);border-radius:50%}div:where(.swal2-icon).swal2-success .swal2-success-fix{position:absolute;z-index:1;top:.5em;left:1.625em;width:.4375em;height:5.625em;transform:rotate(-45deg)}div:where(.swal2-icon).swal2-success [class^=swal2-success-line]{display:block;position:absolute;z-index:2;height:.3125em;border-radius:.125em;background-color:#a5dc86}div:where(.swal2-icon).swal2-success [class^=swal2-success-line][class$=tip]{top:2.875em;left:.8125em;width:1.5625em;transform:rotate(45deg)}div:where(.swal2-icon).swal2-success [class^=swal2-success-line][class$=long]{top:2.375em;right:.5em;width:2.9375em;transform:rotate(-45deg)}@container swal2-popup style(--swal2-icon-animations:true){div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-line-tip{animation:swal2-animate-success-line-tip .75s}div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-line-long{animation:swal2-animate-success-line-long .75s}div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-circular-line-right{animation:swal2-rotate-success-circular-line 4.25s ease-in}}[class^=swal2]{-webkit-tap-highlight-color:rgba(0,0,0,0)}.swal2-show{animation:var(--swal2-show-animation)}.swal2-hide{animation:var(--swal2-hide-animation)}.swal2-noanimation{transition:none}.swal2-scrollbar-measure{position:absolute;top:-9999px;width:50px;height:50px;overflow:scroll}.swal2-rtl .swal2-close{margin-right:initial;margin-left:0}.swal2-rtl .swal2-timer-progress-bar{right:0;left:auto}.swal2-toast{box-sizing:border-box;grid-column:1/4 !important;grid-row:1/4 !important;grid-template-columns:min-content auto min-content;padding:1em;overflow-y:hidden;border:var(--swal2-toast-border);background:var(--swal2-background);box-shadow:var(--swal2-toast-box-shadow);pointer-events:all}.swal2-toast>*{grid-column:2}.swal2-toast h2:where(.swal2-title){margin:.5em 1em;padding:0;font-size:1em;text-align:initial}.swal2-toast .swal2-loading{justify-content:center}.swal2-toast input:where(.swal2-input){height:2em;margin:.5em;font-size:1em}.swal2-toast .swal2-validation-message{font-size:1em}.swal2-toast div:where(.swal2-footer){margin:.5em 0 0;padding:.5em 0 0;font-size:.8em}.swal2-toast button:where(.swal2-close){grid-column:3/3;grid-row:1/99;align-self:center;width:.8em;height:.8em;margin:0;font-size:2em}.swal2-toast div:where(.swal2-html-container){margin:.5em 1em;padding:0;overflow:initial;font-size:1em;text-align:initial}.swal2-toast div:where(.swal2-html-container):empty{padding:0}.swal2-toast .swal2-loader{grid-column:1;grid-row:1/99;align-self:center;width:2em;height:2em;margin:.25em}.swal2-toast .swal2-icon{grid-column:1;grid-row:1/99;align-self:center;width:2em;min-width:2em;height:2em;margin:0 .5em 0 0}.swal2-toast .swal2-icon .swal2-icon-content{display:flex;align-items:center;font-size:1.8em;font-weight:bold}.swal2-toast .swal2-icon.swal2-success .swal2-success-ring{width:2em;height:2em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line]{top:.875em;width:1.375em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line][class$=left]{left:.3125em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line][class$=right]{right:.3125em}.swal2-toast div:where(.swal2-actions){justify-content:flex-start;height:auto;margin:0;margin-top:.5em;padding:0 .5em}.swal2-toast button:where(.swal2-styled){margin:.25em .5em;padding:.4em .6em;font-size:1em}.swal2-toast .swal2-success{border-color:#a5dc86}.swal2-toast .swal2-success [class^=swal2-success-circular-line]{position:absolute;width:1.6em;height:3em;border-radius:50%}.swal2-toast .swal2-success [class^=swal2-success-circular-line][class$=left]{top:-0.8em;left:-0.5em;transform:rotate(-45deg);transform-origin:2em 2em;border-radius:4em 0 0 4em}.swal2-toast .swal2-success [class^=swal2-success-circular-line][class$=right]{top:-0.25em;left:.9375em;transform-origin:0 1.5em;border-radius:0 4em 4em 0}.swal2-toast .swal2-success .swal2-success-ring{width:2em;height:2em}.swal2-toast .swal2-success .swal2-success-fix{top:0;left:.4375em;width:.4375em;height:2.6875em}.swal2-toast .swal2-success [class^=swal2-success-line]{height:.3125em}.swal2-toast .swal2-success [class^=swal2-success-line][class$=tip]{top:1.125em;left:.1875em;width:.75em}.swal2-toast .swal2-success [class^=swal2-success-line][class$=long]{top:.9375em;right:.1875em;width:1.375em}@container swal2-popup style(--swal2-icon-animations:true){.swal2-toast .swal2-success.swal2-icon-show .swal2-success-line-tip{animation:swal2-toast-animate-success-line-tip .75s}.swal2-toast .swal2-success.swal2-icon-show .swal2-success-line-long{animation:swal2-toast-animate-success-line-long .75s}}.swal2-toast.swal2-show{animation:var(--swal2-toast-show-animation)}.swal2-toast.swal2-hide{animation:var(--swal2-toast-hide-animation)}@keyframes swal2-show{0%{transform:translate3d(0, -50px, 0) scale(0.9);opacity:0}100%{transform:translate3d(0, 0, 0) scale(1);opacity:1}}@keyframes swal2-hide{0%{transform:translate3d(0, 0, 0) scale(1);opacity:1}100%{transform:translate3d(0, -50px, 0) scale(0.9);opacity:0}}@keyframes swal2-animate-success-line-tip{0%{top:1.1875em;left:.0625em;width:0}54%{top:1.0625em;left:.125em;width:0}70%{top:2.1875em;left:-0.375em;width:3.125em}84%{top:3em;left:1.3125em;width:1.0625em}100%{top:2.8125em;left:.8125em;width:1.5625em}}@keyframes swal2-animate-success-line-long{0%{top:3.375em;right:2.875em;width:0}65%{top:3.375em;right:2.875em;width:0}84%{top:2.1875em;right:0;width:3.4375em}100%{top:2.375em;right:.5em;width:2.9375em}}@keyframes swal2-rotate-success-circular-line{0%{transform:rotate(-45deg)}5%{transform:rotate(-45deg)}12%{transform:rotate(-405deg)}100%{transform:rotate(-405deg)}}@keyframes swal2-animate-error-x-mark{0%{margin-top:1.625em;transform:scale(0.4);opacity:0}50%{margin-top:1.625em;transform:scale(0.4);opacity:0}80%{margin-top:-0.375em;transform:scale(1.15)}100%{margin-top:0;transform:scale(1);opacity:1}}@keyframes swal2-animate-error-icon{0%{transform:rotateX(100deg);opacity:0}100%{transform:rotateX(0deg);opacity:1}}@keyframes swal2-rotate-loading{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}@keyframes swal2-animate-question-mark{0%{transform:rotateY(-360deg)}100%{transform:rotateY(0)}}@keyframes swal2-animate-i-mark{0%{transform:rotateZ(45deg);opacity:0}25%{transform:rotateZ(-25deg);opacity:.4}50%{transform:rotateZ(15deg);opacity:.8}75%{transform:rotateZ(-5deg);opacity:1}100%{transform:rotateX(0);opacity:1}}@keyframes swal2-toast-show{0%{transform:translateY(-0.625em) rotateZ(2deg)}33%{transform:translateY(0) rotateZ(-2deg)}66%{transform:translateY(0.3125em) rotateZ(2deg)}100%{transform:translateY(0) rotateZ(0deg)}}@keyframes swal2-toast-hide{100%{transform:rotateZ(1deg);opacity:0}}@keyframes swal2-toast-animate-success-line-tip{0%{top:.5625em;left:.0625em;width:0}54%{top:.125em;left:.125em;width:0}70%{top:.625em;left:-0.25em;width:1.625em}84%{top:1.0625em;left:.75em;width:.5em}100%{top:1.125em;left:.1875em;width:.75em}}@keyframes swal2-toast-animate-success-line-long{0%{top:1.625em;right:1.375em;width:0}65%{top:1.25em;right:.9375em;width:0}84%{top:.9375em;right:0;width:1.125em}100%{top:.9375em;right:.1875em;width:1.375em}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="bg bg2"></div>
<div class="bg bg3"></div>
<div class="container" style="display:none">
    <header class="top">
        <div class="logo" id="logoEmpresa">PDL <span>SisGesNom</span>&reg;</div>
        <div class="headline">
            <div class="sub"><b>Sistema de Gestión de Nóminas</b> - Asistente de Instalación</div>
            <div class="badges">
                <span>Versión del sistema <?php echo INSTALLER_VERSION; ?></span>
                <span>Base de datos: <?php echo SYS_DBNAME; ?></span>
                <span>PHP <?php echo PHP_VERSION; ?></span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar wizard-titlebar">
            <i class="fas fa-wand-magic-sparkles tt-icon"></i>
            <span class="tt-text">Asistente de instalación y Despliegue</span>
            <button type="button" class="modal-close" id="wizardClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="steps-nav" id="stepsNav"></div>

        <div class="step-body">
            <div id="loader"><div class="spin"></div><p>Cargando...</p></div>
            <div id="wizardContent"></div>
        </div>
    </div>

    <footer id="footerEmpresa"><b class="fn">SisGesNom - Sistema de Gestión de Nóminas</b><br>Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;</footer>
</div>

<div class="modal-overlay" id="empresaModal" style="display:none">
    <div class="modal-heading">
        <div class="hh">DESPLIEGUE Y <span>CONFIGURACIONES</span></div>
        <div class="ss">SisgesNom - Sistema de Gestión de Nóminas</div>
        <div class="ss-copyright">Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;</div>
        <img src="../images/sigesnom.png" alt="SisGesNom" class="modal-logo">
    </div>
    <div class="modal-box">
        <div class="modal-titlebar">
            <i class="fas fa-building tt-icon"></i>
            <span class="tt-text">Asistente de instalación y Despliegue</span>
            <button type="button" class="modal-close" id="empresaClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="welcome-block">
                <div class="welcome-title">Bienvenido al Asistente de Instalaci&oacute;n de la Base de Datos de SisGesNom</div>
                <div class="welcome-sub">Este asistente te guiar&aacute; a trav&eacute;s del proceso de instalaci&oacute;n del Sistema de N&oacute;minas y Control de Trabajadores para tu instituci&oacute;n.</div>
            </div>
            <h3>&iquest;Para qui&eacute;n se configurar&aacute; SisGesNom°?</h3>
            <p>Escriba el nombre de la empresa, entidad, Mypyme, PDL, TCP. Si pulsa Cancelar se usar&aacute; &laquo;Sin Nombre&raquo;.</p>
            <input class="form-control" id="empresaInput" placeholder="Nombre de la empresa" maxlength="120">
            <div class="actions">
                <button class="btn btn-primary" id="empresaOk"><i class="fas fa-check"></i> Aceptar</button>
                <button class="btn btn-secondary" id="empresaCancel"><i class="fas fa-times"></i> Cancelar</button>
				<button class="btn btn-secondary" id="closeapp" onclick="cerrarApp()"><i class="fa-solid fa-door-open"></i> Salir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="adminModal" style="display:none">
    <div class="modal-heading">
        <div class="hh">DESPLIEGUE Y <span>CONFIGURACIONES</span></div>
        <div class="ss">SisgesNom - Sistema de Gestión de Nóminas</div>
        <div class="ss-copyright">Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;</div>
        <img src="../images/sigesnom.png" alt="SisGesNom" class="modal-logo">
    </div>
    <div class="modal-box modal-wide">
        <div class="modal-titlebar">
            <i class="fas fa-user-shield tt-icon"></i>
            <span class="tt-text">Asistente de instalación y Despliegue</span>
            <button type="button" class="modal-close" id="adminClose" aria-label="Cerrar" onclick="closeAdminModal()">&times;</button>
        </div>
        <div class="modal-body admin-body">
            <h3>Crear usuario administrador</h3>
            <p>Defina el nombre de usuario y la contraseña del administrador del sistema.</p>
            <div class="admin-field">
                <label for="adminUser"><i class="fa-solid fa-user"></i>&nbsp;Nombre de usuario</label>
                <input class="form-control" id="adminUser" placeholder="Ej: superadmin" maxlength="50" autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();createAdminUser();}else if(event.key==='Escape'){event.preventDefault();closeAdminModal();}">
            </div>
            <div class="admin-field">
                <label for="adminPass"><i class="fa-solid fa-lock"></i>&nbsp;Contraseña</label>
                <div class="password-wrapper">
                    <input class="form-control" type="password" id="adminPass" placeholder="Mínimo 6 caracteres" autocomplete="new-password" oninput="updateAdminStrength()" onkeydown="if(event.key==='Enter'){event.preventDefault();createAdminUser();}else if(event.key==='Escape'){event.preventDefault();closeAdminModal();}">
                    <button type="button" class="password-toggle" onclick="togglePass('adminPass', this)" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="strength-meter" id="strengthBar"><span></span><span></span><span></span><span></span></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>
            <div class="admin-field">
                <label for="adminPass2"><i class="fa-solid fa-lock"></i>&nbsp;Repita la contraseña</label>
                <div class="password-wrapper">
                    <input class="form-control" type="password" id="adminPass2" placeholder="Repita la contraseña" autocomplete="new-password" onkeydown="if(event.key==='Enter'){event.preventDefault();createAdminUser();}else if(event.key==='Escape'){event.preventDefault();closeAdminModal();}">
                    <button type="button" class="password-toggle" onclick="togglePass('adminPass2', this)" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div class="admin-msg" id="adminMsg" style="display:none"></div>
            <div class="actions">
                <button class="btn btn-primary" id="adminOk" onclick="createAdminUser()"><i class="fas fa-check"></i> Aceptar</button>
                <button class="btn btn-secondary" id="adminCancel" onclick="closeAdminModal()"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>/*!
* sweetalert2 v11.26.25
* Released under the MIT License.
*/
!function(e,t){"object"==typeof exports&&"undefined"!=typeof module?module.exports=t():"function"==typeof define&&define.amd?define(t):(e="undefined"!=typeof globalThis?globalThis:e||self).Sweetalert2=t()}(this,function(){"use strict";function e(e,t,n){if("function"==typeof e?e===t:e.has(t))return arguments.length<3?t:n;throw new TypeError("Private element is not present on this object")}function t(t,n){return t.get(e(t,n))}function n(e,t,n){(function(e,t){if(t.has(e))throw new TypeError("Cannot initialize the same private elements twice on an object")})(e,t),t.set(e,n)}const o={},i=e=>new Promise(t=>{if(!e)return t();const n=window.scrollX,i=window.scrollY;o.restoreFocusTimeout=setTimeout(()=>{o.previousActiveElement instanceof HTMLElement?(o.previousActiveElement.focus(),o.previousActiveElement=null):document.body&&document.body.focus(),t()},100),window.scrollTo(n,i)}),s="swal2-",r=["container","shown","height-auto","iosfix","popup","modal","no-backdrop","no-transition","toast","toast-shown","show","hide","close","title","html-container","actions","confirm","deny","cancel","footer","icon","icon-content","image","input","file","range","select","radio","checkbox","label","textarea","inputerror","input-label","validation-message","progress-steps","active-progress-step","progress-step","progress-step-line","loader","loading","styled","top","top-start","top-end","top-left","top-right","center","center-start","center-end","center-left","center-right","bottom","bottom-start","bottom-end","bottom-left","bottom-right","grow-row","grow-column","grow-fullscreen","rtl","timer-progress-bar","timer-progress-bar-container","scrollbar-measure","icon-success","icon-warning","icon-info","icon-question","icon-error","draggable","dragging"].reduce((e,t)=>(e[t]=s+t,e),{}),a=["success","warning","info","question","error"].reduce((e,t)=>(e[t]=s+t,e),{}),l="SweetAlert2:",c=e=>e.charAt(0).toUpperCase()+e.slice(1),u=e=>{console.warn(`${l} ${"object"==typeof e?e.join(" "):e}`)},d=e=>{console.error(`${l} ${e}`)},p=[],m=(e,t=null)=>{var n;n=`"${e}" is deprecated and will be removed in the next major release.${t?` Use "${t}" instead.`:""}`,p.includes(n)||(p.push(n),u(n))},h=e=>"function"==typeof e?e():e,g=e=>e&&"function"==typeof e.toPromise,f=e=>g(e)?e.toPromise():Promise.resolve(e),b=e=>e&&Promise.resolve(e)===e,y=()=>document.body.querySelector(`.${r.container}`),v=e=>{const t=y();return t?t.querySelector(e):null},w=e=>v(`.${e}`),C=()=>w(r.popup),A=()=>w(r.icon),E=()=>w(r.title),k=()=>w(r["html-container"]),B=()=>w(r.image),$=()=>w(r["progress-steps"]),L=()=>w(r["validation-message"]),P=()=>v(`.${r.actions} .${r.confirm}`),x=()=>v(`.${r.actions} .${r.cancel}`),T=()=>v(`.${r.actions} .${r.deny}`),S=()=>v(`.${r.loader}`),O=()=>w(r.actions),M=()=>w(r.footer),H=()=>w(r["timer-progress-bar"]),j=()=>w(r.close),I=()=>{const e=C();if(!e)return[];const t=e.querySelectorAll('[tabindex]:not([tabindex="-1"]):not([tabindex="0"])'),n=Array.from(t).sort((e,t)=>{const n=parseInt(e.getAttribute("tabindex")||"0"),o=parseInt(t.getAttribute("tabindex")||"0");return n>o?1:n<o?-1:0}),o=e.querySelectorAll('\n  a[href],\n  area[href],\n  input:not([disabled]),\n  select:not([disabled]),\n  textarea:not([disabled]),\n  button:not([disabled]),\n  iframe,\n  object,\n  embed,\n  [tabindex="0"],\n  [contenteditable],\n  audio[controls],\n  video[controls],\n  summary\n'),i=Array.from(o).filter(e=>"-1"!==e.getAttribute("tabindex"));return[...new Set(n.concat(i))].filter(e=>ee(e))},D=()=>N(document.body,r.shown)&&!N(document.body,r["toast-shown"])&&!N(document.body,r["no-backdrop"]),V=()=>{const e=C();return!!e&&N(e,r.toast)},q=(e,t)=>{if(e.textContent="",t){const n=(new DOMParser).parseFromString(t,"text/html"),o=n.querySelector("head");o&&Array.from(o.childNodes).forEach(t=>{e.appendChild(t)});const i=n.querySelector("body");i&&Array.from(i.childNodes).forEach(t=>{t instanceof HTMLVideoElement||t instanceof HTMLAudioElement?e.appendChild(t.cloneNode(!0)):e.appendChild(t)})}},N=(e,t)=>!!t&&t.split(/\s+/).every(t=>e.classList.contains(t)),_=(e,t,n)=>{if(((e,t)=>{Array.from(e.classList).forEach(n=>{Object.values(r).includes(n)||Object.values(a).includes(n)||Object.values(t.showClass||{}).includes(n)||e.classList.remove(n)})})(e,t),!t.customClass)return;const o=t.customClass[n];o&&("string"==typeof o||o.forEach?z(e,o):u(`Invalid type of customClass.${n}! Expected string or iterable object, got "${typeof o}"`))},F=(e,t)=>{if(!t)return null;switch(t){case"select":case"textarea":case"file":return e.querySelector(`.${r.popup} > .${r[t]}`);case"checkbox":return e.querySelector(`.${r.popup} > .${r.checkbox} input`);case"radio":return e.querySelector(`.${r.popup} > .${r.radio} input:checked`)||e.querySelector(`.${r.popup} > .${r.radio} input:first-child`);case"range":return e.querySelector(`.${r.popup} > .${r.range} input`);default:return e.querySelector(`.${r.popup} > .${r.input}`)}},R=e=>{if(e.focus(),"file"!==e.type){const t=e.value;e.value="",e.value=t}},U=(e,t,n)=>{if(!e||!t)return;const o="string"==typeof t?t.split(/\s+/).filter(Boolean):t;(Array.isArray(e)?e:[e]).forEach(e=>{o.forEach(t=>{n?e.classList.add(t):e.classList.remove(t)})})},z=(e,t)=>{U(e,t,!0)},W=(e,t)=>{U(e,t,!1)},K=(e,t)=>Array.from(e.children).find(e=>e instanceof HTMLElement&&N(e,t)),Y=(e,t,n)=>{n===`${parseInt(`${n}`)}`&&(n=parseInt(n)),n||0===n?e.style.setProperty(t,"number"==typeof n?`${n}px`:n):e.style.removeProperty(t)},X=(e,t="flex")=>{e&&(e.style.display=t)},Z=e=>{e&&(e.style.display="none")},J=(e,t="block")=>{e&&new MutationObserver(()=>{Q(e,e.innerHTML,t)}).observe(e,{childList:!0,subtree:!0})},G=(e,t,n,o)=>{const i=e.querySelector(t);i&&i.style.setProperty(n,o)},Q=(e,t,n="flex")=>{t?X(e,n):Z(e)},ee=e=>Boolean(e&&(e.offsetWidth||e.offsetHeight||e.getClientRects().length)),te=e=>Boolean(e.scrollHeight>e.clientHeight),ne=e=>{const t=window.getComputedStyle(e),n=parseFloat(t.getPropertyValue("animation-duration")||"0"),o=parseFloat(t.getPropertyValue("transition-duration")||"0");return n>0||o>0},oe=(e,t=!1)=>{const n=H();n&&ee(n)&&(t&&(n.style.transition="none",n.style.width="100%"),setTimeout(()=>{n.style.transition=`width ${e/1e3}s linear`,n.style.width="0%"},10))},ie=`\n <div aria-labelledby="${r.title}" aria-describedby="${r["html-container"]}" class="${r.popup}" tabindex="-1">\n   <button type="button" class="${r.close}"></button>\n   <ul class="${r["progress-steps"]}"></ul>\n   <div class="${r.icon}"></div>\n   <img class="${r.image}" />\n   <h2 class="${r.title}" id="${r.title}"></h2>\n   <div class="${r["html-container"]}" id="${r["html-container"]}"></div>\n   <input class="${r.input}" id="${r.input}" />\n   <input type="file" class="${r.file}" />\n   <div class="${r.range}">\n     <input type="range" />\n     <output></output>\n   </div>\n   <select class="${r.select}" id="${r.select}"></select>\n   <div class="${r.radio}"></div>\n   <label class="${r.checkbox}">\n     <input type="checkbox" id="${r.checkbox}" />\n     <span class="${r.label}"></span>\n   </label>\n   <textarea class="${r.textarea}" id="${r.textarea}"></textarea>\n   <div class="${r["validation-message"]}" id="${r["validation-message"]}"></div>\n   <div class="${r.actions}">\n     <div class="${r.loader}"></div>\n     <button type="button" class="${r.confirm}"></button>\n     <button type="button" class="${r.deny}"></button>\n     <button type="button" class="${r.cancel}"></button>\n   </div>\n   <div class="${r.footer}"></div>\n   <div class="${r["timer-progress-bar-container"]}">\n     <div class="${r["timer-progress-bar"]}"></div>\n   </div>\n </div>\n`.replace(/(^|\n)\s*/g,""),se=()=>{o.currentInstance&&o.currentInstance.resetValidationMessage()},re=e=>{const t=(()=>{const e=y();return!!e&&(e.remove(),W([document.documentElement,document.body],[r["no-backdrop"],r["toast-shown"],r["has-column"]]),!0)})();if("undefined"==typeof window||"undefined"==typeof document)return void d("SweetAlert2 requires document to initialize");const n=document.createElement("div");n.className=r.container,t&&z(n,r["no-transition"]),q(n,ie),n.dataset.swal2Theme=e.theme;const i=(e=>{if("string"==typeof e){const t=document.querySelector(e);if(!t)throw new Error(`Target element "${e}" not found`);return t}return e})(e.target||"body");i.appendChild(n),e.topLayer&&(n.setAttribute("popover",""),n.showPopover()),(e=>{const t=C();t&&(t.setAttribute("role",e.toast?"alert":"dialog"),t.setAttribute("aria-live",e.toast?"polite":"assertive"),e.toast||t.setAttribute("aria-modal","true"))})(e),(e=>{"rtl"===window.getComputedStyle(e).direction&&(z(y(),r.rtl),o.isRTL=!0)})(i),(()=>{const e=C();if(!e)return;const t=K(e,r.input),n=K(e,r.file),o=e.querySelector(`.${r.range} input`),i=e.querySelector(`.${r.range} output`),s=K(e,r.select),a=e.querySelector(`.${r.checkbox} input`),l=K(e,r.textarea);t&&(t.oninput=se),n&&(n.onchange=se),s&&(s.onchange=se),a&&(a.onchange=se),l&&(l.oninput=se),o&&i&&(o.oninput=()=>{se(),i.value=o.value},o.onchange=()=>{se(),i.value=o.value})})()},ae=(e,t)=>{e instanceof HTMLElement?t.appendChild(e):"object"==typeof e?le(e,t):e&&q(t,e)},le=(e,t)=>{"jquery"in e?ce(t,e):q(t,e.toString())},ce=(e,t)=>{if(e.textContent="",0 in t)for(let n=0;n in t;n++)e.appendChild(t[n].cloneNode(!0));else e.appendChild(t.cloneNode(!0))},ue=(e,t)=>{const n=O(),o=S();n&&o&&(t.showConfirmButton||t.showDenyButton||t.showCancelButton?X(n):Z(n),_(n,t,"actions"),function(e,t,n){const o=P(),i=T(),s=x();if(!o||!i||!s)return;de(o,"confirm",n),de(i,"deny",n),de(s,"cancel",n),function(e,t,n,o){if(!o.buttonsStyling)return void W([e,t,n],r.styled);z([e,t,n],r.styled);const i=[[e,"confirm",o.confirmButtonColor],[t,"deny",o.denyButtonColor],[n,"cancel",o.cancelButtonColor]];i.forEach(([e,t,n])=>{n&&e.style.setProperty(`--swal2-${t}-button-background-color`,n),function(e){const t=window.getComputedStyle(e);if(t.getPropertyValue("--swal2-action-button-focus-box-shadow"))return;const n=t.backgroundColor.replace(/rgba?\((\d+), (\d+), (\d+).*/,"rgba($1, $2, $3, 0.5)");e.style.setProperty("--swal2-action-button-focus-box-shadow",t.getPropertyValue("--swal2-outline").replace(/ rgba\(.*/,` ${n}`))}(e)})}(o,i,s,n),n.reverseButtons&&(n.toast?(e.insertBefore(s,o),e.insertBefore(i,o)):(e.insertBefore(s,t),e.insertBefore(i,t),e.insertBefore(o,t)))}(n,o,t),q(o,t.loaderHtml||""),_(o,t,"loader"))};function de(e,t,n){const o=c(t);Q(e,n[`show${o}Button`],"inline-block"),q(e,n[`${t}ButtonText`]||""),e.setAttribute("aria-label",n[`${t}ButtonAriaLabel`]||""),e.className=r[t],_(e,n,`${t}Button`)}const pe=(e,t)=>{const n=y();n&&(!function(e,t){"string"==typeof t?e.style.background=t:t||z([document.documentElement,document.body],r["no-backdrop"])}(n,t.backdrop),function(e,t){if(!t)return;t in r?z(e,r[t]):(u('The "position" parameter is not valid, defaulting to "center"'),z(e,r.center))}(n,t.position),function(e,t){if(!t)return;z(e,r[`grow-${t}`])}(n,t.grow),_(n,t,"container"))};var me={innerParams:new WeakMap,domCache:new WeakMap,focusedElement:new WeakMap};const he=["input","file","range","select","radio","checkbox","textarea"],ge=e=>{if(!e.input)return;if(!Ae[e.input])return void d(`Unexpected type of input! Expected ${Object.keys(Ae).join(" | ")}, got "${e.input}"`);const t=we(e.input);if(!t)return;const n=Ae[e.input](t,e);X(t),e.inputAutoFocus&&setTimeout(()=>{R(n)})},fe=(e,t)=>{const n=C();if(!n)return;const o=F(n,e);if(o){(e=>{for(const{name:t}of Array.from(e.attributes))["id","type","value","style"].includes(t)||e.removeAttribute(t)})(o);for(const e in t)o.setAttribute(e,t[e])}},be=e=>{if(!e.input)return;const t=we(e.input);t&&_(t,e,"input")},ye=(e,t)=>{!e.placeholder&&t.inputPlaceholder&&(e.placeholder=t.inputPlaceholder)},ve=(e,t,n)=>{if(n.inputLabel){const o=document.createElement("label"),i=r["input-label"];o.setAttribute("for",e.id),o.className=i,"object"==typeof n.customClass&&z(o,n.customClass.inputLabel),o.innerText=n.inputLabel,t.insertAdjacentElement("beforebegin",o)}},we=e=>{const t=C();if(t)return K(t,r[e]||r.input)},Ce=(e,t)=>{["string","number"].includes(typeof t)?e.value=`${t}`:b(t)||u(`Unexpected type of inputValue! Expected "string", "number" or "Promise", got "${typeof t}"`)},Ae={};Ae.text=Ae.email=Ae.password=Ae.number=Ae.tel=Ae.url=Ae.search=Ae.date=Ae["datetime-local"]=Ae.time=Ae.week=Ae.month=(e,t)=>{const n=e;return Ce(n,t.inputValue),ve(n,n,t),ye(n,t),n.type=t.input,n},Ae.file=(e,t)=>{const n=e;return ve(n,n,t),ye(n,t),n},Ae.range=(e,t)=>{const n=e,o=n.querySelector("input"),i=n.querySelector("output");return o&&(Ce(o,t.inputValue),o.type=t.input,ve(o,e,t)),i&&Ce(i,t.inputValue),e},Ae.select=(e,t)=>{const n=e;if(n.textContent="",t.inputPlaceholder){const e=document.createElement("option");q(e,t.inputPlaceholder),e.value="",e.disabled=!0,e.selected=!0,n.appendChild(e)}return ve(n,n,t),n},Ae.radio=e=>(e.textContent="",e),Ae.checkbox=(e,t)=>{const n=C();if(!n)throw new Error("Popup not found");const o=F(n,"checkbox");if(!o)throw new Error("Checkbox input not found");o.value="1",o.checked=Boolean(t.inputValue);const i=e.querySelector("span");if(i){const e=t.inputPlaceholder||t.inputLabel;e&&q(i,e)}return o},Ae.textarea=(e,t)=>{const n=e;Ce(n,t.inputValue),ye(n,t),ve(n,n,t);return setTimeout(()=>{if("MutationObserver"in window){const e=C();if(!e)return;const o=parseInt(window.getComputedStyle(e).width);new MutationObserver(()=>{if(!document.body.contains(n))return;const e=n.offsetWidth+(i=n,parseInt(window.getComputedStyle(i).marginLeft)+parseInt(window.getComputedStyle(i).marginRight));var i;const s=C();s&&(e>o?s.style.width=`${e}px`:Y(s,"width",t.width))}).observe(n,{attributes:!0,attributeFilter:["style"]})}}),n};const Ee=(e,t)=>{const n=k();n&&(J(n),_(n,t,"htmlContainer"),t.html?(ae(t.html,n),X(n,"block")):t.text?(n.textContent=t.text,X(n,"block")):Z(n),((e,t)=>{const n=C();if(!n)return;const o=me.innerParams.get(e),i=!o||t.input!==o.input;he.forEach(e=>{const o=K(n,r[e]);o&&(fe(e,t.inputAttributes),o.className=r[e],i&&Z(o))}),t.input&&(i&&ge(t),be(t))})(e,t))},ke=(e,t)=>{for(const[n,o]of Object.entries(a))t.icon!==n&&W(e,o);z(e,t.icon&&a[t.icon]),Le(e,t),Be(),_(e,t,"icon")},Be=()=>{const e=C();if(!e)return;const t=window.getComputedStyle(e).getPropertyValue("background-color");e.querySelectorAll("[class^=swal2-success-circular-line], .swal2-success-fix").forEach(e=>{e.style.backgroundColor=t})},$e=(e,t)=>{if(!t.icon&&!t.iconHtml)return;let n=e.innerHTML,o="";if(t.iconHtml)o=Pe(t.iconHtml);else if("success"===t.icon)o=(e=>`\n  ${e.animation?'<div class="swal2-success-circular-line-left"></div>':""}\n  <span class="swal2-success-line-tip"></span> <span class="swal2-success-line-long"></span>\n  <div class="swal2-success-ring"></div>\n  ${e.animation?'<div class="swal2-success-fix"></div>':""}\n  ${e.animation?'<div class="swal2-success-circular-line-right"></div>':""}\n`)(t),n=n.replace(/ style=".*?"/g,"");else if("error"===t.icon)o='\n  <span class="swal2-x-mark">\n    <span class="swal2-x-mark-line-left"></span>\n    <span class="swal2-x-mark-line-right"></span>\n  </span>\n';else if(t.icon){o=Pe({question:"?",warning:"!",info:"i"}[t.icon])}n.trim()!==o.trim()&&q(e,o)},Le=(e,t)=>{if(t.iconColor){e.style.color=t.iconColor,e.style.borderColor=t.iconColor;for(const n of[".swal2-success-line-tip",".swal2-success-line-long",".swal2-x-mark-line-left",".swal2-x-mark-line-right"])G(e,n,"background-color",t.iconColor);G(e,".swal2-success-ring","border-color",t.iconColor)}},Pe=e=>`<div class="${r["icon-content"]}">${e}</div>`;let xe=!1,Te=0,Se=0,Oe=0,Me=0;const He=e=>{const t=C();if(!t)return;const n=A();if(e.target===t||n&&n.contains(e.target)){xe=!0;const n=De(e);Te=n.clientX,Se=n.clientY,Oe=parseInt(t.style.insetInlineStart)||0,Me=parseInt(t.style.insetBlockStart)||0,z(t,"swal2-dragging")}},je=e=>{const t=C();if(t&&xe){let{clientX:n,clientY:i}=De(e);const s=n-Te;t.style.insetInlineStart=`${Oe+(o.isRTL?-s:s)}px`,t.style.insetBlockStart=`${Me+(i-Se)}px`}},Ie=()=>{const e=C();xe=!1,W(e,"swal2-dragging")},De=e=>{const t=e.type.startsWith("touch")?e.touches[0]:e;return{clientX:t.clientX,clientY:t.clientY}},Ve=(e,t)=>{const n=y(),o=C();if(n&&o){if(t.toast){Y(n,"width",t.width),o.style.width="100%";const e=S();e&&o.insertBefore(e,A())}else Y(o,"width",t.width);Y(o,"padding",t.padding),t.color&&(o.style.color=t.color),t.background&&(o.style.background=t.background),Z(L()),qe(o,t),t.draggable&&!t.toast?(z(o,r.draggable),(e=>{e.addEventListener("mousedown",He),document.body.addEventListener("mousemove",je),e.addEventListener("mouseup",Ie),e.addEventListener("touchstart",He),document.body.addEventListener("touchmove",je),e.addEventListener("touchend",Ie)})(o)):(W(o,r.draggable),(e=>{e.removeEventListener("mousedown",He),document.body.removeEventListener("mousemove",je),e.removeEventListener("mouseup",Ie),e.removeEventListener("touchstart",He),document.body.removeEventListener("touchmove",je),e.removeEventListener("touchend",Ie)})(o))}},qe=(e,t)=>{const n=t.showClass||{};e.className=`${r.popup} ${ee(e)?n.popup:""}`,t.toast?(z([document.documentElement,document.body],r["toast-shown"]),z(e,r.toast)):z(e,r.modal),_(e,t,"popup"),"string"==typeof t.customClass&&z(e,t.customClass),t.icon&&z(e,r[`icon-${t.icon}`])},Ne=e=>{const t=document.createElement("li");return z(t,r["progress-step"]),q(t,e),t},_e=e=>{const t=document.createElement("li");return z(t,r["progress-step-line"]),e.progressStepsDistance&&Y(t,"width",e.progressStepsDistance),t},Fe=(e,t)=>{var n;Ve(0,t),pe(0,t),((e,t)=>{const n=$();if(!n)return;const{progressSteps:o,currentProgressStep:i}=t;o&&0!==o.length&&void 0!==i?(X(n),n.textContent="",i>=o.length&&u("Invalid currentProgressStep parameter, it should be less than progressSteps.length (currentProgressStep like JS arrays starts from 0)"),o.forEach((e,s)=>{const a=Ne(e);if(n.appendChild(a),s===i&&z(a,r["active-progress-step"]),s!==o.length-1){const e=_e(t);n.appendChild(e)}})):Z(n)})(0,t),((e,t)=>{const n=me.innerParams.get(e),o=A();if(!o)return;if(n&&t.icon===n.icon)return $e(o,t),void ke(o,t);if(!t.icon&&!t.iconHtml)return void Z(o);if(t.icon&&-1===Object.keys(a).indexOf(t.icon))return d(`Unknown icon! Expected "success", "error", "warning", "info" or "question", got "${t.icon}"`),void Z(o);X(o),$e(o,t),ke(o,t),z(o,t.showClass&&t.showClass.icon),window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change",Be)})(e,t),((e,t)=>{const n=B();n&&(t.imageUrl?(X(n,""),n.setAttribute("src",t.imageUrl),n.setAttribute("alt",t.imageAlt||""),Y(n,"width",t.imageWidth),Y(n,"height",t.imageHeight),n.className=r.image,_(n,t,"image")):Z(n))})(0,t),((e,t)=>{const n=E();n&&(J(n),Q(n,Boolean(t.title||t.titleText),"block"),t.title&&ae(t.title,n),t.titleText&&(n.innerText=t.titleText),_(n,t,"title"))})(0,t),((e,t)=>{const n=j();n&&(q(n,t.closeButtonHtml||""),_(n,t,"closeButton"),Q(n,t.showCloseButton),n.setAttribute("aria-label",t.closeButtonAriaLabel||""))})(0,t),Ee(e,t),ue(0,t),((e,t)=>{const n=M();n&&(J(n),Q(n,Boolean(t.footer),"block"),t.footer&&ae(t.footer,n),_(n,t,"footer"))})(0,t);const i=C();"function"==typeof t.didRender&&i&&t.didRender(i),null===(n=o.eventEmitter)||void 0===n||n.emit("didRender",i)},Re=()=>{var e;return null===(e=P())||void 0===e?void 0:e.click()},Ue=Object.freeze({cancel:"cancel",backdrop:"backdrop",close:"close",esc:"esc",timer:"timer"}),ze=e=>{if(e.keydownTarget&&e.keydownHandlerAdded&&e.keydownHandler){const t=e.keydownHandler;e.keydownTarget.removeEventListener("keydown",t,{capture:e.keydownListenerCapture}),e.keydownHandlerAdded=!1}},We=(e,t)=>{var n;const o=I();return o.length?(-2===(e+=t)&&(e=o.length-1),e===o.length?e=0:-1===e&&(e=o.length-1),o[e].focus(),!(navigator.userAgent.includes("Firefox")&&o[e]instanceof HTMLIFrameElement)):(null===(n=C())||void 0===n||n.focus(),!0)},Ke=["ArrowRight","ArrowDown"],Ye=["ArrowLeft","ArrowUp"],Xe=(e,t,n)=>{e&&(t.isComposing||229===t.keyCode||(e.stopKeydownPropagation&&t.stopPropagation(),"Enter"===t.key?Ze(t,e):"Tab"===t.key?Je(t):[...Ke,...Ye].includes(t.key)?Ge(t.key):"Escape"===t.key&&Qe(t,e,n)))},Ze=(e,t)=>{if(!h(t.allowEnterKey))return;const n=C();if(!n||!t.input)return;const o=F(n,t.input);if(e.target&&o&&e.target instanceof HTMLElement&&e.target.outerHTML===o.outerHTML){if(["textarea","file"].includes(t.input))return;Re(),e.preventDefault()}},Je=e=>{const t=e.target,n=I().findIndex(e=>e===t);let o=!0;o=e.shiftKey?We(n,-1):We(n,1),e.stopPropagation(),o&&e.preventDefault()},Ge=e=>{const t=O(),n=P(),o=T(),i=x();if(!(t&&n&&o&&i))return;const s=[n,o,i];if(document.activeElement instanceof HTMLElement&&!s.includes(document.activeElement))return;const r=Ke.includes(e)?"nextElementSibling":"previousElementSibling";let a=document.activeElement;if(a){for(let e=0;e<t.children.length;e++){if(a=a[r],!a)return;if(a instanceof HTMLButtonElement&&ee(a))break}a instanceof HTMLButtonElement&&a.focus()}},Qe=(e,t,n)=>{e.preventDefault(),h(t.allowEscapeKey)&&n(Ue.esc)};var et={swalPromiseResolve:new WeakMap,swalPromiseReject:new WeakMap};const tt=()=>{Array.from(document.body.children).forEach(e=>{e.hasAttribute("data-previous-aria-hidden")?(e.setAttribute("aria-hidden",e.getAttribute("data-previous-aria-hidden")||""),e.removeAttribute("data-previous-aria-hidden")):e.removeAttribute("aria-hidden")})},nt="undefined"!=typeof window&&Boolean(window.GestureEvent),ot=nt&&/iPad|iPhone|iPod/.test(navigator.userAgent)&&!window.MSStream,it=()=>{const e=y();if(!e)return;let t;e.ontouchstart=e=>{t=st(e)},e.ontouchmove=e=>{t&&(e.preventDefault(),e.stopPropagation())}},st=e=>{const t=e.target,n=y(),o=k();return!(!n||!o)&&(!rt(e)&&!at(e)&&(t===n||!(te(n)||!(t instanceof HTMLElement)||((e,t)=>{let n=e;for(;n&&n!==t;){if(te(n))return!0;n=n.parentElement}return!1})(t,o)||"INPUT"===t.tagName||"TEXTAREA"===t.tagName||te(o)&&o.contains(t))))},rt=e=>Boolean(e.touches&&e.touches.length&&"stylus"===e.touches[0].touchType),at=e=>e.touches&&e.touches.length>1;let lt=null;const ct=e=>{null===lt&&(document.body.scrollHeight>window.innerHeight||"scroll"===e)&&(lt=parseInt(window.getComputedStyle(document.body).getPropertyValue("padding-right")),document.body.style.paddingRight=`${lt+(()=>{const e=document.createElement("div");e.className=r["scrollbar-measure"],document.body.appendChild(e);const t=e.getBoundingClientRect().width-e.clientWidth;return document.body.removeChild(e),t})()}px`)};function ut(e,t,n,s){V()?yt(e,s):(i(n).then(()=>yt(e,s)),ze(o)),nt?(t.setAttribute("style","display:none !important"),t.removeAttribute("class"),t.innerHTML=""):t.remove(),D()&&(null!==lt&&(document.body.style.paddingRight=`${lt}px`,lt=null),(()=>{if(N(document.body,r.iosfix)){const e=parseInt(document.body.style.top,10);W(document.body,r.iosfix),document.body.style.top="",document.body.scrollTop=-1*e}})(),tt()),W([document.documentElement,document.body],[r.shown,r["height-auto"],r["no-backdrop"],r["toast-shown"]])}function dt(e){e=gt(e);const t=et.swalPromiseResolve.get(this),n=pt(this);this.isAwaitingPromise?e.isDismissed||(ht(this),t(e)):n&&t(e)}const pt=e=>{const t=C();if(!t)return!1;const n=me.innerParams.get(e);if(!n||N(t,n.hideClass.popup))return!1;W(t,n.showClass.popup),z(t,n.hideClass.popup);const o=y();return W(o,n.showClass.backdrop),z(o,n.hideClass.backdrop),ft(e,t,n),!0};function mt(e){const t=et.swalPromiseReject.get(this);ht(this),t&&t(e)}const ht=e=>{e.isAwaitingPromise&&(delete e.isAwaitingPromise,me.innerParams.get(e)||e._destroy())},gt=e=>void 0===e?{isConfirmed:!1,isDenied:!1,isDismissed:!0}:Object.assign({isConfirmed:!1,isDenied:!1,isDismissed:!1},e),ft=(e,t,n)=>{var i;const s=y(),r=ne(t);"function"==typeof n.willClose&&n.willClose(t),null===(i=o.eventEmitter)||void 0===i||i.emit("willClose",t),r&&s?bt(e,t,s,Boolean(n.returnFocus),n.didClose):s&&ut(e,s,Boolean(n.returnFocus),n.didClose)},bt=(e,t,n,i,s)=>{o.swalCloseEventFinishedCallback=ut.bind(null,e,n,i,s);const r=function(e){var n;e.target===t&&(null===(n=o.swalCloseEventFinishedCallback)||void 0===n||n.call(o),delete o.swalCloseEventFinishedCallback,t.removeEventListener("animationend",r),t.removeEventListener("transitionend",r))};t.addEventListener("animationend",r),t.addEventListener("transitionend",r)},yt=(e,t)=>{setTimeout(()=>{var n;"function"==typeof t&&t.bind(e.params)(),null===(n=o.eventEmitter)||void 0===n||n.emit("didClose"),e._destroy&&e._destroy()})},vt=e=>{let t=C();if(t||new Gn,t=C(),!t)return;const n=S();V()?Z(A()):wt(t,e),X(n),t.setAttribute("data-loading","true"),t.setAttribute("aria-busy","true"),t.focus()},wt=(e,t)=>{const n=O(),o=S();n&&o&&(!t&&ee(P())&&(t=P()),X(n),t&&(Z(t),o.setAttribute("data-button-to-replace",t.className),n.insertBefore(o,t)),z([e,n],r.loading))},Ct=e=>e.checked?1:0,At=e=>e.checked?e.value:null,Et=e=>e.files&&e.files.length?null!==e.getAttribute("multiple")?e.files:e.files[0]:null,kt=(e,t)=>{const n=C();if(!n)return;const o=e=>{"select"===t.input?function(e,t,n){const o=K(e,r.select);if(!o)return;const i=(e,t,o)=>{const i=document.createElement("option");i.value=o,q(i,t),i.selected=Lt(o,n.inputValue),e.appendChild(i)};t.forEach(e=>{const t=e[0],n=e[1];if(Array.isArray(n)){const e=document.createElement("optgroup");e.label=t,e.disabled=!1,o.appendChild(e),n.forEach(t=>i(e,t[1],t[0]))}else i(o,n,t)}),o.focus()}(n,$t(e),t):"radio"===t.input&&function(e,t,n){const o=K(e,r.radio);if(!o)return;t.forEach(e=>{const t=e[0],i=e[1],s=document.createElement("input"),a=document.createElement("label");s.type="radio",s.name=r.radio,s.value=t,Lt(t,n.inputValue)&&(s.checked=!0);const l=document.createElement("span");q(l,i),l.className=r.label,a.appendChild(s),a.appendChild(l),o.appendChild(a)});const i=o.querySelectorAll("input");i.length&&i[0].focus()}(n,$t(e),t)};g(t.inputOptions)||b(t.inputOptions)?(vt(P()),f(t.inputOptions).then(t=>{e.hideLoading(),o(t)})):"object"==typeof t.inputOptions?o(t.inputOptions):d("Unexpected type of inputOptions! Expected object, Map or Promise, got "+typeof t.inputOptions)},Bt=(e,t)=>{const n=e.getInput();n&&(Z(n),f(t.inputValue).then(o=>{n.value="number"===t.input?`${parseFloat(o)||0}`:`${o}`,X(n),n.focus(),e.hideLoading()}).catch(t=>{d(`Error in inputValue promise: ${t}`),n.value="",X(n),n.focus(),e.hideLoading()}))};const $t=e=>(e instanceof Map?Array.from(e):Object.entries(e)).map(([e,t])=>[e,"object"==typeof t?$t(t):t]),Lt=(e,t)=>Boolean(t)&&null!=t&&t.toString()===e.toString(),Pt=(e,t)=>{const n=me.innerParams.get(e);if(!n.input)return void d(`The "input" parameter is needed to be set when using returnInputValueOn${c(t)}`);const o=e.getInput(),i=((e,t)=>{const n=e.getInput();if(!n)return null;switch(t.input){case"checkbox":return Ct(n);case"radio":return At(n);case"file":return Et(n);default:return t.inputAutoTrim?n.value.trim():n.value}})(e,n);n.inputValidator?xt(e,i,t):o&&!o.checkValidity()?(e.enableButtons(),e.showValidationMessage(n.validationMessage||o.validationMessage)):"deny"===t?Tt(e,i):Mt(e,i)},xt=(e,t,n)=>{const o=me.innerParams.get(e);e.disableInput();Promise.resolve().then(()=>f(o.inputValidator(t,o.validationMessage))).then(o=>{e.enableButtons(),e.enableInput(),o?e.showValidationMessage(o):"deny"===n?Tt(e,t):Mt(e,t)})},Tt=(e,t)=>{const n=me.innerParams.get(e);if(n.showLoaderOnDeny&&vt(T()),n.preDeny){e.isAwaitingPromise=!0;Promise.resolve().then(()=>f(n.preDeny(t,n.validationMessage))).then(n=>{!1===n?(e.hideLoading(),ht(e)):e.close({isDenied:!0,value:void 0===n?t:n})}).catch(t=>Ot(e,t))}else e.close({isDenied:!0,value:t})},St=(e,t)=>{e.close({isConfirmed:!0,value:t})},Ot=(e,t)=>{e.rejectPromise(t)},Mt=(e,t)=>{const n=me.innerParams.get(e);if(n.showLoaderOnConfirm&&vt(),n.preConfirm){e.resetValidationMessage(),e.isAwaitingPromise=!0;Promise.resolve().then(()=>f(n.preConfirm(t,n.validationMessage))).then(n=>{ee(L())||!1===n?(e.hideLoading(),ht(e)):St(e,void 0===n?t:n)}).catch(t=>Ot(e,t))}else St(e,t)};function Ht(){const e=me.innerParams.get(this);if(!e)return;const t=me.domCache.get(this);Z(t.loader),V()?e.icon&&X(A()):jt(t),W([t.popup,t.actions],r.loading),t.popup.removeAttribute("aria-busy"),t.popup.removeAttribute("data-loading"),this.enableButtons()}const jt=e=>{const t=e.loader.getAttribute("data-button-to-replace"),n=t?e.popup.getElementsByClassName(t):[];n.length?X(n[0],"inline-block"):ee(P())||ee(T())||ee(x())||Z(e.actions)};function It(){const e=me.innerParams.get(this),t=me.domCache.get(this);return t?F(t.popup,e.input):null}function Dt(e,t,n){const o=me.domCache.get(e);t.forEach(e=>{o[e].disabled=n})}function Vt(e,t){const n=C();if(n&&e)if("radio"===e.type){n.querySelectorAll(`[name="${r.radio}"]`).forEach(e=>{e.disabled=t})}else e.disabled=t}function qt(){Dt(this,["confirmButton","denyButton","cancelButton"],!1);const e=me.focusedElement.get(this);e instanceof HTMLElement&&document.activeElement===document.body&&e.focus(),me.focusedElement.delete(this)}function Nt(){me.focusedElement.set(this,document.activeElement),Dt(this,["confirmButton","denyButton","cancelButton"],!0)}function _t(){Vt(this.getInput(),!1)}function Ft(){Vt(this.getInput(),!0)}function Rt(e){const t=me.domCache.get(this),n=me.innerParams.get(this);q(t.validationMessage,e),t.validationMessage.className=r["validation-message"],n.customClass&&n.customClass.validationMessage&&z(t.validationMessage,n.customClass.validationMessage),X(t.validationMessage);const o=this.getInput();o&&(o.setAttribute("aria-invalid","true"),o.setAttribute("aria-describedby",r["validation-message"]),R(o),z(o,r.inputerror))}function Ut(){const e=me.domCache.get(this);e.validationMessage&&Z(e.validationMessage);const t=this.getInput();t&&(t.removeAttribute("aria-invalid"),t.removeAttribute("aria-describedby"),W(t,r.inputerror))}const zt={title:"",titleText:"",text:"",html:"",footer:"",icon:void 0,iconColor:void 0,iconHtml:void 0,template:void 0,toast:!1,draggable:!1,animation:!0,theme:"light",showClass:{popup:"swal2-show",backdrop:"swal2-backdrop-show",icon:"swal2-icon-show"},hideClass:{popup:"swal2-hide",backdrop:"swal2-backdrop-hide",icon:"swal2-icon-hide"},customClass:{},target:"body",color:void 0,backdrop:!0,heightAuto:!0,allowOutsideClick:!0,allowEscapeKey:!0,allowEnterKey:!0,stopKeydownPropagation:!0,keydownListenerCapture:!1,showConfirmButton:!0,showDenyButton:!1,showCancelButton:!1,preConfirm:void 0,preDeny:void 0,confirmButtonText:"OK",confirmButtonAriaLabel:"",confirmButtonColor:void 0,denyButtonText:"No",denyButtonAriaLabel:"",denyButtonColor:void 0,cancelButtonText:"Cancel",cancelButtonAriaLabel:"",cancelButtonColor:void 0,buttonsStyling:!0,reverseButtons:!1,focusConfirm:!0,focusDeny:!1,focusCancel:!1,returnFocus:!0,showCloseButton:!1,closeButtonHtml:"&times;",closeButtonAriaLabel:"Close this dialog",loaderHtml:"",showLoaderOnConfirm:!1,showLoaderOnDeny:!1,imageUrl:void 0,imageWidth:void 0,imageHeight:void 0,imageAlt:"",timer:void 0,timerProgressBar:!1,width:void 0,padding:void 0,background:void 0,input:void 0,inputPlaceholder:"",inputLabel:"",inputValue:"",inputOptions:{},inputAutoFocus:!0,inputAutoTrim:!0,inputAttributes:{},inputValidator:void 0,returnInputValueOnDeny:!1,validationMessage:void 0,grow:!1,position:"center",progressSteps:[],currentProgressStep:void 0,progressStepsDistance:void 0,willOpen:void 0,didOpen:void 0,didRender:void 0,willClose:void 0,didClose:void 0,didDestroy:void 0,scrollbarPadding:!0,topLayer:!1},Wt=["allowEscapeKey","allowOutsideClick","background","buttonsStyling","cancelButtonAriaLabel","cancelButtonColor","cancelButtonText","closeButtonAriaLabel","closeButtonHtml","color","confirmButtonAriaLabel","confirmButtonColor","confirmButtonText","currentProgressStep","customClass","denyButtonAriaLabel","denyButtonColor","denyButtonText","didClose","didDestroy","draggable","footer","hideClass","html","icon","iconColor","iconHtml","imageAlt","imageHeight","imageUrl","imageWidth","preConfirm","preDeny","progressSteps","returnFocus","reverseButtons","showCancelButton","showCloseButton","showConfirmButton","showDenyButton","text","title","titleText","theme","willClose"],Kt={allowEnterKey:void 0},Yt=["allowOutsideClick","allowEnterKey","backdrop","draggable","focusConfirm","focusDeny","focusCancel","returnFocus","heightAuto","keydownListenerCapture"],Xt=e=>Object.prototype.hasOwnProperty.call(zt,e),Zt=e=>-1!==Wt.indexOf(e),Jt=e=>Kt[e],Gt=e=>{Xt(e)||u(`Unknown parameter "${e}"`)},Qt=e=>{Yt.includes(e)&&u(`The parameter "${e}" is incompatible with toasts`)},en=e=>{const t=Jt(e);t&&m(e,t)},tn=e=>{!1===e.backdrop&&e.allowOutsideClick&&u('"allowOutsideClick" parameter requires `backdrop` parameter to be set to `true`'),e.theme&&!["light","dark","auto","minimal","borderless","bootstrap-4","bootstrap-4-light","bootstrap-4-dark","bootstrap-5","bootstrap-5-light","bootstrap-5-dark","material-ui","material-ui-light","material-ui-dark","embed-iframe","bulma","bulma-light","bulma-dark"].includes(e.theme)&&u(`Invalid theme "${e.theme}"`);for(const t in e)Gt(t),e.toast&&Qt(t),en(t)};function nn(e){const t=y(),n=C(),o=me.innerParams.get(this);if(!n||N(n,o.hideClass.popup))return void u("You're trying to update the closed or closing popup, that won't work. Use the update() method in preConfirm parameter or show a new popup.");const i=on(e),s=Object.assign({},o,i);tn(s),t&&(t.dataset.swal2Theme=s.theme),Fe(this,s),me.innerParams.set(this,s),Object.defineProperties(this,{params:{value:Object.assign({},this.params,e),writable:!1,enumerable:!0}})}const on=e=>{const t={};return Object.keys(e).forEach(n=>{if(Zt(n)){const o=e;t[n]=o[n]}else u(`Invalid parameter to update: ${n}`)}),t};function sn(){var e;const t=me.domCache.get(this),n=me.innerParams.get(this);n?(t.popup&&o.swalCloseEventFinishedCallback&&(o.swalCloseEventFinishedCallback(),delete o.swalCloseEventFinishedCallback),"function"==typeof n.didDestroy&&n.didDestroy(),null===(e=o.eventEmitter)||void 0===e||e.emit("didDestroy"),rn(this)):an(this)}const rn=e=>{an(e),delete e.params,delete o.keydownHandler,delete o.keydownTarget,delete o.currentInstance},an=e=>{e.isAwaitingPromise?(ln(me,e),e.isAwaitingPromise=!0):(ln(et,e),ln(me,e),delete e.isAwaitingPromise,delete e.disableButtons,delete e.enableButtons,delete e.getInput,delete e.disableInput,delete e.enableInput,delete e.hideLoading,delete e.disableLoading,delete e.showValidationMessage,delete e.resetValidationMessage,delete e.close,delete e.closePopup,delete e.closeModal,delete e.closeToast,delete e.rejectPromise,delete e.update,delete e._destroy)},ln=(e,t)=>{for(const n in e)e[n].delete(t)};var cn=Object.freeze({__proto__:null,_destroy:sn,close:dt,closeModal:dt,closePopup:dt,closeToast:dt,disableButtons:Nt,disableInput:Ft,disableLoading:Ht,enableButtons:qt,enableInput:_t,getInput:It,handleAwaitingPromise:ht,hideLoading:Ht,rejectPromise:mt,resetValidationMessage:Ut,showValidationMessage:Rt,update:nn});const un=(e,t,n)=>{t.popup.onclick=()=>{e&&(dn(e)||e.timer||e.input)||n(Ue.close)}},dn=e=>Boolean(e.showConfirmButton||e.showDenyButton||e.showCancelButton||e.showCloseButton);let pn=!1;const mn=e=>{e.popup.onmousedown=()=>{e.container.onmouseup=function(t){e.container.onmouseup=()=>{},t.target===e.container&&(pn=!0)}}},hn=e=>{e.container.onmousedown=t=>{t.target===e.container&&t.preventDefault(),e.popup.onmouseup=function(t){e.popup.onmouseup=()=>{},(t.target===e.popup||t.target instanceof HTMLElement&&e.popup.contains(t.target))&&(pn=!0)}}},gn=(e,t,n)=>{t.container.onclick=o=>{pn?pn=!1:o.target===t.container&&h(e.allowOutsideClick)&&n(Ue.backdrop)}},fn=e=>e instanceof Element||(e=>"object"==typeof e&&null!==e&&"jquery"in e)(e);const bn=()=>{if(o.timeout)return(()=>{const e=H();if(!e)return;const t=parseInt(window.getComputedStyle(e).width);e.style.removeProperty("transition"),e.style.width="100%";const n=t/parseInt(window.getComputedStyle(e).width)*100;e.style.width=`${n}%`})(),o.timeout.stop()},yn=()=>{if(o.timeout){const e=o.timeout.start();return oe(e),e}};let vn=!1;const wn={};const Cn=e=>{for(let t=e.target;t&&t!==document;t=t.parentNode)for(const e in wn){const n=t.getAttribute&&t.getAttribute(e);if(n)return void wn[e].fire({template:n})}};o.eventEmitter=new class{constructor(){this.events={}}_getHandlersByEventName(e){return void 0===this.events[e]&&(this.events[e]=[]),this.events[e]}on(e,t){const n=this._getHandlersByEventName(e);n.includes(t)||n.push(t)}once(e,t){const n=(...o)=>{this.removeListener(e,n),t.apply(this,o)};this.on(e,n)}emit(e,...t){this._getHandlersByEventName(e).forEach(e=>{try{e.apply(this,t)}catch(e){console.error(e)}})}removeListener(e,t){const n=this._getHandlersByEventName(e),o=n.indexOf(t);o>-1&&n.splice(o,1)}removeAllListeners(e){void 0!==this.events[e]&&(this.events[e].length=0)}reset(){this.events={}}};var An=Object.freeze({__proto__:null,argsToParams:e=>{const t={};return"object"!=typeof e[0]||fn(e[0])?["title","html","icon"].forEach((n,o)=>{const i=e[o];"string"==typeof i||fn(i)?t[n]=i:void 0!==i&&d(`Unexpected type of ${n}! Expected "string" or "Element", got ${typeof i}`)}):Object.assign(t,e[0]),t},bindClickHandler:function(e="data-swal-template"){wn[e]=this,vn||(document.body.addEventListener("click",Cn),vn=!0)},clickCancel:()=>{var e;return null===(e=x())||void 0===e?void 0:e.click()},clickConfirm:Re,clickDeny:()=>{var e;return null===(e=T())||void 0===e?void 0:e.click()},enableLoading:vt,fire:function(...e){return new this(...e)},getActions:O,getCancelButton:x,getCloseButton:j,getConfirmButton:P,getContainer:y,getDenyButton:T,getFocusableElements:I,getFooter:M,getHtmlContainer:k,getIcon:A,getIconContent:()=>w(r["icon-content"]),getImage:B,getInputLabel:()=>w(r["input-label"]),getLoader:S,getPopup:C,getProgressSteps:$,getTimerLeft:()=>o.timeout&&o.timeout.getTimerLeft(),getTimerProgressBar:H,getTitle:E,getValidationMessage:L,increaseTimer:e=>{if(o.timeout){const t=o.timeout.increase(e);return oe(t,!0),t}},isDeprecatedParameter:Jt,isLoading:()=>{const e=C();return!!e&&e.hasAttribute("data-loading")},isTimerRunning:()=>Boolean(o.timeout&&o.timeout.isRunning()),isUpdatableParameter:Zt,isValidParameter:Xt,isVisible:()=>ee(C()),mixin:function(e){return class extends(this){_main(t,n){return super._main(t,Object.assign({},e,n))}}},off:(e,t)=>{o.eventEmitter&&(e?t?o.eventEmitter.removeListener(e,t):o.eventEmitter.removeAllListeners(e):o.eventEmitter.reset())},on:(e,t)=>{o.eventEmitter&&o.eventEmitter.on(e,t)},once:(e,t)=>{o.eventEmitter&&o.eventEmitter.once(e,t)},resumeTimer:yn,showLoading:vt,stopTimer:bn,toggleTimer:()=>{const e=o.timeout;return e&&(e.running?bn():yn())}});class En{constructor(e,t){this.callback=e,this.remaining=t,this.running=!1,this.start()}start(){return this.running||(this.running=!0,this.started=new Date,this.id=setTimeout(this.callback,this.remaining)),this.remaining}stop(){return this.started&&this.running&&(this.running=!1,clearTimeout(this.id),this.remaining-=(new Date).getTime()-this.started.getTime()),this.remaining}increase(e){const t=this.running;return t&&this.stop(),this.remaining+=e,t&&this.start(),this.remaining}getTimerLeft(){return this.running&&(this.stop(),this.start()),this.remaining}isRunning(){return this.running}}const kn=["swal-title","swal-html","swal-footer"],Bn=e=>{const t={};return Array.from(e.querySelectorAll("swal-param")).forEach(e=>{Mn(e,["name","value"]);const n=e.getAttribute("name"),o=e.getAttribute("value");n&&o&&(t[n]=n in zt&&"boolean"==typeof zt[n]?"false"!==o:n in zt&&"object"==typeof zt[n]?JSON.parse(o):o)}),t},$n=e=>{const t={};return Array.from(e.querySelectorAll("swal-function-param")).forEach(e=>{const n=e.getAttribute("name"),o=e.getAttribute("value");n&&o&&(t[n]=new Function(`return ${o}`)())}),t},Ln=e=>{const t={};return Array.from(e.querySelectorAll("swal-button")).forEach(e=>{Mn(e,["type","color","aria-label"]);const n=e.getAttribute("type");if(!n||!["confirm","cancel","deny"].includes(n))return;t[`${n}ButtonText`]=e.innerHTML,t[`show${c(n)}Button`]=!0;const o=e.getAttribute("color");null!==o&&(t[`${n}ButtonColor`]=o);const i=e.getAttribute("aria-label");null!==i&&(t[`${n}ButtonAriaLabel`]=i)}),t},Pn=e=>{const t={},n=e.querySelector("swal-image");if(n){Mn(n,["src","width","height","alt"]);const e=n.getAttribute("src");null!==e&&(t.imageUrl=e||void 0);const o=n.getAttribute("width");null!==o&&(t.imageWidth=o||void 0);const i=n.getAttribute("height");null!==i&&(t.imageHeight=i||void 0);const s=n.getAttribute("alt");null!==s&&(t.imageAlt=s||void 0)}return t},xn=e=>{const t={},n=e.querySelector("swal-icon");return n&&(Mn(n,["type","color"]),n.hasAttribute("type")&&(t.icon=n.getAttribute("type")),n.hasAttribute("color")&&(t.iconColor=n.getAttribute("color")),t.iconHtml=n.innerHTML),t},Tn=e=>{const t={},n=e.querySelector("swal-input");n&&(Mn(n,["type","label","placeholder","value"]),t.input=n.getAttribute("type")||"text",n.hasAttribute("label")&&(t.inputLabel=n.getAttribute("label")),n.hasAttribute("placeholder")&&(t.inputPlaceholder=n.getAttribute("placeholder")),n.hasAttribute("value")&&(t.inputValue=n.getAttribute("value")));const o=Array.from(e.querySelectorAll("swal-input-option"));return o.length&&(t.inputOptions={},o.forEach(e=>{Mn(e,["value"]);const n=e.getAttribute("value");if(!n)return;const o=e.innerHTML;t.inputOptions[n]=o})),t},Sn=(e,t)=>{const n={};for(const o in t){const i=t[o],s=e.querySelector(i);s&&(Mn(s,[]),n[i.replace(/^swal-/,"")]=s.innerHTML.trim())}return n},On=e=>{const t=kn.concat(["swal-param","swal-function-param","swal-button","swal-image","swal-icon","swal-input","swal-input-option"]);Array.from(e.children).forEach(e=>{const n=e.tagName.toLowerCase();t.includes(n)||u(`Unrecognized element <${n}>`)})},Mn=(e,t)=>{Array.from(e.attributes).forEach(n=>{-1===t.indexOf(n.name)&&u([`Unrecognized attribute "${n.name}" on <${e.tagName.toLowerCase()}>.`,""+(t.length?`Allowed attributes are: ${t.join(", ")}`:"To set the value, use HTML within the element.")])})},Hn=e=>{var t,n;const i=y(),s=C();if(!i||!s)return;"function"==typeof e.willOpen&&e.willOpen(s),null===(t=o.eventEmitter)||void 0===t||t.emit("willOpen",s);const r=window.getComputedStyle(document.body).overflowY;if(Vn(i,s,e),setTimeout(()=>{In(i,s)},10),D()&&(Dn(i,void 0!==e.scrollbarPadding&&e.scrollbarPadding,r),(()=>{const e=y();Array.from(document.body.children).forEach(t=>{t.contains(e)||(t.hasAttribute("aria-hidden")&&t.setAttribute("data-previous-aria-hidden",t.getAttribute("aria-hidden")||""),t.setAttribute("aria-hidden","true"))})})()),ot&&!1===e.backdrop&&s.scrollHeight>i.clientHeight&&(i.style.pointerEvents="auto"),V()||o.previousActiveElement||(o.previousActiveElement=document.activeElement),"function"==typeof e.didOpen){const t=e.didOpen;setTimeout(()=>t(s))}null===(n=o.eventEmitter)||void 0===n||n.emit("didOpen",s)},jn=e=>{const t=C();if(!t||e.target!==t)return;const n=y();n&&(t.removeEventListener("animationend",jn),t.removeEventListener("transitionend",jn),n.style.overflowY="auto",W(n,r["no-transition"]))},In=(e,t)=>{ne(t)?(e.style.overflowY="hidden",t.addEventListener("animationend",jn),t.addEventListener("transitionend",jn)):e.style.overflowY="auto"},Dn=(e,t,n)=>{(()=>{if(nt&&!N(document.body,r.iosfix)){const e=document.body.scrollTop;document.body.style.top=-1*e+"px",z(document.body,r.iosfix),it()}})(),t&&"hidden"!==n&&ct(n),setTimeout(()=>{e.scrollTop=0})},Vn=(e,t,n)=>{var o;null!==(o=n.showClass)&&void 0!==o&&o.backdrop&&z(e,n.showClass.backdrop),n.animation?(t.style.setProperty("opacity","0","important"),X(t,"grid"),setTimeout(()=>{var e;null!==(e=n.showClass)&&void 0!==e&&e.popup&&z(t,n.showClass.popup),t.style.removeProperty("opacity")},10)):X(t,"grid"),z([document.documentElement,document.body],r.shown),n.heightAuto&&n.backdrop&&!n.toast&&z([document.documentElement,document.body],r["height-auto"])};var qn=(e,t)=>/^[a-zA-Z0-9.+_'-]+@[a-zA-Z0-9.-]+\.[a-zA-Z0-9-]+$/.test(e)?Promise.resolve():Promise.resolve(t||"Invalid email address"),Nn=(e,t)=>/^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-z]{2,63}\b([-a-zA-Z0-9@:%_+.~#?&/=]*)$/.test(e)?Promise.resolve():Promise.resolve(t||"Invalid URL");function _n(e){!function(e){e.inputValidator||("email"===e.input&&(e.inputValidator=qn),"url"===e.input&&(e.inputValidator=Nn))}(e),e.showLoaderOnConfirm&&!e.preConfirm&&u("showLoaderOnConfirm is set to true, but preConfirm is not defined.\nshowLoaderOnConfirm should be used together with preConfirm, see usage example:\nhttps://sweetalert2.github.io/#ajax-request"),function(e){(!e.target||"string"==typeof e.target&&!document.querySelector(e.target)||"string"!=typeof e.target&&!e.target.appendChild)&&(u('Target parameter is not valid, defaulting to "body"'),e.target="body")}(e),"string"==typeof e.title&&(e.title=e.title.split("\n").join("<br />")),re(e)}let Fn;var Rn=new WeakMap;class Un{constructor(...t){if(n(this,Rn,Promise.resolve({isConfirmed:!1,isDenied:!1,isDismissed:!0})),"undefined"==typeof window)return;Fn=this;const o=Object.freeze(this.constructor.argsToParams(t));var i,s,r;this.params=o,this.isAwaitingPromise=!1,i=Rn,s=this,r=this._main(Fn.params),i.set(e(i,s),r)}_main(e,t={}){if(tn(Object.assign({},t,e)),o.currentInstance){const e=et.swalPromiseResolve.get(o.currentInstance),{isAwaitingPromise:t}=o.currentInstance;o.currentInstance._destroy(),t||e({isDismissed:!0}),D()&&tt()}o.currentInstance=Fn;const n=Wn(e,t);_n(n),Object.freeze(n),o.timeout&&(o.timeout.stop(),delete o.timeout),clearTimeout(o.restoreFocusTimeout);const i=Kn(Fn);return Fe(Fn,n),me.innerParams.set(Fn,n),zn(Fn,i,n)}then(e){return t(Rn,this).then(e)}finally(e){return t(Rn,this).finally(e)}}const zn=(e,t,n)=>new Promise((i,s)=>{const r=t=>{e.close({isDismissed:!0,dismiss:t,isConfirmed:!1,isDenied:!1})};et.swalPromiseResolve.set(e,i),et.swalPromiseReject.set(e,s),t.confirmButton.onclick=()=>{(e=>{const t=me.innerParams.get(e);e.disableButtons(),t.input?Pt(e,"confirm"):Mt(e,!0)})(e)},t.denyButton.onclick=()=>{(e=>{const t=me.innerParams.get(e);e.disableButtons(),t.returnInputValueOnDeny?Pt(e,"deny"):Tt(e,!1)})(e)},t.cancelButton.onclick=()=>{((e,t)=>{e.disableButtons(),t(Ue.cancel)})(e,r)},t.closeButton.onclick=()=>{r(Ue.close)},((e,t,n)=>{e.toast?un(e,t,n):(mn(t),hn(t),gn(e,t,n))})(n,t,r),((e,t,n)=>{if(ze(e),!t.toast){const o=e=>Xe(t,e,n);e.keydownHandler=o;const i=t.keydownListenerCapture?window:C();if(i){e.keydownTarget=i,e.keydownListenerCapture=t.keydownListenerCapture;const n=o;e.keydownTarget.addEventListener("keydown",n,{capture:e.keydownListenerCapture}),e.keydownHandlerAdded=!0}}})(o,n,r),((e,t)=>{"select"===t.input||"radio"===t.input?kt(e,t):["text","email","number","tel","textarea"].some(e=>e===t.input)&&(g(t.inputValue)||b(t.inputValue))&&(vt(P()),Bt(e,t))})(e,n),Hn(n),Yn(o,n,r),Xn(t,n),setTimeout(()=>{t.container.scrollTop=0})}),Wn=(e,t)=>{const n=(e=>{const t="string"==typeof e.template?document.querySelector(e.template):e.template;if(!t)return{};const n=t.content;return On(n),Object.assign(Bn(n),$n(n),Ln(n),Pn(n),xn(n),Tn(n),Sn(n,kn))})(e),o=Object.assign({},zt,t,n,e);return o.showClass=Object.assign({},zt.showClass,o.showClass),o.hideClass=Object.assign({},zt.hideClass,o.hideClass),!1===o.animation&&(o.showClass={backdrop:"swal2-noanimation"},o.hideClass={}),o},Kn=e=>{const t={popup:C(),container:y(),actions:O(),confirmButton:P(),denyButton:T(),cancelButton:x(),loader:S(),closeButton:j(),validationMessage:L(),progressSteps:$()};return me.domCache.set(e,t),t},Yn=(e,t,n)=>{const o=H();Z(o),t.timer&&(e.timeout=new En(()=>{n("timer"),delete e.timeout},t.timer),t.timerProgressBar&&o&&(X(o),_(o,t,"timerProgressBar"),setTimeout(()=>{e.timeout&&e.timeout.running&&oe(t.timer)})))},Xn=(e,t)=>{if(!t.toast)return h(t.allowEnterKey)?void(Zn(e)||Jn(e,t)||We(-1,1)):(m("allowEnterKey","preConfirm: () => false"),void e.popup.focus())},Zn=e=>{const t=Array.from(e.popup.querySelectorAll("[autofocus]"));for(const e of t)if(e instanceof HTMLElement&&ee(e))return e.focus(),!0;return!1},Jn=(e,t)=>t.focusDeny&&ee(e.denyButton)?(e.denyButton.focus(),!0):t.focusCancel&&ee(e.cancelButton)?(e.cancelButton.focus(),!0):!(!t.focusConfirm||!ee(e.confirmButton))&&(e.confirmButton.focus(),!0);Un.prototype.disableButtons=Nt,Un.prototype.enableButtons=qt,Un.prototype.getInput=It,Un.prototype.disableInput=Ft,Un.prototype.enableInput=_t,Un.prototype.hideLoading=Ht,Un.prototype.disableLoading=Ht,Un.prototype.showValidationMessage=Rt,Un.prototype.resetValidationMessage=Ut,Un.prototype.close=dt,Un.prototype.closePopup=dt,Un.prototype.closeModal=dt,Un.prototype.closeToast=dt,Un.prototype.rejectPromise=mt,Un.prototype.update=nn,Un.prototype._destroy=sn,Object.assign(Un,An),Object.keys(cn).forEach(e=>{Un[e]=function(...t){if(Fn&&Fn[e])return Fn[e](...t)}}),Un.DismissReason=Ue,Un.version="11.26.25";const Gn=Un;return Gn.default=Gn,Gn}),void 0!==this&&this.Sweetalert2&&(this.swal=this.sweetAlert=this.Swal=this.SweetAlert=this.Sweetalert2);
"undefined"!=typeof document&&function(e,t){var n=e.createElement("style");if(e.getElementsByTagName("head")[0].appendChild(n),n.styleSheet)n.styleSheet.disabled||(n.styleSheet.cssText=t);else try{n.innerHTML=t}catch(e){n.innerText=t}}(document,":root{--swal2-outline: 0 0 0 3px rgba(100, 150, 200, 0.5);--swal2-container-padding: 0.625em;--swal2-backdrop: rgba(0, 0, 0, 0.4);--swal2-backdrop-transition: background-color 0.15s;--swal2-width: 32em;--swal2-padding: 0 0 1.25em;--swal2-border: none;--swal2-border-radius: 0.3125rem;--swal2-background: white;--swal2-color: #545454;--swal2-show-animation: swal2-show 0.3s;--swal2-hide-animation: swal2-hide 0.15s forwards;--swal2-icon-zoom: 1;--swal2-title-padding: 0.8em 1em 0;--swal2-html-container-padding: 1em 1.6em 0.3em;--swal2-input-border: 1px solid #d9d9d9;--swal2-input-border-radius: 0.1875em;--swal2-input-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px transparent;--swal2-input-background: transparent;--swal2-input-transition: border-color 0.2s, box-shadow 0.2s;--swal2-input-hover-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px transparent;--swal2-input-focus-border: 1px solid #b4dbed;--swal2-input-focus-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.06), 0 0 0 3px rgba(100, 150, 200, 0.5);--swal2-progress-step-background: #add8e6;--swal2-validation-message-background: #f0f0f0;--swal2-validation-message-color: #666;--swal2-footer-border-color: #eee;--swal2-footer-background: transparent;--swal2-footer-color: inherit;--swal2-timer-progress-bar-background: rgba(0, 0, 0, 0.3);--swal2-close-button-position: initial;--swal2-close-button-inset: auto;--swal2-close-button-font-size: 2.5em;--swal2-close-button-color: #ccc;--swal2-close-button-transition: color 0.2s, box-shadow 0.2s;--swal2-close-button-outline: initial;--swal2-close-button-box-shadow: inset 0 0 0 3px transparent;--swal2-close-button-focus-box-shadow: inset var(--swal2-outline);--swal2-close-button-hover-transform: none;--swal2-actions-justify-content: center;--swal2-actions-width: auto;--swal2-actions-margin: 1.25em auto 0;--swal2-actions-padding: 0;--swal2-actions-border-radius: 0;--swal2-actions-background: transparent;--swal2-action-button-transition: background-color 0.2s, box-shadow 0.2s;--swal2-action-button-hover: black 10%;--swal2-action-button-active: black 10%;--swal2-confirm-button-box-shadow: none;--swal2-confirm-button-border-radius: 0.25em;--swal2-confirm-button-background-color: #7066e0;--swal2-confirm-button-color: #fff;--swal2-deny-button-box-shadow: none;--swal2-deny-button-border-radius: 0.25em;--swal2-deny-button-background-color: #dc3741;--swal2-deny-button-color: #fff;--swal2-cancel-button-box-shadow: none;--swal2-cancel-button-border-radius: 0.25em;--swal2-cancel-button-background-color: #6e7881;--swal2-cancel-button-color: #fff;--swal2-toast-show-animation: swal2-toast-show 0.5s;--swal2-toast-hide-animation: swal2-toast-hide 0.1s forwards;--swal2-toast-border: none;--swal2-toast-box-shadow: 0 0 1px hsl(0deg 0% 0% / 0.075), 0 1px 2px hsl(0deg 0% 0% / 0.075), 1px 2px 4px hsl(0deg 0% 0% / 0.075), 1px 3px 8px hsl(0deg 0% 0% / 0.075), 2px 4px 16px hsl(0deg 0% 0% / 0.075)}[data-swal2-theme=dark]{--swal2-dark-theme-black: #19191a;--swal2-dark-theme-white: #e1e1e1;--swal2-background: var(--swal2-dark-theme-black);--swal2-color: var(--swal2-dark-theme-white);--swal2-footer-border-color: #555;--swal2-input-background: color-mix(in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10%);--swal2-validation-message-background: color-mix( in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10% );--swal2-validation-message-color: var(--swal2-dark-theme-white);--swal2-timer-progress-bar-background: rgba(255, 255, 255, 0.7)}@media(prefers-color-scheme: dark){[data-swal2-theme=auto]{--swal2-dark-theme-black: #19191a;--swal2-dark-theme-white: #e1e1e1;--swal2-background: var(--swal2-dark-theme-black);--swal2-color: var(--swal2-dark-theme-white);--swal2-footer-border-color: #555;--swal2-input-background: color-mix(in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10%);--swal2-validation-message-background: color-mix( in srgb, var(--swal2-dark-theme-black), var(--swal2-dark-theme-white) 10% );--swal2-validation-message-color: var(--swal2-dark-theme-white);--swal2-timer-progress-bar-background: rgba(255, 255, 255, 0.7)}}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown){overflow:hidden}body.swal2-height-auto{height:auto !important}body.swal2-no-backdrop .swal2-container{background-color:rgba(0,0,0,0) !important;pointer-events:none}body.swal2-no-backdrop .swal2-container .swal2-popup{pointer-events:auto}body.swal2-no-backdrop .swal2-container .swal2-modal{box-shadow:0 0 10px var(--swal2-backdrop)}body.swal2-toast-shown .swal2-container{box-sizing:border-box;width:360px;max-width:100%;background-color:rgba(0,0,0,0);pointer-events:none}body.swal2-toast-shown .swal2-container.swal2-top{inset:0 auto auto 50%;transform:translateX(-50%)}body.swal2-toast-shown .swal2-container.swal2-top-end,body.swal2-toast-shown .swal2-container.swal2-top-right{inset:0 0 auto auto}body.swal2-toast-shown .swal2-container.swal2-top-start,body.swal2-toast-shown .swal2-container.swal2-top-left{inset:0 auto auto 0}body.swal2-toast-shown .swal2-container.swal2-center-start,body.swal2-toast-shown .swal2-container.swal2-center-left{inset:50% auto auto 0;transform:translateY(-50%)}body.swal2-toast-shown .swal2-container.swal2-center{inset:50% auto auto 50%;transform:translate(-50%, -50%)}body.swal2-toast-shown .swal2-container.swal2-center-end,body.swal2-toast-shown .swal2-container.swal2-center-right{inset:50% 0 auto auto;transform:translateY(-50%)}body.swal2-toast-shown .swal2-container.swal2-bottom-start,body.swal2-toast-shown .swal2-container.swal2-bottom-left{inset:auto auto 0 0}body.swal2-toast-shown .swal2-container.swal2-bottom{inset:auto auto 0 50%;transform:translateX(-50%)}body.swal2-toast-shown .swal2-container.swal2-bottom-end,body.swal2-toast-shown .swal2-container.swal2-bottom-right{inset:auto 0 0 auto}@media print{body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown){overflow-y:scroll !important}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown)>[aria-hidden=true]{display:none}body.swal2-shown:not(.swal2-no-backdrop,.swal2-toast-shown) .swal2-container{position:static !important}}div:where(.swal2-container){display:grid;position:fixed;z-index:1060;inset:0;box-sizing:border-box;grid-template-areas:\"top-start     top            top-end\" \"center-start  center         center-end\" \"bottom-start  bottom-center  bottom-end\";grid-template-rows:minmax(min-content, auto) minmax(min-content, auto) minmax(min-content, auto);height:100%;padding:var(--swal2-container-padding);overflow-x:hidden;transition:var(--swal2-backdrop-transition);-webkit-overflow-scrolling:touch}div:where(.swal2-container).swal2-backdrop-show,div:where(.swal2-container).swal2-noanimation{background:var(--swal2-backdrop)}div:where(.swal2-container).swal2-backdrop-hide{background:rgba(0,0,0,0) !important}div:where(.swal2-container).swal2-top-start,div:where(.swal2-container).swal2-center-start,div:where(.swal2-container).swal2-bottom-start{grid-template-columns:minmax(0, 1fr) auto auto}div:where(.swal2-container).swal2-top,div:where(.swal2-container).swal2-center,div:where(.swal2-container).swal2-bottom{grid-template-columns:auto minmax(0, 1fr) auto}div:where(.swal2-container).swal2-top-end,div:where(.swal2-container).swal2-center-end,div:where(.swal2-container).swal2-bottom-end{grid-template-columns:auto auto minmax(0, 1fr)}div:where(.swal2-container).swal2-top-start>.swal2-popup{align-self:start}div:where(.swal2-container).swal2-top>.swal2-popup{grid-column:2;place-self:start center}div:where(.swal2-container).swal2-top-end>.swal2-popup,div:where(.swal2-container).swal2-top-right>.swal2-popup{grid-column:3;place-self:start end}div:where(.swal2-container).swal2-center-start>.swal2-popup,div:where(.swal2-container).swal2-center-left>.swal2-popup{grid-row:2;align-self:center}div:where(.swal2-container).swal2-center>.swal2-popup{grid-column:2;grid-row:2;place-self:center center}div:where(.swal2-container).swal2-center-end>.swal2-popup,div:where(.swal2-container).swal2-center-right>.swal2-popup{grid-column:3;grid-row:2;place-self:center end}div:where(.swal2-container).swal2-bottom-start>.swal2-popup,div:where(.swal2-container).swal2-bottom-left>.swal2-popup{grid-column:1;grid-row:3;align-self:end}div:where(.swal2-container).swal2-bottom>.swal2-popup{grid-column:2;grid-row:3;place-self:end center}div:where(.swal2-container).swal2-bottom-end>.swal2-popup,div:where(.swal2-container).swal2-bottom-right>.swal2-popup{grid-column:3;grid-row:3;place-self:end end}div:where(.swal2-container).swal2-grow-row>.swal2-popup,div:where(.swal2-container).swal2-grow-fullscreen>.swal2-popup{grid-column:1/4;width:100%}div:where(.swal2-container).swal2-grow-column>.swal2-popup,div:where(.swal2-container).swal2-grow-fullscreen>.swal2-popup{grid-row:1/4;align-self:stretch}div:where(.swal2-container).swal2-no-transition{transition:none !important}div:where(.swal2-container)[popover]{width:auto;border:0}div:where(.swal2-container) div:where(.swal2-popup){display:none;position:relative;box-sizing:border-box;grid-template-columns:minmax(0, 100%);width:var(--swal2-width);max-width:100%;padding:var(--swal2-padding);border:var(--swal2-border);border-radius:var(--swal2-border-radius);background:var(--swal2-background);color:var(--swal2-color);font-family:inherit;font-size:1rem}div:where(.swal2-container) div:where(.swal2-popup):focus{outline:none}div:where(.swal2-container) div:where(.swal2-popup).swal2-loading{overflow-y:hidden}div:where(.swal2-container) div:where(.swal2-popup).swal2-draggable{cursor:grab}div:where(.swal2-container) div:where(.swal2-popup).swal2-draggable div:where(.swal2-icon){cursor:grab}div:where(.swal2-container) div:where(.swal2-popup).swal2-dragging{cursor:grabbing}div:where(.swal2-container) div:where(.swal2-popup).swal2-dragging div:where(.swal2-icon){cursor:grabbing}div:where(.swal2-container) h2:where(.swal2-title){position:relative;max-width:100%;margin:0;padding:var(--swal2-title-padding);color:inherit;font-size:1.875em;font-weight:600;text-align:center;text-transform:none;overflow-wrap:break-word;cursor:initial}div:where(.swal2-container) div:where(.swal2-actions){display:flex;z-index:1;box-sizing:border-box;flex-wrap:wrap;align-items:center;justify-content:var(--swal2-actions-justify-content);width:var(--swal2-actions-width);margin:var(--swal2-actions-margin);padding:var(--swal2-actions-padding);border-radius:var(--swal2-actions-border-radius);background:var(--swal2-actions-background)}div:where(.swal2-container) div:where(.swal2-loader){display:none;align-items:center;justify-content:center;width:2.2em;height:2.2em;margin:0 1.875em;animation:swal2-rotate-loading 1.5s linear 0s infinite normal;border-width:.25em;border-style:solid;border-radius:100%;border-color:#2778c4 rgba(0,0,0,0) #2778c4 rgba(0,0,0,0)}div:where(.swal2-container) button:where(.swal2-styled){margin:.3125em;padding:.625em 1.1em;transition:var(--swal2-action-button-transition);border:none;box-shadow:0 0 0 3px rgba(0,0,0,0);font-weight:500}div:where(.swal2-container) button:where(.swal2-styled):not([disabled]){cursor:pointer}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm){border-radius:var(--swal2-confirm-button-border-radius);background:initial;background-color:var(--swal2-confirm-button-background-color);box-shadow:var(--swal2-confirm-button-box-shadow);color:var(--swal2-confirm-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm):hover{background-color:color-mix(in srgb, var(--swal2-confirm-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm):active{background-color:color-mix(in srgb, var(--swal2-confirm-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny){border-radius:var(--swal2-deny-button-border-radius);background:initial;background-color:var(--swal2-deny-button-background-color);box-shadow:var(--swal2-deny-button-box-shadow);color:var(--swal2-deny-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny):hover{background-color:color-mix(in srgb, var(--swal2-deny-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-deny):active{background-color:color-mix(in srgb, var(--swal2-deny-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel){border-radius:var(--swal2-cancel-button-border-radius);background:initial;background-color:var(--swal2-cancel-button-background-color);box-shadow:var(--swal2-cancel-button-box-shadow);color:var(--swal2-cancel-button-color);font-size:1em}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel):hover{background-color:color-mix(in srgb, var(--swal2-cancel-button-background-color), var(--swal2-action-button-hover))}div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-cancel):active{background-color:color-mix(in srgb, var(--swal2-cancel-button-background-color), var(--swal2-action-button-active))}div:where(.swal2-container) button:where(.swal2-styled):focus-visible{outline:none;box-shadow:var(--swal2-action-button-focus-box-shadow)}div:where(.swal2-container) button:where(.swal2-styled)[disabled]:not(.swal2-loading){opacity:.4}div:where(.swal2-container) button:where(.swal2-styled)::-moz-focus-inner{border:0}div:where(.swal2-container) div:where(.swal2-footer){margin:1em 0 0;padding:1em 1em 0;border-top:1px solid var(--swal2-footer-border-color);background:var(--swal2-footer-background);color:var(--swal2-footer-color);font-size:1em;text-align:center;cursor:initial}div:where(.swal2-container) .swal2-timer-progress-bar-container{position:absolute;right:0;bottom:0;left:0;grid-column:auto !important;overflow:hidden;border-bottom-right-radius:var(--swal2-border-radius);border-bottom-left-radius:var(--swal2-border-radius)}div:where(.swal2-container) div:where(.swal2-timer-progress-bar){width:100%;height:.25em;background:var(--swal2-timer-progress-bar-background)}div:where(.swal2-container) img:where(.swal2-image){max-width:100%;margin:2em auto 1em;cursor:initial}div:where(.swal2-container) button:where(.swal2-close){position:var(--swal2-close-button-position);inset:var(--swal2-close-button-inset);z-index:2;align-items:center;justify-content:center;width:1.2em;height:1.2em;margin-top:0;margin-right:0;margin-bottom:-1.2em;padding:0;overflow:hidden;transition:var(--swal2-close-button-transition);border:none;border-radius:var(--swal2-border-radius);outline:var(--swal2-close-button-outline);background:rgba(0,0,0,0);color:var(--swal2-close-button-color);font-family:monospace;font-size:var(--swal2-close-button-font-size);cursor:pointer;justify-self:end}div:where(.swal2-container) button:where(.swal2-close):hover{transform:var(--swal2-close-button-hover-transform);background:rgba(0,0,0,0);color:#f27474}div:where(.swal2-container) button:where(.swal2-close):focus-visible{outline:none;box-shadow:var(--swal2-close-button-focus-box-shadow)}div:where(.swal2-container) button:where(.swal2-close)::-moz-focus-inner{border:0}div:where(.swal2-container) div:where(.swal2-html-container){z-index:1;justify-content:center;margin:0;padding:var(--swal2-html-container-padding);overflow:auto;color:inherit;font-size:1.125em;font-weight:normal;line-height:normal;text-align:center;overflow-wrap:break-word;word-break:break-word;cursor:initial}div:where(.swal2-container) input:where(.swal2-input),div:where(.swal2-container) input:where(.swal2-file),div:where(.swal2-container) textarea:where(.swal2-textarea),div:where(.swal2-container) select:where(.swal2-select),div:where(.swal2-container) div:where(.swal2-radio),div:where(.swal2-container) label:where(.swal2-checkbox){margin:1em 2em 3px}div:where(.swal2-container) input:where(.swal2-input),div:where(.swal2-container) input:where(.swal2-file),div:where(.swal2-container) textarea:where(.swal2-textarea){box-sizing:border-box;width:auto;transition:var(--swal2-input-transition);border:var(--swal2-input-border);border-radius:var(--swal2-input-border-radius);background:var(--swal2-input-background);box-shadow:var(--swal2-input-box-shadow);color:inherit;font-size:1.125em}div:where(.swal2-container) input:where(.swal2-input).swal2-inputerror,div:where(.swal2-container) input:where(.swal2-file).swal2-inputerror,div:where(.swal2-container) textarea:where(.swal2-textarea).swal2-inputerror{border-color:#f27474 !important;box-shadow:0 0 2px #f27474 !important}div:where(.swal2-container) input:where(.swal2-input):hover,div:where(.swal2-container) input:where(.swal2-file):hover,div:where(.swal2-container) textarea:where(.swal2-textarea):hover{box-shadow:var(--swal2-input-hover-box-shadow)}div:where(.swal2-container) input:where(.swal2-input):focus,div:where(.swal2-container) input:where(.swal2-file):focus,div:where(.swal2-container) textarea:where(.swal2-textarea):focus{border:var(--swal2-input-focus-border);outline:none;box-shadow:var(--swal2-input-focus-box-shadow)}div:where(.swal2-container) input:where(.swal2-input)::placeholder,div:where(.swal2-container) input:where(.swal2-file)::placeholder,div:where(.swal2-container) textarea:where(.swal2-textarea)::placeholder{color:#ccc}div:where(.swal2-container) .swal2-range{margin:1em 2em 3px;background:var(--swal2-background)}div:where(.swal2-container) .swal2-range input{width:80%}div:where(.swal2-container) .swal2-range output{width:20%;color:inherit;font-weight:600;text-align:center}div:where(.swal2-container) .swal2-range input,div:where(.swal2-container) .swal2-range output{height:2.625em;padding:0;font-size:1.125em;line-height:2.625em}div:where(.swal2-container) .swal2-input{height:2.625em;padding:0 .75em}div:where(.swal2-container) .swal2-file{width:75%;margin-right:auto;margin-left:auto;background:var(--swal2-input-background);font-size:1.125em}div:where(.swal2-container) .swal2-textarea{height:6.75em;padding:.75em}div:where(.swal2-container) .swal2-select{min-width:50%;max-width:100%;padding:.375em .625em;background:var(--swal2-input-background);color:inherit;font-size:1.125em}div:where(.swal2-container) .swal2-radio,div:where(.swal2-container) .swal2-checkbox{align-items:center;justify-content:center;background:var(--swal2-background);color:inherit}div:where(.swal2-container) .swal2-radio label,div:where(.swal2-container) .swal2-checkbox label{margin:0 .6em;font-size:1.125em}div:where(.swal2-container) .swal2-radio input,div:where(.swal2-container) .swal2-checkbox input{flex-shrink:0;margin:0 .4em}div:where(.swal2-container) label:where(.swal2-input-label){display:flex;justify-content:center;margin:1em auto 0}div:where(.swal2-container) div:where(.swal2-validation-message){align-items:center;justify-content:center;margin:1em 0 0;padding:.625em;overflow:hidden;background:var(--swal2-validation-message-background);color:var(--swal2-validation-message-color);font-size:1em;font-weight:300}div:where(.swal2-container) div:where(.swal2-validation-message)::before{content:\"!\";display:inline-block;width:1.5em;min-width:1.5em;height:1.5em;margin:0 .625em;border-radius:50%;background-color:#f27474;color:#fff;font-weight:600;line-height:1.5em;text-align:center}div:where(.swal2-container) .swal2-progress-steps{flex-wrap:wrap;align-items:center;max-width:100%;margin:1.25em auto;padding:0;background:rgba(0,0,0,0);font-weight:600}div:where(.swal2-container) .swal2-progress-steps li{display:inline-block;position:relative}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step{z-index:20;flex-shrink:0;width:2em;height:2em;border-radius:2em;background:#2778c4;color:#fff;line-height:2em;text-align:center}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step{background:#2778c4}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step~.swal2-progress-step{background:var(--swal2-progress-step-background);color:#fff}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step.swal2-active-progress-step~.swal2-progress-step-line{background:var(--swal2-progress-step-background)}div:where(.swal2-container) .swal2-progress-steps .swal2-progress-step-line{z-index:10;flex-shrink:0;width:2.5em;height:.4em;margin:0 -1px;background:#2778c4}div:where(.swal2-icon){position:relative;box-sizing:content-box;justify-content:center;width:5em;height:5em;margin:2.5em auto .6em;zoom:var(--swal2-icon-zoom);border:.25em solid rgba(0,0,0,0);border-radius:50%;border-color:#000;font-family:inherit;line-height:5em;cursor:default;user-select:none}div:where(.swal2-icon) .swal2-icon-content{display:flex;align-items:center;font-size:3.75em}div:where(.swal2-icon).swal2-error{border-color:#f27474;color:#f27474}div:where(.swal2-icon).swal2-error .swal2-x-mark{position:relative;flex-grow:1}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line]{display:block;position:absolute;top:2.3125em;width:2.9375em;height:.3125em;border-radius:.125em;background-color:#f27474}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line][class$=left]{left:1.0625em;transform:rotate(45deg)}div:where(.swal2-icon).swal2-error [class^=swal2-x-mark-line][class$=right]{right:1em;transform:rotate(-45deg)}div:where(.swal2-icon).swal2-error.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-error.swal2-icon-show .swal2-x-mark{animation:swal2-animate-error-x-mark .5s}div:where(.swal2-icon).swal2-warning{border-color:#f8bb86;color:#f8bb86}div:where(.swal2-icon).swal2-warning.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-warning.swal2-icon-show .swal2-icon-content{animation:swal2-animate-i-mark .5s}div:where(.swal2-icon).swal2-info{border-color:#3fc3ee;color:#3fc3ee}div:where(.swal2-icon).swal2-info.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-info.swal2-icon-show .swal2-icon-content{animation:swal2-animate-i-mark .8s}div:where(.swal2-icon).swal2-question{border-color:#87adbd;color:#87adbd}div:where(.swal2-icon).swal2-question.swal2-icon-show{animation:swal2-animate-error-icon .5s}div:where(.swal2-icon).swal2-question.swal2-icon-show .swal2-icon-content{animation:swal2-animate-question-mark .8s}div:where(.swal2-icon).swal2-success{border-color:#a5dc86;color:#a5dc86}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line]{position:absolute;width:3.75em;height:7.5em;border-radius:50%}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line][class$=left]{top:-0.4375em;left:-2.0635em;transform:rotate(-45deg);transform-origin:3.75em 3.75em;border-radius:7.5em 0 0 7.5em}div:where(.swal2-icon).swal2-success [class^=swal2-success-circular-line][class$=right]{top:-0.6875em;left:1.875em;transform:rotate(-45deg);transform-origin:0 3.75em;border-radius:0 7.5em 7.5em 0}div:where(.swal2-icon).swal2-success .swal2-success-ring{position:absolute;z-index:2;top:-0.25em;left:-0.25em;box-sizing:content-box;width:100%;height:100%;border:.25em solid rgba(165,220,134,.3);border-radius:50%}div:where(.swal2-icon).swal2-success .swal2-success-fix{position:absolute;z-index:1;top:.5em;left:1.625em;width:.4375em;height:5.625em;transform:rotate(-45deg)}div:where(.swal2-icon).swal2-success [class^=swal2-success-line]{display:block;position:absolute;z-index:2;height:.3125em;border-radius:.125em;background-color:#a5dc86}div:where(.swal2-icon).swal2-success [class^=swal2-success-line][class$=tip]{top:2.875em;left:.8125em;width:1.5625em;transform:rotate(45deg)}div:where(.swal2-icon).swal2-success [class^=swal2-success-line][class$=long]{top:2.375em;right:.5em;width:2.9375em;transform:rotate(-45deg)}div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-line-tip{animation:swal2-animate-success-line-tip .75s}div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-line-long{animation:swal2-animate-success-line-long .75s}div:where(.swal2-icon).swal2-success.swal2-icon-show .swal2-success-circular-line-right{animation:swal2-rotate-success-circular-line 4.25s ease-in}[class^=swal2]{-webkit-tap-highlight-color:rgba(0,0,0,0)}.swal2-show{animation:var(--swal2-show-animation)}.swal2-hide{animation:var(--swal2-hide-animation)}.swal2-noanimation{transition:none}.swal2-scrollbar-measure{position:absolute;top:-9999px;width:50px;height:50px;overflow:scroll}.swal2-rtl .swal2-close{margin-right:initial;margin-left:0}.swal2-rtl .swal2-timer-progress-bar{right:0;left:auto}.swal2-toast{box-sizing:border-box;grid-column:1/4 !important;grid-row:1/4 !important;grid-template-columns:min-content auto min-content;padding:1em;overflow-y:hidden;border:var(--swal2-toast-border);background:var(--swal2-background);box-shadow:var(--swal2-toast-box-shadow);pointer-events:auto}.swal2-toast>*{grid-column:2}.swal2-toast h2:where(.swal2-title){margin:.5em 1em;padding:0;font-size:1em;text-align:initial}.swal2-toast .swal2-loading{justify-content:center}.swal2-toast input:where(.swal2-input){height:2em;margin:.5em;font-size:1em}.swal2-toast .swal2-validation-message{font-size:1em}.swal2-toast div:where(.swal2-footer){margin:.5em 0 0;padding:.5em 0 0;font-size:.8em}.swal2-toast button:where(.swal2-close){grid-column:3/3;grid-row:1/99;align-self:center;width:.8em;height:.8em;margin:0;font-size:2em}.swal2-toast div:where(.swal2-html-container){margin:.5em 1em;padding:0;overflow:initial;font-size:1em;text-align:initial}.swal2-toast div:where(.swal2-html-container):empty{padding:0}.swal2-toast .swal2-loader{grid-column:1;grid-row:1/99;align-self:center;width:2em;height:2em;margin:.25em}.swal2-toast .swal2-icon{grid-column:1;grid-row:1/99;align-self:center;width:2em;min-width:2em;height:2em;margin:0 .5em 0 0}.swal2-toast .swal2-icon .swal2-icon-content{display:flex;align-items:center;font-size:1.8em;font-weight:bold}.swal2-toast .swal2-icon.swal2-success .swal2-success-ring{width:2em;height:2em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line]{top:.875em;width:1.375em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line][class$=left]{left:.3125em}.swal2-toast .swal2-icon.swal2-error [class^=swal2-x-mark-line][class$=right]{right:.3125em}.swal2-toast div:where(.swal2-actions){justify-content:flex-start;height:auto;margin:0;margin-top:.5em;padding:0 .5em}.swal2-toast button:where(.swal2-styled){margin:.25em .5em;padding:.4em .6em;font-size:1em}.swal2-toast .swal2-success{border-color:#a5dc86}.swal2-toast .swal2-success [class^=swal2-success-circular-line]{position:absolute;width:1.6em;height:3em;border-radius:50%}.swal2-toast .swal2-success [class^=swal2-success-circular-line][class$=left]{top:-0.8em;left:-0.5em;transform:rotate(-45deg);transform-origin:2em 2em;border-radius:4em 0 0 4em}.swal2-toast .swal2-success [class^=swal2-success-circular-line][class$=right]{top:-0.25em;left:.9375em;transform-origin:0 1.5em;border-radius:0 4em 4em 0}.swal2-toast .swal2-success .swal2-success-ring{width:2em;height:2em}.swal2-toast .swal2-success .swal2-success-fix{top:0;left:.4375em;width:.4375em;height:2.6875em}.swal2-toast .swal2-success [class^=swal2-success-line]{height:.3125em}.swal2-toast .swal2-success [class^=swal2-success-line][class$=tip]{top:1.125em;left:.1875em;width:.75em}.swal2-toast .swal2-success [class^=swal2-success-line][class$=long]{top:.9375em;right:.1875em;width:1.375em}.swal2-toast .swal2-success.swal2-icon-show .swal2-success-line-tip{animation:swal2-toast-animate-success-line-tip .75s}.swal2-toast .swal2-success.swal2-icon-show .swal2-success-line-long{animation:swal2-toast-animate-success-line-long .75s}.swal2-toast.swal2-show{animation:var(--swal2-toast-show-animation)}.swal2-toast.swal2-hide{animation:var(--swal2-toast-hide-animation)}@keyframes swal2-show{0%{transform:translate3d(0, -50px, 0) scale(0.9);opacity:0}100%{transform:translate3d(0, 0, 0) scale(1);opacity:1}}@keyframes swal2-hide{0%{transform:translate3d(0, 0, 0) scale(1);opacity:1}100%{transform:translate3d(0, -50px, 0) scale(0.9);opacity:0}}@keyframes swal2-animate-success-line-tip{0%{top:1.1875em;left:.0625em;width:0}54%{top:1.0625em;left:.125em;width:0}70%{top:2.1875em;left:-0.375em;width:3.125em}84%{top:3em;left:1.3125em;width:1.0625em}100%{top:2.8125em;left:.8125em;width:1.5625em}}@keyframes swal2-animate-success-line-long{0%{top:3.375em;right:2.875em;width:0}65%{top:3.375em;right:2.875em;width:0}84%{top:2.1875em;right:0;width:3.4375em}100%{top:2.375em;right:.5em;width:2.9375em}}@keyframes swal2-rotate-success-circular-line{0%{transform:rotate(-45deg)}5%{transform:rotate(-45deg)}12%{transform:rotate(-405deg)}100%{transform:rotate(-405deg)}}@keyframes swal2-animate-error-x-mark{0%{margin-top:1.625em;transform:scale(0.4);opacity:0}50%{margin-top:1.625em;transform:scale(0.4);opacity:0}80%{margin-top:-0.375em;transform:scale(1.15)}100%{margin-top:0;transform:scale(1);opacity:1}}@keyframes swal2-animate-error-icon{0%{transform:rotateX(100deg);opacity:0}100%{transform:rotateX(0deg);opacity:1}}@keyframes swal2-rotate-loading{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}@keyframes swal2-animate-question-mark{0%{transform:rotateY(-360deg)}100%{transform:rotateY(0)}}@keyframes swal2-animate-i-mark{0%{transform:rotateZ(45deg);opacity:0}25%{transform:rotateZ(-25deg);opacity:.4}50%{transform:rotateZ(15deg);opacity:.8}75%{transform:rotateZ(-5deg);opacity:1}100%{transform:rotateX(0);opacity:1}}@keyframes swal2-toast-show{0%{transform:translateY(-0.625em) rotateZ(2deg)}33%{transform:translateY(0) rotateZ(-2deg)}66%{transform:translateY(0.3125em) rotateZ(2deg)}100%{transform:translateY(0) rotateZ(0deg)}}@keyframes swal2-toast-hide{100%{transform:rotateZ(1deg);opacity:0}}@keyframes swal2-toast-animate-success-line-tip{0%{top:.5625em;left:.0625em;width:0}54%{top:.125em;left:.125em;width:0}70%{top:.625em;left:-0.25em;width:1.625em}84%{top:1.0625em;left:.75em;width:.5em}100%{top:1.125em;left:.1875em;width:.75em}}@keyframes swal2-toast-animate-success-line-long{0%{top:1.625em;right:1.375em;width:0}65%{top:1.25em;right:.9375em;width:0}84%{top:.9375em;right:0;width:1.125em}100%{top:.9375em;right:.1875em;width:1.375em}}");
/* sweetalert-dark.js
 * Tema oscuro global para SweetAlert2 (estilo Windows 11).
 * - Emite la librería SweetAlert2 bajo demanda si no está cargada.
 * - Aplica colores/estilos dark por defecto a TODOS los modales.
 * - Los parámetros explícitos de cada llamada a Swal.fire() tienen prioridad.
 */
(function () {
    'use strict';

    var DARK_THEME = {
        background: '#1a1a24',
        color: '#ffffff',
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#33333f',
        reverseButtons: true,
        backdrop: 'rgba(0, 0, 0, 0.72)'
    };

    var DARK_CUSTOM_CLASS = {
        popup: 'swal-dark-popup',
        title: 'swal-dark-title',
        htmlContainer: 'swal-dark-html',
        confirmButton: 'swal-dark-confirm',
        cancelButton: 'swal-dark-cancel'
    };

    function injectDarkCss() {
        var css =
            '.swal-dark-popup{border:1px solid rgba(255,255,255,0.12) !important;border-radius:16px !important;' +
            'backdrop-filter:blur(24px) !important;font-family:"Inter","Segoe UI",sans-serif;}' +
            '.swal-dark-title{color:#ffffff !important;}' +
            '.swal-dark-html{color:#d1d5db !important;}' +
            '.swal-dark-confirm,.swal-dark-cancel{border-radius:8px !important;font-weight:600 !important;}';
        var style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }

    function swalBaseUrl() {
        var path = (window.location.pathname || '').toLowerCase();
        if (path.indexOf('/modules/') !== -1 || path.indexOf('/instalarbd/') !== -1) {
            return '../nominas/js/';
        }
        return 'js/';
    }

    function applyTheme() {
        if (!window.Swal || typeof Swal.fire !== 'function') return;
        injectDarkCss();

        if (Swal.DEFAULT_PARAMS && typeof Swal.DEFAULT_PARAMS === 'object') {
            Swal.DEFAULT_PARAMS = Object.assign({}, DARK_THEME, Swal.DEFAULT_PARAMS);
        }

        // Envolver Swal.fire para fusionar el tema oscuro con las opciones de cada llamada
        var origFire = Swal.fire.bind(Swal);
        Swal.fire = function (arg) {
            var opts = (typeof arg === 'string') ? { title: arg } : (arg || {});
            var merged = Object.assign({}, DARK_THEME, opts);
            if (opts.customClass && typeof opts.customClass === 'object') {
                merged.customClass = Object.assign({}, DARK_CUSTOM_CLASS, opts.customClass);
            } else {
                merged.customClass = DARK_CUSTOM_CLASS;
            }
            return origFire(merged);
        };
    }

    function emitSwalAndApply() {
        var tries = 0;
        var timer = setInterval(function () {
            if (window.Swal && typeof Swal.fire === 'function') {
                clearInterval(timer);
                applyTheme();
            } else if (++tries > 300) {
                clearInterval(timer);
            }
        }, 25);
    }

    if (window.Swal && typeof Swal.fire === 'function') {
        applyTheme();
    } else {
        // SweetAlert2 aún no está cargado: emitirlo bajo demanda
        var s = document.createElement('script');
        s.src = swalBaseUrl() + 'sweetalert2.all.min.js';
        s.onload = function () { applyTheme(); };
        document.head.appendChild(s);
        emitSwalAndApply();
    }
})();
</script>
<script>
var SYS_NAME = <?php echo json_encode(SYS_NAME, JSON_UNESCAPED_UNICODE); ?>;
var SYS_NAME_SHORT = <?php echo json_encode(SYS_NAME_SHORT, JSON_UNESCAPED_UNICODE); ?>;
var SYS_DBNAME = <?php echo json_encode(SYS_DBNAME, JSON_UNESCAPED_UNICODE); ?>;
var INSTALLER_VERSION = <?php echo json_encode(INSTALLER_VERSION); ?>;

function togglePass(id, btn) {
    var input = document.getElementById(id);
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
}

var STEPS = [
    { key: 'welcome',    title: 'Bienvenida',             desc: 'Bienvenido al asistente de instalación.' },
    { key: 'requirements', title: 'Requisitos',           desc: 'Verificación de requisitos del servidor.' },
    { key: 'database',   title: 'Base de Datos',          desc: 'Cree la base de datos e importe el esquema.' },
    { key: 'company',    title: 'Empresa',                desc: 'Datos generales de la entidad.' },
    { key: 'config',     title: 'Configuración',          desc: 'Ajustes del sistema y correo electrónico.' },
    { key: 'admin',      title: 'Usuario Admin',          desc: 'Elija el administrador del sistema.' },
    { key: 'complete',   title: 'Completar',              desc: 'Instalación finalizada.' }
];

var state = {
    step: 0,
    maxStep: 0,
    data: null,
    db: null,
    completed: false,
    empresa: 'Sin Nombre'
};

function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function api(action, body, onOk) {
    openLog();
    var data = new FormData();
    data.append('action', action);
    Object.keys(body || {}).forEach(function (k) { data.append(k, body[k]); });
    var req = new XMLHttpRequest();
    req.open('POST', window.location.href, true);
    req.onreadystatechange = function () {
        if (req.readyState !== 4) return;
        var res;
        try { res = JSON.parse(req.responseText); } catch (e) { res = { success: false, message: 'Respuesta no válida del servidor: ' + req.responseText }; }
        if (res.log) { state.log = res.log; }
        onOk(res);
    };
    req.send(data);
}

function showLoader(on) {
    document.getElementById('loader').style.display = on ? 'block' : 'none';
    document.getElementById('wizardContent').style.display = on ? 'none' : 'block';
    if (on) { openLog(); }
}

function notify(msg, type, loading) {
    var a = document.getElementById('alert');
    if (!a) return;
    a.className = 'alert show ' + (type || 'warning') + (loading ? ' loading' : '');
    a.innerHTML = (loading ? '<i class="fa-solid fa-circle-notch spin-icon"></i>' : '') + esc(msg) +
        '<button type="button" class="alert-close" onclick="this.parentNode.style.display=\'none\'" aria-label="Cerrar" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>';
    a.style.display = 'block';
}

function clearNotify() {
    var a = document.getElementById('alert');
    if (a) { a.className = 'alert'; a.style.display = 'none'; }
}

function renderLog() {
    var el = document.getElementById('logBox');
    if (el) { el.textContent = state.log || ''; el.scrollTop = el.scrollHeight; }
}

function renderNav() {
    var nav = document.getElementById('stepsNav');
    var html = '';
    STEPS.forEach(function (s, i) {
        var locked = i > state.maxStep || (state.step >= 3 && i < 3);
        var cls = i === state.step ? 'active' : (locked ? 'locked' : (i < state.step ? 'done' : ''));
        var num = locked ? '&#128274;' : (i < state.step ? '&#10003;' : (i + 1));
        html += '<div class="step-item ' + cls + '"' + (locked ? '' : ' onclick="goStep(' + i + ')"') + '>' +
                '<div class="num">' + num + '</div>' +
                '<div class="lbl">' + esc(s.title) + '</div></div>';
    });
    nav.innerHTML = html;
}

function completeStep(n) {
    if (n + 1 > state.maxStep) { state.maxStep = n + 1; }
    renderNav();
}

function nowStamp() {
    var d = new Date();
    function p(n) { return (n < 10 ? '0' : '') + n; }
    return '[' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds()) + ']';
}

function goStep(i) {
    if (i < 0 || i >= STEPS.length) return;
    if (i > state.maxStep) { notify('Complete primero el paso actual para activar el siguiente.'); return; }
    if (state.step >= 3 && i < 3) { notify('No puede volver a la Base de Datos ni a pasos anteriores.'); return; }
    state.step = i;
    renderNav();
    renderStep();
    if (i === 6 && state.log && state.log.indexOf('TERMINADOS') === -1) {
        state.log += '\n' + nowStamp() + ' PROCESOS DE DESPLIEGUE Y CONFIGURACIONES TERMINADOS!!';
        renderLog();
    }
}

function btnNext(label, action, cls) {
    return '<button class="btn ' + (cls || 'btn-primary') + '" id="btnNext" onclick="' + (action || 'goStep(state.step + 1)') + '"><i class="fa-solid fa-arrow-right"></i>&nbsp;' + label + '</button>';
}

function renderStep() {
    var html = '';
    var s = STEPS[state.step];
    html += '<h2 class="panel-title">' + esc(s.title) + '</h2>';
    html += '<p class="panel-desc">' + esc(s.desc) + '</p>';
    html += '<div class="actions">';
    var prev = state.step <= 2
        ? '<button class="btn btn-secondary" onclick="' + (state.step <= 1 ? 'askEmpresa(function(){ goStep(0); })' : 'goStep(state.step - 1)') + '"><i class="fa-solid fa-arrow-left"></i>&nbsp;Anterior</button>'
        : '<span></span>';
    html += prev;
    html += '</div>';
    html += '<div class="alert" id="alert"></div>';
    html += '<div id="stepContent"></div>';
    document.getElementById('wizardContent').innerHTML = html;

    var actions = document.querySelector('#wizardContent > .actions');
    if (state.step === 0) {
        actions.insertAdjacentHTML('beforeend', btnNext('Siguiente', 'completeStep(0); goStep(state.step + 1)'));
    } else if (state.step === 6) {
        // sin botones por defecto, se renderizan en el contenido
    }
    renderStepContent();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderStepContent() {
    var el = document.getElementById('stepContent');
    var actions = document.querySelector('#wizardContent > .actions');
    clearNotify();
    switch (state.step) {
        case 0: renderWelcome(el, actions); break;
        case 1: renderRequirements(el, actions); break;
        case 2: renderDatabase(el, actions); break;
        case 3: renderCompany(el, actions); break;
        case 4: renderConfig(el, actions); break;
        case 5: renderAdmin(el, actions); break;
        case 6: renderComplete(el, actions); break;
    }
}

function renderWelcome(el, actions) {
    el.innerHTML =
        '<div class="card-info">Este asistente creará la base de datos <b>' + esc(SYS_DBNAME) +
        '</b> del Sistema de Gestión de Nóminas <b>' + esc(state.empresa) + '</b>, importará su esquema, actualizará ' +
        '<b>config.php</b> con las credenciales de conexión y guardará la configuración general.</div>' +
        '<div class="summary">' +
        '<div class="item"><div class="k">Sistema</div><div class="v">Sistema de Gestión de Nóminas ' + esc(state.empresa) + '</div></div>' +
        '<div class="item"><div class="k">Versión del instalador</div><div class="v">' + INSTALLER_VERSION + '</div></div>' +
        '<div class="item"><div class="k">PHP</div><div class="v">' + esc('<?php echo PHP_VERSION; ?>') + '</div></div>' +
        '<div class="item"><div class="k">Base de datos destino</div><div class="v">' + esc(SYS_DBNAME) + '</div></div>' +
        '<div class="item full"><div class="k">Requisitos</div><div class="v small">PHP 8.1 o superior, extensiones mysqli/pdo_mysql/gd/fileinfo, ' +
        'servidor MySQL accesible, permisos de escritura en la carpeta de NOMINAS y carpeta vendor (PHPMailer).</div></div>' +
        '</div>';
}

function mysqlAuthHtml(a) {
    a = a || {};
    return '<div class="mysql-auth">' +
           '<div class="auth-title">Credenciales de acceso a MySQL</div>' +
           '<input class="form-control" id="mysql_host" placeholder="Host" value="' + esc(a.host || 'localhost') + '" style="margin-bottom:6px">' +
           '<input class="form-control" id="mysql_port" placeholder="Puerto" value="' + esc(a.port || '3306') + '" style="margin-bottom:6px">' +
           '<input class="form-control" id="mysql_user" placeholder="Usuario" value="' + esc(a.user || 'root') + '" style="margin-bottom:6px">' +
           '<div class="password-wrapper"><input class="form-control" type="password" id="mysql_pass" placeholder="Contraseña" value="">' +
           '<button type="button" class="password-toggle" onclick="togglePass(\'mysql_pass\', this)" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div>' +
           '<button class="btn btn-sm btn-outline" style="width:100%;justify-content:center;margin-top:8px" onclick="testMysqlAuth()">' +
           '<i class="fa-solid fa-plug"></i>&nbsp;Probar conexión</button>' +
           '<div id="mysqlAuthMsg" class="auth-msg"></div>' +
           '</div>';
}

function testMysqlAuth() {
    var msg = document.getElementById('mysqlAuthMsg');
    if (msg) { msg.className = 'auth-msg'; msg.innerHTML = 'Probando...'; }
    var body = {
        db_host: document.getElementById('mysql_host').value,
        db_port: document.getElementById('mysql_port').value,
        db_user: document.getElementById('mysql_user').value,
        db_pass: document.getElementById('mysql_pass').value
    };
    api('test_connection', body, function (res) {
        if (res.success) {
            if (msg) { msg.className = 'auth-msg ok'; msg.innerHTML = '<i class="fa-solid fa-check"></i> ' + esc(res.message); }
            notify(res.message, 'success');
            setTimeout(renderStepContent, 800);
        } else {
            if (msg) { msg.className = 'auth-msg err'; msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + esc(res.message || 'No se pudo conectar.'); }
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error de conexion', html: '<i class="fa-solid fa-triangle-exclamation" style="font-size:48px;color:#e74c3c"></i><br>' + esc(mysqlErrorFront(res.message)), confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Aceptar' });
            } else {
                notify(res.message || 'Error de conexion.', 'error');
            }
        }
    });
}

function renderRequirements(el, actions) {
    el.innerHTML = '<div id="reqResult">Cargando requisitos...</div>';    api('check_requirements', {}, function (res) {
        if (!res.success) { el.innerHTML = '<div class="alert error show">' + esc(res.message) + '</div>'; return; }
        state.data = res.data;
        var lis = '';
        (res.data.requirements || []).forEach(function (r) {
            lis += '<li class="' + (r.ok ? 'ok' : 'fail') + '">' +
                   '<div class="req-head"><span class="icon">' + (r.ok ? '&#9989;' : '&#10060;') + '</span>' +
                   '<span class="rname">' + esc(r.name) + '</span></div>' +
                   '<span class="val">' + esc(r.value || '') + '</span>' +
                   (r.desc ? '<span class="rdesc">' + esc(r.desc) + '</span>' : '') +
                   (r.showAuth ? mysqlAuthHtml(r.auth) : '') +
                   '</li>';
        });
        var alertHtml = res.data.allOk
            ? '<div class="alert success show">Todos los requisitos se cumplen. Puede continuar.</div>'
            : (res.data.soloOpcionalPendiente
                ? '<div class="alert success show">Solo falta un requisito opcional (no impide continuar). Puede continuar igualmente.</div>'
                : '<div class="alert error show">Hay requisitos pendientes. Corríjalos antes de continuar.</div>');
        el.innerHTML = alertHtml + '<ul class="req-list">' + lis + '</ul>';
    actions.innerHTML = '';
    actions.insertAdjacentHTML('beforeend', state.step > 0 ? '<button class="btn btn-secondary" onclick="goStep(state.step - 1)"><i class="fa-solid fa-arrow-left"></i>&nbsp;Anterior</button>' : '<span></span>');
    if (res.data.allOk) {
        actions.insertAdjacentHTML('beforeend', btnNext('Siguiente', 'completeStep(1); goStep(state.step + 1)', 'btn-primary'));
    } else {
        if (res.data.soloOpcionalPendiente) {
            actions.insertAdjacentHTML('beforeend', btnNext('<i class="fa-solid fa-circle-check"></i>&nbsp;Continuar', 'completeStep(1); goStep(state.step + 1)', 'btn-primary'));
        }
        actions.insertAdjacentHTML('beforeend', btnNext('Reintentar', 'renderStepContent()', 'btn-secondary'));
    }
    });
}

function renderDatabase(el, actions) {
    var d = { host: 'localhost', port: 3306, name: '<?php echo SYS_DBNAME; ?>', user: 'root', pass: '' };
    if (state.db) { d = state.db; }
    el.innerHTML =
        '<div class="form-grid">' +
        '<div class="form-group"><label>Servidor (host)</label><input class="form-control" id="db_host" value="' + esc(d.host) + '"></div>' +
        '<div class="form-group"><label>Puerto</label><input class="form-control" id="db_port" value="' + esc(d.port) + '"></div>' +
        '<div class="form-group"><label>Nombre de la base de datos</label><input class="form-control" id="db_name" value="' + esc(d.name) + '"></div>' +
        '<div class="form-group"><label>Usuario</label><input class="form-control" id="db_user" value="' + esc(d.user) + '"></div>' +
        '<div class="form-group"><label>Contraseña</label><div class="password-wrapper"><input class="form-control" type="password" id="db_pass" value="' + esc(d.pass) + '">' +
        '<button type="button" class="password-toggle" onclick="togglePass(\'db_pass\', this)" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div></div>' +
        '<div class="form-group"><label>&nbsp;</label><div class="check-row" style="margin-top:10px"><input type="checkbox" id="db_recreate"><span>Reiniciar BD (eliminar la base de datos si existe)</span></div></div>' +
        '</div>' +
        '<div class="actions" style="margin-top:18px;border-top:none;padding-top:0">' +
        '<button class="btn btn-outline btn-sm" onclick="testConn()"><i class="fa-solid fa-plug"></i>&nbsp;Probar conexión</button>' +
        '<button class="btn btn-primary" onclick="createDb()"><i class="fa-solid fa-database"></i>&nbsp;Crear Base de Datos</button>' +
        '</div>' +
        '<div class="salva-row">' +
        '<button class="btn btn-salva btn-sm" id="btnSalva" disabled onclick="document.getElementById(\'salvaFile\').click()">' +
        '<i class="fa-solid fa-cloud-arrow-up"></i>&nbsp;Subir una salva</button>' +
        '<span class="salva-hint">Importar una copia de seguridad <b>.sql</b> o <b>.zip</b> de la base de datos. <b>Requiere conexión probada.</b></span>' +
        '<input type="file" id="salvaFile" accept=".sql,.zip" style="display:none" onchange="subirSalva()">' +
        '</div>' +
        '<div class="log-toggle" id="logToggle" onclick="toggleLog()"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
        '<div class="log-box" id="logBox" style="display:none"></div>';

    var collect = function () {
        return {
            db_host: document.getElementById('db_host').value,
            db_port: document.getElementById('db_port').value,
            db_name: document.getElementById('db_name').value,
            db_user: document.getElementById('db_user').value,
            db_pass: document.getElementById('db_pass').value,
            db_recreate: document.getElementById('db_recreate').checked ? '1' : '0'
        };
    };
    state.collectDb = collect;

    var actions2 = document.querySelector('#wizardContent > .actions');
    actions2.innerHTML = '';
    actions2.insertAdjacentHTML('beforeend', '<button class="btn btn-secondary" onclick="goStep(state.step - 1)"><i class="fa-solid fa-arrow-left"></i>&nbsp;Anterior</button>');
    actions2.insertAdjacentHTML('beforeend', btnNext('Siguiente', 'goNextAfterDb()'));
}

function openLog() {
    var box = document.getElementById('logBox');
    var tog = document.getElementById('logToggle');
    if (!box) return;
    box.classList.remove('collapsed');
    box.style.display = 'block';
    if (tog) { tog.classList.add('open'); }
    renderLog();
}

function toggleLog() {
    var box = document.getElementById('logBox');
    var tog = document.getElementById('logToggle');
    if (!box) return;
    if (box.classList.contains('collapsed') || box.style.display === 'none') {
        openLog();
    } else {
        box.classList.add('collapsed');
        if (tog) { tog.classList.remove('open'); }
    }
}

function clearLog() {
    state.log = '';
    var box = document.getElementById('logBox');
    if (box) { box.textContent = ''; }
}

function showDbLog() {
    openLog();
}

function testConn() {
    if (!state.collectDb) return;
    notify('Probando conexión...', 'warning', true);
    api('test_connection', state.collectDb(), function (res) {
        if (res.success) {
            notify('Conexion exitosa a MySQL (servidor ' + esc(res.server) + ').', 'success');
            var b = document.getElementById('btnSalva');
            if (b) b.disabled = false;
        }
        else {
            var errMsg = mysqlErrorFront(res.message);
            notify(errMsg, 'error');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error de conexion', html: '<i class="fa-solid fa-triangle-exclamation" style="font-size:48px;color:#e74c3c"></i><br>' + esc(errMsg), confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Aceptar' });
            }
        }
        showDbLog();
    });
}

function subirSalva() {
    var input = document.getElementById('salvaFile');
    if (!input || !input.files || !input.files[0]) { notify('Seleccione un archivo .sql o .zip.', 'warning'); return; }
    if (!state.collectDb) return;
    var file = input.files[0];
    var ext = file.name.toLowerCase().split('.').pop();
    if (ext !== 'sql' && ext !== 'zip') { notify('El archivo debe tener extensión .sql o .zip', 'error'); return; }
    var body = state.collectDb();
    var data = new FormData();
    data.append('action', 'upload_salva');
    Object.keys(body).forEach(function (k) { data.append(k, body[k]); });
    data.append('salva', file);
    showLoader(true);
    setImportBar(1, 'Subiendo salva "' + esc(file.name) + '"...');
    var req = new XMLHttpRequest();
    req.open('POST', window.location.href, true);
    req.upload.onprogress = function (e) {
        if (e.lengthComputable && e.total > 0) {
            var up = Math.round((e.loaded / e.total) * 100);
            setImportBar(Math.round(up * 0.09), 'Subiendo salva... ' + up + '%');
        }
    };
    req.onreadystatechange = function () {
        if (req.readyState !== 4) return;
        var res;
        try { res = JSON.parse(req.responseText); } catch (e) { res = { success: false, message: 'Respuesta no válida del servidor: ' + req.responseText }; }
        if (res.log) { state.log = res.log; }
        showLoader(false);
        if (!res.success) {
            notify(res.message, 'error');
            showDbLog();
            input.value = '';
            return;
        }
        if (res.dbname) { body.db_name = res.dbname; }
        input.value = '';
        importSalvaBatchLoop(body);
    };
    req.send(data);
}

function importSalvaBatchLoop(body) {
    setImportBar(10, 'Importando salva...');
    api('import_salva_batch', {}, function (res) {
        if (res.log) { state.log = res.log; }
        if (res.done) {
            if (res.success) {
                state.db = body;
                state.db.name = res.dbname || body.db_name;
                var dbNameInput = document.getElementById('db_name');
                if (dbNameInput) { dbNameInput.value = state.db.name; }
                state.completed = true;
                if (res.empresa && res.empresa.nombre_empresa) {
                    state.empresa = res.empresa.nombre_empresa;
                }
                state.empresaData = res.empresa || {};
                applyEmpresaName();
                setImportBar(100, res.message || 'Salva importada correctamente.');
                setTimeout(function () {
                    notify(res.message || 'Salva importada correctamente.', 'success');
                    completeStep(2);
                    var hasEmpresa = state.empresaData && state.empresaData.nombre_empresa;
                    if (hasEmpresa) { completeStep(3); }
                    mostrarRepetirProceso();
                    var actions = document.querySelector('#wizardContent > .actions');
                    actions.innerHTML = '';
                    actions.insertAdjacentHTML('beforeend', '<button class="btn btn-secondary" onclick="goStep(state.step - 1)"><i class="fa-solid fa-arrow-left"></i>&nbsp;Anterior</button>');
                    actions.insertAdjacentHTML('beforeend', btnNext('Siguiente', 'goNextAfterDb()'));
                }, 400);
            } else {
                var msg = res.message || 'La salva se importó con errores.';
                if (res.errors && res.errors.length) { msg += ' ' + res.errors.slice(0, 3).join(' | '); }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error al importar la salva', html: '<i class="fa-solid fa-triangle-exclamation" style="font-size:48px;color:#e74c3c"></i><br>' + esc(mysqlErrorFront(msg)), confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Aceptar' });
                } else {
                    notify(msg, 'error');
                }
            }
        } else {
            var pct = Math.round((res.processed / res.total) * 90) + 10;
            setImportBar(pct, 'Importando salva... ' + res.processed + '/' + res.total);
            importSalvaBatchLoop(body);
        }
        showDbLog();
    });
}

function setImportBar(pct, label) {
    var a = document.getElementById('alert');
    if (!a) return;
    pct = Math.max(0, Math.min(100, pct));
    a.className = 'alert show warning';
    a.innerHTML = esc(label) + '<div class="pbar"><div class="pbar-fill" style="width:' + pct + '%"></div></div>' +
        '<button type="button" class="alert-close" onclick="this.parentNode.style.display=\'none\'" aria-label="Cerrar" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>';
    a.style.display = 'block';
}

function mysqlErrorFront(msg) {
    if (!msg) return 'Ocurrió un error desconocido.';
    var map = [
        [/Access denied.*using password:\s*YES/i, 'Usuario y/o Contraseña incorrecta de la base de datos.'],
        [/Access denied/i, 'Acceso denegado para el usuario indicado (compruebe usuario y contraseña).'],
        [/Unknown database/i, 'La base de datos especificada no existe.'],
        [/Can't connect/i, 'No se pudo conectar al servidor MySQL (¿está en ejecución?).'],
        [/Connection refused/i, 'Conexión rechazada por el servidor MySQL.'],
        [/Connection timed out/i, 'Tiempo de espera de conexión agotado.'],
        [/Unknown host/i, 'Host de MySQL desconocido o inaccesible.'],
        [/Duplicate entry/i, 'Entrada duplicada en una tabla.'],
        [/Table .* already exists/i, 'Una de las tablas ya existe.'],
        [/Table .* doesn't exist/i, 'Una de las tablas del esquema no existe.'],
        [/permission denied/i, 'Permiso denegado en MySQL.'],
        [/Cannot drop database/i, 'No se pudo eliminar la base de datos.']
    ];
    for (var i = 0; i < map.length; i++) {
        if (map[i][0].test(msg)) return map[i][1];
    }
    return msg;
}

function createDb() {
    if (!state.collectDb) return;
    var btn = document.querySelector('#stepContent .btn-primary');
    if (btn) btn.disabled = true;
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Creando base de datos',
            html: '<i class="fa-solid fa-database" style="font-size:48px;color:#0078d4;margin-bottom:12px"></i><br>Creando base de datos e importando esquema, por favor espere...',
            allowOutsideClick: false,
            allowEnterKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });
    }
    notify('Creando base de datos e importando esquema...', 'warning', true);
    api('create_database', state.collectDb(), function (res) {
        Swal.close();
        if (res.success) {
            state.db = state.collectDb();
            state.db.name = state.db.db_name;
            state.completed = true;
            notify('Base de datos creada e importada correctamente.', 'success');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Base de datos creada', html: '<i class="fa-solid fa-check-circle" style="font-size:48px;color:#2ecc71"></i><br>Base de datos creada e importada correctamente.', confirmButtonText: '<i class="fa-solid fa-arrow-right"></i>&nbsp;Continuar' });
            }
            mostrarRepetirProceso();
            var actions = document.querySelector('#wizardContent > .actions');
            actions.innerHTML = '';
            actions.insertAdjacentHTML('beforeend', '<button class="btn btn-secondary" onclick="goStep(state.step - 1)"><i class="fa-solid fa-arrow-left"></i>&nbsp;Anterior</button>');
            actions.insertAdjacentHTML('beforeend', btnNext('Siguiente', 'completeStep(2); goStep(state.step + 1)'));
        } else {
            var msg = mysqlErrorFront(res.message);
            notify(msg, 'error');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error al crear la base de datos', html: '<i class="fa-solid fa-triangle-exclamation" style="font-size:48px;color:#e74c3c"></i><br>' + esc(msg), confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Aceptar' });
            }
            if (btn) btn.disabled = false;
        }
        showDbLog();
    });
}

function mostrarRepetirProceso() {
    var row = document.querySelector('#stepContent .actions');
    if (!row || document.getElementById('btnRepetirProceso')) return;
    row.insertAdjacentHTML('beforeend', '<button class="btn btn-warning" id="btnRepetirProceso" onclick="repetirProcesoDb()"><i class="fa-solid fa-rotate-right"></i>&nbsp;Repetir proceso</button>');
}

function repetirProcesoDb() {
    var b = document.querySelector('#stepContent .actions button[onclick="createDb()"]');
    if (b) b.disabled = false;
    var s = document.getElementById('btnSalva');
    if (s) s.disabled = false;
    var rec = document.getElementById('db_recreate');
    if (rec) rec.checked = true;
    var rp = document.getElementById('btnRepetirProceso');
    if (rp) rp.remove();
    notify('Proceso reiniciado. Puede crear la base de datos nuevamente o importar otra salva.', 'warning');
}

function goNextAfterDb() {
    if (!state.db) { notify('Primero cree la base de datos correctamente.'); return; }
    completeStep(2);
    goStep(state.step + 1);
}

function renderCompany(el, actions) {
    var d = state.empresaData || {};
    var v = function (k, def) { var x = d[k]; return (x !== undefined && x !== null && x !== '') ? x : def; };
    var dbName = (state.db && state.db.name) ? state.db.name : '<?php echo SYS_DBNAME; ?>';
    el.innerHTML =
        '<div class="card-info">Estos datos son parámetros operativos y reglas de negocio que garantizan el correcto funcionamiento del sistema. Actúan como repositorio central de configuración para toda la plataforma.</div>' +
        '<div style="margin-bottom:12px;padding:8px 14px;border-radius:8px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);font-size:13px;color:#4ade80"><i class="fa-solid fa-database"></i>&nbsp;Base de datos: <b>' + esc(dbName) + '</b></div>' +
        '<div class="form-grid grid-empresa">' +
        '<div class="form-group full"><label>Nombre de la Empresa</label><input class="form-control" id="nombre_empresa" value="' + esc(v('nombre_empresa', state.empresa)) + '"></div>' +
        '<div class="form-group col-2"><label>REEUP / Código de Identificación Fiscal</label><input class="form-control" id="reeup_empresa" value="' + esc(v('reeup_empresa', '')) + '"></div>' +
        '<div class="form-group col-2"><label>NIT (Número de Identificación Tributaria)</label><input class="form-control" id="nit_empresa" value="' + esc(v('nit_empresa', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Teléfono de la Empresa</label><input class="form-control" id="telefono_empresa" value="' + esc(v('telefono_empresa', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Correo de Contacto Empresa</label><input class="form-control" id="email_empresa" value="' + esc(v('email_empresa', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Dirección</label><input class="form-control" id="direccion_empresa" value="' + esc(v('direccion_empresa', '')) + '"></div>' +
        '<div class="form-group full"><label>Slogan</label><input class="form-control" id="slogan_empresa" value="' + esc(v('slogan', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Jefe de Proyecto</label><input class="form-control" id="jefe_proyecto" value="' + esc(v('jefe_proyecto', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Especialista en Gestión Económica</label><input class="form-control" id="especialista_gestion" value="' + esc(v('especialista_gestion', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Especialista de Gestión de Rec. Humanos</label><input class="form-control" id="especialista_gestionRRHH" value="' + esc(v('especialista_gestionRRHH', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Intendente</label><input class="form-control" id="intendente" value="' + esc(v('intendente', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Teléfono de Soporte</label><input class="form-control" id="telefono_soporte" value="' + esc(v('telefono_soporte', '')) + '"></div>' +
        '<div class="form-group col-2"><label>Correo de Soporte</label><input class="form-control" id="email_soporte" value="' + esc(v('email_soporte', '')) + '"></div>' +
        '</div>' +
        '<div class="actions" style="margin-top:18px;border-top:none;padding-top:0">' +
        '<button class="btn btn-primary" onclick="saveCompany()"><i class="fa-solid fa-floppy-disk"></i>&nbsp;Guardar y Continuar</button>' +
        '</div>' +
        '<div class="log-toggle" id="logToggle" onclick="toggleLog()"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
        '<div class="log-box" id="logBox" style="display:none"></div>';
}

function dbCredsBody() {
    var b = {};
    if (state.db) {
        b.db_host = state.db.db_host;
        b.db_port = state.db.db_port;
        b.db_name = state.db.db_name;
        b.db_user = state.db.db_user;
        b.db_pass = state.db.db_pass;
    }
    return b;
}

function saveCompany() {
    var body = dbCredsBody();
    body.nombre_empresa = document.getElementById('nombre_empresa').value;
    body.direccion_empresa = document.getElementById('direccion_empresa').value;
    body.reeup_empresa = document.getElementById('reeup_empresa').value;
    body.nit_empresa = document.getElementById('nit_empresa').value;
    body.slogan = document.getElementById('slogan_empresa').value;
    body.telefono_empresa = document.getElementById('telefono_empresa').value;
    body.telefono_soporte = document.getElementById('telefono_soporte').value;
    body.email_soporte = document.getElementById('email_soporte').value;
    body.email_empresa = document.getElementById('email_empresa').value;
    body.jefe_proyecto = document.getElementById('jefe_proyecto').value;
    body.especialista_gestion = document.getElementById('especialista_gestion').value;
    body.especialista_gestionRRHH = document.getElementById('especialista_gestionRRHH').value;
    body.intendente = document.getElementById('intendente').value;
    notify('Guardando datos de la empresa...', 'warning', true);
    api('save_company_config', body, function (res) {
        if (res.success) { notify('Datos de la empresa guardados.', 'success'); completeStep(3); goStep(state.step + 1); }
        else { notify(res.message, 'error'); }
        showDbLog();
    });
}

function renderConfig(el, actions) {
    el.innerHTML =
        '<div class="card-info">Configuración del servidor de correo. El sistema usa estos datos para enviar ' +
        'correos (recuperación de contraseña, notificaciones). Puede dejarla desactivada.</div>' +
        '<div class="form-grid">' +
        '<div class="form-group"><label>Activar envío de correo</label><select class="form-control" id="mail_activo">' +
        '<option value="0">No</option><option value="1" selected>Sí</option></select></div>' +
        '<div class="form-group"><label>Proveedor</label><select class="form-control" id="mail_proveedor" onchange="applyMailProvider()">' +
        '<option value="">Personalizado</option><option value="gmail">Gmail</option><option value="outlook">Outlook</option>' +
        '<option value="zoho">Zoho</option><option value="etecsa" selected>ETECSA</option></select></div>' +
        '<div class="form-group"><label>Servidor SMTP</label><input class="form-control" id="mail_host" value="smtp.nauta.cu"></div>' +
        '<div class="form-group"><label>Puerto</label><input class="form-control" id="mail_port" value="25"></div>' +
        '<div class="form-group"><label>Seguridad</label><select class="form-control" id="mail_encryption">' +
        '<option value="tls">TLS</option><option value="ssl">SSL</option><option value="none" selected>Ninguna</option></select></div>' +
        '<div class="form-group"><label>Usuario</label><input class="form-control" id="mail_usuario" value="kakycu@nauta.cu"></div>' +
        '<div class="form-group"><label>Contraseña / App Password</label><div class="password-wrapper"><input class="form-control" type="password" id="mail_password" value="lrf8110">' +
        '<button type="button" class="password-toggle" onclick="togglePass(\'mail_password\', this)" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div></div>' +
        '<div class="form-group"><label>Correo remitente</label><input class="form-control" id="mail_from" value="noreply_SisGesNom@gmail.com"></div>' +
        '<div class="form-group"><label>Nombre del remitente</label><input class="form-control" id="mail_from_name" value="Soporte ' + esc(state.empresa) + '"></div>' +
        '</div>' +
        '<div class="actions" style="margin-top:18px;border-top:none;padding-top:0">' +
        '<button class="btn btn-primary" onclick="saveSystemConfig()"><i class="fa-solid fa-circle-check"></i>&nbsp;Guardar y Finalizar</button>' +
        '</div>' +
        '<div class="log-toggle" id="logToggle" onclick="toggleLog()"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
        '<div class="log-box" id="logBox" style="display:none"></div>';
}

var MAIL_PRESETS = {
    '':      { host: '',              port: '',   enc: '' },
    gmail:   { host: 'smtp.gmail.com', port: '587', enc: 'tls' },
    outlook: { host: 'smtp-mail.outlook.com', port: '587', enc: 'tls' },
    zoho:    { host: 'smtp.zoho.com',  port: '465', enc: 'ssl' },
    etecsa:  { host: 'smtp.nauta.cu',  port: '25',  enc: 'none' }
};
function applyMailProvider() {
    var p = document.getElementById('mail_proveedor').value;
    var preset = MAIL_PRESETS[p] || MAIL_PRESETS[''];
    document.getElementById('mail_host').value = preset.host;
    document.getElementById('mail_port').value = preset.port;
    document.getElementById('mail_encryption').value = preset.enc;
}

function saveSystemConfig() {
    var body = dbCredsBody();
    body.mail_activo = document.getElementById('mail_activo').value;
    body.mail_proveedor = document.getElementById('mail_proveedor').value;
    body.mail_host = document.getElementById('mail_host').value;
    body.mail_port = document.getElementById('mail_port').value;
    body.mail_encryption = document.getElementById('mail_encryption').value;
    body.mail_usuario = document.getElementById('mail_usuario').value;
    body.mail_password = document.getElementById('mail_password').value;
    body.mail_from = document.getElementById('mail_from').value;
    body.mail_from_name = document.getElementById('mail_from_name').value;
    notify('Guardando configuración...', 'warning', true);
    api('save_config', body, function (res) {
        if (res.success) { notify('Configuración guardada.', 'success'); state.completed = true; completeStep(4); goStep(state.step + 1); }
        else { notify(res.message, 'error'); }
        showDbLog();
    });
}

function renderAdmin(el, actions) {
    var hasCustom = !!state.adminUser && state.adminUser !== 'admin';
    el.innerHTML =
        '<div class="card-info">Defina qui&eacute;n ser&aacute; el <b>usuario administrador</b> del sistema. Puede conservar el ' +
        'usuario <b>admin</b> incluido en el esquema o crear uno nuevo.</div>' +
        '<div class="admin-options">' +
        '<label class="admin-opt' + (hasCustom ? '' : ' selected') + '">' +
        '<input type="radio" name="adminOpt" value="embedded"' + (hasCustom ? '' : ' checked') + ' onchange="selectAdminOpt(this)">' +
        '<i class="fa-solid fa-user-shield"></i>' +
        '<div><b>Usar el usuario admin incluido</b>' +
        '<span>Usuario: <b>admin</b> &middot; Contraseña: <b>password</b>. Recomendado para una instalaci&oacute;n r&aacute;pida.</span></div>' +
        '</label>' +
        '<label class="admin-opt' + (hasCustom ? ' selected' : '') + '">' +
        '<input type="radio" name="adminOpt" value="create"' + (hasCustom ? ' checked' : '') + ' onchange="selectAdminOpt(this)">' +
        '<i class="fa-solid fa-user-plus"></i>' +
        '<div><b>Crear un nuevo usuario administrador</b>' +
        '<span>Defina su propio nombre de usuario y contrase&ntilde;a de acceso.</span></div>' +
        '</label>' +
        '</div>' +
        '<div class="actions" style="margin-top:18px;border-top:none;padding-top:0">' +
        '<button class="btn btn-primary" onclick="adminNext()"><i class="fa-solid fa-arrow-right"></i>&nbsp;Siguiente</button>' +
        '</div>' +
        '<div class="log-toggle" id="logToggle" onclick="toggleLog()"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
        '<div class="log-box" id="logBox" style="display:none"></div>';
    if (hasCustom) { notify('Nuevo usuario administrador creado: ' + esc(state.adminUser), 'success'); }
}

function selectAdminOpt(input) {
    document.querySelectorAll('.admin-opt').forEach(function (l) { l.classList.remove('selected'); });
    var card = input.closest('.admin-opt');
    if (card) { card.classList.add('selected'); }
    clearNotify();
    if (input.value === 'create' && !state.adminUser) {
        openAdminModal();
    }
}

function adminNext() {
    var opt = document.querySelector('input[name="adminOpt"]:checked');
    var value = opt ? opt.value : 'embedded';
    if (value === 'embedded') {
        completeStep(5);
        goStep(6);
    } else {
        openAdminModal();
    }
}

function adminMsg(text, type) {
    var el = document.getElementById('adminMsg');
    if (!el) return;
    if (!text) { el.style.display = 'none'; return; }
    el.className = 'admin-msg ' + (type || 'error');
    el.innerHTML = esc(text);
    el.style.display = 'block';
}

function openAdminModal() {
    var overlay = document.getElementById('adminModal');
    if (!overlay) return;
    overlay.style.display = 'flex';
    adminMsg('');
    var user = document.getElementById('adminUser');
    var pass = document.getElementById('adminPass');
    var pass2 = document.getElementById('adminPass2');
    if (user) { user.value = ''; }
    if (pass) { pass.value = ''; }
    if (pass2) { pass2.value = ''; }
    updateAdminStrength();
    setTimeout(function () { if (user) { user.focus(); } }, 60);
}

function closeAdminModal() {
    var overlay = document.getElementById('adminModal');
    if (overlay) { overlay.style.display = 'none'; }
    if (!state.adminUser) {
        var rb = document.querySelector('input[name="adminOpt"][value="embedded"]');
        if (rb) { rb.checked = true; selectAdminOpt(rb); }
    }
}

function updateAdminStrength() {
    var input = document.getElementById('adminPass');
    var bar = document.getElementById('strengthBar');
    if (!input || !bar) return;
    var p = input.value || '';
    var score = 0;
    if (p.length >= 6) score++;
    if (p.length >= 10) score++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^a-zA-Z0-9]/.test(p)) score++;
    score = Math.min(4, score);
    var colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    var labels = ['', 'D&eacute;bil', 'Media', 'Buena', 'Fuerte'];
    var seg = bar.querySelectorAll('span');
    seg.forEach(function (s, i) { s.style.background = i < score ? colors[score] : 'rgba(255, 255, 255, .12)'; });
    var lbl = document.getElementById('strengthLabel');
    if (lbl) { lbl.textContent = score > 0 ? labels[score] : ''; }
}

function createAdminUser() {
    var user = document.getElementById('adminUser').value.trim();
    var pass = document.getElementById('adminPass').value;
    var pass2 = document.getElementById('adminPass2').value;
    if (!user) { adminMsg('Escriba el nombre de usuario del administrador.', 'error'); document.getElementById('adminUser').focus(); return; }
    if (user.length < 3) { adminMsg('El nombre de usuario debe tener al menos 3 caracteres.', 'error'); return; }
    if (!/^[a-zA-Z0-9_.-]+$/.test(user)) { adminMsg('El usuario solo puede contener letras, n&uacute;meros, punto, guion y subrayado.', 'error'); return; }
    if (pass.length < 6) { adminMsg('La contraseña debe tener al menos 6 caracteres.', 'error'); return; }
    if (pass !== pass2) { adminMsg('Las contraseñas no coinciden.', 'error'); return; }
    var body = dbCredsBody();
    body.usuario = user;
    body.password = pass;
    adminMsg('Creando usuario administrador...', 'success');
    api('save_admin', body, function (res) {
        if (res.success) {
            state.adminUser = user;
            adminMsg('Usuario administrador creado.', 'success');
            var overlay = document.getElementById('adminModal');
            if (overlay) { overlay.style.display = 'none'; }
            completeStep(5);
            goStep(6);
        } else {
            adminMsg(res.message, 'error');
        }
        showDbLog();
    });
}

function installBase() {
    var path = window.location.pathname || '/';
    var origin = window.location.protocol + '//' + window.location.hostname + (window.location.port ? ':' + window.location.port : '');
    // El instalador esta en .../InstalarBD/ o .../instalarbd/
    var idx = path.toLowerCase().lastIndexOf('/instalarbd/');
    if (idx >= 0) {
        return origin + path.substring(0, idx);
    }
    var idx2 = path.lastIndexOf('/');
    return origin + (idx2 > 0 ? path.substring(0, idx2) : '');
}

// Páginas de la aplicación (carpeta NOMINAS)
function appUrl(file) {
    return installBase() + '/nominas/' + (file || 'login.php');
}

// Páginas del sitio general (raíz del proyecto)
function siteUrl(file) {
    return installBase() + '/' + (file || 'index.php');
}

function renderComplete(el, actions) {
    var url = appUrl();
    var adminUser = state.adminUser || 'admin';
    var adminNote = (state.adminUser && state.adminUser !== 'admin')
        ? 'El usuario administrador <b>' + esc(adminUser) + '</b> fue creado durante la instalación. '
        : 'El usuario administrador <b>admin</b> (Franklin Ramos Lamadrid) viene incluido en el esquema con la contraseña <b>password</b>. ';
    notify('La instalación del Sistema de Gestión de Nóminas ' + esc(state.empresa) + ' se ha completado correctamente.', 'success');
    setTimeout(function () {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Instalación completada',
                html: '<i class="fa-solid fa-check-circle" style="font-size:48px;color:#2ecc71"></i><br>La instalación del Sistema de Gestión de Nóminas <b>' + esc(state.empresa) + '</b> se ha completado correctamente.',
                confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Aceptar',
                allowOutsideClick: false
            });
        }
    }, 400);
    el.innerHTML =
        '<div class="alert success show">&#9989; La instalación del Sistema de Gestión de Nóminas ' + esc(state.empresa) + ' se ha completado correctamente.</div>' +
        '<div class="summary">' +
        '<div class="item"><div class="k">Base de datos</div><div class="v">' + esc(state.db ? state.db.name : '<?php echo SYS_DBNAME; ?>') + '</div></div>' +
        '<div class="item"><div class="k">Usuario administrador</div><div class="v">' + esc(adminUser) + '</div></div>' +
        '<div class="item full"><div class="k">Acceso al sistema</div><div class="v"><a class="link" href="' + esc(url) + '" target="_blank">' + esc(url) + '</a></div></div>' +
        '</div>' +
        '<div class="card-info"><b>Importante:</b> ' + adminNote +
        'Recomendamos cambiarla en su primer acceso desde la opción "Cambiar contraseña".</div>' +
        '<div class="section-title">Acceso</div>' +
        '<div class="links-grid">' +
        '<a class="btn btn-success" href="' + esc(siteUrl('index.php')) + '" target="_blank"><i class="fa-solid fa-house"></i>&nbsp;Ir al Inicio General</a> ' +
        '<a class="btn btn-primary" href="' + esc(appUrl('login.php')) + '" target="_blank"><i class="fa-solid fa-key"></i>&nbsp;Acceder al Sistema</a> ' +
        '</div>' +
        '<div class="section-title" style="margin-top:18px">Información</div>' +
        '<div class="links-grid">' +
		'<a class="btn btn-outline" href="' + esc(siteUrl('contacto.php')) + '" target="_blank"><i class="fa-solid fa-envelope"></i>&nbsp;Contacto</a> ' +
        '<a class="btn btn-outline" href="' + esc(siteUrl('soporte.php')) + '" target="_blank"><i class="fa-solid fa-headset"></i>&nbsp;Soporte</a> ' +
        '<a class="btn btn-outline" href="' + esc(siteUrl('privacidad.php')) + '" target="_blank"><i class="fa-solid fa-shield-halved"></i>&nbsp;Privacidad</a> ' +
        '<a class="btn btn-outline" href="' + esc(siteUrl('terminos.php')) + '" target="_blank"><i class="fa-solid fa-file-contract"></i>&nbsp;Términos</a> ' +
        '</div>' +
        '<div class="center mt">' +
        '<button class="btn btn-danger" onclick="deleteInstaller()"><i class="fa-solid fa-trash-can"></i>&nbsp;Eliminar instalador</button> ' +
        '<button class="btn btn-outline" onclick="cerrarVentana()"><i class="fa-solid fa-xmark"></i>&nbsp;Cerrar</button>' +
        '</div>' +
        '<div class="log-toggle" id="logToggle" onclick="toggleLog()"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
        '<div class="log-box" id="logBox" style="display:none"></div>';
    if (state.log) { showDbLog(); }
}

function cerrarApp() {
    cerrarVentana();
}

function cerrarVentana() {
    var msg = '¿Desea cerrar la ventana o pestaña del instalador?';
    var showFallback = function () {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'No se pudo cerrar',
                html: 'El navegador impide cerrar esta pestaña automáticamente.<br>Ciérrela manualmente con <b>Ctrl+W</b> o desde la pestaña.',
                icon: 'info',
                confirmButtonText: '<i class="fa-solid fa-hand"></i>&nbsp;Entendido'
            });
        } else {
            alert('El navegador impide cerrar esta pestaña automáticamente. Ciérrela manualmente (Ctrl+W).');
        }
    };
    var doClose = function () {
        try { window.close(); } catch (e) {}
        setTimeout(function () {
            try {
                var w = window.open('', '_self', '');
                if (w) { w.close(); }
            } catch (e) {}
            setTimeout(showFallback, 400);
        }, 400);
    };
    if (typeof Swal === 'undefined') {
        if (confirm(msg)) { doClose(); }
        return;
    }
    Swal.fire({
        title: '¿Cerrar?',
        text: msg,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-door-open"></i>&nbsp;Sí, cerrar',
        cancelButtonText: '<i class="fa-solid fa-xmark"></i>&nbsp;Cancelar',
        reverseButtons: true
    }).then(function (result) {
        if (result.isConfirmed) { doClose(); }
    });
}

function deleteInstaller() {
    if (typeof Swal === 'undefined') {
        if (!confirm('¿Seguro que desea eliminar el archivo instalador? Se conservará una copia de seguridad.')) return;
        doDeleteInstaller();
        return;
    }
    Swal.fire({
        title: '¿Eliminar instalador?',
        html: '¿Seguro que desea eliminar el archivo instalador?<br>Se conservará una copia de seguridad.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-trash-can"></i>&nbsp;Sí, eliminar',
        cancelButtonText: '<i class="fa-solid fa-xmark"></i>&nbsp;Cancelar',
        reverseButtons: true
    }).then(function (result) {
        if (result.isConfirmed) { doDeleteInstaller(); }
    });
}

function doDeleteInstaller() {
    notify('Eliminando instalador...', 'warning', true);
    api('delete_installer', {}, function (res) {
        if (res.success) {
            document.body.innerHTML = '<div class="container" style="background:#fff;border-radius:12px;padding:40px;text-align:center;margin-top:60px">' +
                '<h2 style="color:#166534">Instalación finalizada</h2>' +
                '<p class="small mt">El instalador fue eliminado correctamente. Se conservó una copia de seguridad en <b>InstalarBD_SisGesNom.instalado.php</b>.</p>' +
                '<p class="center mt"><a class="btn btn-success" href="' + esc(appUrl('index.php')) + '" target="_blank"><i class="fa-solid fa-rocket"></i>&nbsp;Ir al Sistema</a>' +
                ' <button class="btn btn-outline" onclick="cerrarVentana()"><i class="fa-solid fa-xmark"></i>&nbsp;Cerrar</button></p>' +
                '<div class="log-toggle" id="logToggle" onclick="toggleLog()" style="margin-top:14px"><i class="fa-solid fa-chevron-right chev"></i>&nbsp;Registro de actividad<button type="button" class="btn-log-clear" onclick="event.stopPropagation();clearLog()" title="Limpiar registro"><i class="fa-solid fa-trash-can"></i></button></div>' +
                '<div class="log-box" id="logBox" style="text-align:left">' + esc(res.log || '') + '</div></div>';
        } else {
            notify(res.message, 'error');
            showDbLog();
        }
    });
}

function applyEmpresaName() {
    var name = state.empresa || 'Sin Nombre';
    document.title = esc(name) + ' | Instalador - ' + SYS_NAME_SHORT;
    var logo = document.getElementById('logoEmpresa');
    if (logo) { logo.innerHTML = esc(name); }
    var foot = document.getElementById('footerEmpresa');
    if (foot) { foot.innerHTML = '<b class="fn">SisGesNom - Sistema de Gestión de Nóminas</b><br>Instalador del módulo de nóminas ' + esc(name) + ' | Copyright &copy; 2000 - ' + new Date().getFullYear() + '. All Right Reserved to UnicornioSoftware&reg;'; }
}

function askEmpresa(onDone) {
    var overlay = document.getElementById('empresaModal');
    var input = document.getElementById('empresaInput');
    if (!overlay || !input) { applyEmpresaName(); onDone(); return; }
    input.value = '';
    overlay.style.display = 'flex';
    setTimeout(function () { input.focus(); }, 60);
    var finish = function (value) {
        value = (value || '').trim();
        if (!value) {
            var msg = 'No se ha escrito el nombre de la empresa. ¿Seguro que desea dejar el nombre de la empresa PDL, Institución TCP en blanco?';
            var aplicar = function () {
                state.empresa = 'Sin Nombre';
                overlay.style.display = 'none';
                applyEmpresaName();
                onDone();
            };
            if (typeof Swal === 'undefined') {
                if (confirm(msg)) { aplicar(); }
                return;
            }
            Swal.fire({
                title: 'Nombre de empresa en blanco',
                html: msg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-check"></i>&nbsp;Sí, dejar en blanco',
                cancelButtonText: '<i class="fa-solid fa-pen"></i>&nbsp;Escribir nombre',
                reverseButtons: true,
                allowOutsideClick: false,
                returnFocus: false
            }).then(function (result) {
                if (result.isConfirmed) { aplicar(); }
                else { setTimeout(function () { input.focus(); }, 150); }
            });
            return;
        }
        state.empresa = value;
        overlay.style.display = 'none';
        applyEmpresaName();
        onDone();
    };
    document.getElementById('empresaOk').onclick = function () { finish(input.value); };
    document.getElementById('empresaCancel').onclick = function () { finish(''); };
    document.getElementById('empresaClose').onclick = function () { finish(''); };
    input.onkeydown = function (e) {
        if (e.key === 'Enter') { e.preventDefault(); setTimeout(function () { document.getElementById('empresaOk').click(); }, 0); }
        if (e.key === 'Escape') { finish(''); }
    };
}

// Inicialización
window.addEventListener('DOMContentLoaded', function () {
    var closeBtn = document.getElementById('wizardClose');
    if (closeBtn) {
        closeBtn.onclick = function () {
            if (state.step === 0) { askEmpresa(function () { goStep(0); }); return; }
            var msg = 'La instalación aún no ha llegado al final.';
            if (typeof Swal === 'undefined') {
                if (confirm(msg + ' ¿Desea cerrar la ventana del proceso?')) { cerrarVentana(); }
                return;
            }
            Swal.fire({
                title: 'Instalación en curso',
                html: msg + '<br>¿Desea continuar hasta completarla o cerrar la ventana del proceso?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-forward"></i>&nbsp;Llegar al final',
                cancelButtonText: '<i class="fa-solid fa-door-open"></i>&nbsp;Cerrar ventana',
                reverseButtons: true
            }).then(function (result) {
                if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) { cerrarVentana(); }
            });
        };
    }
    askEmpresa(function () {
        var container = document.querySelector('.container');
        if (container) { container.style.display = ''; }
        renderNav();
        renderStep();
    });
});
</script>
</body>
</html>