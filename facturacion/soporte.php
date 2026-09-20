<?php
$page_title = "Centro de Soporte | SISFACT PDL Visiones";
$support_id = "TICKET-" . strtoupper(substr(md5(time()), 0, 6));
$soporte_whatsapp = "5359860773";
$soporte_email = "soporte@pdlvisiones.com";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <!-- SweetAlert2 con tema oscuro -->
		<link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
    <style>
        :root {
            --bg-dark: #0a0a0a;
            --mica-bg: rgba(28, 28, 28, 0.75);
            --mica-border: rgba(255, 255, 255, 0.1);
            --accent: #0078d4;
            --accent-hover: #005a9e;
            --whatsapp-green: #25D366;
            --text-main: #ffffff;
            --text-muted: #a0a0a0;
            --success: #32d74b;
            --card-bg: rgba(255, 255, 255, 0.03);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(0, 120, 212, 0.12) 0%, transparent 35%),
                radial-gradient(circle at 100% 100%, rgba(209, 52, 56, 0.05) 0%, transparent 35%);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.0;
            overflow: hidden; /* Evita scroll general del body */
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("assets/carbon-fibre.png");
            opacity: 0.03;
            pointer-events: none;
            z-index: -1;
        }

        /* --- HEADER --- */
        .header {
            padding: 8px 40px;
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(25px);
            border-bottom: 1px solid var(--mica-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-logo-img { height: 38px; width: auto; object-fit: contain; }
        .brand-text h1 { font-size: 16px; font-weight: 700; letter-spacing: -0.5px; }

        .system-status {
            font-size: 11px;
            display: flex; align-items: center; gap: 8px;
            color: var(--success);
            background: rgba(50, 215, 75, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(50, 215, 75, 0.2);
        }

        .pulse-dot {
            width: 7px; height: 7px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        /* --- HERO --- */
        .hero { padding: 20px 20px 10px; text-align: center; }
        .hero h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .hero p { color: var(--text-muted); font-size: 14px; }

        /* --- MAIN CONTENT --- */
        .container {
            flex: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 10px 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            overflow: hidden; /* El scroll será interno si es necesario */
        }

        .support-card {
            background: var(--mica-bg);
            backdrop-filter: blur(40px);
            border: 1px solid var(--mica-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow-y: auto; /* Scroll interno para la tarjeta de soporte */
        }

        .form-group { margin-bottom: 12px; }
        .form-label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px; font-weight: 500; }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--mica-border);
            border-radius: 8px;
            padding: 10px 12px;
            color: white;
            outline: none;
            transition: all 0.2s;
            font-size: 13px;
        }

        .form-input:focus, .form-textarea:focus {
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.08);
        }

        /* --- FAQ SECTION --- */
        .faq-section { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--mica-border); }
        .faq-section h3 { margin-bottom: 12px; font-size: 16px; font-weight: 600; }
        
        .faq-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--mica-border);
            border-radius: 8px;
            margin-bottom: 8px;
            overflow: hidden;
        }
        .faq-question {
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            font-size: 13px;
            transition: background 0.2s;
            user-select: none;
        }
        .faq-question:hover { background: rgba(255, 255, 255, 0.05); }
        .faq-question i { 
            font-size: 10px; 
            transition: transform 0.3s ease;
            color: var(--accent);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0, 1, 0, 1), padding 0.3s ease;
            color: var(--text-muted);
            font-size: 12px;
            background: rgba(0,0,0,0.1);
        }

        .faq-answer-inner { padding: 12px 15px; }

        /* Clase activa para FAQ */
        .faq-item.active .faq-answer { max-height: 500px; border-top: 1px solid var(--mica-border); }
        .faq-item.active .faq-question i { transform: rotate(90deg); }

        /* --- INFO PANEL --- */
        .info-panel { display: flex; flex-direction: column; gap: 15px; }
        .mini-card {
            background: var(--card-bg);
            border: 1px solid var(--mica-border);
            border-radius: 12px;
            padding: 15px;
        }
        .mini-card i { font-size: 18px; color: var(--accent); margin-bottom: 10px; display: block; }
        .mini-card h4 { margin-bottom: 4px; font-size: 14px; }
        .mini-card p { font-size: 12px; color: var(--text-muted); }

        /* --- BUTTONS --- */
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: none;
            text-decoration: none;
        }

        .btn-whatsapp { background: var(--whatsapp-green); color: white; }
        .btn-whatsapp:hover { background: #1eb956; transform: translateY(-2px); }

        .btn-email { background: var(--accent); color: white; }
        .btn-email:hover { background: var(--accent-hover); transform: translateY(-2px); }

        /* --- FOOTER --- */
        .footer {
            padding: 15px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            border-top: 1px solid var(--mica-border);
            background: rgba(0,0,0,0.2);
        }

        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 1; }
            70% { transform: scale(1.2); opacity: 0.5; }
            100% { transform: scale(0.9); opacity: 1; }
        }

        /* Scrollbar Estilizada */
        .support-card::-webkit-scrollbar { width: 6px; }
        .support-card::-webkit-scrollbar-track { background: transparent; }
        .support-card::-webkit-scrollbar-thumb { background: var(--mica-border); border-radius: 10px; }

        @media (max-width: 900px) {
            body { overflow-y: auto; height: auto; }
            .container { grid-template-columns: 1fr; overflow: visible; height: auto; }
            .btn-group { grid-template-columns: 1fr; }
        }
