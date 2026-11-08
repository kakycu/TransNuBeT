<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$periodo = $_GET['periodo'] ?? '';
$tipo = $_GET['tipo'] ?? '';

if (!preg_match('/^\d{4}-\d{2}$/', $periodo) || !in_array($tipo, ['automatica', 'extraordinaria', 'vacaciones', 'bono', 'ajuste'], true)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

$anio = substr($periodo, 0, 4);
$mes = substr($periodo, 5, 2);
$periodo_desde = "$anio-$mes-01";
$periodo_hasta = date('Y-m-t', strtotime($periodo_desde));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
$stmt->execute([$periodo_desde, $periodo_hasta, $tipo]);
$existe = $stmt->fetchColumn() > 0;

echo json_encode(['success' => true, 'existe' => $existe]);
