<?php
// logout_display.php - Página que muestra el mensaje de logout

// Iniciar sesión para obtener el nombre del usuario
session_start();

// Obtener el nombre del usuario desde la sesión o usar 'Usuario' por defecto
$nombre_usuario = isset($_SESSION['usuario_nombre']) ? htmlspecialchars($_SESSION['usuario_nombre']) : 'Usuario';
$nombre_usuario = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : $nombre_usuario;
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : $nombre_usuario;
$nombre_usuario = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : $nombre_usuario;

// Obtener el username (si existe en la sesión)
$username = isset($_SESSION['usuario']) ? htmlspecialchars($_SESSION['usuario']) : '';
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : $username;
$username = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : $username;

// Formatear el nombre completo: Nombre Apellidos (usuario)
$nombre_completo = $nombre_usuario;

// Si tenemos username, agregarlo entre paréntesis
if (!empty($username) && $username !== $nombre_usuario) {
    $nombre_completo = $nombre_usuario . ' (' . $username . ')';
}

// También crear una versión corta (solo primer nombre + username)
$nombre_corto = $nombre_usuario;
if (strpos($nombre_usuario, ' ') !== false) {
    $nombre_partes = explode(' ', $nombre_usuario);
    $nombre_corto = $nombre_partes[0]; // Solo el primer nombre
}

// Agregar username al nombre corto si existe
if (!empty($username) && $username !== $nombre_corto) {
    $nombre_corto_con_usuario = $nombre_corto . ' (' . $username . ')';
} else {
    $nombre_corto_con_usuario = $nombre_corto;
}

// Si no hay nombre de usuario, usar valores por defecto
if (empty($nombre_usuario) || $nombre_usuario === 'Usuario') {
    $nombre_completo = 'Estimado usuario';
    $nombre_corto_con_usuario = 'Estimado usuario';
    $nombre_corto = 'Estimado usuario';
}

