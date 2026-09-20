-- Eliminar base de datos si existe
DROP DATABASE IF EXISTS `sisfact_imdl`;
CREATE DATABASE `sisfact_imdl` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- CREAR/ELIMINAR BASE DE DATOS
-- ============================================
DROP DATABASE IF EXISTS `sisfact_imdl`;
CREATE DATABASE `sisfact_imdl` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sisfact_imdl`;

-- ============================================
-- CREACIÓN DE TABLAS
-- ============================================

-- Tabla: clasif_cat_de_serv
CREATE TABLE `clasif_cat_de_serv` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(200) NOT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos en clasif_cat_de_serv
INSERT INTO `clasif_cat_de_serv` (`id`, `codigo`, `descripcion`, `activo`, `fecha_creacion`) VALUES
        (1, 'IMP1', 'IMPRESIÓN DE PLANTILLAS Y/O MODELOS', 1, '2025-12-31 01:00:00'),
		(2, 'IMP2', 'IMPRESIÓN DE TARJETAS E INVITACIONES', 1, '2025-12-31 01:01:00'),
		(3, 'IMP3', 'IMPRESIÓN EN PAPEL DOCUMENTOS E IMÁGENES', 1, '2025-12-31 01:02:00'),
		(4, 'IMP4', 'IMPRESIÓN EN CARTULINA Y/O PAPEL FOTOGRÁFICO', 1, '2025-12-31 01:03:00'),
		(5, 'PLS1', 'PLATICADOS', 1, '2025-12-31 01:04:00'),
		(6, 'FS01', 'FOTOCOPIAS / SCANERS', 1, '2025-12-31 01:05:00'),
		(7, 'OTIMP', 'OTROS SERVICIOS', 1, '2025-12-31 01:06:00'),
		(8, 'FM01', 'MATERIAL DE OFICINA', 1, '2025-12-31 01:07:00'),
		(9, 'ALB1', 'ALIMENTOS Y BEBIDAS', 1, '2025-12-31 01:08:00'),
		(10, 'EE01', 'EQUIPOS Y ACCESORIOS ELECTRÓNICOS', 1, '2025-12-31 01:09:00'),
		(11, 'PPR1', 'PARTES Y/O PIEZAS DE RESPUESTO', 1, '2025-12-31 01:10:00'),
		(12, 'PRA1', 'PRODUCTOS DE ASEO Y LIMPIEZA', 1, '2025-12-31 01:11:00');

-- Tabla: clasif_clientes
CREATE TABLE `clasif_clientes` (
		  `id` int(11) NOT NULL,
		  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `direccion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `ResponsableEntidad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NoCIResp` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `CodReup` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NIT` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `ContratoNo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `SucursalCobroLocalidad` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NoCtaDeudor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `fechaRegistro` date DEFAULT NULL,
		  `vigenciapor` int(11) DEFAULT 0,
		  `fechaVence` date GENERATED ALWAYS AS (`fechaRegistro` + interval `vigenciapor` year) VIRTUAL,
		  `activo` tinyint(1) DEFAULT NULL,
		  `renovac` tinyint(1) DEFAULT 0 COMMENT '1=Si, 0=No',
		  `si_renova_cant` int(11) DEFAULT 0,
		  `fechafinalcontrato` date GENERATED ALWAYS AS (`fechaRegistro` + interval `vigenciapor` + if(`renovac` = 1,`si_renova_cant`,0) year) VIRTUAL,
		  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: clasif_rol
CREATE TABLE `clasif_rol` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(10) NOT NULL,
            `descripcion` varchar(100) NOT NULL,
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar roles
INSERT INTO `clasif_rol` (`codigo`, `descripcion`) VALUES
            ('Admin', 'Administrador del Sistema'),
            ('Visor', 'Visualizador'),
            ('Editor', 'Facturador / Editor'),
            ('Super', 'Supervisor General'),
            ('Soft', 'Programador');

