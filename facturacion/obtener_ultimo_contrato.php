<?php
require_once 'config/header.php';

try {
    $db = Database::getConnection();
    
    // Buscar el último contrato con formato CVIS-####
    $sql_ultimo_contrato = "SELECT ContratoNo FROM clasif_clientes WHERE ContratoNo LIKE 'CVIS-%' ORDER BY LENGTH(ContratoNo), ContratoNo DESC LIMIT 1";
    $stmt_contrato = $db->query($sql_ultimo_contrato);
    $ultimo_contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo_contrato && preg_match('/CVIS-(\d+)/', $ultimo_contrato['ContratoNo'], $matches)) {
        $nuevo_numero_contrato = (int)$matches[1] + 1;
        $nuevo_contrato = 'CVIS-' . str_pad($nuevo_numero_contrato, 4, '0', STR_PAD_LEFT);
    } else {
        $nuevo_contrato = 'CVIS-0001';
    }
    
    echo json_encode([
        'success' => true,
        'nuevo_contrato' => $nuevo_contrato
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}