// Limpiar completamente la sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Despedida SISFACT PDL VISIONES</title>
  <link rel="icon" type="image/x-icon" href="assets/logov.png">
  <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/sweetalert2.min.css">
  <!-- Animate.css para animaciones -->
  <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <style>
    /* ESTILOS BASE - TEMA AZUL */
    :root {
      --primary: #007bff;
      --primary-dark: #0056b3;
      --primary-light: #a0c8ff;
      --primary-transparent: rgba(0, 123, 255, 0.1);
      --bg-dark: #0a0f1a;
      --bg-card: rgba(20, 30, 48, 0.9);
      --text-light: #e0e7ff;
      --text-muted: #8fa3c9;
    }
    
    body {
      margin: 0;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      background: linear-gradient(135deg, 
          #0a0f1a 0%, 
          #0c1b2e 25%, 
          #0e2038 50%, 
          #0a1929 75%, 
          #0a0f1a 100%);
      background-size: 400% 400%;
      animation: gradientBG 15s ease infinite;
      position: relative;
      overflow-x: hidden;
    }

    /* Fondo con logo repetido como marca de agua */
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('assets/logov.png');
      background-repeat: repeat;
      background-size: 180px 180px;
      background-position: 0 0;
      opacity: 0.08;
      z-index: 1;
      animation: subtleMove 40s linear infinite;
      pointer-events: none;
    }

    /* Capa para difuminar el logo de fondo */
    body::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at center, transparent 20%, rgba(10, 15, 26, 0.85) 70%);
      z-index: 1;
      pointer-events: none;
    }

    /* Partículas de fondo mejoradas */
    .particles {
      position: absolute;
      width: 100%;
      height: 100%;
      z-index: 2;
      pointer-events: none;
      overflow: hidden;
    }

    .particle {
      position: absolute;
      background: var(--primary);
      border-radius: 50%;
      animation: floatParticle linear infinite;
    }

    /* Partículas pequeñas azules */
    .particle.small {
      width: 3px;
      height: 3px;
      background: var(--primary-light);
      box-shadow: 0 0 8px var(--primary-light);
    }

    /* Partículas medianas */
    .particle.medium {
      width: 6px;
      height: 6px;
      background: var(--primary);
      box-shadow: 0 0 12px var(--primary);
    }

    /* Partículas grandes (brillos) */
    .particle.large {
      width: 10px;
      height: 10px;
      background: white;
      box-shadow: 0 0 20px var(--primary-light), 0 0 40px var(--primary);
    }

    /* Partículas con efecto de parpadeo */
    .particle.twinkle {
      width: 4px;
      height: 4px;
      background: white;
      box-shadow: 0 0 15px white;
      animation: floatParticle linear infinite, twinkle 3s infinite alternate;
    }

    header, footer {
      width: 100%;
      max-width: 550px;
      text-align: center;
      color: var(--primary);
      padding: 1rem 0;
      font-weight: 600;
      letter-spacing: 0.06em;
      user-select: none;
      position: relative;
      z-index: 10;
    }

    header {
      font-size: 1.1rem;
      margin-bottom: 20px;
      text-shadow: 0 2px 10px rgba(0, 123, 255, 0.3);
      backdrop-filter: blur(5px);
      background: rgba(20, 30, 48, 0.5);
      border-radius: 15px;
      padding: 15px;
      border: 1px solid rgba(0, 123, 255, 0.2);
    }

    footer {
      position: fixed;
      bottom: 0;
      background: linear-gradient(to top, rgba(10, 15, 26, 0.95), transparent);
      border-top: 1px solid rgba(0, 123, 255, 0.2);
      font-size: 0.9rem;
      opacity: 0.8;
      z-index: 100;
      width: 100%;
      max-width: 100%;
      padding: 15px 0;
      backdrop-filter: blur(5px);
      color: var(--primary-light);
    }

    /* Diálogo con efecto cristal en tonos azules */
    main[role="dialog"] {
      position: relative;
      width: 100%;
      max-width: 550px;
      background: rgba(20, 30, 48, 0.25);
      backdrop-filter: blur(25px) saturate(180%);
      -webkit-backdrop-filter: blur(25px) saturate(180%);
      border-radius: 24px;
      padding: 40px 35px;
      border: 1px solid rgba(0, 123, 255, 0.15);
      box-shadow: 
          0 25px 50px rgba(0, 0, 0, 0.3),
          0 0 0 1px rgba(0, 123, 255, 0.1),
          0 0 30px rgba(0, 123, 255, 0.1),
          inset 0 0 30px rgba(0, 123, 255, 0.05);
      color: var(--text-light);
      text-align: center;
      z-index: 10;
      overflow: hidden;
      animation: fadeInUp 0.8s ease-out;
    }

    /* Efecto de borde brillante azul */
    main[role="dialog"]::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border-radius: 24px;
      padding: 2px;
      background: linear-gradient(135deg, 
          rgba(0, 123, 255, 0.3), 
          rgba(0, 123, 255, 0.1), 
          rgba(0, 123, 255, 0.3));
      -webkit-mask: 
          linear-gradient(#fff 0 0) content-box, 
          linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      pointer-events: none;
    }

    /* Título */
    .modal-title {
      font-weight: 700;
      font-size: 2rem;
      color: var(--primary);
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 25px;
      line-height: 1.3;
      text-shadow: 0 2px 10px rgba(0, 123, 255, 0.3);
      position: relative;
      padding-bottom: 15px;
    }

    .modal-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 25%;
      width: 50%;
      height: 3px;
      background: linear-gradient(90deg, 
          transparent, 
          rgba(0, 123, 255, 0.8), 
          transparent);
      border-radius: 3px;
    }

    .user-name {
      color: var(--primary-light);
      font-weight: 800;
      text-shadow: 0 2px 8px rgba(0, 123, 255, 0.4);
      animation: pulse 2s infinite;
    }

    .wave-emoji {
      font-size: 2rem;
      display: inline-block;
      animation: wave 2s infinite;
      transform-origin: 70% 70%;
    }

    /* Texto */
    .modal-text {
      font-size: 1.2rem;
      color: var(--text-muted);
      line-height: 1.6;
      margin-bottom: 30px;
      padding: 0 10px;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
      animation: fadeIn 1s ease-out 0.3s both;
    }

    /* Fila de iconos/emojis */
    .icons-row {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin: 35px 0;
      animation: fadeIn 1s ease-out 0.6s both;
    }

    .icons-row span {
      font-size: 3rem;
      display: inline-block;
      cursor: default;
      transition: all 0.3s ease;
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
      position: relative;
    }

    .icons-row span:hover {
      transform: translateY(-8px) scale(1.2);
      filter: drop-shadow(0 8px 16px rgba(0, 123, 255, 0.3));
    }

    .icons-row span::after {
      content: attr(title);
      position: absolute;
      bottom: -30px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.8rem;
      background: rgba(0, 0, 0, 0.85);
      color: var(--primary);
      padding: 6px 12px;
      border-radius: 8px;
      opacity: 0;
      transition: opacity 0.3s;
      white-space: nowrap;
      border: 1px solid rgba(0, 123, 255, 0.3);
      font-weight: 600;
    }

    .icons-row span:hover::after {
      opacity: 1;
    }

    /* Contenedor de botones */
    .buttons-container {
      display: flex;
      flex-direction: column;
      gap: 15px;
      align-items: center;
      margin-top: 20px;
    }

    /* Botones */
    button {
      border: none;
      border-radius: 12px;
      padding: 18px 30px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      position: relative;
      overflow: hidden;
      letter-spacing: 0.5px;
      width: 100%;
      max-width: 280px;
      animation: fadeInUp 0.8s ease-out 0.9s both;
      z-index: 11;
    }

    .btn-login {
      background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(0, 123, 255, 0.05) 100%);
      color: var(--primary);
      border: 2px solid var(--primary);
      box-shadow: 0 8px 20px rgba(0, 123, 255, 0.2);
    }

    .btn-logout {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      box-shadow: 0 8px 20px rgba(0, 123, 255, 0.4);
    }

    button i {
      font-size: 1.2rem;
    }

    /* Efectos hover */
    .btn-login:hover {
      transform: translateY(-3px);
      background: linear-gradient(135deg, rgba(0, 123, 255, 0.2) 0%, rgba(0, 123, 255, 0.1) 100%);
      box-shadow: 0 12px 25px rgba(0, 123, 255, 0.3);
      color: var(--primary-light);
    }

    .btn-logout:hover {
      transform: translateY(-3px);
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      box-shadow: 0 12px 25px rgba(0, 123, 255, 0.5);
    }

    /* Efecto pulse para el botón de entrar */
    .pulse-glow {
      animation: pulseGlow 2s infinite;
    }

    /* Efecto de brillo en hover */
    button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, 
          transparent, 
          rgba(255, 255, 255, 0.15), 
          transparent);
      transition: left 0.7s;
    }

    button:hover::before {
      left: 100%;
    }

    /* Botón secundario flotante */
    .btn-back-to-top {
      position: fixed;
      bottom: 80px;
      right: 20px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      border: none;
      border-radius: 50%;
      width: 56px;
      height: 56px;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 101;
      opacity: 0.9;
      transition: all 0.3s ease;
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.4);
      animation: bounceIn 1s ease-out 1s both;
    }

    .btn-back-to-top:hover {
      opacity: 1;
      transform: translateY(-5px) scale(1.1);
      box-shadow: 0 10px 25px rgba(0, 123, 255, 0.6);
    }

    /* Animaciones */
    @keyframes gradientBG {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    @keyframes subtleMove {
      0% { background-position: 0 0; }
      100% { background-position: 180px 180px; }
    }

    @keyframes floatParticle {
      0% {
        transform: translateY(100vh) translateX(var(--start-x));
        opacity: 0;
      }
      10% {
        opacity: 1;
      }
      90% {
        opacity: 1;
      }
      100% {
        transform: translateY(-100px) translateX(var(--end-x));
        opacity: 0;
      }
    }

    @keyframes twinkle {
      0%, 100% { opacity: 0.3; }
      50% { opacity: 1; }
    }

    @keyframes wave {
      0% { transform: rotate(0deg); }
      10% { transform: rotate(14deg); }
      20% { transform: rotate(-8deg); }
      30% { transform: rotate(14deg); }
      40% { transform: rotate(-4deg); }
      50% { transform: rotate(10deg); }
      60% { transform: rotate(0deg); }
      100% { transform: rotate(0deg); }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes bounceIn {
      0% {
        opacity: 0;
        transform: scale(0.3);
      }
      50% {
        opacity: 1;
        transform: scale(1.05);
      }
      70% {
        transform: scale(0.9);
      }
      100% {
        transform: scale(1);
      }
    }

    @keyframes pulse {
      0%, 100% {
        opacity: 1;
        text-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
      }
      50% {
        opacity: 0.9;
        text-shadow: 0 0 20px rgba(0, 123, 255, 0.5);
      }
    }

    @keyframes pulseGlow {
      0%, 100% { 
        box-shadow: 0 8px 20px rgba(0, 123, 255, 0.2);
      }
      50% { 
        box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4), 0 0 15px rgba(0, 123, 255, 0.3);
      }
    }

    /* Responsive */
    @media (max-width: 600px) {
      body {
        padding: 15px;
      }
      
      body::before {
        background-size: 120px 120px;
        animation: subtleMove 60s linear infinite;
      }
      
      main[role="dialog"] {
        padding: 30px 20px;
        margin: 15px;
      }
      
      .modal-title {
        font-size: 1.6rem;
        flex-direction: column;
        gap: 10px;
      }
      
      .modal-text {
        font-size: 1.05rem;
      }
      
      .icons-row {
        gap: 20px;
        margin: 25px 0;
      }
      
      .icons-row span {
        font-size: 2.5rem;
      }
      
      button {
        padding: 16px 20px;
        font-size: 1rem;
        max-width: 100%;
      }
      
      .btn-back-to-top {
        width: 50px;
        height: 50px;
        bottom: 70px;
        right: 15px;
      }
    }

    /* Estilos para SweetAlert personalizado */
    .swal2-custom-popup {
      background: rgba(20, 30, 48, 0.95) !important;
      backdrop-filter: blur(20px) !important;
      border: 1px solid rgba(0, 123, 255, 0.3) !important;
      border-radius: 20px !important;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4) !important;
    }
    
    .swal2-custom-title {
      color: var(--primary) !important;
      font-size: 1.5rem !important;
      text-shadow: 0 2px 10px rgba(0, 123, 255, 0.3) !important;
    }
    
    .swal2-custom-html {
      color: var(--text-light) !important;
    }
    
    .btn-confirm-custom, .btn-cancel-custom {
      padding: 12px 30px !important;
      font-size: 1rem !important;
      font-weight: 600 !important;
      border-radius: 10px !important;
      transition: all 0.3s ease !important;
    }
    
    .btn-confirm-custom {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
      border: none !important;
      color: white !important;
    }
    
    .btn-confirm-custom:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 20px rgba(0, 123, 255, 0.4) !important;
    }
    
    .btn-cancel-custom:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 20px rgba(102, 102, 102, 0.4) !important;
    }
  </style>
