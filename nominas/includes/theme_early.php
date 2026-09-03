<?php
/**
 * theme_early.php - Redirige a theme_config.php (compatibilidad)
 *
 * Incluye el configurador centralizado de tema que aplica
 * TODAS las variables de personalización desde localStorage
 * ANTES del primer render (anti-FOUC).
 *
 * @deprecated Usar theme_config.php directamente en nuevos archivos.
 */
include __DIR__ . '/theme_config.php';
