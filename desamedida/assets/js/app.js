/**
 * js/app.js
 * Inicialización global y lógica compartida de la interfaz de CyberForja.
 */

/**
 * Escapa HTML para insertar de forma segura texto no confiable dentro de
 * plantillas que se asignan a innerHTML. Usar SIEMPRE que se interpole un
 * valor que puede venir de un usuario (nombre, email, asunto, mensaje, ID de
 * transacción, etc.) — cualquier dato que un visitante o cliente controle,
 * incluso sin haber iniciado sesión (p. ej. el formulario de contacto o la
 * lista de espera son públicos).
 */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
window.escapeHtml = escapeHtml;

document.addEventListener('DOMContentLoaded', async function () {

  // 1. Inicializar autenticación (recuperar sesión si existe)
  if (window.Auth) {
    // Suscribirse y pintar ANTES de esperar la verificación de red: init()
    // notifica primero con la sesión cacheada en localStorage, así el header
    // aparece al instante y se re-sincroniza cuando responde /api/auth/me.
    window.Auth.onAuthChange(updateAuthUI);
    updateAuthUI();

    await window.Auth.init();
    updateAuthUI();
  }

  // 2. Manejo de redirección automática por expiración de sesión
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('expired')) {
    showToast('Tu sesión ha expirado. Por favor, inicia sesión de nuevo.', 'warning');
  }

});

/**
 * Actualiza los elementos de la interfaz que dependen del estado del usuario.
 */
function updateAuthUI() {
  if (!window.Auth) return;

  const user = window.Auth.getUser();
  const isAuth = window.Auth.isAuthenticated();
  const isAdmin = window.Auth.isAdmin();

  // Elementos con data-auth (usar querySelectorAll para mayor flexibilidad)
  const guestOnly = document.querySelectorAll('[data-auth="guest-only"]');
  const userMenu = document.querySelectorAll('[data-auth="user-menu"]');
  const userNames = document.querySelectorAll('[data-auth="user-name"]');
  const adminLinks = document.querySelectorAll('[data-auth="admin-link"]');
  const logoutBtns = document.querySelectorAll('[data-auth="logout-btn"]');
  const loginBtns = document.querySelectorAll('[data-auth="login-btn"]');
  const registerBtns = document.querySelectorAll('[data-auth="register-btn"]');
  const panelLinks = document.querySelectorAll('[data-auth="panel-link"]');

  if (isAuth && user) {
    // Usuario autenticado
    guestOnly.forEach(el => el.classList.add('hidden'));
    loginBtns.forEach(el => el.classList.add('hidden'));
    registerBtns.forEach(el => el.classList.add('hidden'));

    userMenu.forEach(el => el.classList.remove('hidden'));
    userNames.forEach(el => el.textContent = user.name || 'Usuario');

    // Mostrar/ocultar link de admin
    adminLinks.forEach(el => {
      if (isAdmin) {
        el.classList.remove('hidden');
      } else {
        el.classList.add('hidden');
      }
    });

    // Ajustar links del Panel según el rol: admin → /admin, partner → su
    // Consola del Artífice, cliente normal → dashboard de cuenta.
    // (is_partner llega como boolean del login o como 0/1 de /auth/me.)
    const esPartner = user.is_partner === true || user.is_partner === 1;
    panelLinks.forEach(link => {
      link.href = isAdmin ? '/admin' : (esPartner ? '/artifice/' : '/pages/account/dashboard.html');
    });

    // Configurar Logout
    logoutBtns.forEach(btn => {
      if (!btn.hasAttribute('data-auth-bound')) {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();
          await window.Auth.logout();
        });
        btn.setAttribute('data-auth-bound', 'true');
      }
    });
  } else {
    // Usuario anónimo
    guestOnly.forEach(el => el.classList.remove('hidden'));
    loginBtns.forEach(el => el.classList.remove('hidden'));
    registerBtns.forEach(el => el.classList.remove('hidden'));

    userMenu.forEach(el => el.classList.add('hidden'));
    adminLinks.forEach(el => el.classList.add('hidden'));
  }

  // Inicializar dropdown del usuario si existe
  initUserDropdown();

  // Inicializar menú móvil (hamburguesa) si existe
  initMobileMenu();
}

