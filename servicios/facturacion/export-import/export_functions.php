<?php
/**
 * Clase FactExportImport
 * Funciones para importar y exportar tbl_fact y tbl_fact_detalle
 * Formatos: XML, JSON, SQL
 * SOPORTE INTEGRAL: Base64, Columnas Virtuales y Auto-creación de tablas.
 * 
 * NOTA: Se mantiene la función importFromSQL ORIGINAL que funciona perfectamente
 * SOLO se corrigieron las funciones de JSON/XML
 */
    setlocale(LC_ALL, "es_ES");
    setlocale(LC_TIME, "spanish");
	if (!ini_get('date.timezone')) {
		date_default_timezone_set('America/New_York'); 
	} else {
		date_default_timezone_set('America/New_York'); 
	}

require_once '../config/database.php';

class FactExportImport {
    private $db;
    private $tableColumnsCache = []; // Caché de columnas para optimizar importación
	
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * ==========================================
     *            SECCIÓN EXPORTACIÓN
     * ==========================================
     */
    
    public function getJSONData($tableName) {
        if (!in_array($tableName, ['tbl_fact', 'tbl_fact_detalle'])) {
            throw new Exception("Tabla no válida para exportación");
        }
        
        $query = "SELECT * FROM $tableName";
        $result = $this->db->query($query);
        
        if (!$result) {
            throw new Exception("Error en consulta: " . $this->db->error);
        }
        
        $data = array();
        while ($row = $result->fetch_assoc()) {
            // Convertir valores NULL a null en JSON
            foreach ($row as $key => $value) {
                if ($value === null) {
                    $row[$key] = null;
                }
            }
            $data[] = $row;
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }
    
    public function getFactWithDetailsJSON() {
        $query = "SELECT f.* FROM tbl_fact f ORDER BY f.id";
        $result = $this->db->query($query);
        
        if (!$result) {
            throw new Exception("Error en consulta principal: " . $this->db->error);
        }
        
        $data = array();
        while ($factura = $result->fetch_assoc()) {
            $detallesQuery = "SELECT d.* FROM tbl_fact_detalle d WHERE d.factura_id = " . intval($factura['id']);
            $detallesResult = $this->db->query($detallesQuery);
            $detalles = array();
            
            if ($detallesResult) {
                while ($detalle = $detallesResult->fetch_assoc()) {
                    // Convertir valores NULL
                    foreach ($detalle as $key => $value) {
                        if ($value === null) {
                            $detalle[$key] = null;
                        }
                    }
                    $detalles[] = $detalle;
                }
            }
            
            // Convertir valores NULL en factura
            foreach ($factura as $key => $value) {
                if ($value === null) {
                    $factura[$key] = null;
                }
            }
            
            $factura['detalles'] = $detalles;
            $data[] = $factura;
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }
    
    public function getXMLData($tableName, $pretty = true) {
        if (!in_array($tableName, ['tbl_fact', 'tbl_fact_detalle'])) {
            throw new Exception("Tabla no válida para exportación");
        }
        
        $query = "SELECT * FROM $tableName";
        $result = $this->db->query($query);
        if (!$result) throw new Exception("Error en consulta: " . $this->db->error);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        if ($pretty) $dom->formatOutput = true;
        
        $root = $dom->createElement('data');
        $dom->appendChild($root);
        
        while ($row = $result->fetch_assoc()) {
            $item = $dom->createElement('item');
            foreach ($row as $key => $value) {
                $child = $dom->createElement($key);
                if ($value !== null) {
                    $text = $dom->createTextNode(htmlspecialchars($value, ENT_XML1, 'UTF-8'));
                    $child->appendChild($text);
                } else {
                    // Para valores NULL, agregar atributo xsi:nil="true"
                    $child->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                    $child->setAttribute('xsi:nil', 'true');
                }
                $item->appendChild($child);
            }
            $root->appendChild($item);
        }
        return $dom->saveXML();
    }
    
    public function getFactWithDetailsXML() {
        return $this->getFactWithDetailsXMLFormatted(true);
    }
    
    public function getFactWithDetailsXMLFormatted($pretty = true) {
        $query = "SELECT f.* FROM tbl_fact f ORDER BY f.id";
        $result = $this->db->query($query);
        if (!$result) throw new Exception("Error consulta: " . $this->db->error);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        if ($pretty) { 
            $dom->formatOutput = true; 
            $dom->preserveWhiteSpace = false; 
        }
        
        $facturas = $dom->createElement('facturas');
        $dom->appendChild($facturas);
        
        while ($factura = $result->fetch_assoc()) {
            $facturaNode = $dom->createElement('factura');
            foreach ($factura as $key => $value) {
                if ($key != 'detalles') {
                    $child = $dom->createElement($key);
                    if ($value !== null) {
                        $text = $dom->createTextNode(htmlspecialchars($value, ENT_XML1, 'UTF-8'));
                        $child->appendChild($text);
                    } else {
                        $child->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                        $child->setAttribute('xsi:nil', 'true');
                    }
                    $facturaNode->appendChild($child);
                }
            }
            
            $detallesQuery = "SELECT d.* FROM tbl_fact_detalle d WHERE d.factura_id = " . intval($factura['id']);
            $detallesResult = $this->db->query($detallesQuery);
            $detallesNode = $dom->createElement('detalles');
            
            if ($detallesResult) {
                while ($detalle = $detallesResult->fetch_assoc()) {
                    $detalleNode = $dom->createElement('detalle');
                    foreach ($detalle as $dKey => $dValue) {
                        $dChild = $dom->createElement($dKey);
                        if ($dValue !== null) {
                            $dText = $dom->createTextNode(htmlspecialchars($dValue, ENT_XML1, 'UTF-8'));
                            $dChild->appendChild($dText);
                        } else {
                            $dChild->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                            $dChild->setAttribute('xsi:nil', 'true');
                        }
                        $detalleNode->appendChild($dChild);
                    }
                    $detallesNode->appendChild($detalleNode);
                }
            }
            $facturaNode->appendChild($detallesNode);
            $facturas->appendChild($facturaNode);
        }
        return $dom->saveXML();
    }
    

    public function importFromJSON($filePath, $tableName) {
        if (!file_exists($filePath)) throw new Exception("Archivo no encontrado");
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        if (!$data) throw new Exception("JSON inválido: " . json_last_error_msg());
        
        $importedRows = 0; $skippedRows = 0; $errors = [];
        $this->db->begin_transaction();
        
        try {
            if ($tableName === 'complete') {
                foreach ($data as $index => $facturaData) {
                    $detalles = isset($facturaData['detalles']) ? $facturaData['detalles'] : [];
                    unset($facturaData['detalles']);
                    
                    $facturaId = $this->insertRowGeneric('tbl_fact', $facturaData);
                    if ($facturaId) {
                        $importedRows++;
                        foreach ($detalles as $detalle) {
                            $detalle['factura_id'] = $facturaId;
                            $this->insertRowGeneric('tbl_fact_detalle', $detalle);
                        }
                    } else {
                        $skippedRows++;
                    }
                }
            } else {
                foreach ($data as $index => $row) {
                    if ($this->insertRowGeneric($tableName, $row)) {
                        $importedRows++;
                    } else {
                        $skippedRows++;
                    }
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        return ['imported' => $importedRows, 'skipped' => $skippedRows, 'errors' => $errors];
    }

    private function importTableFromJSON($data, $tableName, &$importedRows, &$skippedRows, &$errors) {
        if (empty($data)) {
            $errors[] = "El archivo JSON está vacío";
            return;
        }
        
        $tableColumns = $this->getTableColumns($tableName);
        
        foreach ($data as $index => $row) {
            try {
                // Si el row tiene estructura anidada (como en exportación completa), extraer solo los campos de la tabla
                if ($tableName === 'tbl_fact' && isset($row['detalles'])) {
                    // Es una exportación completa, extraer solo los campos de factura
                    $row = array_diff_key($row, ['detalles' => '']);
                }
                
                // Filtrar solo columnas que existen en la tabla
                $filteredRow = [];
                foreach ($tableColumns as $col) {
                    if (array_key_exists($col, $row)) {
                        $value = $row[$col];
                        // Convertir strings vacíos a null para campos que lo permitan
                        if ($value === '' && $this->isNullableColumn($tableName, $col)) {
                            $value = null;
                        }
                        $filteredRow[$col] = $this->convertValueForImport($tableName, $col, $value);
                    }
                }
                
                if (empty($filteredRow)) {
                    $skippedRows++;
                    $errors[] = "Fila $index: No hay datos válidos para insertar";
                    continue;
                }
                
                // Construir la consulta INSERT
                $columns = array_keys($filteredRow);
                $placeholders = array_fill(0, count($columns), '?');
                $values = array_values($filteredRow);
                
                $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) 
                        VALUES (" . implode(', ', $placeholders) . ")
                        ON DUPLICATE KEY UPDATE ";
                
                $updates = [];
                foreach ($columns as $col) {
                    $updates[] = "`$col` = VALUES(`$col`)";
                }
                $sql .= implode(', ', $updates);
                
                $stmt = $this->db->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Error preparando consulta: " . $this->db->error);
                }
                
                // Determinar tipos para bind_param
                $types = '';
                $bindValues = [];
                foreach ($values as $val) {
                    if ($val === null) {
                        $types .= 's'; // MySQL trata NULL como string
                        $bindValues[] = null;
                    } elseif (is_int($val)) {
                        $types .= 'i';
                        $bindValues[] = $val;
                    } elseif (is_float($val)) {
                        $types .= 'd';
                        $bindValues[] = $val;
                    } else {
                        $types .= 's';
                        $bindValues[] = (string)$val;
                    }
                }
                
                // Para manejar NULLs correctamente, necesitamos usar bind_param con referencias
                $params = [];
                $params[] = $types;
                for ($i = 0; $i < count($bindValues); $i++) {
                    $params[] = &$bindValues[$i];
                }
                
                call_user_func_array([$stmt, 'bind_param'], $params);
                
                if ($stmt->execute()) {
                    $importedRows++;
                } else {
                    $skippedRows++;
                    $errors[] = "Fila $index: " . $stmt->error;
                }
                
                $stmt->close();
                
            } catch (Exception $e) {
                $skippedRows++;
                $errors[] = "Fila $index: " . $e->getMessage();
            }
        }
    }

    private function importCompleteFromJSON($data, &$importedRows, &$skippedRows, &$errors) {
        if (empty($data)) {
            $errors[] = "El archivo JSON está vacío";
            return;
        }
        
        foreach ($data as $facturaIndex => $facturaData) {
            try {
                // Validar que tiene la estructura esperada
                if (!is_array($facturaData)) {
                    $skippedRows++;
                    $errors[] = "Factura $facturaIndex: Formato inválido";
                    continue;
                }
                
                // Extraer detalles y removerlos de los datos de factura
                $detalles = isset($facturaData['detalles']) ? $facturaData['detalles'] : [];
                unset($facturaData['detalles']);
                
                // Insertar factura
                $facturaId = $this->insertFacturaFromJSON($facturaData, $importedRows, $skippedRows, $errors);
                
                if ($facturaId && !empty($detalles)) {
                    foreach ($detalles as $detalleIndex => $detalle) {
                        try {
                            // Asegurar que el detalle tenga el factura_id correcto
                            $detalle['factura_id'] = $facturaId;
                            
                            // Insertar detalle
                            $this->insertDetalleFromJSON($detalle, $importedRows, $skippedRows, $errors);
                            
                        } catch (Exception $e) {
                            $errors[] = "Factura $facturaIndex, Detalle $detalleIndex: " . $e->getMessage();
                        }
                    }
                }
                
            } catch (Exception $e) {
                $skippedRows++;
                $errors[] = "Factura $facturaIndex: " . $e->getMessage();
            }
        }
    }

    private function insertFacturaFromJSON($facturaData, &$importedRows, &$skippedRows, &$errors) {
        $tableColumns = $this->getTableColumns('tbl_fact');
        
        // Filtrar datos
        $filteredData = [];
        foreach ($tableColumns as $col) {
            if (array_key_exists($col, $facturaData)) {
                $value = $facturaData[$col];
                if ($value === '' && $this->isNullableColumn('tbl_fact', $col)) {
                    $value = null;
                }
                $filteredData[$col] = $this->convertValueForImport('tbl_fact', $col, $value);
            }
        }
        
        if (empty($filteredData)) {
            $skippedRows++;
            $errors[] = "Factura sin datos válidos";
            return false;
        }
        
        // Verificar si ya existe (para ON DUPLICATE KEY)
        $exists = false;
        if (isset($facturaData['id'])) {
            $check = $this->db->query("SELECT id FROM tbl_fact WHERE id = " . intval($facturaData['id']));
            $exists = $check && $check->num_rows > 0;
        }
        
        // Construir consulta
        $columns = array_keys($filteredData);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($filteredData);
        
        $sql = "INSERT INTO tbl_fact (`" . implode('`, `', $columns) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE ";
        
        $updates = [];
        foreach ($columns as $col) {
            $updates[] = "`$col` = VALUES(`$col`)";
        }
        $sql .= implode(', ', $updates);
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $errors[] = "Error preparando inserción de factura: " . $this->db->error;
            return false;
        }
        
        // Preparar tipos y valores para bind_param
        $types = '';
        $bindValues = [];
        foreach ($values as $val) {
            if ($val === null) {
                $types .= 's';
                $bindValues[] = null;
            } elseif (is_int($val)) {
                $types .= 'i';
                $bindValues[] = $val;
            } elseif (is_float($val)) {
                $types .= 'd';
                $bindValues[] = $val;
            } else {
                $types .= 's';
                $bindValues[] = (string)$val;
            }
        }
        
        // Bind parameters con referencias
        $params = [$types];
        for ($i = 0; $i < count($bindValues); $i++) {
            $params[] = &$bindValues[$i];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $params);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id ?: (isset($facturaData['id']) ? $facturaData['id'] : null);
            $stmt->close();
            
            if (!$exists) {
                $importedRows++;
            }
            return $id;
        } else {
            $errors[] = "Error insertando factura: " . $stmt->error;
            $stmt->close();
            return false;
        }
    }

    private function insertDetalleFromJSON($detalleData, &$importedRows, &$skippedRows, &$errors) {
        $tableColumns = $this->getTableColumns('tbl_fact_detalle');
        
        // Filtrar datos
        $filteredData = [];
        foreach ($tableColumns as $col) {
            if (array_key_exists($col, $detalleData)) {
                $value = $detalleData[$col];
                if ($value === '' && $this->isNullableColumn('tbl_fact_detalle', $col)) {
                    $value = null;
                }
                $filteredData[$col] = $this->convertValueForImport('tbl_fact_detalle', $col, $value);
            }
        }
        
        if (empty($filteredData)) {
            $skippedRows++;
            $errors[] = "Detalle sin datos válidos";
            return false;
        }
        
        // Verificar si ya existe
        $exists = false;
        if (isset($detalleData['id'])) {
            $check = $this->db->query("SELECT id FROM tbl_fact_detalle WHERE id = " . intval($detalleData['id']));
            $exists = $check && $check->num_rows > 0;
        }
        
        // Construir consulta
        $columns = array_keys($filteredData);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($filteredData);
        
        $sql = "INSERT INTO tbl_fact_detalle (`" . implode('`, `', $columns) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE ";
        
        $updates = [];
        foreach ($columns as $col) {
            $updates[] = "`$col` = VALUES(`$col`)";
        }
        $sql .= implode(', ', $updates);
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            $errors[] = "Error preparando inserción de detalle: " . $this->db->error;
            return false;
        }
        
        // Preparar tipos
        $types = '';
        $bindValues = [];
        foreach ($values as $val) {
            if ($val === null) {
                $types .= 's';
                $bindValues[] = null;
            } elseif (is_int($val)) {
                $types .= 'i';
                $bindValues[] = $val;
            } elseif (is_float($val)) {
                $types .= 'd';
                $bindValues[] = $val;
            } else {
                $types .= 's';
                $bindValues[] = (string)$val;
            }
        }
        
        $params = [$types];
        for ($i = 0; $i < count($bindValues); $i++) {
            $params[] = &$bindValues[$i];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $params);
        
        if ($stmt->execute()) {
            $stmt->close();
            if (!$exists) {
                $importedRows++;
            }
            return true;
        } else {
            $errors[] = "Error insertando detalle: " . $stmt->error;
            $stmt->close();
            return false;
        }
    }

