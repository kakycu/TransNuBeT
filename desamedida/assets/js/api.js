/**
 * js/api.js
 * Cliente API centralizado para la comunicación con el backend de CyberForja.
 * Todas las peticiones al servidor deben pasar por este módulo.
 */

const API_BASE = '/api';

// Configuración global
const API_CONFIG = {
  timeout: 15000, // 15 segundos
  retries: 1
};

/**
 * Función base para realizar peticiones de red a la API.
 * @param {string} endpoint - El path del endpoint (ej: '/auth/login').
 * @param {object} options - Opciones de fetch (method, body, headers, etc).
 */
async function apiRequest(endpoint, options = {}) {
  const url = `${API_BASE}${endpoint}`;
  
  // Headers por defecto
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers
  };
  
  // Agregar token de autorización si está disponible en window.Auth
  if (window.Auth) {
    const token = window.Auth.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
  }
  
  // Configurar AbortController para el timeout
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);
  
  try {
    const response = await fetch(url, {
      ...options,
      headers,
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);
    
    // Parsear respuesta JSON
    const data = await response.json();
    
    // Manejar errores de respuesta HTTP
    if (!response.ok) {
      // Si el token expiró o es inválido (401), limpiar sesión
      if (response.status === 401 && window.Auth) {
        window.Auth.clearSession();
        // Redirigir a login si no estamos ya allí (soportamos URLs limpias sin .html)
        const currentPath = window.location.pathname;
        if (!currentPath.includes('/login')) {
          const redirectPath = encodeURIComponent(currentPath + window.location.search);
          window.location.href = `/login?expired=1&redirect=${redirectPath}`;
        }
      }
      
      return {
        success: false,
        error: data.error || data.message || `Error ${response.status}`,
        status: response.status,
        // Código de negocio opcional que envía el handler (p. ej. 'pendiente_aprobacion')
        // para que la UI distinga el caso sin depender del texto.
        code: data.code || null
      };
    }
    
    return data;
    
  } catch (err) {
    clearTimeout(timeoutId);
    
    if (err.name === 'AbortError') {
      return { success: false, error: 'La solicitud tardó demasiado. Intenta de nuevo.', status: 0 };
    }
    
    console.error('API Request Error:', err);
    return { success: false, error: 'Error de conexión con el servidor.', status: 0 };
  }
}

// ============================================================
// MÓDULOS ESPECÍFICOS DE LA API
// ============================================================

const ApiAuth = {
  register: (name, email, password) => apiRequest('/auth/register', {
    method: 'POST',
    body: JSON.stringify({ name, email, password })
  }),
  login: (email, password) => apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password })
  }),
  logout: () => apiRequest('/auth/logout', { method: 'POST' }),
  me: () => apiRequest('/auth/me'),
  forgotPassword: (email) => apiRequest('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email })
  }),
  resetPassword: (token, newPassword) => apiRequest('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({ token, newPassword })
  })
};

