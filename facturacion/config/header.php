<?php
// Cargamos la lógica desde el nuevo archivo
// config/header.php

require_once 'init.php';

// Configuración de variables visuales
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--<title>SISFACT PDL Visiones - Sistema de Facturación</title>-->
    
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Windows 11 CSS Principal -->
    <link rel="stylesheet" href="css/windows11.css">
</head>
<body>

<?php 
if (isset($_SESSION['maintenance_bypass_notice']) && $_SESSION['maintenance_bypass_notice'] === true) {
    // Intentar obtener el nombre del usuario de diferentes formas
    $nombre_programador = 'Programador';
    
    if (isset($_SESSION['usuario_nombre']) && !empty($_SESSION['usuario_nombre'])) {
        $nombre_programador = $_SESSION['usuario_nombre'];
    } elseif (isset($_SESSION['nombre']) && !empty($_SESSION['nombre'])) {
        $nombre_programador = $_SESSION['nombre'];
    } elseif (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        $nombre_programador = $_SESSION['user_name'];
    } elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        $nombre_programador = $_SESSION['username'];
    }
    ?>
    <div id="maintenance-notice" style="position: fixed; top: -30px; left: 0; width: 100%; background: linear-gradient(90deg, #ffc107, #ffdb4d); color: #000; text-align: center; padding: 4px; font-weight: 700; z-index: 100001; font-size: 13px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border-bottom: 1px solid #e0a800; transition: top 0.3s ease;">
        <i class="fas fa-tools me-2"></i> MODO MANTENIMIENTO ACTIVO 
        <span style="margin-left: 10px; font-weight: 600; background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 3px; font-size: 12px;">
            <i class="fas fa-user-cog me-1"></i><?php echo htmlspecialchars($nombre_programador); ?> - (Usuario Programador)
        </span>
        <span style="font-weight: normal; margin-left: 10px; font-size: 12px;">(El sistema está oculto para el resto de los Usuarios)</span>
        <button onclick="this.parentElement.style.top='-30px'; document.body.style.marginTop='0';" style="background: transparent; border: none; color: #000; font-size: 16px; cursor: pointer; margin-left: 15px; padding: 0 5px; line-height: 1;">&times;</button>
    </div>
    
    <script>
        const notice = document.getElementById('maintenance-notice');
        
        // Mostrar al cargar y ocultar después de 3 segundos
        notice.style.top = '0';
        document.body.style.marginTop = "28px";
        setTimeout(() => notice.style.top = '-30px', 3000);
        
        // Detectar scroll para mostrar/ocultar
        window.addEventListener('scroll', () => {
            const scrollTop = window.scrollY;
            
            // Mostrar solo cuando estás en el top (0-50px)
            if (scrollTop < 50) {
                notice.style.top = '0';
                document.body.style.marginTop = "28px";
            } 
            // Ocultar al bajar más de 50px
            else if (scrollTop > 50) {
                notice.style.top = '-30px';
                document.body.style.marginTop = "0";
            }
        });
        
        // Mostrar al hover en header/navbar (siempre)
        document.addEventListener('mouseover', (e) => {
            if (e.target.closest('header, nav, .navbar')) {
                notice.style.top = '0';
                document.body.style.marginTop = "28px";
            }
        });
        
        // Ocultar 1 segundo después de salir del aviso
        notice.addEventListener('mouseleave', () => {
            setTimeout(() => {
                // Solo ocultar si no estamos en el top de la página
                if (window.scrollY > 50) {
                    notice.style.top = '-30px';
                    document.body.style.marginTop = "0";
                }
            }, 1000);
        });
/**
 * Convierte un número a letras (Versión JS exacta de tu PHP)
 */
function convertirNumeroALetras(numero, moneda = 'PESOS', centimos = 'CENTAVOS') {
    // 1. Asegurar formato numérico y 2 decimales
    let num = parseFloat(numero).toFixed(2);
    
    // 2. Separar parte entera y decimal
    const partes = num.split('.');
    const entero = partes[0];
    const decimal = partes[1];

    // Caso CERO
    if (parseInt(entero) === 0) {
        return `CERO ${moneda} CON ${decimal}/100`;
    }

    // Definición de sufijos (Escala Larga)
    // 0=Unidad, 1=Mil, 2=Millón, 3=Mil(Millones), 4=Billón, etc.
    const sufijos = {
        0: '', 
        1: 'MIL', 
        2: ['MILLÓN', 'MILLONES'], 
        3: 'MIL', 
        4: ['BILLÓN', 'BILLONES'], 
        5: 'MIL', 
        6: ['TRILLÓN', 'TRILLONES'],
        7: 'MIL',
        8: ['CUATRILLÓN', 'CUATRILLONES']
    };

    // 3. Dividir en grupos de 3 (invertido)
    // En JS no hay str_split directo para esto, usamos regex o array manipulación
    const reversed = entero.split('').reverse().join('');
    const chunks = reversed.match(/.{1,3}/g) || [];
    
    let texto_array = [];

    // 4. Iterar sobre los grupos
    chunks.forEach((chunk, index) => {
        // Volvemos el chunk al orden normal
        const triada = chunk.split('').reverse().join('');
        const num_triada = parseInt(triada);

        if (num_triada === 0) return;

        // Convertir el número de 3 dígitos a letras
        let texto_triada = convertirTriada(num_triada);

        // Determinar el sufijo
        let sufijo = '';
        if (sufijos[index]) {
            const s = sufijos[index];
            if (Array.isArray(s)) {
                // Es pluralizable (Millón/Billón)
                sufijo = (num_triada === 1) ? s[0] : s[1];
            } else {
                sufijo = s;
            }
        }

        // Reglas especiales
        
        // A) Si es 1000, 1000000000 (MIL, UN MIL -> MIL)
        if (sufijo === 'MIL' && num_triada === 1) {
            texto_triada = ''; 
        }

        // Agregar al array (al inicio, unshift)
        const parte_final = (texto_triada + ' ' + sufijo).trim();
        texto_array.unshift(parte_final);
    });

    // 5. Unir y formatear
    let resultado = texto_array.join(' ');
    
    // Limpieza de espacios dobles
    resultado = resultado.replace(/\s+/g, ' ').trim();

    return `${resultado} ${moneda} CON ${decimal}/100`;
}

// Función auxiliar para triadas (0-999)
function convertirTriada(num) {
    const unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    const decenas  = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    const diez_veinte = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    const veinti    = ['VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    const centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    let texto = '';
    
    // Centenas
    const c = Math.floor(num / 100);
    const resto = num % 100;

    if (c > 0) {
        if (c === 1 && resto === 0) {
            texto += 'CIEN';
        } else {
            texto += centenas[c];
        }
        if (resto > 0) texto += ' ';
    }

    // Decenas y Unidades
    if (resto > 0) {
        if (resto < 10) {
            texto += unidades[resto];
        } else if (resto >= 10 && resto < 20) {
            // Ajuste de índice porque JS arrays son base 0
            texto += diez_veinte[resto - 10]; 
        } else if (resto >= 20 && resto < 30) {
             texto += veinti[resto - 20];
        } else {
            const d = Math.floor(resto / 10);
            const u = resto % 10;
            
            texto += decenas[d];
            if (u > 0) {
                texto += ' Y ' + unidades[u];
            }
        }
    }
    
    return texto;
}
    </script>
<?php } ?>