/* --- ESTILOS DE FORMULARIO ACTUALIZADOS --- */
.form-input, .form-textarea, .form-select {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--mica-border);
    border-radius: 8px;
    padding: 10px 12px;
    color: white;
    outline: none;
    transition: all 0.2s ease;
    font-size: 13px;
    font-family: 'Inter', sans-serif;
}

/* Estilo específico para el Select (Combo) */
.form-select {
    appearance: none; /* Elimina la flecha por defecto del navegador */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a0a0a0'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 14px;
    padding-right: 40px; /* Espacio para la flecha */
    cursor: pointer;
}

/* Estilo para las opciones del combo (Dropdown) */
.form-select option {
    background-color: #1a1a1a; /* Fondo oscuro para que sea legible en dark theme */
    color: white;
    padding: 10px;
}

.form-input:focus, .form-textarea:focus, .form-select:focus {
    border-color: var(--accent);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.2);
}

.form-input::placeholder, .form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}
.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

    </style>
</head>
<body>

    <header class="header">
        <div class="brand">
            <img src="assets/logov.png" alt="SISFAC Logo" class="brand-logo-img">
            <div class="brand-text">
                <h1>Soporte Técnico PDL VISIONES</h1>
            </div>
        </div>
        <div class="system-status">
            <div class="pulse-dot"></div>
            Sistemas en línea
        </div>
    </header>

    <section class="hero">
        <h2>Centro de Soporte Técnico PDL VISIONES</h2>
        <p>Referencia: <span style="color: var(--accent); font-weight: 600;"><?php echo $support_id; ?></span></p>
    </section>

    <main class="container">
        <div class="support-card">
            <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                <i class="fas fa-edit" style="color: var(--accent);"></i> Nuevo Ticket de Atención a Usuarios
            </h3>
            
            <form id="supportForm">
                <input type="hidden" id="ticket_id" value="<?php echo $support_id; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Su Nombre</label>
                        <input type="text" id="nombre" class="form-input" placeholder="Nombre completo" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" id="email" class="form-input" placeholder="correo@ejemplo.com" required>
                    </div>
                </div>

<div class="form-group">
    <label class="form-label">Categoría</label>
    <select id="categoria" class="form-select">
        <option value="Error de Sistema">Error de Sistema / Bug</option>
        <option value="Facturación">Problema de Facturación</option>
        <option value="Acceso">Acceso / Contraseña</option>
        <option value="Solicitud">Nueva Función</option>
        <!-- Nuevas opciones relacionadas con instalación de base de datos -->
        <option value="Instalación BD">Problema de Instalación de Base de Datos</option>
        <option value="Conexión BD">Error de Conexión a Base de Datos</option>
        <option value="Creación Tablas">Error al Crear Tablas</option>
        <option value="Permisos BD">Problema de Permisos de Base de Datos</option>
        <option value="Configuración BD">Configuración de Base de Datos</option>
        <option value="Backup/Restore">Backup o Restauración de BD</option>
        <option value="Migración BD">Migración de Base de Datos</option>
        <!-- Opciones técnicas adicionales -->
        <option value="Requisitos Sistema">Verificación de Requisitos</option>
        <option value="Configuración Servidor">Configuración del Servidor</option>
        <option value="Problema PHP">Error PHP</option>
        <option value="Seguridad">Problema de Seguridad</option>
        <option value="Actualización">Actualización del Sistema</option>
        <option value="Otro">Otro Problema</option>
    </select>