const ApiProducts = {
  getAll: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(query ? `/products?${query}` : '/products');
  },
  getBySlug: (slug) => apiRequest(`/products?slug=${encodeURIComponent(slug)}`),
  getPrices: () => apiRequest('/products/prices'),
  admin: {
    getAll: () => apiRequest('/admin/products'),
    create: (data) => apiRequest('/admin/products', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data) => apiRequest(`/admin/products?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id) => apiRequest(`/admin/products?id=${id}`, { method: 'DELETE' })
  }
};

const ApiLicenses = {
  getMyLicenses: () => apiRequest('/user/licenses'),
  updateLabel: (licenseId, userLabel) => apiRequest('/user/licenses/update-label', {
    method: 'PATCH',
    body: JSON.stringify({ license_id: licenseId, user_label: userLabel })
  }),
  downloadLic: async (licenseId) => {
    const url = `${API_BASE}/user/licenses/${licenseId}/download-lic`;
    const headers = { 'Authorization': `Bearer ${window.Auth ? window.Auth.getToken() : ''}` };
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);
    try {
      const res = await fetch(url, { headers, signal: controller.signal });
      clearTimeout(timeoutId);
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return { success: false, error: data.error || `Error ${res.status}`, status: res.status };
      }
      const blob = await res.blob();
      const disposition = res.headers.get('Content-Disposition');
      let filename = `license-${licenseId}.lic`;
      if (disposition) {
        const match = disposition.match(/filename="?(.+?)"?$/);
        if (match) filename = match[1];
      }
      return { success: true, blob, filename };
    } catch (e) {
      clearTimeout(timeoutId);
      return { success: false, error: 'Error de conexion' };
    }
  },
  revoke: (licenseId, reason = 'Revocada por el usuario') => apiRequest(`/user/licenses/${licenseId}/revoke`, {
    method: 'POST',
    body: JSON.stringify({ reason })
  }),
  resetHardware: (licenseId, reqBase64, reason = '') => apiRequest(`/user/licenses/${licenseId}/reset-hardware`, {
    method: 'POST',
    body: JSON.stringify({ req_base64: reqBase64, reason })
  }),
  admin: {
    getAll: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(`/admin/licenses?${query}`);
    }
  }
};

const ApiCheckout = {
  getExchangeRate: () => apiRequest('/exchange-rate'),
  createOrder: (data) => apiRequest('/checkout/create-order', {
    method: 'POST',
    body: JSON.stringify(data)
  }),
  submitManualTx: (data) => apiRequest('/checkout/submit-manual', {
    method: 'POST',
    body: JSON.stringify(data)
  }),
  initiateEnzona: (orderId) => apiRequest('/checkout/enzona/initiate', {
    method: 'POST',
    body: JSON.stringify({ order_id: orderId })
  }),
  initiatePaypal: (orderId) => apiRequest('/checkout/paypal/initiate', {
    method: 'POST',
    body: JSON.stringify({ order_id: orderId })
  }),
  complete: (data) => apiRequest('/checkout/complete', {
    method: 'POST',
    body: JSON.stringify(data)
  })
};

const ApiPayments = {
  getStatus: (id) => apiRequest(`/payments/${id}/status`)
};

const ApiUser = {
  getProfile: () => apiRequest('/user/profile'),
  updateProfile: (data) => apiRequest('/user/profile', { method: 'PUT', body: JSON.stringify(data) }),
  changePassword: (oldPw, newPw) => apiRequest('/user/password', {
    method: 'PUT', 
    body: JSON.stringify({ currentPassword: oldPw, newPassword: newPw })
  }),
  getDownloadUrl: (productId) => apiRequest(`/user/downloads?product_id=${encodeURIComponent(productId)}`),
  getPayments: () => apiRequest('/user/payments')
};

const ApiMessages = {
  send: (data) => apiRequest('/messages', { method: 'POST', body: JSON.stringify(data) }),
  admin: {
    getAll: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(`/admin/messages?${query}`);
    },
    reply: (id, text) => apiRequest(`/admin/messages/${id}`, {
      method: 'PUT', 
      body: JSON.stringify({ action: 'reply', reply: text })
    }),
    markRead: (id) => apiRequest(`/admin/messages/${id}`, {
      method: 'PUT', 
      body: JSON.stringify({ action: 'read' })
    })
  }
};

const ApiAdmin = {
  getStats: () => apiRequest('/admin/stats'),
  getUsers: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/users?${query}`);
  },
  searchUsers: (q) => apiRequest(`/admin/users?q=${encodeURIComponent(q)}`),
  createUser: (data) => apiRequest('/admin/users', { method: 'POST', body: JSON.stringify(data) }),
  updateUser: (id, data) => apiRequest(`/admin/users/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  // Convierte a un usuario existente en Partner certificado ({ action:'make_partner', province, send_email })
  makePartner: (id, data) => apiRequest(`/admin/users/${id}`, { method: 'POST', body: JSON.stringify(data) }),
  deleteUser: (id) => apiRequest(`/admin/users/${id}`, { method: 'DELETE' }),
  changeUserPassword: (userId, newPassword) => apiRequest('/admin/users/change-password', {
    method: 'PATCH',
    body: JSON.stringify({ user_id: userId, new_password: newPassword })
  }),
  getPayments: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/payments?${query}`);
  },
  validatePayment: (id) => apiRequest(`/admin/payments/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ action: 'validate' })
  }),
  rejectPayment: (id) => apiRequest(`/admin/payments/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ action: 'reject' })
  }),
  createPayment: (data) => apiRequest('/admin/payments', { method: 'POST', body: JSON.stringify(data) }),
  uploadQr: (formData) => {
    const token = window.Auth ? window.Auth.getToken() : '';
    return fetch('/api/admin/upload-qr', {
      method: 'POST',
      headers: token ? { 'Authorization': `Bearer ${token}` } : {},
      body: formData   // multipart/form-data — NO pasar Content-Type manualmente
    }).then(r => r.json());
  },
  getLogins: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/logins?${query}`);
  },
  getDownloads: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/downloads?${query}`);
  },
  getAnalytics: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/analytics?${query}`);
  },
  // Red de Partners
  getPartners: () => apiRequest('/admin/partners'),
  partnerAction: (id, data) => apiRequest(`/admin/partners/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data)
  }),
  // Historial del examen teórico del partner (sin respuestas) + elegibilidad
  getPartnerAttempts: (id) => apiRequest(`/admin/partners/${id}?resource=attempts`),
  // Advertencias por cobro indebido (historial con estado vigente/caducada/anulada)
  getPartnerWarnings: (id) => apiRequest(`/admin/partners/${id}?resource=warnings`),
  reassignPartner: (id, toPartnerId) => apiRequest(`/admin/partners/${id}`, {
    method: 'POST',
    body: JSON.stringify({ action: 'reassign', to_partner_id: toPartnerId })
  }),
  getCommissions: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/commissions?${query}`);
  },
  commissionAction: (id, data) => apiRequest(`/admin/commissions/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data)
  }),
  // Banco de preguntas del examen de certificación
  getExamQuestions: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/admin/exam-questions?${query}`);
  },
  createExamQuestions: (data) => apiRequest('/admin/exam-questions', {
    method: 'POST',
    body: JSON.stringify(data)
  }),
  examQuestionAction: (id, data) => apiRequest(`/admin/exam-questions/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data)
  }),
  deleteExamQuestion: (id) => apiRequest(`/admin/exam-questions/${id}`, { method: 'DELETE' }),
  resetLicenseHardware: (id) => apiRequest(`/admin/licenses/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ action: 'reset' })
  }),
  revokeLicense: (id, reason) => apiRequest(`/admin/licenses/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ action: 'revoke', reason })
  }),
  // Re-anclaje de soporte: desbloquea a un cliente re-anclando su licencia al equipo
  // que reporta (fila de telemetría) o a un .req que envió. Forja y le envía el .lic.
  reanchorLicense: (id, source) => apiRequest(`/admin/licenses/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ action: 're-anchor', ...source })
  })
};

const ApiSettings = {
  getAll: () => apiRequest('/admin/settings'),
  save: (settings) => apiRequest('/admin/settings', {
    method: 'POST',
    body: JSON.stringify({ settings })
  })
};

const ApiKeyForge = {
  generate: (data) => apiRequest('/admin/keyforge/generate', {
    method: 'POST',
    body: JSON.stringify(data)
  })
};

const ApiWaitlist = {
  subscribe: (data) => apiRequest('/waitlist/subscribe', { method: 'POST', body: JSON.stringify(data) }),
  admin: {
    getAll: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(query ? `/admin/waitlist?${query}` : '/admin/waitlist');
    },
    sendEmails: (data) => apiRequest('/admin/waitlist', { method: 'POST', body: JSON.stringify(data) }),
    // Sin wrapper para action:'notify' (marca notificado SIN enviar correo): no
    // tiene botón que lo dispare y no debe ser fácil reconectarlo sin querer.
    // El backend lo sigue aceptando vía POST directo si algún día hace falta.
    delete: (id) => apiRequest(`/admin/waitlist?id=${id}`, { method: 'DELETE' })
  }
};

const ApiCrashes = {
  getAll: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(query ? `/admin/crash-reports?${query}` : '/admin/crash-reports');
  },
  getById: (id) => apiRequest(`/admin/crash-reports/${id}`),
  setStatus: (id, status) => apiRequest(`/admin/crash-reports/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status })
  }),
  // Marcado por lote sobre ids explícitos (máx. 200). Nunca por filtro: no debe
  // poder alcanzar reportes que el admin no tenga delante.
  setStatusBulk: (ids, status) => apiRequest('/admin/crash-reports', {
    method: 'PATCH',
    body: JSON.stringify({ ids, status })
  })
};

