<?php
// ajax_delete_historico.php
// Habilitar reporte de errores de PHP para que salgan en el log
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para guardar logs en un archivo de texto
function debugLog($mensaje) {
    $contenido = date('Y-m-d H:i:s') . " - " . $mensaje . "\n";
    file_put_contents('debug_delete.txt', $contenido, FILE_APPEND);
}

ob_start();
session_start();
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Error inicial',
    'db_name' => 'Desconocido'
];

try {
    debugLog("------------------------------------------------");
    debugLog("Iniciando solicitud de borrado.");

    // 1. Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception("Sesión no iniciada.");
    }
    debugLog("Usuario ID: " . $_SESSION['usuario_id']);

    // 2. Conexión a Base de Datos
    if (file_exists('config/database.php')) require_once 'config/database.php';
    elseif (file_exists('config/db.php')) require_once 'config/db.php';
    elseif (file_exists('config/conexion.php')) require_once 'config/conexion.php';
    else require_once 'historico.php';

    if (class_exists('Database')) {
        $db = Database::getConnection();
        // FUERZA EL MODO DE ERRORES A EXCEPTION
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        throw new Exception("Clase Database no encontrada.");
    }

    // VERIFICAR A QUÉ BD ESTAMOS CONECTADOS
    $stmtName = $db->query("SELECT DATABASE()");
    $dbName = $stmtName->fetchColumn();
    $response['db_name'] = $dbName;
    debugLog("Conectado a la Base de Datos: " . $dbName);

    // 3. Permisos
    $stmtUser = $db->prepare("SELECT rol_id FROM clasif_usuarios WHERE id = :id");
    $stmtUser->execute(['id' => $_SESSION['usuario_id']]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    $rol = (int)$currentUser['rol_id'];
    debugLog("Rol del usuario: " . $rol);

    if ($rol !== 1 && $rol !== 4) {
        throw new Exception("Rol no autorizado.");
    }

    // 4. Procesar ID
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        debugLog("Acción solicitada: " . $accion);

        if ($accion === 'eliminar_item') {
            $id = (int)($_POST['id'] ?? 0);
            debugLog("ID a eliminar: " . $id);

            if ($id <= 0) throw new Exception("ID inválido recibido: " . $_POST['id']);

            // --- INTENTO DE BORRADO CON TRANSACCIÓN EXPLÍCITA ---
            try {
                // Iniciamos transacción
                $db->beginTransaction();

                $sql = "DELETE FROM historico_operaciones WHERE id = :id";
                $stmt = $db->prepare($sql);
                // Forzamos que sea entero
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $filasAfectadas = $stmt->rowCount();
                debugLog("Ejecutado. Filas afectadas: " . $filasAfectadas);

                // Confirmamos cambios
                $db->commit();
                debugLog("Commit realizado.");

                if ($filasAfectadas > 0) {
                    $response['success'] = true;
                    $response['message'] = "Eliminado correctamente.";
                } else {
                    // Verificar si el ID existía antes
                    $check = $db->query("SELECT count(*) FROM historico_operaciones WHERE id = $id")->fetchColumn();
                    debugLog("Verificación post-intento: El registro $id " . ($check > 0 ? "EXISTE (No se pudo borrar)" : "NO EXISTE (Ya no está)"));
                    
                    if ($check == 0) {
                         // Si ya no existe, técnicamente fue un éxito o ya estaba borrado
                         $response['success'] = true;
                         $response['message'] = "El registro ya no existe (¿Borrado previo?).";
                    } else {
                         throw new Exception("La consulta corrió pero no borró nada. Posible restricción FK.");
                    }
                }

            } catch (Exception $ex) {
                // Si falla, revertimos
                if ($db->inTransaction()) {
                    $db->rollBack();
                    debugLog("Rollback ejecutado por error.");
                }
                throw $ex;
            }
        }
        elseif ($accion === 'eliminar_todo') {
             // Lógica de eliminar todo...
             $db->query("DELETE FROM historico_operaciones"); // Simplificado
             $response['success'] = true;
             $response['message'] = "Todo borrado.";
        }
    }

} catch (Exception $e) {
    debugLog("ERROR CRÍTICO: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_clean();
echo json_encode($response);
exit;
?>