    public function importFromXML($filePath, $tableName) {
        $xml = simplexml_load_file($filePath);
        if ($xml === false) throw new Exception("XML inválido");
        
        $importedRows = 0; $skippedRows = 0; $errors = [];
        $this->db->begin_transaction();
        
        try {
            if ($tableName === 'complete') {
                // El XML completo tiene estructura <facturas><factura>...<detalles><detalle>...
                $root = $xml->getName() == 'facturas' ? $xml : $xml->factura;
                foreach ($xml->factura as $factNode) {
                    $facturaData = [];
                    foreach ($factNode->children() as $child) {
                        if ($child->getName() !== 'detalles') {
                            $facturaData[$child->getName()] = (string)$child;
                        }
                    }
                    
                    $facturaId = $this->insertRowGeneric('tbl_fact', $facturaData);
                    if ($facturaId) {
                        $importedRows++;
                        if (isset($factNode->detalles)) {
                            foreach ($factNode->detalles->detalle as $detNode) {
                                $detalleData = [];
                                foreach ($detNode->children() as $dChild) {
                                    $detalleData[$dChild->getName()] = (string)$dChild;
                                }
                                $detalleData['factura_id'] = $facturaId;
                                $this->insertRowGeneric('tbl_fact_detalle', $detalleData);
                            }
                        }
                    } else {
                        $skippedRows++;
                    }
                }
            } else {
                // XML simple de una tabla <data><item>...
                foreach ($xml->children() as $item) {
                    $row = [];
                    foreach ($item->children() as $child) {
                        $row[$child->getName()] = (string)$child;
                    }
                    if ($this->insertRowGeneric($tableName, $row)) {
                        $importedRows++;
                    } else {
                        $skippedRows++;
                    }
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        return ['imported' => $importedRows, 'skipped' => $skippedRows, 'errors' => $errors];
    }

   

	
	private function importTableFromXML($xml, $tableName, &$importedRows, &$skippedRows, &$errors) {
        foreach ($xml->children() as $item) {
            $row = []; foreach ($item->children() as $child) $row[$child->getName()] = (string)$child;
            if ($this->insertRowGeneric($tableName, $row)) $importedRows++; else $skippedRows++;
        }
    }

    private function importCompleteFromXML($xml, &$importedRows, &$skippedRows, &$errors) {
        foreach ($xml->children() as $facturaNode) {
            $facturaData = []; $detalles = [];
            foreach ($facturaNode->children() as $child) {
                if ($child->getName() === 'detalles') {
                    foreach ($child->children() as $detalleNode) {
                        $d = []; foreach ($detalleNode->children() as $dc) $d[$dc->getName()] = (string)$dc;
                        $detalles[] = $d;
                    }
                } else $facturaData[$child->getName()] = (string)$child;
            }
            $facturaId = $this->insertRowGeneric('tbl_fact', $facturaData);
            if ($facturaId) {
                $importedRows++;
                foreach ($detalles as $dd) { 
                    $dd['factura_id'] = $facturaId; 
                    $this->insertRowGeneric('tbl_fact_detalle', $dd); 
                }
            } else { $skippedRows++; }
        }
    }

   /**
     * Inserta una fila genérica optimizada
     */
    private function insertRowGeneric($tableName, $data) {
        // Cargar metadatos de tabla una sola vez para velocidad extrema
        if (!isset($this->tableColumnsCache[$tableName])) {
            $columns = [];
            $res = $this->db->query("SHOW COLUMNS FROM `$tableName`");
            while ($c = $res->fetch_assoc()) {
                $columns[$c['Field']] = [
                    'Type' => strtolower($c['Type']),
                    'Null' => $c['Null'] === 'YES',
                    'Extra' => strtolower($c['Extra'])
                ];
            }
            $this->tableColumnsCache[$tableName] = $columns;
        }

        $metadata = $this->tableColumnsCache[$tableName];
        $filteredData = [];
        
        foreach ($metadata as $colName => $info) {
            // Ignorar columnas generadas/virtuales
            if (strpos($info['Extra'], 'generated') !== false || strpos($info['Extra'], 'virtual') !== false) {
                continue;
            }
            
            if (array_key_exists($colName, $data)) {
                $val = $data[$colName];
                if (($val === null || $val === '') && $info['Null']) {
                    $filteredData[$colName] = null;
                } else {
                    // Limpieza básica de tipos
                    if (preg_match('/(int|tinyint|bigint)/', $info['Type'])) $val = (int)$val;
                    elseif (preg_match('/(decimal|float|double)/', $info['Type'])) $val = (float)str_replace(',', '.', $val);
                    $filteredData[$colName] = $val;
                }
            }
        }

        if (empty($filteredData)) return false;

        $cols = array_keys($filteredData);
        $placeholders = array_fill(0, count($cols), '?');
        $updates = [];
        foreach ($cols as $c) $updates[] = "`$c` = VALUES(`$c`)";

        $sql = "INSERT INTO `$tableName` (`" . implode("`, `", $cols) . "`) 
                VALUES (" . implode(",", $placeholders) . ") 
                ON DUPLICATE KEY UPDATE " . implode(", ", $updates);

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        $types = "";
        $values = [];
        foreach ($filteredData as $v) {
            if (is_null($v)) { $types .= "s"; $values[] = null; }
            elseif (is_int($v)) { $types .= "i"; $values[] = $v; }
            elseif (is_double($v) || is_float($v)) { $types .= "d"; $values[] = $v; }
            else { $types .= "s"; $values[] = (string)$v; }
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $id = $this->db->insert_id ?: (isset($data['id']) ? $data['id'] : true);
        $stmt->close();
        
        return $success ? $id : false;
    }


    /**
     * ==========================================
     *      SECCIÓN SQL (TU CÓDIGO ORIGINAL - INTACTO)
     * ==========================================
     */

    public function importFromSQL($filePath) {
        if (!file_exists($filePath)) throw new Exception("Archivo no encontrado");
        
        // 1. CONFIGURACIÓN DE PAQUETES GRANDES PARA BASE64
        // Intentar establecer max_allowed_packet a 1GB
        try {
            $db = Database::getConnection();
            
            // Intentar establecer max_allowed_packet a 1GB para la sesión actual
            @$db->exec("SET GLOBAL max_allowed_packet = 1073741824");
            
            // Si falla, intentar establecer solo para la sesión
            if (@$db->errorInfo()[0] !== '00000') {
                @$db->exec("SET SESSION max_allowed_packet = 1073741824");
            }
            
            // Opcional: verificar el valor actual (sin mostrar nada)
            @$stmt = $db->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
            if ($stmt) {
                @$row = $stmt->fetch(PDO::FETCH_ASSOC);
                // No se hace nada con el resultado
            }
            
        } catch (Exception $e) {
            // Silencioso - no hacer nada
        }
        // FIN: Bloque para establecer max_allowed_packet        

        // Aumentar tiempos de espera para evitar "Server has gone away"
        $this->db->query("SET SESSION wait_timeout = 28800");
        $this->db->query("SET SESSION net_read_timeout = 300");

        // Configuración inicial de sesión
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        $this->db->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $this->db->query("SET AUTOCOMMIT = 1"); 

        // Configuración inicial de sesión
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        $this->db->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $this->db->query("SET AUTOCOMMIT = 1"); 
        
        // Limpieza previa
        $this->dropAllTables();
        
        $sqlContent = file_get_contents($filePath);
        if ($sqlContent === false) throw new Exception("No se pudo leer el archivo");

        // Dividir consultas respetando bloques Base64 (Analizador de caracteres)
        $queries = $this->splitSQLQueries($sqlContent);
        
        $imported = 0; $successful = 0; $errors = [];
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;

            // Reparar sintaxis de ESTRUCTURA únicamente (Para auto-crear tablas correctamente)
            if (preg_match('/^CREATE\s+TABLE/i', $query)) {
                $query = $this->preprocessStructureSQL($query);
            }
            
            if ($this->db->query($query)) {
                $successful++;
                if (stripos($query, 'INSERT INTO') === 0) $imported += $this->db->affected_rows;
            } else {
                $sqlError = $this->db->error;
                $errors[] = "Error SQL: " . $sqlError . " | Bloque: " . substr($query, 0, 100) . "...";
                
                // Si la conexión se pierde por el tamaño del Base64, abortar para informar al usuario
                if (stripos($sqlError, 'gone away') !== false || stripos($sqlError, 'packet bigger') !== false) {
                    $errors[] = "CRÍTICO: La base de datos rechazó un paquete Base64 muy grande. Se requiere ajustar max_allowed_packet en el servidor.";
                    break;
                }
            }
        }
        
        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
        return ['imported' => $imported, 'successful_queries' => $successful, 'errors' => $errors];
    }

    private function preprocessStructureSQL($sql) {
        // 1. Quitar comas huérfanas antes de cierre )
        $sql = preg_replace('/,\s*\)/m', ' )', $sql);
        // 2. Reparar error de current_timestamp() sin coma posterior
        $sql = preg_replace('/(current_timestamp\(\))\s+(`?[a-z0-9_]+`?\s+[a-z]+)/i', '$1, $2', $sql);
        // 3. Normalizar Columnas Virtuales
        $sql = preg_replace_callback('/GENERATED\s+ALWAYS\s+AS\s*\((.*?)\)\s+VIRTUAL/is', function($m) {
            $inner = str_replace('`', '', $m[1]);
            return "GENERATED ALWAYS AS ($inner) VIRTUAL";
        }, $sql);
        return $sql;
    }

    private function splitSQLQueries($sql) {
        $queries = []; $current = ''; $inString = false; $stringChar = ''; $escaped = false; $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($escaped) { $current .= $char; $escaped = false; continue; }
            if ($char === '\\') { $current .= $char; $escaped = true; continue; }
            if (($char === "'" || $char === '"')) {
                if (!$inString) { $inString = true; $stringChar = $char; } 
                elseif ($char === $stringChar) { $inString = false; }
            }
            $current .= $char;
            if ($char === ';' && !$inString) {
                $trimmed = trim($current);
                if ($trimmed !== '') $queries[] = $trimmed;
                $current = '';
            }
        }
        if (trim($current) !== '') $queries[] = trim($current);
        return $queries;
    }

    private function preprocessSQL($sql) {
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*!.*?\*\//s', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace_callback('/GENERATED\s+ALWAYS\s+AS\s*\(`?([a-zA-Z0-9_]+)`?\s*\+\s*interval\s+`?([a-zA-Z0-9_]+)`?\s+([a-z]+)\)\s+VIRTUAL/i', function($m) {
            return "GENERATED ALWAYS AS ($m[1] + INTERVAL $m[2] " . strtoupper($m[3]) . ") VIRTUAL";
        }, $sql);
        $sql = preg_replace_callback('/GENERATED\s+ALWAYS\s+AS\s*\(`?([a-zA-Z0-9_]+)`?\s*\+\s*interval\s+if\(`?([a-zA-Z0-9_]+)`?\s*=\s*1,`?([a-zA-Z0-9_]+)`?,0\)\s+([a-z]+)\)\s+VIRTUAL/i', function($m) {
            return "GENERATED ALWAYS AS ($m[1] + INTERVAL IF($m[2] = 1, $m[3], 0) " . strtoupper($m[4]) . ") VIRTUAL";
        }, $sql);
        return $sql;
    }

