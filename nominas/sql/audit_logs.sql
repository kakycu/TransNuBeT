-- ============================================================
-- TABLA DE AUDITORÍA: audit_logs
-- Sistema de logs para trazabilidad y transparencia de las
-- operaciones realizadas en el sistema.
--
-- NOTAS:
--   * action_type   : LO DEFINE EL SISTEMA (no la BD). Es un
--                      VARCHAR porque hay muchas acciones
--                      específicas (crear_trabajador,
--                      dar_baja_trabajador, contabilizar_nomina,
--                      iniciar_sesion, etc.). El catálogo oficial
--                      vive en logger.php (constante LOG_ACCIONES).
--   * details        : JSON arbitrario con contexto adicional.
--   * hash_chain     : SHA-256 del (id anterior + datos del
--                      registro actual). Permitirá detectar
--                      manipulación de registros.
--   * created_at     : SIEMPRE en formato 24h (YYYY-MM-DD HH:MM:SS).
--   * user_id, username, user_email: referencias genéricas al
--                      usuario; se adaptan al schema real
--                      (tabla clasif_usuarios).
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NULL,
    username       VARCHAR(100) NULL,
    user_email     VARCHAR(150) NULL,
    auth_provider  ENUM('local','google','system','anonimo') NOT NULL DEFAULT 'anonimo',
    action_type    VARCHAR(100) NOT NULL,
    module         VARCHAR(50) NOT NULL,
    description    VARCHAR(255) NOT NULL,
    details        JSON NULL,
    ip_address     VARCHAR(45) NOT NULL,
    user_agent     VARCHAR(500) NULL,
    request_method VARCHAR(10) NULL,
    request_url    VARCHAR(500) NULL,
    status         ENUM('success','failed') NOT NULL DEFAULT 'success',
    error_message  TEXT NULL,
    hash_chain     CHAR(64) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Índices para filtrar y ordenar la página "Histórico de Operaciones"
    INDEX idx_created  (created_at),
    INDEX idx_user     (user_id),
    INDEX idx_action   (action_type),
    INDEX idx_ip       (ip_address),
    INDEX idx_provider (auth_provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REPLACE: como el INSERT de logger.php calcula hash_chain en
-- función del id anterior, NO se debe importar/restaurar esta
-- tabla con TRUNCATE (perdería la cadena de hashes). Si se
-- restaura, hacerlo insertando en orden ascendente de id.
-- ============================================================