const ApiFinances = {
  getStakeholders: () => apiRequest('/admin/finances/stakeholders'),
  createStakeholder: (data) => apiRequest('/admin/finances/stakeholders', { method: 'POST', body: JSON.stringify(data) }),
  updateStakeholder: (id, data) => apiRequest(`/admin/finances/stakeholders/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteStakeholder: (id) => apiRequest(`/admin/finances/stakeholders/${id}`, { method: 'DELETE' }),
  getExpenses: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(query ? `/admin/finances/expenses?${query}` : '/admin/finances/expenses');
  },
  createExpense: (data) => apiRequest('/admin/finances/expenses', { method: 'POST', body: JSON.stringify(data) }),
  updateExpense: (id, data) => apiRequest(`/admin/finances/expenses/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteExpense: (id) => apiRequest(`/admin/finances/expenses/${id}`, { method: 'DELETE' }),
  getCuts: () => apiRequest('/admin/finances/cuts'),
  createCut: (data) => apiRequest('/admin/finances/cuts', { method: 'POST', body: JSON.stringify(data) }),
  getCut: (id) => apiRequest(`/admin/finances/cuts/${id}`),
  updateCut: (id, data) => apiRequest(`/admin/finances/cuts/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteCut: (id) => apiRequest(`/admin/finances/cuts/${id}`, { method: 'DELETE' })
};

const ApiTickets = {
  admin: {
    getAll: () => apiRequest('/admin/support-tickets'),
    updateStatus: (id, status) => apiRequest(`/admin/support-tickets/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({ status })
    })
  }
};