    public function dropAllTables() {
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        $result = $this->db->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_array()) { $this->db->query("DROP TABLE IF EXISTS `{$row[0]}`"); }
        }
        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
    }


public function backupDatabase($customName = '', $backupDir = 'backups/') {
    $dbHost = '127.0.0.1'; 
    $dbName = 'sisfact_imdl'; 
    $dbUser = 'root'; 
    $dbPass = '';

    try {
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $serverInfo = $pdo->query('SELECT VERSION() as version')->fetch(PDO::FETCH_ASSOC);
        $serverVersion = $serverInfo['version'];
        $phpVersion = phpversion();
    } catch (PDOException $e) {
        throw new Exception("Error conexión: " . $e->getMessage());
    }

    $tables = [];
    $result = $pdo->query('SHOW TABLES');
    while ($row = $result->fetch(PDO::FETCH_NUM)) { 
        $tables[] = $row[0]; 
    }

    // HEADER
    // Comprobar Horario verano
    if (date("I") == 0) {
        $fechaGeneracion = date('d-m-Y');
        $horaGeneración = date("h:i:s A", strtotime('-1 hour'));
    } else {
        $fechaGeneracion = date('d-m-Y');
        $horaGeneración = date("h:i:s A");
    }
    
    $content = "-- phpMyAdmin SQL Dump\n";
    $content .= "-- https://www.phpmyadmin.net/\n";
    $content .= "--\n";
    $content .= "-- Servidor: $dbHost\n";
    $content .= "-- Versión del servidor: $serverVersion\n";
    $content .= "-- Versión de PHP: $phpVersion\n\n";
    
    $content .= "-- Exportaciones SISFACT PDL VISIONES --\n";
    $content .= "-- Salva SQL de las Bases de Datos del Sistema de Facturación SISFACT PDL VISIONES\n-- Webmaster: Franklin Ramos Lamadrid (kaky°)®\n-- Email: kakycu@gmail.com\n-- Copyright © 2025 - " . date('Y') . ".\n-- ------------------------------------------------------------------------------\n";
    $content .= "-- phpMyAdmin Volcado de Datos SQL.\n";
    $content .= "-- version cliente: " . mysqli_get_client_info() . "\n";
    $content .= "-- https://www.pdl-visiones.cu/sisfact\n";
    $content .= "-- https://sisfact.pdl-visiones.cu/\n";
    $content .= "-- ------------------------------------------------------------------------------\n";
    $content .= "-- Nombre del Servidor: " . $_SERVER['SERVER_NAME'] . "\n";
    $content .= "-- Dirección del Servidor: " . $_SERVER['SERVER_ADDR'] . "\n";
    
    $content .= "-- Tiempo de generación: " . $fechaGeneracion . " a las " . $horaGeneración . "\n";

    $content .= "\n\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $content .= "START TRANSACTION;\n";
    $content .= "SET time_zone = \"+00:00\";\n\n\n";
    
    $content .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $content .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $content .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $content .= "/*!40101 SET NAMES utf8mb4 */;\n\n";

    $content .= "DROP DATABASE IF EXISTS sisfact_imdl;\nCREATE DATABASE `sisfact_imdl` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;\nUSE `sisfact_imdl`;\n\n";

    $content .= "--\n-- Base de datos: `$dbName`\n--\n\n";

    $columnTypes = [];
    $virtualColumns = [];
    $indicesByTable = [];
    $constraintsByTable = [];
    $autoIncrementByTable = [];

    foreach ($tables as $table) {
        // Obtener información de columnas
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $columnTypes[$table] = [];
        $virtualColumns[$table] = [];
        
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columnTypes[$table][$col['Field']] = $col['Type'];
            
            if (stripos($col['Extra'], 'generated') !== false || 
                stripos($col['Extra'], 'virtual') !== false || 
                stripos($col['Extra'], 'stored') !== false) {
                $virtualColumns[$table][] = $col['Field'];
            }
        }

        $content .= "-- --------------------------------------------------------\n\n";
        $content .= "--\n-- Estructura de tabla para la tabla `$table`\n--\n\n";

        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $rawSql = $row[1];

        $lines = explode("\n", $rawSql);
        $cleanColumns = [];
        $tableIndices = [];
        $tableConstraints = [];
        $engineInfo = "";

        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (preg_match('/^\) ENGINE=/', $trimmed)) {
                $engineLine = preg_replace('/ AUTO_INCREMENT=\d+/', '', $trimmed);
                $engineInfo = ltrim($engineLine, ')'); 
                continue;
            }
            
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY)/i', $trimmed)) {
                $tableIndices[] = rtrim($trimmed, ',');
                continue;
            }

            if (preg_match('/^CONSTRAINT/i', $trimmed)) {
                $tableConstraints[] = rtrim($trimmed, ',');
                continue;
            }

            if (preg_match('/^`/', $trimmed)) {
                $colDef = preg_replace('/ AUTO_INCREMENT/i', '', $trimmed);
                $cleanColumns[] = rtrim($colDef, ',');
            }
        }

        $content .= "CREATE TABLE `$table` (\n  " . implode(",\n  ", array_filter($cleanColumns)) . "\n)" . $engineInfo . ";\n\n";

        // Guardar índices y constraints
        if (!empty($tableIndices)) {
            $indicesByTable[$table] = $tableIndices;
        }
        if (!empty($tableConstraints)) {
            $constraintsByTable[$table] = $tableConstraints;
        }

        // VOLCADO DE DATOS
        $rows = $pdo->query("SELECT * FROM `$table`");
        $allRows = $rows->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($allRows) > 0) {
            $content .= "--\n-- Volcado de datos para la tabla `$table`\n--\n\n";
            
            $allColumnNames = array_keys($allRows[0]);
            $virtualForThisTable = isset($virtualColumns[$table]) ? $virtualColumns[$table] : [];
            $columnNamesForInsert = array_diff($allColumnNames, $virtualForThisTable);
            
            if (empty($columnNamesForInsert)) {
                continue;
            }
            
            $columnsList = "`" . implode("`, `", $columnNamesForInsert) . "`";
            $content .= "INSERT INTO `$table` ($columnsList) VALUES\n";
            
            $dataRows = [];
            foreach ($allRows as $r) {
                $vals = [];
                foreach ($columnNamesForInsert as $colName) {
                    $v = $r[$colName];
                    if ($v === null) {
                        $vals[] = "NULL";
                    } else {
                        $colType = strtolower(trim($columnTypes[$table][$colName]));
                        $isStrictInteger = preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(|$)/', $colType);
                        $isDecimalType = preg_match('/^(decimal|numeric|float|double|real)(\(|$)/', $colType);
                        
                        if ($isStrictInteger && !$isDecimalType) {
                            $vals[] = $v;
                        } else {
                            $vals[] = $pdo->quote($v);
                        }
                    }
                }
                $dataRows[] = "(" . implode(", ", $vals) . ")";
            }
            $content .= implode(",\n", $dataRows) . ";\n\n";
        }

        // AUTO_INCREMENT
        if (preg_match('/AUTO_INCREMENT=(\d+)/', $rawSql, $matches)) {
            $aiValue = $matches[1];
            $colInfo = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Extra LIKE '%auto_increment%'")->fetch(PDO::FETCH_ASSOC);
            if ($colInfo) {
                $autoIncrementByTable[$table] = [
                    'field' => $colInfo['Field'],
                    'type' => $colInfo['Type'],
                    'val' => $aiValue
                ];
            }
        }
    }

    // ÍNDICES
    if (!empty($indicesByTable)) {
        $content .= "--\n-- Indices para tablas volcadas\n--\n\n";
        foreach ($indicesByTable as $tableName => $indices) {
            $content .= "--\n-- Indices de la tabla `$tableName`\n--\n";
            $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $indices) . ";\n\n";
        }
    }

    // AUTO_INCREMENT
    if (!empty($autoIncrementByTable)) {
        $content .= "--\n-- AUTO_INCREMENT de las tablas volcadas\n--\n\n";
        foreach ($autoIncrementByTable as $tableName => $info) {
            $content .= "--\n-- AUTO_INCREMENT de la tabla `$tableName`\n--\n";
            $content .= "ALTER TABLE `$tableName`\n";
            $content .= "  MODIFY `{$info['field']}` {$info['type']} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$info['val']};\n\n";
        }
    }

    // FOREIGN KEYS
    if (!empty($constraintsByTable)) {
        $content .= "--\n-- Restricciones para tablas volcadas\n--\n\n";
        foreach ($constraintsByTable as $tableName => $constraints) {
            $content .= "--\n-- Filtros para la tabla `$tableName`\n--\n";
            $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $constraints) . ";\n\n";
        }
    }

    $content .= "COMMIT;\n\n";
    $content .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $content .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $content .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // GENERAR NOMBRE DEL ARCHIVO
    // Formatear hora y fecha para el nombre del archivo
    $horaForFile = str_replace([':', ' '], '', $horaGeneración);
    $fechaForFile = str_replace('-', '', $fechaGeneracion);
    
    if (!empty($customName)) {
        // Sanitizar el nombre personalizado
        $customName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $customName);
        if (empty($customName)) {
            $customName = 'Salva_SISFACPDLVIS';
        }
        
        // Formato: NOMBRE_PERSONALIZADO_HHMMSSAM-DDMMYYYY.sql
        // Ejemplo: Backup_Enero_055647AM-21022026.sql
        $filePath = $backupDir . $customName . '_' . $horaForFile . '-' . $fechaForFile . '.sql';
    } else {
        // Formato original: Salva_SISFACPDLVIS_HHMMSSAM-DDMMYYYY.sql
        $filePath = $backupDir . 'Salva_SISFACPDLVIS_' . $horaForFile . '-' . $fechaForFile . '.sql';
    }

    file_put_contents($filePath, $content);
    
    return $filePath;
}


    /**
     * ==========================================
     *            VALIDACIONES Y UTILIDADES
     * ==========================================
     */

    private function convertValueForImport($tableName, $key, $value) {
        if ($value === null) {
            return null;
        }
        
        // Obtener información de la columna
        $query = "SHOW COLUMNS FROM `$tableName` WHERE Field = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $columnInfo = $result->fetch_assoc();
        $stmt->close();
        
        if (!$columnInfo) {
            return $value;
        }
        
        $type = strtolower($columnInfo['Type']);
        
        // Campos de fecha/hora
        if (strpos($type, 'date') !== false || strpos($type, 'time') !== false) {
            if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                return null;
            }
            $timestamp = strtotime($value);
            if ($timestamp) {
                if ($type === 'date') {
                    return date('Y-m-d', $timestamp);
                } else {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
            return $value;
        }
        
        // Enteros
        if (preg_match('/(tinyint|smallint|mediumint|int|bigint)/', $type)) {
            return is_numeric($value) ? (int)$value : 0;
        }
        
        // Decimales
        if (preg_match('/(decimal|float|double|numeric)/', $type)) {
            $value = str_replace(',', '.', $value);
            return is_numeric($value) ? (float)$value : 0;
        }
        
        // Strings y otros
        return (string)$value;
    }

    private function getTableColumns($tableName) {
        $columns = [];
        $query = "SHOW COLUMNS FROM `$tableName`";
        $result = $this->db->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        
        return $columns;
    }

    private function isNullableColumn($tableName, $columnName) {
        $query = "SHOW COLUMNS FROM `$tableName` WHERE Field = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $columnName);
        $stmt->execute();
        $result = $stmt->get_result();
        $column = $result->fetch_assoc();
        $stmt->close();
        
        return $column && $column['Null'] === 'YES';
    }

    public function validateSQLFile($filePath, $originalFileName = null) {
        $errors = array();
        if (!file_exists($filePath)) { $errors[] = "El archivo no existe"; return $errors; }
        if (filesize($filePath) == 0) { $errors[] = "El archivo está vacío"; return $errors; }
        if (filesize($filePath) > 300 * 1024 * 1024) $errors[] = "El archivo es demasiado grande (máximo 300MB)";
        if ($originalFileName) {
            $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            if ($extension !== 'sql' && $extension !== 'txt') { $errors[] = "El archivo debe tener extensión .sql o .txt"; }
        }
        return $errors;
    }

    public function validateImportFile($filePath, $format) {
        $errors = array();
        if (!file_exists($filePath)) return ["El archivo no existe"];
        if (filesize($filePath) == 0) return ["El archivo está vacío"];
        switch ($format) {
            case 'json':
                json_decode(file_get_contents($filePath));
                if (json_last_error() !== JSON_ERROR_NONE) $errors[] = "JSON inválido: " . json_last_error_msg();
                break;
            case 'xml':
                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($filePath);
                if ($xml === false) {
                    $xmlErrors = libxml_get_errors();
                    foreach ($xmlErrors as $error) {
                        $errors[] = "XML error: " . $error->message;
                    }
                    libxml_clear_errors();
                }
                break;
        }
        return $errors;
    }

    public function formatXML($xmlString) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        @$dom->loadXML($xmlString);
        return $dom->saveXML();
    }
}
	
function getUTCOffset($timezone, $dospuntos=true, $ignoreDST=false)
	{
	  $timezone_identifiers = DateTimeZone::listIdentifiers();
	  $dtz = new DateTimeZone($timezone);
	  $format=($dospuntos)?'%+03d:%02u':'%+03d%02u';

		
	  if (!$ignoreDST)
		{
		  $offset = $dtz->getOffset(new DateTime());
		  return sprintf($format, $offset / 3600, abs($offset) % 3600 / 60).' '.$timezone_identifiers[114].' ('.date('T').')';
		}
	  else
		{
		  $transitions = $dtz->getTransitions(time());
		  foreach ($transitions as $transition)
		{
		  if (!$transition['isdst'])
			return sprintf($format, $transition['offset'] / 3600, abs($transition['offset']) % 3600 / 60).' '.$timezone_identifiers[114];
		}
		  return false;
		}
	}
?>