</head>
<body>

  <!-- Contenedor de partículas -->
  <div class="particles" id="particles-container"></div>

  <header>
    <img src="assets/logov.png" alt="Logo PDL Visiones" WIDTH="50" height="50">SISFACT - Sistema de Facturación PDL Visiones
  </header>

  <main role="dialog" aria-modal="true" aria-labelledby="logoutModalLabel">
    <!-- Mensaje personalizado con nombre completo y username -->
    <div class="modal-title" id="logoutModalLabel">
      ¡Hasta luego, <span class="user-name"><?php echo $nombre_completo; ?></span>! 
      <span class="wave-emoji">👋</span>
    </div>
    
    <p class="modal-text">
      Gracias <span class="user-name"><?php echo $nombre_corto_con_usuario; ?></span> 
      por confiar en nuestro sistema.<br/>
      Tu seguridad es nuestra prioridad.<br/>
      ¡Esperamos verte pronto de nuevo!
    </p>
    
    <div class="icons-row" aria-hidden="true">
      <!-- Reemplazar todos los íconos con emojis -->
      <span title="Satisfacción">😊</span>
      <span title="Innovación">🚀</span>
      <span title="Seguridad">🛡️</span>
    </div>
    
    <div class="buttons-container">
      <!-- Botón VOLVER A ENTRAR -->
      <button class="btn-login pulse-glow" id="loginAgainBtn">
        <i class="fas fa-sign-in-alt"></i> Volver a entrar
      </button>
      
      <!-- Botón PRINCIPAL con icono HOME -->
      <button class="btn-logout" id="sweetLogoutBtn">
        <i class="fas fa-home"></i> Ir al inicio
      </button>
    </div>
  </main>

  <footer>
    &copy; <?php echo date('Y'); ?> SISFACT PDL Visiones • 2.2.3 • Todos los derechos reservados.
  </footer>

  <!-- Botón SECUNDARIO con icono HOME (alternativo) -->
  <button class="btn-back-to-top" id="sweetLogoutBtn2">
    <i class="fas fa-home"></i>
  </button>

  <!-- SweetAlert2 JS -->
  <script src="js/sweetalert211.js"></script>
  
  <script>
    // FUNCIÓN PARA CREAR PARTÍCULAS MEJORADAS
    function createParticles() {
      const container = document.getElementById('particles-container');
      if (!container) return;
      
      const particleCounts = {
        small: 40,
        medium: 20,
        large: 8,
        twinkle: 15
      };
      
      const particleTypes = ['small', 'medium', 'large', 'twinkle'];
      
      particleTypes.forEach(type => {
        for (let i = 0; i < particleCounts[type]; i++) {
          const particle = document.createElement('div');
          particle.className = `particle ${type}`;
          
          // Posición inicial aleatoria
          const startX = Math.random() * 100;
          const size = parseInt(getComputedStyle(particle).width);
          
          // Configurar propiedades de animación
          particle.style.left = `${startX}%`;
          particle.style.top = `${Math.random() * 100}%`;
          
          // Valores aleatorios para animación
          const duration = 15 + Math.random() * 25;
          const delay = Math.random() * 5;
          const startXVar = Math.random() * 100 - 50;
          const endXVar = Math.random() * 100 - 50;
          
          particle.style.setProperty('--start-x', `${startXVar}px`);
          particle.style.setProperty('--end-x', `${endXVar}px`);
          particle.style.animationDuration = `${duration}s`;
          particle.style.animationDelay = `${delay}s`;
          
          container.appendChild(particle);
        }
      });
    }
    
    // FUNCIÓN PARA MANEJAR EL BOTÓN "IR AL INICIO" (REUTILIZABLE)
    function setupLogoutButton(buttonElement) {
      if (buttonElement) {
        buttonElement.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Cerrar toast de despedida si está abierto
          if (typeof closeGoodbyeToast === 'function') {
            closeGoodbyeToast();
          }
          
          Swal.fire({
            title: '¿Seguro que quieres salir?',
            html: `
              <div style="text-align: center; padding: 20px;">
                <div style="font-size: 4rem; animation: wave 2s infinite; color: #007bff;">👋</div>
                <p style="margin-top: 20px; color: #007bff; font-weight: bold;">
                  ¡Gracias por usar nuestro sistema, 
                  <span style="color: #a0c8ff;"><?php echo $nombre_corto_con_usuario; ?></span>!
                </p>
                <p style="color: #aaa; font-size: 0.9rem;">
                  Tu sesión será cerrada completamente
                </p>
                <style>
                  @keyframes wave {
                    0%, 100% { transform: rotate(0deg); }
                    20% { transform: rotate(15deg); }
                    40% { transform: rotate(-10deg); }
                    60% { transform: rotate(10deg); }
                    80% { transform: rotate(-5deg); }
                  }
                </style>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-home"></i> Sí, salir',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#666',
            background: 'rgba(20, 30, 48, 0.95)',
            backdrop: 'rgba(0,0,0,0.8)',
            color: '#e0e7ff',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showClass: {
              popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
              popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
              popup: 'swal2-custom-popup',
              title: 'swal2-custom-title',
              htmlContainer: 'swal2-custom-html',
              confirmButton: 'btn-confirm-custom',
              cancelButton: 'btn-cancel-custom'
            }
          }).then((result) => {
            if (result.isConfirmed) {
              // Mostrar mensaje de despedida personalizado
              Swal.fire({
                title: '¡Hasta pronto, <?php echo $nombre_corto_con_usuario; ?>!',
                html: `
                  <div style="text-align: center; padding: 10px;">
                    <div style="font-size: 3rem; animation: takeOff 1.5s ease-out forwards; color: #007bff;">🚀</div>
                    <p style="margin-top: 15px; color: #007bff; font-weight: bold;">
                      Cerrando sesión y redirigiendo...
                    </p>
                    <div style="
                      width: 80%;
                      height: 4px;
                      background: rgba(0, 123, 255, 0.2);
                      border-radius: 2px;
                      margin: 15px auto 0 auto;
                      overflow: hidden;
                    ">
                      <div style="
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(90deg, transparent, #007bff, transparent);
                        border-radius: 2px;
                        animation: loadingBar 2s linear infinite;
                      "></div>
                    </div>
                    <style>
                      @keyframes takeOff {
                        0% { transform: translateY(0) scale(1); opacity: 1; }
                        100% { transform: translateY(-50px) scale(0.8); opacity: 0; }
                      }
                      @keyframes loadingBar {
                        0% { transform: translateX(-100%); }
                        100% { transform: translateX(100%); }
                      }
                    </style>
                  </div>
                `,
                timer: 2000,
                timerProgressBar: false,
                showConfirmButton: false,
                background: 'rgba(20, 30, 48, 0.95)',
                color: '#e0e7ff',
                showClass: {
                  popup: 'animate__animated animate__zoomIn'
                },
                hideClass: {
                  popup: 'animate__animated animate__zoomOut'
                },
                willClose: () => {
                  // Limpiar almacenamiento del navegador
                  clearBrowserStorage();
                  
                  // Redirigir al script de logout_process.php para limpiar la sesión PHP
                  window.location.href = 'index.php';
                }
              });
            }
          });
        });
      }
    }
    
    // FUNCIÓN PARA LIMPIAR ALMACENAMIENTO DEL NAVEGADOR
    function clearBrowserStorage() {
      console.log('Limpiando almacenamiento del navegador...');
      
      // Limpiar localStorage
      try {
        localStorage.clear();
        console.log('localStorage limpiado');
      } catch (e) {
        console.error('Error limpiando localStorage:', e);
      }
      
      // Limpiar sessionStorage
      try {
        sessionStorage.clear();
        console.log('sessionStorage limpiado');
      } catch (e) {
        console.error('Error limpiando sessionStorage:', e);
      }
      
      // Limpiar cookies
      function deleteCookie(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
      }
      
      // Eliminar cookies comunes de sesión
      deleteCookie('PHPSESSID');
      deleteCookie('session_token');
      deleteCookie('user_session');
      
      console.log('Limpieza completada');
    }
    
    // Configurar SweetAlert
    document.addEventListener('DOMContentLoaded', function() {
      const logoutBtn = document.getElementById('sweetLogoutBtn');
      const logoutBtn2 = document.getElementById('sweetLogoutBtn2');
      const loginBtn = document.getElementById('loginAgainBtn');
      
      // Variable para controlar el toast
      let goodbyeToast = null;
      
      // Función para cerrar el toast con animación
      window.closeGoodbyeToast = function() {
        if (goodbyeToast && typeof goodbyeToast.close === 'function') {
          goodbyeToast.close();
        }
      };
      
      // Crear partículas
      createParticles();
      
      // Configurar AMBOS botones de logout
      setupLogoutButton(logoutBtn);
      setupLogoutButton(logoutBtn2);
      
<!-- En la sección de scripts JavaScript, modifica la parte del toast (busca "MOSTRAR TOAST DE DESPEDIDA"): -->

// MOSTRAR TOAST DE DESPEDIDA PERSONALIZADO CON NOMBRE DE USUARIO
setTimeout(() => {
  goodbyeToast = Swal.fire({
    toast: true,
    position: 'top',
    icon: 'success',
    title: 'Sesión cerrada exitosamente',
    text: '¡Hasta pronto, <?php echo $nombre_corto_con_usuario; ?>!',
    showConfirmButton: false,
    timer: 7000,
    timerProgressBar: true,
    background: 'rgba(20, 30, 48, 0.95)',
    // backdrop: 'rgba(0,0,0,0.4)', 
    backdrop: false, 
    color: '#007bff',
    iconColor: '#007bff',
    width: '420px',
    padding: '1rem',
    showClass: {
      popup: 'animate__animated animate__fadeInRight'
    },
    hideClass: {
      popup: 'animate__animated animate__fadeOutRight'
    },
    customClass: {
      popup: 'swal2-custom-popup'
    },
    didOpen: (toast) => {
      const timerProgressBar = toast.querySelector('.swal2-timer-progress-bar');
      if (timerProgressBar) {
        timerProgressBar.style.background = '#007bff';
      }
    }
  });
}, 800);
      
      // BOTÓN VOLVER A ENTRAR
      if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Cerrar toast de despedida si está abierto
          closeGoodbyeToast();
          
          Swal.fire({
            title: '¿Volver a entrar, <?php echo $nombre_corto_con_usuario; ?>?',
            html: `
              <div style="text-align: center; padding: 20px;">
                <div style="font-size: 4rem; color: #007bff; animation: bounce 1s infinite;">🔐</div>
                <p style="margin-top: 20px; color: #007bff; font-weight: bold;">
                  ¿Deseas iniciar sesión nuevamente, 
                  <span style="color: #a0c8ff;"><?php echo $nombre_corto_con_usuario; ?></span>?
                </p>
                <p style="color: #aaa; font-size: 0.9rem;">
                  Serás redirigido a la página de login
                </p>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-sign-in-alt"></i> Sí, entrar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#666',
            background: 'rgba(20, 30, 48, 0.95)',
            color: '#e0e7ff',
            backdrop: 'rgba(0,0,0,0.8)',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showClass: {
              popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
              popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
              popup: 'swal2-custom-popup',
              title: 'swal2-custom-title',
              htmlContainer: 'swal2-custom-html',
              confirmButton: 'btn-confirm-custom',
              cancelButton: 'btn-cancel-custom'
            }
          }).then((result) => {
            if (result.isConfirmed) {
              // Mostrar animación de carga personalizada
              Swal.fire({
                title: 'Redirigiendo...',
                html: `
                  <div style="text-align: center; padding: 10px;">
                    <div style="
                      width: 3rem;
                      height: 3rem;
                      border: 0.25em solid #007bff;
                      border-right-color: transparent;
                      border-radius: 50%;
                      animation: spin 1s linear infinite;
                      margin: 0 auto 15px auto;
                    "></div>
                    <p style="color: #007bff; font-weight: bold;">
                      Preparando tu sesión, 
                      <span style="color: #a0c8ff;"><?php echo $nombre_corto_con_usuario; ?></span>...
                    </p>
                    <style>
                      @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                      }
                    </style>
                  </div>
                `,
                timer: 2500,
                timerProgressBar: true,
                showConfirmButton: false,
                background: 'rgba(20, 30, 48, 0.95)',
                color: '#e0e7ff',
                showClass: {
                  popup: 'animate__animated animate__zoomIn'
                },
                hideClass: {
                  popup: 'animate__animated animate__zoomOut'
                },
                willClose: () => {
                  // Limpiar almacenamiento antes de redirigir
                  clearBrowserStorage();
                  
                  // Redirigir a login.php
                  window.location.href = 'login.php?t=' + Date.now();
                }
              });
            }
          });
        });
      }
      
      // Limpiar almacenamiento al cargar la página
      clearBrowserStorage();
      
      // Prevenir navegación atrás
      history.pushState(null, null, location.href);
      window.onpopstate = function() {
        history.go(1);
      };
    });
  </script>
</body>
</html>	