// Releases de las apps de escritorio. Solo lectura: la publicación la hace el CD
// contra POST /api/updates/releases con API key, no el navegador.
const ApiReleases = {
  admin: {
    getAll: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(query ? `/admin/releases?${query}` : '/admin/releases');
    }
  }
};

// Exponer globalmente
// ═══ PANEL DEL ARTÍFICE (Red de Partners) ═══
const ApiPartner = {
  me: () => apiRequest('/partner/me'),
  updateProfile: (data) => apiRequest('/partner/me', {
    method: 'PUT',
    body: JSON.stringify(data)
  }),
  // Foto del Directorio: el panel ya la manda recortada a 400×400
  uploadPhoto: (blob, nombre) => {
    const token = window.Auth ? window.Auth.getToken() : '';
    const formData = new FormData();
    formData.append('photo', blob, nombre || 'foto.webp');
    return fetch('/api/partner/photo', {
      method: 'POST',
      headers: token ? { 'Authorization': `Bearer ${token}` } : {},
      body: formData   // multipart/form-data — NO pasar Content-Type manualmente
    }).then(r => r.json());
  },
  deletePhoto: () => apiRequest('/partner/photo', { method: 'DELETE' }),
  clients: () => apiRequest('/partner/clients'),
  commissions: () => apiRequest('/partner/commissions')
};