</div>

                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea id="descripcion" class="form-textarea" rows="3" placeholder="Detalle su problema..." required></textarea>
                </div>

                <div class="btn-group">
                    <button type="button" onclick="sendToWhatsApp()" class="btn btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </button>
                    <button type="button" onclick="sendToEmail()" class="btn btn-email">
                        <i class="fas fa-envelope"></i> Email
                    </button>
                </div>
            </form>

            <div class="faq-section">
                <h3>Preguntas Frecuentes</h3>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Cómo recupero mi contraseña?</span>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Puede solicitar un restablecimiento desde la pantalla de inicio de sesión haciendo clic en <strong>"¿Olvidó su contraseña?"</strong>. Recibirás un enlace seguro en su correo institucional.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Funciona sin conexión a internet?</span>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            SISFAC Sistema de Facturación PDL VISIONES requiere conexión activa para sincronizar facturas con el servidor central, aunque permite la visualización de datos consultados previamente.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="info-panel">
            <div class="mini-card" style="border-left: 3px solid var(--whatsapp-green);">
                <i class="fab fa-whatsapp" style="color: var(--whatsapp-green);"></i>
                <h4>WhatsApp</h4>
                <p>Línea directa para urgencias críticas:<br><b>+53 59860773</b></p>
            </div>

            <div class="mini-card" style="border-left: 3px solid var(--accent);">
                <i class="fas fa-clock"></i>
                <h4>Horario</h4>
                <p>Lun - Vie: 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</p>
            </div>

            <div class="mini-card">
                <i class="fas fa-shield-alt"></i>
                <h4>Privacidad</h4>
                <p>Sus datos están protegidos bajo protocolos de cifrado corporativo.</p>
            </div>

            <!-- BOTONES DE NAVEGACIÓN ADICIONALES -->
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-house"></i> Ir al Inicio
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Regresar atrás
                </button>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Entrar al Sistema
                </a>
            </div>
        </aside>
    </main>

    <footer class="footer">
        © <?php echo date('Y'); ?> SISFAC PDL Visiones. Infraestructura de Gestión Protegida.
    </footer>

    <script>
        function toggleFaq(element) {
            const faqItem = element.parentElement;
            faqItem.classList.toggle('active');
        }

        function sendToWhatsApp() {
            const ticket = document.getElementById('ticket_id').value;
            const nombre = document.getElementById('nombre').value;
            const email = document.getElementById('email').value;
            const cat = document.getElementById('categoria').value;
            const desc = document.getElementById('descripcion').value;

if(!nombre || !email || !desc) { 
    Swal.fire({
        icon: 'error',
        title: '⚠️ CAMPOS REQUERIDOS ⚠️',
        html: '<div style="text-align: left; padding: 15px; border: 1px solid #333; border-radius: 8px;">' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Nombre</span>' +
              '</div>' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Email</span>' +
              '</div>' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Descripción</span>' +
              '</div>' +
              '</div>',
        footer: '<i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i> Todos los campos son obligatorios',
        confirmButtonText: '<i class="fas fa-check"></i> Entendido',
        confirmButtonColor: '#2b2b2b',
        background: '#0a0a0f',
        color: '#e0e0e0',
        iconColor: '#ff4444'
    });
    return; 
}

            const msg = `*🚀 SISFAC SOPORTE*\n*ID:* ${ticket}\n*Usuario:* ${nombre}\n*Email:* ${email}\n*Categoría:* ${cat}\n*Problema:* ${desc}`;
            window.open(`https://wa.me/<?php echo $soporte_whatsapp; ?>?text=${encodeURIComponent(msg)}`, '_blank');
        }

        function sendToEmail() {
            const ticket = document.getElementById('ticket_id').value;
            const nombre = document.getElementById('nombre').value;
            const desc = document.getElementById('descripcion').value;

if(!nombre || !email || !desc) { 
    Swal.fire({
        icon: 'error',
        title: '⚠️ CAMPOS REQUERIDOS ⚠️',
        html: '<div style="text-align: left; padding: 15px; border: 1px solid #333; border-radius: 8px;">' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Nombre</span>' +
              '</div>' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Email</span>' +
              '</div>' +
              '<div style="margin: 10px 0; padding: 8px; background: rgba(255,107,107,0.1); border-left: 3px solid #ff6b6b;">' +
              '  <i class="fas fa-times-circle" style="color: #ff6b6b; margin-right: 10px; text-shadow: 0 0 5px #ff6b6b;"></i>' +
              '  <span style="color: #ffffff; font-weight: 600;">Descripción</span>' +
              '</div>' +
              '</div>',
        footer: '<i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i> Todos los campos son obligatorios',
        confirmButtonText: '<i class="fas fa-check"></i> Entendido',
        confirmButtonColor: '#2b2b2b',
        background: '#0a0a0f',
        color: '#e0e0e0',
        iconColor: '#ff4444'
    });
    return; 
}
            const subject = `Ticket Soporte: ${ticket}`;
            const body = `Nombre: ${nombre}\n\nDescripción:\n${desc}`;
            window.location.href = `mailto:<?php echo $soporte_email; ?>?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }
    </script>
</body>
</html>