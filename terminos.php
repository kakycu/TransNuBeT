<?php
// terminos.php
session_start();
$COMPANY_NAME = 'PDL TransNuBeT';
$SITE_NAME = $COMPANY_NAME . ' - Términos y Condiciones';
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
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(59, 130, 246, 0.05) 30px, rgba(59, 130, 246, 0.05) 60px),
                repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(139, 92, 246, 0.03) 35px, rgba(139, 92, 246, 0.03) 70px);
            pointer-events: none;
            z-index: 0;
        }
        .content-card {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 40px 48px;
            color: #e2e8f0;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; background: linear-gradient(145deg, #ffffff, #94a3f0); background-clip: text; -webkit-background-clip: text; color: transparent; }
        h2 { font-size: 1.3rem; margin: 1.5rem 0 1rem; color: #60a5fa; border-left: 4px solid #3b82f6; padding-left: 15px; }
        p, li { line-height: 1.6; color: #cbd5e1; }
        ul { margin: 0.5rem 0 1rem 2rem; }
        .footer-links { margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(59,130,246,0.3); display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .footer-links a { color: #94a3b8; text-decoration: none; transition: 0.2s; }
        .footer-links a:hover { color: #60a5fa; text-decoration: underline; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,0.15); border: 1px solid #3b82f6; border-radius: 40px; padding: 8px 20px; color: #60a5fa; text-decoration: none; margin-bottom: 20px; transition: 0.2s; }
        .btn-back:hover { background: rgba(59,130,246,0.3); transform: translateX(-4px); }
        @media (max-width: 640px) { .content-card { padding: 24px 20px; } h1 { font-size: 1.6rem; } }
    </style>
</head>
<body>
<div class="content-card">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
    <h1><i class="fas fa-file-contract me-3"></i>Términos y Condiciones</h1>
    <p class="text-muted mb-4">Última actualización: <?php echo date('d/m/Y'); ?></p>

    <h2>1. Aceptación de los términos</h2>
    <p>Al acceder y utilizar el Sistema de Gestión de Nóminas (en adelante "el Sistema"), usted acepta quedar vinculado por estos Términos y Condiciones, así como por todas las leyes y regulaciones aplicables. Si no está de acuerdo con alguno de estos términos, se le prohíbe utilizar o acceder a este Sistema.</p>

    <h2>2. Uso del sistema</h2>
    <p>El Sistema es proporcionado exclusivamente para la gestión interna de nóminas, trabajadores y pagos del Proyecto de Desarrollo Local <strong><?php echo htmlspecialchars($COMPANY_NAME); ?></strong>. El acceso está restringido a usuarios autorizados mediante credenciales personales e intransferibles. Cada usuario es responsable de mantener la confidencialidad de su contraseña y de todas las actividades que ocurran bajo su cuenta.</p>

    <h2>3. Propiedad intelectual</h2>
    <p>Todo el contenido, código fuente, diseño, bases de datos, logotipos y documentación asociada son propiedad exclusiva de <?php echo htmlspecialchars($COMPANY_NAME); ?> y están protegidos por las leyes de propiedad intelectual cubanas e internacionales. Queda prohibida la reproducción, distribución, modificación o ingeniería inversa sin autorización expresa por escrito.</p>

    <h2>4. Limitación de responsabilidad</h2>
    <p>El Sistema se proporciona "tal cual", sin garantías de ningún tipo. <?php echo htmlspecialchars($COMPANY_NAME); ?> no será responsable por daños directos, indirectos, incidentales o consecuentes derivados del uso o la imposibilidad de uso del Sistema, incluso si se ha advertido de la posibilidad de tales daños.</p>

    <h2>5. Modificaciones</h2>
    <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Los cambios entrarán en vigor inmediatamente después de su publicación. Se recomienda revisar periódicamente esta página.</p>

    <h2>6. Legislación aplicable</h2>
    <p>Estos términos se rigen por las leyes de la República de Cuba. Cualquier disputa será resuelta ante los tribunales competentes de la localidad.</p>

    <div class="footer-links">
        <a href="privacidad.php"><i class="fas fa-lock"></i> Política de Privacidad</a>
        <a href="soporte.php"><i class="fas fa-envelope"></i> Soporte</a>
        <a href="index.php"><i class="fas fa-home"></i> Ir al Inicio</a>
    </div>
</div>
</body>
</html>