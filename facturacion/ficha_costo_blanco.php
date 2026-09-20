<?php
// modelo_ficha_costo_blanco.php
ob_start();
session_start();

require_once 'config/header.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getConnection();
    $sql_config = "SELECT * FROM configuracion_sistema LIMIT 1";
    $stmt_config = $db->query($sql_config);
    $config = $stmt_config->fetch();
} catch (Exception $e) {
    $config = [];
}

ob_end_flush();

// Obtener parámetro de tipo (por si se quiere usar el mismo archivo para diferentes variantes)
$tipo = isset($_GET['t']) ? $_GET['t'] : 'ficha';
?>
<!DOCTYPE html>
<html lang="es" data-theme="black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MODELO DE FICHA DE COSTO - SISFACT PDL VISIONES</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #000000;
            --secondary: #111827;
            --border: #d1d5db;
            --bg-soft: #f9fafb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 60px;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="black"] {
            --primary: #000000;
            --secondary: #111827;
            --border: #d1d5db;
            --bg-soft: #f9fafb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="red"] {
            --primary: #991b1b;
            --secondary: #dc2626;
            --border: #fca5a5;
            --bg-soft: #fee2e2;
            --text-main: #991b1b;
            --text-muted: #f87171;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="green"] {
            --primary: #166534;
            --secondary: #16a34a;
            --border: #86efac;
            --bg-soft: #dcfce7;
            --text-main: #166534;
            --text-muted: #4ade80;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="yellow"] {
            --primary: #b45309;
            --secondary: #fbbf24;
            --border: #fde68a;
            --bg-soft: #fffde7;
            --text-main: #78350f;
            --text-muted: #d4a017;
            --header-text-color: #000000;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="blue"] {
            --primary: #1e40af;
            --secondary: #2563eb;
            --border: #93c5fd;
            --bg-soft: #eff6ff;
            --text-main: #1e40af;
            --text-muted: #3b82f6;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="cyan"] {
            --primary: #0e7490;
            --secondary: #0891b2;
            --border: #67e8f9;
            --bg-soft: #ecfeff;
            --text-main: #0e7490;
            --text-muted: #22d3ee;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="purple"] {
            --primary: #7c3aed;
            --secondary: #8b5cf6;
            --border: #c4b5fd;
            --bg-soft: #f5f3ff;
            --text-main: #7c3aed;
            --text-muted: #a78bfa;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="pink"] {
            --primary: #be185d;
            --secondary: #db2777;
            --border: #f9a8d4;
            --bg-soft: #fdf2f8;
            --text-main: #be185d;
            --text-muted: #f472b6;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="orange"] {
            --primary: #c2410c;
            --secondary: #ea580c;
            --border: #fdba74;
            --bg-soft: #fff7ed;
            --text-main: #c2410c;
            --text-muted: #fb923c;
            --header-text-color: #000000;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="gray"] {
            --primary: #374151;
            --secondary: #4b5563;
            --border: #d1d5db;
            --bg-soft: #f9fafb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="indigo"] {
            --primary: #3730a3;
            --secondary: #4f46e5;
            --border: #a5b4fc;
            --bg-soft: #eef2ff;
            --text-main: #3730a3;
            --text-muted: #6366f1;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        [data-theme="teal"] {
            --primary: #115e59;
            --secondary: #0d9488;
            --border: #5eead4;
            --bg-soft: #f0fdfa;
            --text-main: #115e59;
            --text-muted: #2dd4bf;
            --header-text-color: #ffffff;
            --header-bg-invert-color: var(--primary);
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: var(--text-main);
            transition: margin-left 0.3s ease;
            padding-top: 10px;
            margin-left: var(--sidebar-collapsed-width);
        }

        body.sidebar-expanded {
            margin-left: var(--sidebar-width);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .sidebar-header {
            justify-content: center;
            padding: 20px 0;
        }

        .sidebar-header .logo {
            font-size: 24px;
            color: #3498db;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 18px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-header h3 {
            opacity: 0;
            width: 0;
        }

        .sidebar-menu {
            padding: 15px 0;
            overflow: hidden;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            gap: 12px;
        }

        .sidebar-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-item .icon {
            font-size: 18px;
            min-width: 24px;
            text-align: center;
        }

        .sidebar-item .text {
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-item .text {
            opacity: 0;
            width: 0;
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 10px 20px;
        }

        .toggle-sidebar-btn {
            position: absolute;
            bottom: 20px;
            right: 15px;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-sidebar-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(180deg);
        }

        .sidebar.collapsed .toggle-sidebar-btn {
            right: 12px;
        }

        .sidebar-section-title {
            padding: 10px 20px;
            font-size: 12px;
            text-transform: uppercase;
            color: #7f8c8d;
            letter-spacing: 1px;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-section-title {
            opacity: 0;
            width: 0;
            padding: 10px 0;
        }

        /* Theme selector buttons */
        .theme-btn {
            position: relative;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .theme-btn:hover {
            border-color: currentColor;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .theme-btn.active {
            border-color: #ffffff;
            box-shadow: 0 0 0 2px #3b82f6, 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .theme-btn::before {
            content: attr(data-tooltip);
            position: absolute;
            visibility: hidden;
            opacity: 0;
            background-color: #1f2937;
            color: #ffffff;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 4px;
            white-space: nowrap;
            bottom: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            transition: all 0.2s ease;
            pointer-events: none;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .theme-btn::after {
            content: '';
            position: absolute;
            visibility: hidden;
            opacity: 0;
            border: 5px solid transparent;
            border-top-color: #1f2937;
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            transition: all 0.2s ease;
            pointer-events: none;
            z-index: 1000;
        }
        
        .theme-btn:hover::before,
        .theme-btn:hover::after {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        .theme-btn.theme-black { background: linear-gradient(135deg, #000000 50%, #111827 50%); }
        .theme-btn.theme-red { background: linear-gradient(135deg, #991b1b 50%, #dc2626 50%); }
        .theme-btn.theme-green { background: linear-gradient(135deg, #166534 50%, #16a34a 50%); }
        .theme-btn.theme-yellow { background: linear-gradient(135deg, #b45309 50%, #fbbf24 50%); }
        .theme-btn.theme-blue { background: linear-gradient(135deg, #1e40af 50%, #2563eb 50%); }
        .theme-btn.theme-cyan { background: linear-gradient(135deg, #0e7490 50%, #0891b2 50%); }
        .theme-btn.theme-purple { background: linear-gradient(135deg, #7c3aed 50%, #8b5cf6 50%); }
        .theme-btn.theme-pink { background: linear-gradient(135deg, #be185d 50%, #db2777 50%); }
        .theme-btn.theme-orange { background: linear-gradient(135deg, #c2410c 50%, #ea580c 50%); }
        .theme-btn.theme-gray { background: linear-gradient(135deg, #374151 50%, #4b5563 50%); }
        .theme-btn.theme-indigo { background: linear-gradient(135deg, #3730a3 50%, #4f46e5 50%); }
        .theme-btn.theme-teal { background: linear-gradient(135deg, #115e59 50%, #0d9488 50%); }

        /* Contenedor de ficha - TAMAÑO CARTA */
        .invoice-container {
            max-width: 8.5in;
            margin: 0 auto 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 15px 20px;
            position: relative;
            border: 1px solid var(--border);
        }

        /* Estilos específicos para ficha de costo */
        .ficha-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        .ficha-table th {
            background-color: var(--primary) !important;
            color: white !important;
            padding: 6px 4px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .ficha-table td {
            padding: 4px;
            border: 1px solid var(--border);
            vertical-align: middle;
        }

        .ficha-table .text-right {
            text-align: right;
        }

        .ficha-table .text-center {
            text-align: center;
        }

        .ficha-table .bold {
            font-weight: bold;
        }

        .ficha-table .bg-soft {
            background-color: var(--bg-soft);
        }

        .ficha-table .indent-1 {
            padding-left: 20px !important;
        }

        .firma-section {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            border-top: 2px solid var(--primary);
            padding-top: 15px;
        }

        .firma-box {
            width: 45%;
        }

        .firma-line {
            border-bottom: 1px solid var(--text-main);
            width: 100%;
            height: 1px;
            margin: 8px 0;
        }

        .footer-observaciones {
            font-size: 9px;
            color: var(--text-muted);
            text-align: center;
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid var(--border);
        }

        /* Estilos de impresión */
        @media print {
            .sidebar,
            .sidebar-toggle,
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                margin-left: 0 !important;
            }
            
            .invoice-container {
                max-width: 8.5in !important;
                width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 10px 15px !important;
            }
            
            @page {
                size: Letter;
                margin: 0.4in;
            }
            
            .ficha-table th {
                background-color: var(--primary) !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @media screen {
            .invoice-container {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body data-theme="black">

<!-- Sidebar -->
<div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header">
        <img src="assets/logov.png" alt="Logo" width="42" height="42" style="vertical-align: middle; margin-right: 10px; border-radius: 5px;">
        <div style="flex: 1; overflow: hidden;">
            <div style="font-size: 11px; color: #bdc3c7; text-transform: uppercase; letter-spacing: 0.5px;">MODELO</div>
            <div style="font-size: 15px; font-weight: 600; color: #fbbf24; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">FICHA DE COSTO</div>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="sidebar-section-title">ACCIONES DISPONIBLES</div>
        
        <a href="javascript:void(0)" onclick="imprimirFicha()" class="sidebar-item">
            <i class="fas fa-print icon"></i>
            <span class="text">Imprimir Ficha</span>
        </a>
        
        <a href="facturas.php" class="sidebar-item">
            <i class="fas fa-arrow-left icon"></i>
            <span class="text">Volver a Facturas</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-tachometer-alt icon"></i>
            <span class="text">Regresar al Dashboard</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-section-title">TEMAS DE COLOR</div>
        
        <div style="padding: 10px 20px;">
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 5px;">
                <div class="theme-btn theme-black active" onclick="setTheme('black')" data-tooltip="Tema Negro"></div>
                <div class="theme-btn theme-red" onclick="setTheme('red')" data-tooltip="Tema Rojo"></div>
                <div class="theme-btn theme-green" onclick="setTheme('green')" data-tooltip="Tema Verde"></div>
                <div class="theme-btn theme-yellow" onclick="setTheme('yellow')" data-tooltip="Tema Amarillo"></div>
                <div class="theme-btn theme-blue" onclick="setTheme('blue')" data-tooltip="Tema Azul"></div>
                <div class="theme-btn theme-cyan" onclick="setTheme('cyan')" data-tooltip="Tema Cyan"></div>
                <div class="theme-btn theme-purple" onclick="setTheme('purple')" data-tooltip="Tema Púrpura"></div>
                <div class="theme-btn theme-pink" onclick="setTheme('pink')" data-tooltip="Tema Rosa"></div>
                <div class="theme-btn theme-orange" onclick="setTheme('orange')" data-tooltip="Tema Naranja"></div>
                <div class="theme-btn theme-gray" onclick="setTheme('gray')" data-tooltip="Tema Gris"></div>
                <div class="theme-btn theme-indigo" onclick="setTheme('indigo')" data-tooltip="Tema Índigo"></div>
                <div class="theme-btn theme-teal" onclick="setTheme('teal')" data-tooltip="Tema Verde Azulado"></div>
            </div>
        </div>
        
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-section-title">Información</div>
        
        <div style="padding: 10px 20px; color: white;">
            <div class="data-row" style="color: white;">
                <span style="min-width: 100px;">Documento:</span>
                <span>Ficha de Costo</span>
            </div>
            <div class="data-row" style="color: white;">
                <span style="min-width: 100px;">Resolución:</span>
                <span>148/2023 MFP</span>
            </div>
        </div>
    </div>
    
    <button class="toggle-sidebar-btn" id="toggleSidebar">
        <i class="fas fa-chevron-left"></i>
    </button>
</div>

<!-- Botón para toggle sidebar -->
<button class="btn btn-primary sidebar-toggle no-print" id="sidebarToggle" style="position: fixed; top: 10px; left: 10px; z-index: 999;">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <!-- Contenedor principal - FICHA DE COSTO EN BLANCO -->
    <div id="fichaContainer">
        <div class="invoice-container" id="pagina-1" data-page="1" style="position: relative; overflow: hidden;">
            
            <table class="ficha-table">
                <tr>
                    <th colspan="4">MINISTERIO DE FINANZAS Y PRECIOS</th>
                </tr>
                <tr>
                    <td colspan="4" style="background-color: var(--bg-soft); padding: 4px; text-align: center; font-weight: bold;">
                        FICHA DE COSTOS Y GASTOS DE PRODUCTOS Y SERVICIOS - EVALUACIÓN DE PRECIOS Y TARIFAS
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="background-color: #F9F9F9; padding: 4px;">
                        <strong>Resolución:</strong> 148/2023 MFP &nbsp;|&nbsp; 
                        <strong>Emisión:</strong> ____/____/________ &nbsp;|&nbsp; 
                        <strong>Vigencia:</strong> ____/____/________
                    </td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Producto o Servicio:</strong> </td>
                    <td><strong>Código:</strong> </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <strong>UM:</strong>  &nbsp;&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;|&nbsp;&nbsp; 
                        <strong>Nivel Producción:</strong>  &nbsp;&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;
                        <strong>% Capacidad:</strong> _____________________ %<br> 
                        <strong>Categoría:</strong> 
                    </td>
                </tr>
                
                <tr>
                    <th style="text-align: left;">CONCEPTOS</th>
                    <th>Fila</th>
                    <th style="text-align: right;">Costo Base</th>
                    <th style="text-align: right;">Costo Nuevo</th>
                </tr>
                
                <tr><td>Gasto Material</td><td class="text-center">1</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">De ello: Insumos (Materias primas)</td><td class="text-center">1.1</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">Combustibles y lubricantes</td><td class="text-center">1.2</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">Energía</td><td class="text-center">1.3</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">Agua</td><td class="text-center">1.4</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Salario Directo o retribución</td><td class="text-center">2</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Otros Gastos Directos</td><td class="text-center">3</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Gastos asociados a la producción (Depreciación)</td><td class="text-center">4</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">De ello, salarios</td><td class="text-center">4.1</td><td class="text-right"></td><td class="text-right"></td></tr>
                
                <tr class="bold bg-soft">
                    <td>COSTO TOTAL (1+2+3+4)</td>
                    <td class="text-center">5</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                
                <tr><td>Gastos Generales y de Administración</td><td class="text-center">6</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">De ello, salarios</td><td class="text-center">6.1</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Gastos de Distribución y Venta</td><td class="text-center">7</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td class="indent-1">De ello, salarios</td><td class="text-center">7.1</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Gastos Financieros</td><td class="text-center">8</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Gastos Financiamiento OSDE</td><td class="text-center">9</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Gastos Tributarios (Seg. Social e Impuestos)</td><td class="text-center">10</td><td class="text-right"></td><td class="text-right"></td></tr>
                
                <tr class="bold">
                    <td>TOTAL DE GASTOS (6 al 10)</td>
                    <td class="text-center">11</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                
                <tr class="bold bg-soft">
                    <td>TOTAL DE COSTOS Y GASTOS (5+11)</td>
                    <td class="text-center">12</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                
                <tr><td>Utilidad</td><td class="text-center">13</td><td class="text-right"></td><td class="text-right"></td></tr>
                
                <tr class="bold" style="background-color: #E0E0E0 !important;">
                    <td>PRECIO O TARIFA</td>
                    <td class="text-center">14</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                
                <tr><td>PRECIO UNITARIO AJUSTADO</td><td class="text-center">15</td><td class="text-right"></td><td class="text-right"></td></tr>
                <tr><td>Datos sobre precios de referencia</td><td class="text-center">16</td><td class="text-right">Plan/Anterior</td><td class="text-right">Precio Actual</td></tr>
            </table>
            
            <!-- Firmas -->
            <div class="firma-section">
                <div class="firma-box">
                    <strong>Elaborado por:</strong>
                    <div class="firma-line"></div>
                    <strong>Firma/Cargo:</strong>
                    <div class="firma-line"></div>
                    <strong>Fecha:</strong> ____/____/________
                </div>
                <div class="firma-box">
                    <strong>Aprobado por:</strong>
                    <div class="firma-line"></div>
                    <strong>Firma/Cargo:</strong>
                    <div class="firma-line"></div>
                    <strong>Fecha:</strong> ____/____/________
                </div>
            </div>
            
            <div class="footer-observaciones">
                <p>SISFACT PDL Visiones - Modelo de Ficha de Costo - Res. 148/2023 MFP</p>
            </div>
        </div>
    </div>
</div>

<script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="js/sweetalert211.js"></script>
<script>
    // Sidebar functionality
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body = document.body;

    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        body.classList.toggle('sidebar-expanded');
        
        const icon = toggleSidebarBtn.querySelector('i');
        if (sidebar.classList.contains('collapsed')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-chevron-left';
        }
        
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    toggleSidebarBtn.addEventListener('click', toggleSidebar);
    sidebarToggle.addEventListener('click', toggleSidebar);

    // Load sidebar state from localStorage
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('factura-theme') || 'black';
        setTheme(savedTheme);
        
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            body.classList.remove('sidebar-expanded');
            toggleSidebarBtn.querySelector('i').className = 'fas fa-chevron-right';
        } else {
            sidebar.classList.remove('collapsed');
            body.classList.add('sidebar-expanded');
            toggleSidebarBtn.querySelector('i').className = 'fas fa-chevron-left';
        }
        
        updateThemeButtons(savedTheme);
    });

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('factura-theme', theme);
        updateThemeButtons(theme);
        
        document.querySelectorAll('.invoice-container').forEach(container => {
            container.setAttribute('data-theme', theme);
        });
    }

    function updateThemeButtons(theme) {
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`.theme-btn.theme-${theme}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
    }

    // Función de impresión
    function imprimirFicha() {
        Swal.fire({
            title: '<i class="fas fa-print mr-2"></i> Preparando Ficha de Costo',
            html: '<p style="color:#d1d5db">Generando modelo para impresión...</p>',
            allowOutsideClick: false,
            showConfirmButton: false,
            background: '#111827',
            color: '#f9fafb',
            timer: 800,
            didClose: () => {
                prepararParaImpresion();
                setTimeout(() => {
                    window.print();
                    setTimeout(() => {
                        restaurarDespuesImpresion();
                    }, 500);
                }, 500);
            }
        });
    }

    function prepararParaImpresion() {
        document.querySelectorAll('.no-print').forEach(el => {
            el.style.display = 'none';
        });
        document.body.classList.add('printing');
    }
    
    function restaurarDespuesImpresion() {
        document.querySelectorAll('.no-print').forEach(el => {
            el.style.display = '';
        });
        document.body.classList.remove('printing');
    }

    // Estilos CSS para modo oscuro
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup {
            background: #1f2937 !important;
            border: 1px solid #374151 !important;
            border-radius: 8px !important;
            color: #f9fafb !important;
        }
        .swal2-title { color: #f9fafb !important; }
        .swal2-html-container { color: #d1d5db !important; }
        .swal2-confirm { background-color: #3b82f6 !important; }
        .swal2-cancel { background-color: #6b7280 !important; color: #f9fafb !important; }
        .swal2-close { color: #9ca3af !important; }
        .swal2-close:hover { color: #f3f4f6 !important; }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>