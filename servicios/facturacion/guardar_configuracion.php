<?php
// guardar_configuracion.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data) {
        // Guardar en sesión
        $_SESSION['tema_windows'] = $data['tema_windows'] ?? 'dark';
        $_SESSION['color_accent'] = $data['color_accent'] ?? '#0078d4';
        $_SESSION['sidebar_mini'] = $data['sidebar_mini'] ?? false;
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}