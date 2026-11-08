<?php
// config.php - Configuración central de la BASE DE DATOS.
// Este es el ÚNICO archivo donde se guardan el nombre de la base de datos y
// la contraseña de MySQL. El instalador actualiza estas constantes
// automáticamente al configurar la conexión.
//
// La conexión PDO, la carga de configuración desde la BD y las funciones del
// sistema están en config/database.php (lo incluyen todas las páginas).

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', 'sql311.infinityfree.com');
define('DB_NAME', 'if0_42708110_transnubet_nomina');
define('DB_USER', 'if0_42708110');
define('DB_PASS', 'frl8110kaky');          // Contraseña de MySQL (puede estar vacía)
define('DB_CHARSET', 'utf8mb4');

// ============================================
// VERSIÓN DEL SISTEMA
// ============================================
define('SITE_VERSION', 'v2.0.1');

// ============================================
// RUTA BASE
// ============================================
define('BASE_URL', '/nominas');
define('BASE_PATH', __DIR__);
define('ASSETS_PATH', BASE_PATH . '/assets');

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