-- Tabla: clasif_serv
CREATE TABLE `clasif_serv` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(200) NOT NULL,
            `categoria_id` int(11) DEFAULT NULL,
            `costo` decimal(10,2) NOT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_creacion` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar servicios
INSERT INTO `clasif_serv` (`id`, `codigo`, `descripcion`, `categoria_id`, `costo`, `activo`, `fecha_creacion`) VALUES
		(1, 'CR01', 'Credencial (x unidad)', 7, '200.00', 1, '2025-01-15 15:00:00'),
		(2, 'CR02', 'Credencial (x unidad) con colgante', 7, '280.00', 1, '2025-01-15 15:01:00'),
		(3, 'DC01', 'Diploma en Cartulina. Forrado Vinilo', 4, '300.00', 1, '2025-01-15 15:02:00'),
		(4, 'DI01', 'Diseño de Diploma, Tarjetas y Otros (Hasta)', 7, '500.00', 1, '2025-01-15 15:03:00'),
		(5, 'E001', 'Encuadernado', 4, '600.00', 1, '2025-01-15 15:04:00'),
		(6, 'F001', 'Fotografía 5x7″', 4, '75.00', 1, '2025-01-15 15:05:00'),
		(7, 'F002', 'Fotografía 1x1″ - 3 En Adelante', 4, '50.00', 1, '2025-01-15 15:06:00'),
		(8, 'F003', '4 Fotos Pasaporte/Visa', 4, '500.00', 1, '2025-01-15 15:07:00'),
		(9, 'F004', 'Fotografía 8½x11″', 4, '1000.00', 1, '2025-01-15 15:08:00'),
		(10, 'FS01', 'Fotocopia / Scaner', 6, '45.00', 1, '2025-01-15 15:09:00'),
		(11, 'FS02', 'Fotocopia / Scaner D/C', 6, '75.00', 1, '2025-01-15 15:10:00'),
		(12, 'GT01', 'Grabar Tazón/Taza', 7, '2300.00', 1, '2025-01-01 08:46:00'),
		(13, 'I001', 'Impresión de Documentos (B/N)', 3, '25.00', 1, '2025-01-01 08:47:00'),
		(14, 'I002', 'Impresión de Documento (Doble Cara B/N)', 3, '45.00', 1, '2025-01-01 08:48:00'),
		(15, 'I003', 'Impresión de Documentos (Color)', 3, '35.00', 1, '2025-01-01 08:49:00'),
		(16, 'I004', 'Impresión de Documentos a color (Doble Cara)', 3, '65.00', 1, '2025-01-01 08:50:00'),
		(17, 'I005', 'Impresión de imágenes en papel normal', 3, '55.00', 1, '2025-01-01 08:51:00'),
		(18, 'I006', 'Impresión de imágenes en papel normal D/C', 3, '95.00', 1, '2025-01-01 08:52:00'),
		(19, 'IC01', 'Impresión en Cartulina 8½x11″', 4, '300.00', 1, '2025-01-01 08:53:00'),
		(20, 'IC02', 'Impresión en Cartulina 12x18″', 4, '90.00', 1, '2025-01-01 08:54:00'),
		(21, 'IC03', 'Impresión en Cartulina D/C 8½x11″', 4, '570.00', 1, '2025-01-01 08:55:00'),
		(22, 'IC04', 'Impresión en Cartulina D/C 12x18″', 4, '170.00', 1, '2025-01-01 08:56:00'),
		(23, 'IC05', 'Impresión Diplomas en Cartulina 8½x11″', 4, '360.00', 1, '2025-01-01 08:57:00'),
		(24, 'IC06', 'Impresión Diplomas en Cartulina 12x18″', 4, '110.00', 1, '2025-01-01 08:58:00'),
		(25, 'IC07', 'Impresión de imágenes en cartulina 8½x11″', 4, '360.00', 1, '2025-01-01 08:59:00'),
		(26, 'IC08', 'Impresión de imágenes en cartulina 8½x11″', 4, '110.00', 1, '2025-01-01 09:00:00'),
		(27, 'IT01', 'Impresión Sencilla de Tarjetas', 2, '25.00', 1, '2025-01-01 09:01:00'),
		(28, 'IT02', 'Impresión Doble Cara Tarjetas', 2, '65.00', 1, '2025-01-01 09:02:00'),
		(29, 'MP01', 'Mica para platicar(tamaño solapin)', 5, '200.00', 1, '2025-01-01 09:03:00'),
		(30, 'MP02', 'Mica para platicar(tamaño carta)', 5, '1000.00', 1, '2025-01-01 09:04:00'),
		(31, 'P001', 'Impresión de Planillas (B/N)', 1, '25.00', 1, '2025-01-01 09:05:00'),
		(32, 'P002', 'Impresión de Planillas (Doble Cara B/N)', 1, '50.00', 1, '2025-01-01 09:06:00'),
		(33, 'P003', 'Impresión de Planillas (Color)', 1, '50.00', 1, '2025-01-01 09:07:00'),
		(34, 'P004', 'Impresión de Planillas (Doble Cara Color)', 1, '55.00', 1, '2025-01-01 09:08:00'),
		(35, 'ST01', 'Sublimación textil', 7, '1800.00', 1, '2025-01-01 09:09:00'),
		(36, 'TE01', 'Trámites de Embajadas', 7, '1000.00', 1, '2025-01-01 09:10:00'),
		(37, 'LC01', 'Lápiceros', 8, '80.00', 1, '2025-01-01 09:11:00'),
		(38, 'FO01', 'Foliadora', 8, '2596.95', 1, '2025-01-01 09:12:00'),
		(39, 'AR01', 'Archivador DL 5071', 8, '254.29', 1, '2025-01-01 09:13:00'),
		(40, 'AR02', 'Archivador Tipo Libro', 8, '891.75', 1, '2025-01-01 09:14:00'),
		(41, 'AR03', 'Archivador con 1 presilla', 8, '761.25', 1, '2025-01-01 09:15:00'),
		(42, 'AL01', 'Almohadilla para cuño', 8, '95.70', 1, '2025-01-01 09:16:00'),
		(43, 'AR04', 'Archivador con 2 presilla', 8, '761.25', 1, '2025-01-01 09:17:00'),
		(44, 'PD01', 'Porta documentos colores', 8, '98.60', 1, '2025-01-01 09:18:00'),
		(45, 'PC01', 'Paper Clip colores 728', 8, '65.25', 1, '2025-01-01 09:19:00'),
		(46, 'PC02', 'Paper Clip colores 733', 8, '65.25', 1, '2025-01-01 09:20:00'),
		(47, 'PC03', 'Paper Clip colores 750', 8, '137.75', 1, '2025-01-01 09:21:00'),
		(48, 'AR05', 'Archivador con presillas', 8, '761.25', 1, '2025-01-01 09:22:00'),
		(49, 'AR06', 'Archivador DL 5072', 8, '398.75', 1, '2025-01-01 09:23:00'),
		(50, 'EP01', 'Estuche de presillas', 8, '172.50', 1, '2025-01-01 09:24:00'),
		(51, 'SP01', 'Saca Presillas', 8, '113.10', 1, '2025-01-01 09:25:00'),
		(52, 'CP10', 'Café Prensado 10 Oz', 9, '1875.00', 1, '2025-01-01 09:26:00'),
		(53, 'MO01', 'Telefono  móvil, cargador, cable, cover y mica', 10, '80500.00', 1, '2025-01-01 09:27:00'),
		(54, 'TPC1', 'Tinta para Cuño', 8, '488.04', 1, '2025-01-01 09:28:00'),
		(55, 'CPS1', 'Cajas de presilla 50 mm', 8, '195.00', 1, '2025-01-01 09:29:00'),
		(56, 'HI01', 'Hojas de Índice', 8, '156.00', 1, '2025-01-01 09:30:00'),
		(57, 'AR07', 'Archivador Transparente c/corredera', 8, '195.00', 1, '2025-01-01 09:31:00'),
		(58, 'CA01', 'Calculadora Profesional 12 Digitos', 10, '5600.00', 1, '2025-01-01 09:32:00'),
		(59, 'TIL1', 'Tinta para Impresora (1LT)', 8, '48500.00', 1, '2025-01-01 09:33:00'),
		(60, 'CP01', 'Cuño personalizado (Rectangular)', 8, '8450.00', 1, '2025-01-01 09:34:00'),
		(61, 'HC50', 'Paquete Hojas (8½x11″) Carta 500U', 8, '4030.00', 1, '2025-01-01 09:35:00'),
		(62, 'PRA1', 'Piezas Respuesto/Accesorios p/Reparac.Medios de Computo', 11, '340050.00', 1, '2025-01-01 09:36:00'),
		(63, 'CP02', 'Cuño personalizado (Redondo)', 8, '8294.00', 1, '2025-01-01 09:37:00'),
		(64, 'CP03', 'Cuño personalizado (Cuadrado)', 8, '8294.00', 1, '2025-01-01 09:38:00'),
		(65, 'TCE1', 'Tarjeta Estiba', 2, '15.00', 1, '2025-01-01 09:39:00'),
		(66, 'TCE2', 'Tarjeta Firma', 2, '10.00', 1, '2025-01-01 09:40:00'),
		(67, 'CVS1', 'Carné Vacunación Fiebre Amarilla', 2, '350.00', 1, '2025-01-01 09:41:00'),
		(68, 'CVS2', 'Carné Vacunación COVID', 2, '50.00', 1, '2025-01-01 09:42:00'),
		(69, 'CP00', 'Cuño Personalizado(Diferentes Formas)', 2, '9555.00', 1, '2025-01-01 09:43:00'),
		(70, 'CP04', 'Cuño Redondo Personalizado', 2, '10300.00', 1, '2025-01-01 09:44:00'),
		(71, 'AR08', 'Archivador DL', 8, '390.00', 1, '2025-01-01 09:45:00'),
		(72, 'AR09', 'Archivador Tipo Libro', 8, '1086.00', 1, '2025-01-01 09:46:00'),
		(73, 'PY01', 'Partes y Piezas (Triciclos)', 11, '191200.00', 1, '2025-01-01 09:47:00'),
		(74, 'PY02', 'Partes y Piezas (Compresores)', 11, '43500.00', 1, '2025-01-01 09:48:00'),
		(75, 'PY03', 'Partes y Piezas (Máquinas de Soldar)', 11, '60000.00', 1, '2025-01-01 09:49:00'),
		(76, 'RHA7', 'Ron Habanclub Añejo 750ml', 9, '4500.00', 1, '2025-01-01 10:00:00'),
		(77, 'RHA1', 'Ron Habanclub Añejo 1 Litro', 9, '5800.00', 1, '2025-01-01 10:01:00'),
		(78, 'RH3A', 'Ron Habanclub 3 Años 750ml', 9, '5200.00', 1, '2025-01-01 10:02:00'),
		(79, 'RH7A', 'Ron Habanclub 7 Años 750ml', 9, '6800.00', 1, '2025-01-01 10:03:00'),
		(80, 'RCLA', 'Ron Cubano Legendario Añejo', 9, '6200.00', 1, '2025-01-01 10:04:00'),
		(81, 'RHBL', 'Ron Habanclub Blanco', 9, '4200.00', 1, '2025-01-01 10:05:00'),
		(82, 'RSCR', 'Ron Santiago de Cuba 7 Años', 9, '7200.00', 1, '2025-01-01 10:06:00'),
		(83, 'GMAR', 'Galletas María (Diferentes Sabores)', 9, '900.00', 1, '2025-01-01 10:07:00'),
		(84, 'CTMY', 'Caramelos Tomy (Diferentes Sabores)', 9, '450.00', 1, '2025-01-01 10:08:00'),
		(85, 'GDL3', 'Galletas Dulce de Leche 350g', 9, '990.00', 1, '2025-01-01 10:09:00'),
		(86, 'GMNT', 'Galletas de Manteca 300g', 9, '780.00', 1, '2025-01-01 10:10:00'),
		(87, 'GINT', 'Galletas Integrales 400g', 9, '920.00', 1, '2025-01-01 10:11:00'),
		(88, 'GCOC', 'Galletas de Coco 300g', 9, '890.00', 1, '2025-01-01 10:12:00'),
		(89, 'GCHO', 'Galletas de Chocolate 350g', 9, '950.00', 1, '2025-01-01 10:13:00'),
		(90, 'GVAI', 'Galletas Vainilla 350g', 9, '850.00', 1, '2025-01-01 10:14:00'),
		(91, 'DET5', 'Detergente en polvo 500g', 12, '450.00', 1, '2025-01-01 10:15:00'),
		(92, 'DET1', 'Detergente en polvo 1kg', 12, '650.00', 1, '2025-01-01 10:16:00'),
		(93, 'DET3', 'Detergente en polvo 3kg', 12, '1450.00', 1, '2025-01-01 10:17:00'),
		(94, 'JABP', 'Jabón de pasta (Bolívar) 300g', 12, '280.00', 1, '2025-01-01 10:18:00'),
		(95, 'JABL', 'Jabón de lavar (Líquido) 1L', 12, '520.00', 1, '2025-01-01 10:19:00'),
		(96, 'CLOR', 'Cloro 1L', 12, '220.00', 1, '2025-01-01 10:20:00'),
		(97, 'CLR3', 'Cloro 3L', 12, '550.00', 1, '2025-01-01 10:21:00'),
		(98, 'SUAV', 'Suavizante para ropa 1L', 12, '580.00', 1, '2025-01-01 10:22:00'),
		(99, 'LIMP', 'Limpiador multiusos 500ml', 12, '320.00', 1, '2025-01-01 10:23:00'),
		(100, 'DESG', 'Desengrasante 500ml', 12, '380.00', 1, '2025-01-01 10:24:00'),
		(101, 'ESPJ', 'Esponja para platos (3 unidades)', 12, '150.00', 1, '2025-01-01 10:25:00'),
		(102, 'PLH0', 'Papel higiénico (12 rollos)', 12, '1250.00', 1, '2025-01-01 10:26:00'),
		(103, 'SERV', 'Servilletas 100 unidades', 12, '280.00', 1, '2025-01-01 10:27:00'),
		(104, 'JABC', 'Jabón de baño (3 unidades)', 12, '390.00', 1, '2025-01-01 10:28:00'),
		(105, 'PS00', 'Pasta dental 90ml', 12, '320.00', 1, '2025-01-01 10:29:00'),
		(106, 'PS01', 'Pasta dental 120ml', 12, '500.00', 1, '2025-01-01 10:30:00'),
		(107, 'PLH1', 'Papel higiénico (4 rollos)', 12, '600.00', 1, '2025-01-01 10:31:00'),
		(108, 'COT0', 'Colcha de trapear', 12, '350.00', 1, '2025-01-01 10:32:00');
		

-- Tabla: clasif_usuarios
CREATE TABLE `clasif_usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(50) NOT NULL,
            `apellidos` varchar(100) NOT NULL,
            `no_ci` varchar(11) NOT NULL,
            `direccion_particular` text DEFAULT NULL,
            `telefono_contacto` varchar(15) DEFAULT NULL,
            `fecha_registro` datetime DEFAULT current_timestamp(),
            `rol_id` int(11) DEFAULT NULL,
            `foto` longtext DEFAULT NULL,
            `usuario` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `no_ci` (`no_ci`),
            UNIQUE KEY `usuario` (`usuario`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: configuracion_sistema
CREATE TABLE `configuracion_sistema` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre_empresa` varchar(200) NOT NULL,
            `nombre_proyecto` varchar(200) NOT NULL,
            `logo` longtext DEFAULT NULL,
            `fondo_web` longtext DEFAULT NULL,
            `direccion` text DEFAULT NULL,
            `telefono` varchar(15) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `cuenta_bancaria` varchar(50) DEFAULT NULL,
            `banco` varchar(100) DEFAULT NULL,
            `sucursal` varchar(100) DEFAULT NULL,
            `cod_reeup` varchar(50) DEFAULT NULL,
            `cod_nit` varchar(11) DEFAULT NULL,
            `sitio_web` varchar(255) DEFAULT NULL,
            `fecha_inicio_operaciones` DATE NOT NULL,
            `director_nombre` varchar(100) DEFAULT NULL,
            `director_ci` varchar(11) DEFAULT NULL,
            `director_telefono` varchar(15) DEFAULT NULL,
            `facturador_nombre` varchar(100) DEFAULT NULL,
            `facturador_ci` varchar(11) DEFAULT NULL,
            `facturador_telefono` varchar(15) DEFAULT NULL,
            `whatsapp_ON` tinyint(1) DEFAULT 1,
            `whatsapp_numero` varchar(20) DEFAULT NULL,
            `modo_mantenimiento` tinyint(1) NOT NULL DEFAULT 0,
			`restabpw` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: historico_operaciones
CREATE TABLE `historico_operaciones` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `operacion` varchar(100) NOT NULL,
            `descripcion` text DEFAULT NULL,
            `fecha_hora` datetime DEFAULT current_timestamp(),
            `usuario_id` int(11) DEFAULT NULL,
            `usuario_nombre` varchar(150) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: historico_cierres
CREATE TABLE `historico_cierres` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tipo` int(1) DEFAULT 1 NOT NULL,
            `periodo_mes` INT NULL, -- 1 al 12 (solo para tipo MES)
            `periodo_anio` INT NOT NULL, -- Ej: 2026
            `fecha_ejecucion` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `usuario_id` INT NOT NULL,
            `total_facturas` INT DEFAULT 0,
            `importe_total` DECIMAL(15,2) DEFAULT 0.00,
            `cant_pagadas` INT DEFAULT 0,
            `cant_contabilizadas` INT DEFAULT 0,
            `observaciones` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla: tbl_fact
CREATE TABLE `tbl_fact` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `no_fact` varchar(20) NOT NULL,
            `cliente_id` int(11) DEFAULT NULL,
            `tipo_pago_id` int(11) DEFAULT NULL,
            `subtotal` decimal(10,2) NOT NULL,
            `total_general` decimal(10,2) NOT NULL,
            `usuario_id` int(11) DEFAULT NULL,
            `estado` enum('PENDIENTE','CONTABILIZADA','ANULADA','PAGADA', 'CERRADA') DEFAULT 'PENDIENTE',
            `fecha_emision` datetime NOT NULL,
            `fecha_contabilizacion` datetime DEFAULT NULL,
            `fecha_pago` datetime DEFAULT NULL,
            `Ref_pago` varchar(100) DEFAULT NULL,
            `observaciones` text DEFAULT NULL,
            `usuario_contabilizacion` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `no_fact` (`no_fact`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tbl_fact_detalle
CREATE TABLE `tbl_fact_detalle` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `factura_id` int(11) DEFAULT NULL,
            `servicio_id` int(11) DEFAULT NULL,
            `cantidad` int(11) NOT NULL,
            `precio_unitario` decimal(10,2) NOT NULL,
            `total_linea` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tbl_planes
CREATE TABLE `tbl_planes` (
            `anio` int(11) NOT NULL AUTO_INCREMENT,
            `mes_plan` int(11) NOT NULL,
            `importe` decimal(10,2) DEFAULT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `observaciones` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`anio`,`mes_plan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tipos_pago
CREATE TABLE `tipos_pago` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(100) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tipos de pago
INSERT INTO `tipos_pago` (`id`, `codigo`, `descripcion`) VALUES
        (1, 'ABO', 'ABONO'),
        (2, 'ANT', 'ANTICIPO'),
        (3, 'BIM', 'BILLETERA MÓVIL'),
        (4, 'CHC', 'CHEQUE CERTIFICADO'),
        (5, 'CRE', 'CRÉDITO COMERCIAL'),
        (6, 'DEP', 'DEPÓSITO BANCARIO'),
        (7, 'EFE', 'EFECTIVO'),
        (8, 'LET', 'LETRA DE CAMBIO'),
        (9, 'OTR', 'OTROS'),
        (10, 'PCO', 'PAGO CONTRA ENTREGA'),
        (11, 'PDI', 'PAGO DIGITAL'),
        (12, 'PEI', 'PAGO EN LÍNEA'),
        (13, 'PM', 'PAGO MÓVIL (TRANSFERMÓVIL)'),
        (14, 'PPA', 'PAGO PARCIAL'),
        (15, 'PAY', 'PAYPAL'),
        (16, 'TCR', 'TARJETA DE CRÉDITO'),
        (17, 'TDB', 'TARJETA DE DÉBITO'),
        (18, 'TIE', 'TARJETA INTERNACIONAL (MASTERCARD/VISA/AMEX)'),
        (19, 'ACH', 'TRANSFERENCIA ACH'),
        (20, 'TRA', 'TRANSFERENCIA BANCARIA'),
        (21, 'VAR', 'VARIOS MÉTODOS');

-- ============================================
-- RESTRICCIONES DE CLAVE FORÁNEA
-- ============================================

-- Restricción: Usuarios -> Roles
ALTER TABLE clasif_usuarios
        ADD CONSTRAINT fk_usuario_rol
        FOREIGN KEY (rol_id) 
        REFERENCES clasif_rol(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

-- Restricción: Servicios -> Categorías
ALTER TABLE clasif_serv
        ADD CONSTRAINT fk_servicio_categoria
        FOREIGN KEY (categoria_id) 
        REFERENCES clasif_cat_de_serv(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Facturas -> Clientes
ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_cliente
        FOREIGN KEY (cliente_id) 
        REFERENCES clasif_clientes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Facturas -> Tipos de pago
ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_tipo_pago
        FOREIGN KEY (tipo_pago_id) 
        REFERENCES tipos_pago(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Facturas -> Usuario creador
ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_usuario
        FOREIGN KEY (usuario_id) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Facturas -> Usuario contabilizador
ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_usuario_cont
        FOREIGN KEY (usuario_contabilizacion) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Detalle factura -> Factura
ALTER TABLE tbl_fact_detalle
        ADD CONSTRAINT fk_detalle_factura
        FOREIGN KEY (factura_id) 
        REFERENCES tbl_fact(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

-- Restricción: Detalle factura -> Servicio
ALTER TABLE tbl_fact_detalle
        ADD CONSTRAINT fk_detalle_servicio
        FOREIGN KEY (servicio_id) 
        REFERENCES clasif_serv(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Historial -> Usuario
ALTER TABLE historico_operaciones
        ADD CONSTRAINT fk_historico_usuario
        FOREIGN KEY (usuario_id) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Restricción: Historial de cierres -> Usuario
ALTER TABLE `historico_cierres`
        ADD CONSTRAINT `historico_cierres_ibfk_1`
        FOREIGN KEY (`usuario_id`) 
        REFERENCES `clasif_usuarios`(`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

-- ============================================
-- ÍNDICES PARA MEJORAR RENDIMIENTO
-- ============================================

CREATE INDEX idx_clasif_serv_categoria ON clasif_serv(categoria_id);
CREATE INDEX idx_tbl_fact_cliente ON tbl_fact(cliente_id);
CREATE INDEX idx_tbl_fact_fecha ON tbl_fact(fecha_emision);
CREATE INDEX idx_tbl_fact_estado ON tbl_fact(estado);
CREATE INDEX idx_tbl_fact_detalle_factura ON tbl_fact_detalle(factura_id);
CREATE INDEX idx_clasif_clientes_codigo ON clasif_clientes(codigo);
CREATE INDEX idx_clasif_serv_codigo ON clasif_serv(codigo);

-- Finalizar transacción
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
