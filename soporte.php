<?php
// soporte.php
session_start();
$COMPANY_NAME = 'PDL TransNuBeT';
$SITE_NAME = $COMPANY_NAME . ' - Soporte Técnico';
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
        .content-card {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 40px 48px;
            color: #e2e8f0;
        }
        h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(145deg, #ffffff, #94a3f0); background-clip: text; -webkit-background-clip: text; color: transparent; }
        .contact-info { margin: 2rem 0; background: rgba(0,0,0,0.3); border-radius: 24px; padding: 20px; text-align: center; }
        .contact-info i { font-size: 2rem; color: #60a5fa; margin-right: 10px; vertical-align: middle; }
        .contact-info a { color: #60a5fa; text-decoration: none; font-size: 1.2rem; font-weight: 500; }
        .contact-info a:hover { text-decoration: underline; }
        .support-card { background: rgba(59,130,246,0.1); border-radius: 20px; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6; }
        .support-card i { font-size: 1.5rem; margin-right: 15px; color: #3b82f6; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,0.15); border: 1px solid #3b82f6; border-radius: 40px; padding: 8px 20px; color: #60a5fa; text-decoration: none; margin-bottom: 20px; }
        .btn-back:hover { background: rgba(59,130,246,0.3); transform: translateX(-4px); }
        .footer-links { margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(59,130,246,0.3); display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .footer-links a { color: #94a3b8; text-decoration: none; }
        .footer-links a:hover { color: #60a5fa; text-decoration: underline; }
        @media (max-width: 640px) { .content-card { padding: 24px 20px; } }
    </style>
</head>
<body>
<div class="content-card">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
    <h1><i class="fas fa-headset me-3"></i>Soporte Técnico</h1>
    <p>¿Necesitas ayuda con el Sistema de Gestión de Nóminas? Contáctanos a través de los siguientes canales oficiales.</p>

    <div class="contact-info">
        <i class="fas fa-envelope"></i> <a href="mailto:soporte_TransNuBeT@gmail.com">soporte_TransNuBeT@gmail.com</a><br>
        <i class="fab fa-whatsapp mt-2" style="color:#25D366;"></i> <a href="https://wa.me/5359860773?text=Hola%2C%20necesito%20soporte%20t%C3%A9cnico%20para%20el%20sistema%20de%20n%C3%B3minas">+53 5986 0773</a><br>
        <small class="text-muted">(Horario laboral: Lunes a Viernes, 8:00am – 5:00pm)</small>
    </div>

    <div class="support-card">
        <i class="fas fa-ticket-alt"></i> <strong>Reporte de incidencias</strong><br>
        Si encuentras un error o comportamiento inesperado, envía un correo con el asunto <strong>[INCIDENCIA]</strong> describiendo detalladamente el problema, adjuntando capturas de pantalla si es posible.
    </div>
    <div class="support-card">
        <i class="fas fa-question-circle"></i> <strong>Preguntas frecuentes</strong><br>
        Consulta la documentación interna o solicita una reunión con el especialista de gestión económica para dudas sobre cálculos de nómina, impuestos o vacaciones.
    </div>
    <div class="support-card">
        <i class="fas fa-database"></i> <strong>Solicitud de respaldo</strong><br>
        Los administradores pueden generar copias de seguridad manualmente desde la opción <em>"Salva del Sistema Manual"</em> en el menú de opciones.
    </div>

    <div class="footer-links">
        <a href="terminos.php"><i class="fas fa-file-contract"></i> Términos</a>
        <a href="privacidad.php"><i class="fas fa-lock"></i> Privacidad</a>
        <a href="index.php"><i class="fas fa-home"></i> Ir al Inicio</a>
    </div>
</div>
</body>
</html>