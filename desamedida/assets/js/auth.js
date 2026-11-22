/**
 * js/auth.js
 * Gestión de la autenticación y estado de sesión en el frontend.
 * Mantiene el token en memoria/sessionStorage y datos básicos en localStorage.
 */

const Auth = (function() {
  
  // ============================================================
  // ESTADO INTERNO
  // ============================================================
  
  // Token en memoria (se pierde al refrescar, pero se recupera del sessionStorage)
  let _token = null;
  // Usuario actual en memoria
  let _user = null;
  
  // Callbacks para notificar cambios de estado
  const _listeners = [];

  // Promesa memoizada de init(): varios scripts (app.js, page-guards,
  // purchase-wizard, artifice-panel) llaman init() en la misma carga de página;
  // sin esto cada uno dispara su propio /api/auth/me duplicado.
  let _initPromise = null;
  
  // Clave para persistir info básica en localStorage
  const SESSION_DATA_KEY = 'cyberforja_session_info';

  // ============================================================
  // FUNCIONES PRIVADAS
  // ============================================================

  function _notify() {
    _listeners.forEach(cb => {
      try { cb(_user); } catch(e) { console.error('Auth Listener Error:', e); }
    });
  }

  function _saveLocal(token, user) {
    _token = token;
    _user = user;
    
    // Guardar token en sessionStorage
    sessionStorage.setItem('cyberforja_at', token);
    
    // Guardar info mínima en localStorage para persistencia UI.
    // is_partner se normaliza a booleano: el login lo entrega como boolean
    // pero /auth/me lo trae como 0/1 de SQLite.
    localStorage.setItem(SESSION_DATA_KEY, JSON.stringify({
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      is_partner: user.is_partner === true || user.is_partner === 1,
      lastSeen: Date.now()
    }));
    
    _notify();
  }

  // ============================================================
  // API PÚBLICA
  // ============================================================

  return {
    
    /**
     * Inicializa la autenticación recuperando la sesión guardada.
     * Memoizada: llamadas concurrentes comparten una única verificación.
     */
    init() {
      if (_initPromise) return _initPromise;
      _initPromise = this._doInit();
      return _initPromise;
    },

    async _doInit() {
      // Intentar recuperar token: sessionStorage primero, luego localStorage fallback
      let savedToken = sessionStorage.getItem('cyberforja_at');
      const savedInfo = localStorage.getItem(SESSION_DATA_KEY);

      if (savedToken && savedInfo) {
        _token = savedToken;
        try {
          _user = JSON.parse(savedInfo);
          _notify();
          
          // Verificar validez real del token contra el backend
          try {
            const response = await window.Api.auth.me();
            if (response.success) {
              _user = response.data.user;
              _saveLocal(_token, _user);
              return true;
            }
          } catch (e) {
            // Error de red o servidor — mantener sesión cached
          }
          
          // Si llegamos aquí, la verificación falló pero mantenemos la sesión cached
          // Se validará en la próxima llamada real a la API
          return _token !== null;
        } catch (e) {
          this.clearSession();
          return false;
        }
      }
      return false;
    },

    /**
     * Proceso de Login.
     */
    async login(email, password) {
      const response = await window.Api.auth.login(email, password);
      if (response.success) {
        const user = response.data.user;
        
        // Interceptar usuario no verificado ANTES de crear la sesión local
        if (user.email_verified === false || user.email_verified === 0) {
            return { success: false, unverified: true, email: user.email };
        }
        
        // Usuario verificado, creamos la sesión local
        _saveLocal(response.data.token, user);
        // Invalidar la memoización: un init() posterior debe verificar la sesión nueva
        _initPromise = null;
        return { success: true };
      }
      return { success: false, error: response.error };
    },

    /**
     * Proceso de Registro.
     */
    async register(name, email, password) {
      const response = await window.Api.auth.register(name, email, password);
      if (response.success) {
        // No creamos la sesión automáticamente porque el email no está verificado aún
        return { success: true, email: email };
      }
      return { success: false, error: response.error };
    },

    /**
     * Cierre de sesión (remoto y local).
     */
    async logout() {
      try {
        await window.Api.auth.logout();
      } catch (e) { /* ignorar fallo de red en logout */ }
      this.clearSession();
      window.location.href = '/';
    },

    /**
     * Limpia todo rastro de sesión local.
     */
    clearSession() {
      _token = null;
      _user = null;
      _initPromise = null;
      sessionStorage.removeItem('cyberforja_at');
      localStorage.removeItem(SESSION_DATA_KEY);
      _notify();
    },

    /**
     * Refresca la sesión cacheada con los datos que devuelve una acción
     * (p. ej. el PUT de perfil), sin volver a pedir /api/auth/me.
     */
    updateUser(cambios) {
      if (!_user || !cambios) return;
      _user = { ..._user, ...cambios };
      _saveLocal(_token, _user);
    },

    // Getters básicos
    getToken: () => _token,
    getUser: () => _user,
    isAuthenticated: () => _token !== null && _user !== null,
    isAdmin: () => _user && _user.role === 'admin',

    /**
     * Suscribirse a cambios en el estado de autenticación.
     */
    onAuthChange: (cb) => {
      if (typeof cb === 'function') _listeners.push(cb);
    },

    /**
     * Bloqueo de ruta si no está autenticado.
     */
    requireAuth() {
      if (!this.isAuthenticated()) {
        const currentPath = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `/login?redirect=${currentPath}`;
        return false;
      }
      return true;
    },

    /**
     * Bloqueo de ruta si no es administrador.
     */
    requireAdmin() {
      if (!this.requireAuth()) return false;
      if (!this.isAdmin()) {
        window.location.href = '/pages/account/dashboard.html';
        return false;
      }
      return true;
    }
  };

})();

// Exponer globalmente
window.Auth = Auth;
