<?php
// privacidad.php
session_start();
$COMPANY_NAME = 'PDL TransNuBeT';
$SITE_NAME = $COMPANY_NAME . ' - Política de Privacidad';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../logotn.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            pointer-events: none;
            opacity: 0.1;
            z-index: 0;
        }
        body::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(59, 130, 246, 0.05) 30px, rgba(59, 130, 246, 0.05) 60px);
            pointer-events: none;
            z-index: 0;
        }
        .content-card {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 40px 48px;
            color: #e2e8f0;
        }
        h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(145deg, #ffffff, #94a3f0); background-clip: text; -webkit-background-clip: text; color: transparent; }
        h2 { font-size: 1.3rem; margin: 1.5rem 0 1rem; color: #60a5fa; border-left: 4px solid #3b82f6; padding-left: 15px; }
        p, li { line-height: 1.6; color: #cbd5e1; }
        ul { margin: 0.5rem 0 1rem 2rem; }
        .footer-links { margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(59,130,246,0.3); display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .footer-links a { color: #94a3b8; text-decoration: none; transition: 0.2s; }
        .footer-links a:hover { color: #60a5fa; text-decoration: underline; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,0.15); border: 1px solid #3b82f6; border-radius: 40px; padding: 8px 20px; color: #60a5fa; text-decoration: none; margin-bottom: 20px; }
        .btn-back:hover { background: rgba(59,130,246,0.3); transform: translateX(-4px); }
    </style>
</head>
<body>
<div class="content-card">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
    <h1><i class="fas fa-lock me-3"></i>Política de Privacidad</h1>
    <p class="text-muted mb-4">Última actualización: <span style="color: yellow;font-weight:bold;">25/05/2026</span></p>

    <h2>1. Datos que recopilamos</h2>
    <p>El Sistema recopila información personal de los trabajadores y usuarios autorizados, incluyendo: nombre completo, carné de identidad, número de teléfono, dirección, área de trabajo, salario, datos bancarios, historial de pagos y vacaciones. También se almacenan registros de acceso (IP, fecha, hora).</p>

    <h2>2. Uso de la información</h2>
    <p>Los datos se utilizan exclusivamente para:</p>
    <ul>
        <li>Gestionar nóminas, pagos y deducciones legales.</li>
        <li>Control de vacaciones y licencias.</li>
        <li>Generar reportes internos y cumplir con obligaciones fiscales (CESS, impuestos).</li>
        <li>Auditorías de seguridad y trazabilidad de acciones.</li>
    </ul>

    <h2>3. Protección de datos</h2>
    <p>Toda la información se almacena en servidores locales con acceso restringido mediante autenticación robusta y cifrado de contraseñas. No compartimos datos personales con terceros, excepto cuando sea requerido por las autoridades competentes de la República de Cuba.</p>

    <h2>4. Derechos del usuario</h2>
    <p>Usted tiene derecho a solicitar la corrección, eliminación o limitación del tratamiento de sus datos personales, siempre que no afecte obligaciones legales. Para ejercer estos derechos, comuníquese con el administrador del sistema.</p>

    <h2>5. Cookies y seguimiento</h2>
    <p>El Sistema utiliza cookies de sesión estrictamente necesarias para el funcionamiento (autenticación). No se emplean cookies de terceros ni sistemas de analítica externos.</p>

    <h2>6. Consentimiento</h2>
    <p>Al utilizar el Sistema, usted otorga su consentimiento explícito para el tratamiento de sus datos personales conforme a esta política.</p>

    <div class="footer-links">
        <a href="terminos.php"><i class="fas fa-file-contract"></i> Términos y Condiciones</a>
        <a href="soporte.php"><i class="fas fa-envelope"></i> Soporte</a>
        <a href="index.php"><i class="fas fa-home"></i> Ir al Inicio</a>
    </div>
</div>
</body>
</html>