// Cola de aprobación de reset de hardware (Fase 2 anti-abuso).
const ApiHwResets = {
  admin: {
    getAll: () => apiRequest('/admin/licenses/hw-resets'),
    decide: (id, action, reason) => apiRequest(`/admin/licenses/hw-resets/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ action, reason })
    })
  }
};

// Asistente IA: base de conocimiento en D1 y reindexado de Vectorize (fase 2).
// Todas estas rutas cuelgan de /api/admin/ y las protege el gate de rol admin
// del middleware; el navegador manda la cookie/Origin y el CSRF global aplica.
const ApiAsistente = {
  admin: {
    listarKb: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(query ? `/admin/assistant/kb?${query}` : '/admin/assistant/kb');
    },
    obtenerKb: (id) => apiRequest(`/admin/assistant/kb/${id}`),
    crearKb: (data) => apiRequest('/admin/assistant/kb', {
      method: 'POST',
      body: JSON.stringify(data)
    }),
    actualizarKb: (id, data) => apiRequest(`/admin/assistant/kb/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    }),
    borrarKb: (id) => apiRequest(`/admin/assistant/kb/${id}`, { method: 'DELETE' }),
    versionesKb: (id) => apiRequest(`/admin/assistant/kb/${id}/versiones`),
    // El reindexado va por lotes: el panel llama en bucle con el next_offset
    // que devuelve cada respuesta hasta que `done` sea true.
    reindexar: (offset = 0) => apiRequest('/admin/assistant/reindex', {
      method: 'POST',
      body: JSON.stringify({ offset })
    }),
    // Auditoría de conversaciones (fase 3). El texto lo escriben desconocidos y
    // lo lee un admin: quien lo pinte tiene que pasarlo por escapeHtml().
    listarConversaciones: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return apiRequest(query
        ? `/admin/assistant/conversaciones?${query}`
        : '/admin/assistant/conversaciones');
    },
    // Red de seguridad de la retención: el borrado normal va pegado a cada
    // escritura del chat, porque Pages no puede programar tareas.
    limpiarConversaciones: () => apiRequest('/admin/assistant/conversaciones', {
      method: 'POST',
      body: JSON.stringify({ accion: 'limpiar' })
    }),
    // Triaje (fase 3.5): marcar deja constancia de lo que ya revisaste, para que
    // la cola encoja en vez de crecer. `reviewed_by` lo pone el servidor desde la
    // sesión: mandarlo desde aquí no serviría de nada.
    marcarConversacion: (id, review, review_note) => apiRequest(`/admin/assistant/conversaciones/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({ review, review_note })
    }),
    // Borrado individual. No hay papelera: lo que se borra aquí no vuelve.
    borrarConversacion: (id) => apiRequest(`/admin/assistant/conversaciones/${id}`, {
      method: 'DELETE'
    })
  }
};

const ApiCorrespondence = {
  admin: {
    preview: (data) => apiRequest('/admin/correspondence/preview', { method: 'POST', body: JSON.stringify(data) }),
    send: (data) => apiRequest('/admin/correspondence/send', { method: 'POST', body: JSON.stringify(data) })
  }
};

const ApiArticles = {
  admin: {
    getAll: (params = {}) => {
      const qs = new URLSearchParams(params).toString();
      return apiRequest(`/admin/articles${qs ? '?' + qs : ''}`);
    },
    getById: (id) => apiRequest(`/admin/articles/${id}`),
    create: (data) => apiRequest('/admin/articles', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data) => apiRequest(`/admin/articles/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete: (id) => apiRequest(`/admin/articles/${id}`, { method: 'DELETE' }),
    publish: (id) => apiRequest(`/admin/articles/${id}/publish`, { method: 'POST' }),
    unpublish: (id) => apiRequest(`/admin/articles/${id}/unpublish`, { method: 'POST' })
  }
};

window.Api = {
  request: (endpoint, options = {}) => {
    const clean = endpoint.startsWith('/api') ? endpoint.slice(4) : endpoint;
    return apiRequest(clean.startsWith('/') ? clean : `/${clean}`, options);
  },
  auth: ApiAuth,
  products: ApiProducts,
  licenses: ApiLicenses,
  payments: ApiPayments,
  checkout: ApiCheckout,
  user: ApiUser,
  messages: ApiMessages,
  admin: ApiAdmin,
  settings: ApiSettings,
  waitlist: ApiWaitlist,
  keyforge: ApiKeyForge,
  finances: ApiFinances,
  crashes: ApiCrashes,
  partner: ApiPartner,
  tickets: ApiTickets,
  releases: ApiReleases,
  hwResets: ApiHwResets,
  asistente: ApiAsistente,
  correspondence: ApiCorrespondence,
  articles: ApiArticles
};
