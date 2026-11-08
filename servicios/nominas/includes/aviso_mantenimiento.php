<?php
/**
 * aviso_mantenimiento.php - Aviso "MODO MANTENIMIENTO ACTIVO" para el rol 5
 *
 * Es la segunda de las dos barras: la barra roja de MODO BYPASS ACTIVADO se
 * dibuja en login.php?access_bypass=true (acceso sin sesion). Esta aqui para
 * cuando el programador ya esta logueado y recorre el sistema con el
 * mantenimiento activo o el subsistema de Nominas desactivado.
 *
 * Replica el aviso de facturacion/config/header.php: misma barra ambar delgada,
 * mismo chip con el nombre del programador, mismo cierre con &times;, mismo
 * ocultado a los 3 segundos y misma reaparicion al hacer scroll al inicio o al
 * pasar el mouse sobre el encabezado.
 *
 * Se incluye desde includes/footer.php. No se usa output buffering: paginas
 * como empleados.php incrustan logos base64 de ~1 MB por empleado y acumular
 * todo el HTML en memoria supera el memory_limit de 128 MB.
 */

if (!function_exists('aviso_mantenimiento_es_ajax')) {
    function aviso_mantenimiento_es_ajax() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return $accept !== '' && strpos($accept, 'text/html') === false;
    }
}

if (!function_exists('aviso_mantenimiento_motivo')) {
    /**
     * Motivo del aviso solo para el rol 5 (Programador): 'mantenimiento',
     * 'subsistema_inactivo' o null cuando el sistema funciona con normalidad.
     */
    function aviso_mantenimiento_motivo() {
        global $pdo;

        if (empty($_SESSION['logged_in']) || (int)($_SESSION['rol_id'] ?? 0) !== 5) {
            return null;
        }
        if (aviso_mantenimiento_es_ajax() || !isset($pdo) || !$pdo instanceof PDO) {
            return null;
        }

        require_once __DIR__ . '/subsistemas.php';
        return motivo_bloqueo($pdo);
    }
}

if (!function_exists('aviso_mantenimiento_programador')) {
    function aviso_mantenimiento_programador() {
        foreach (['user_nombre', 'usuario_nombre', 'nombre', 'user_name', 'username'] as $clave) {
            if (!empty($_SESSION[$clave])) {
                return $_SESSION[$clave];
            }
        }
        return 'Programador';
    }
}

if (!function_exists('aviso_mantenimiento_html')) {
    function aviso_mantenimiento_html($motivo) {
        $nombre = htmlspecialchars(aviso_mantenimiento_programador());

        $chip_motivo = ($motivo === 'subsistema_inactivo')
            ? '<span style="font-weight: 700; margin-left: 10px; background: #dc3545; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
            <i class="fas fa-power-off me-1"></i>Subsistema Inactivo
        </span>'
            : '';

        return '
    <div id="maintenance-notice" style="position: fixed; top: -30px; left: 0; width: 100%; background: linear-gradient(90deg, #ffc107, #ffdb4d); color: #000; text-align: center; padding: 4px; font-weight: 700; z-index: 100001; font-size: 13px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border-bottom: 1px solid #e0a800; transition: top 0.3s ease;">
        <i class="fas fa-tools me-2"></i> MODO MANTENIMIENTO ACTIVO
        <span style="margin-left: 10px; font-weight: 600; background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 3px; font-size: 12px;">
            <i class="fas fa-user-cog me-1"></i>' . $nombre . ' - (Usuario Programador)
        </span>
        ' . $chip_motivo . '
        <span style="font-weight: normal; margin-left: 10px; font-size: 12px;">(El sistema está oculto para el resto de los Usuarios)</span>
        <button onclick="this.parentElement.style.top=\'-30px\'; document.body.style.marginTop=\'0\';" style="background: transparent; border: none; color: #000; font-size: 16px; cursor: pointer; margin-left: 15px; padding: 0 5px; line-height: 1;">&times;</button>
    </div>

    <script>
        (function() {
            const notice = document.getElementById("maintenance-notice");
            if (!notice) return;

            let timerOcultar = null;

            function mostrar() {
                clearTimeout(timerOcultar);
                notice.style.top = "0";
                document.body.style.marginTop = "28px";
            }

            function ocultar() {
                notice.style.top = "-30px";
                document.body.style.marginTop = "0";
            }

            mostrar();
            timerOcultar = setTimeout(ocultar, 3000);

            window.addEventListener("scroll", function() {
                if (window.scrollY < 50) {
                    mostrar();
                } else {
                    ocultar();
                }
            });

            document.addEventListener("mouseover", function(e) {
                if (e.target.closest("header, nav, .navbar, .win-navbar, .header-actions")) {
                    mostrar();
                }
            });

            notice.addEventListener("mouseleave", function() {
                setTimeout(function() {
                    if (window.scrollY > 50) {
                        ocultar();
                    }
                }, 1000);
            });
        })();
    </script>
        ';
    }
}