/**
 * Gestiona el menú móvil desplegable (hamburguesa) en la navegación.
 */
function initMobileMenu() {
  const btn = document.getElementById('mobile-menu-btn');
  const menu = document.getElementById('mobile-menu');

  if (!btn || !menu) return;
  if (btn.hasAttribute('data-mobile-bound')) return;

  const closeMenu = () => {
    menu.classList.add('hidden');
    btn.setAttribute('aria-expanded', 'false');
    const icon = btn.querySelector('i');
    if (icon) icon.className = 'fas fa-bars text-lg';
  };

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const willOpen = menu.classList.contains('hidden');
    menu.classList.toggle('hidden');
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    const icon = btn.querySelector('i');
    if (icon) icon.className = willOpen ? 'fas fa-xmark text-lg' : 'fas fa-bars text-lg';
  });

  // Cerrar al pulsar un enlace del menú
  menu.querySelectorAll('.mobile-link').forEach(link => {
    link.addEventListener('click', closeMenu);
  });

  btn.setAttribute('data-mobile-bound', 'true');
}

/**
 * Gestiona el comportamiento del menú desplegable del usuario.
 */
function initUserDropdown() {
  const btn = document.getElementById('user-menu-btn');
  const dropdown = document.getElementById('user-dropdown');

  if (!btn || !dropdown) return;

  // Evitar múltiples listeners si se llama varias veces
  if (btn.hasAttribute('data-dropdown-bound')) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('hidden');
    // Animación opcional si se desea
    if (!dropdown.classList.contains('hidden')) {
      dropdown.classList.add('animate-fade-in-up');
    }
  });

  // Cerrar al hacer click fuera
  document.addEventListener('click', () => {
    dropdown.classList.add('hidden');
  });

  btn.setAttribute('data-dropdown-bound', 'true');
}

/**
 * Muestra una notificación visual (Toast) al usuario.
 * @param {string} message - El texto a mostrar.
 * @param {string} type - 'success', 'error', 'warning', 'info' (por defecto 'info').
 */
function showToast(message, type = 'info') {
  const container = document.getElementById('toast-container') || createToastContainer();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type} flex items-center p-4 mb-4 text-white rounded-lg shadow-lg border border-white/10 glass-morphism transition-all duration-300 transform translate-x-full opacity-0`;

  // Iconos según el tipo (usando FontAwesome si está disponible)
  const icons = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
  };

  toast.innerHTML = `
    <i class="fa-solid ${icons[type]} mr-3 text-lg"></i>
    <div class="text-sm font-medium">${message}</div>
    <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-white/50 hover:text-white rounded-lg p-1.5 inline-flex h-8 w-8" onclick="this.parentElement.remove()">
      <i class="fa-solid fa-xmark"></i>
    </button>
  `;

  container.appendChild(toast);

  // Trigger animación de entrada
  setTimeout(() => {
    toast.classList.remove('translate-x-full', 'opacity-0');
  }, 10);

  // Auto-eliminar después de 4 segundos
  setTimeout(() => {
    toast.classList.add('opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function createToastContainer() {
  const container = document.createElement('div');
  container.id = 'toast-container';
  container.className = 'fixed bottom-5 right-5 z-[9999] flex flex-col items-end pointer-events-none';
  // Permitir clics en los toasts dentro del container
  container.style.cssText = 'pointer-events: none;';
  document.body.appendChild(container);

  // Inyectar estilos CSS para el container
  const style = document.createElement('style');
  style.textContent = `
    #toast-container .toast { pointer-events: auto; }
    .glass-morphism { backdrop-filter: blur(8px); background: rgba(30, 41, 59, 0.8); }
    .toast-success { border-left: 4px solid #10B981; }
    .toast-error { border-left: 4px solid #EF4444; }
    .toast-warning { border-left: 4px solid #F59E0B; }
    .toast-info { border-left: 4px solid #3B82F6; }
  `;
  document.head.appendChild(style);

  return container;
}

window.showToast = showToast;
window.updateAuthUI = updateAuthUI;

/**
 * Alterna la visibilidad de un campo de contraseña.
 * @param {string} inputId - El ID del input a alternar.
 * @param {HTMLElement} btn - El botón que disparó la acción.
 */
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

window.togglePassword = togglePassword;
