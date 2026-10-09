<?php
// includes/mantenimiento_bd.php - Diagnostico de SOLO LECTURA de la base de datos.
// No modifica, no borra ni optimiza nada: CHECK TABLE, indices de claves
// foraneas, colaciones de los pares de JOIN y filas huerfanas.

if (!function_exists('bd_diagnosticar')) {
    function bd_diagnosticar($pdo)
    {
        $t0 = microtime(true);
        $out = [
            'success'    => true,
            'error'      => '',
            'base'       => '',
            'tablas'     => [],
            'fks'        => [],
            'collations' => [],
            'huerfanos'  => [],
            'resumen'    => [],
            'segundos'   => 0,
        ];

        try {
            $out['base'] = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

            $esc = function ($id) {
                return '`' . str_replace('`', '``', (string)$id) . '`';
            };

            // ---------- 1. Tablas + CHECK TABLE + fragmentacion ----------
            $tablas = $pdo->query(
                "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE, TABLE_COLLATION
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                  ORDER BY TABLE_NAME"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tablas as $t) {
                $nom = (string)$t['TABLE_NAME'];
                $bytesTotal = (int)$t['DATA_LENGTH'] + (int)$t['INDEX_LENGTH'] + (int)$t['DATA_FREE'];
                $librePct = $bytesTotal > 0 ? round(100 * (int)$t['DATA_FREE'] / $bytesTotal, 1) : 0.0;

                $estado = 'ok';
                $mensaje = 'Sin problemas';
                try {
                    $filasCheck = $pdo->query('CHECK TABLE ' . $esc($nom))->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($filasCheck as $fc) {
                        $tipo = strtolower((string)($fc['Msg_type'] ?? ''));
                        $texto = (string)($fc['Msg_text'] ?? '');
                        if ($tipo === 'error') {
                            $estado = 'error';
                            $mensaje = $texto;
                        } elseif ($tipo === 'warning' && $estado !== 'error') {
                            $estado = 'aviso';
                            $mensaje = $texto;
                        } elseif ($estado === 'ok') {
                            $mensaje = $texto;
                        }
                    }
                } catch (PDOException $e) {
                    $estado = 'error';
                    $mensaje = $e->getMessage();
                }

                $out['tablas'][] = [
                    'nombre'     => $nom,
                    'motor'      => (string)($t['ENGINE'] ?? '-'),
                    'filas'      => (int)$t['TABLE_ROWS'],
                    'libre_pct'  => $librePct,
                    'collation'  => (string)($t['TABLE_COLLATION'] ?? ''),
                    'check'      => $estado,
                    'mensaje'    => $mensaje,
                ];
            }

            // ---------- 2. Claves foraneas: sin indice y colacion ----------
            $fkFilas = $pdo->query(
                "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION,
                        REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
                  ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION"
            )->fetchAll(PDO::FETCH_ASSOC);

            $grupos = [];
            foreach ($fkFilas as $f) {
                $clave = $f['TABLE_NAME'] . '::' . $f['CONSTRAINT_NAME'];
                if (!isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'tabla'      => (string)$f['TABLE_NAME'],
                        'constraint' => (string)$f['CONSTRAINT_NAME'],
                        'columnas'   => [],
                        'padre'      => (string)$f['REFERENCED_TABLE_NAME'],
                        'padre_cols' => [],
                    ];
                }
                $grupos[$clave]['columnas'][] = (string)$f['COLUMN_NAME'];
                $grupos[$clave]['padre_cols'][] = (string)$f['REFERENCED_COLUMN_NAME'];
            }

            $stmtIdx = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1"
            );
            $stmtCol = $pdo->prepare(
                "SELECT COLLATION_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );

            foreach ($grupos as $g) {
                $primera = $g['columnas'][0] ?? '';
                $stmtIdx->execute([$g['tabla'], $primera]);
                $tieneIndice = (int)$stmtIdx->fetchColumn() > 0;

                $collHija = null;
                $collPadre = null;
                $stmtCol->execute([$g['tabla'], $primera]);
                $vHija = $stmtCol->fetchColumn();
                $collHija = ($vHija === false || $vHija === null) ? null : (string)$vHija;
                $stmtCol->execute([$g['padre'], $g['padre_cols'][0] ?? $primera]);
                $vPadre = $stmtCol->fetchColumn();
                $collPadre = ($vPadre === false || $vPadre === null) ? null : (string)$vPadre;
                $collOk = ($collHija === null || $collPadre === null) ? true : ($collHija === $collPadre);

                $out['fks'][] = [
                    'tabla'        => $g['tabla'],
                    'constraint'   => $g['constraint'],
                    'columnas'     => implode(', ', $g['columnas']),
                    'padre'        => $g['padre'],
                    'padre_cols'   => implode(', ', $g['padre_cols']),
                    'tiene_indice' => $tieneIndice,
                    'coll_ok'      => $collOk,
                    'coll_hija'    => (string)($collHija ?? ''),
                    'coll_padre'   => (string)($collPadre ?? ''),
                ];
            }

            // ---------- 3. Pares FK con colaciones distintas ----------
            foreach ($out['fks'] as $fk) {
                if (!$fk['coll_ok']) {
                    $out['collations'][] = $fk;
                }
            }

            // ---------- 3b. Colaciones heterogeneas entre columnas de texto ----------
            $colsTexto = $pdo->query(
                "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL
                  ORDER BY COLLATION_NAME"
            )->fetchAll(PDO::FETCH_ASSOC);
            $conteoColl = [];
            foreach ($colsTexto as $c) {
                $cl = (string)$c['COLLATION_NAME'];
                $conteoColl[$cl] = ($conteoColl[$cl] ?? 0) + 1;
            }
            if (count($conteoColl) > 1) {
                arsort($conteoColl);
                $mayoritaria = (string)array_key_first($conteoColl);
                foreach ($colsTexto as $c) {
                    if ((string)$c['COLLATION_NAME'] !== $mayoritaria && count($out['collations']) < 40) {
                        $out['collations'][] = [
                            'tabla'        => (string)$c['TABLE_NAME'],
                            'constraint'   => '-',
                            'columnas'     => (string)$c['COLUMN_NAME'],
                            'padre'        => '-',
                            'padre_cols'   => '-',
                            'tiene_indice' => true,
                            'coll_ok'      => false,
                            'coll_hija'    => (string)$c['COLLATION_NAME'],
                            'coll_padre'   => $mayoritaria,
                            'motivo'       => 'Collation distinta a la mayoritaria de la BD (' . $mayoritaria . ')',
                        ];
                    }
                }
            }

            // ---------- 4. Filas huerfanas (hija sin padre) ----------
            foreach ($grupos as $g) {
                $condOn = [];
                $condHija = [];
                foreach ($g['columnas'] as $i => $col) {
                    $padreCol = $g['padre_cols'][$i] ?? $col;
                    $condOn[] = 'h.' . $esc($col) . ' = p.' . $esc($padreCol);
                    $condHija[] = 'h.' . $esc($col) . ' IS NOT NULL';
                }
                $sql = 'SELECT COUNT(*) FROM ' . $esc($g['tabla']) . ' h
                          LEFT JOIN ' . $esc($g['padre']) . ' p ON ' . implode(' AND ', $condOn) . '
                         WHERE ' . implode(' AND ', $condHija) . ' AND p.' . $esc($g['padre_cols'][0]) . ' IS NULL';
                try {
                    $n = (int)$pdo->query($sql)->fetchColumn();
                    if ($n > 0) {
                        $colsSel = implode(', ', array_map(function ($c) use ($esc) {
                            return 'h.' . $esc($c);
                        }, $g['columnas']));
                        $sqlEj = 'SELECT ' . $colsSel . ' FROM ' . $esc($g['tabla']) . ' h
                                    LEFT JOIN ' . $esc($g['padre']) . ' p ON ' . implode(' AND ', $condOn) . '
                                   WHERE ' . implode(' AND ', $condHija) . ' AND p.' . $esc($g['padre_cols'][0]) . ' IS NULL
                                   LIMIT 5';
                        $ejemplos = [];
                        foreach ($pdo->query($sqlEj)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                            $parts = [];
                            foreach ($fila as $k => $v) {
                                $parts[] = $k . '=' . (string)$v;
                            }
                            $ejemplos[] = implode(', ', $parts);
                        }
                        $out['huerfanos'][] = [
                            'tabla'      => $g['tabla'],
                            'constraint' => $g['constraint'],
                            'padre'      => $g['padre'],
                            'columnas'   => implode(', ', $g['columnas']),
                            'cantidad'   => $n,
                            'ejemplos'   => $ejemplos,
                        ];
                    }
                } catch (PDOException $e) {
                    $out['collations'][] = [
                        'tabla'      => $g['tabla'],
                        'constraint' => $g['constraint'],
                        'columnas'   => implode(', ', $g['columnas']),
                        'padre'      => $g['padre'],
                        'padre_cols' => implode(', ', $g['padre_cols']),
                        'tiene_indice' => true,
                        'coll_ok'    => false,
                        'coll_hija'  => '-',
                        'coll_padre' => '-',
                        'motivo'     => 'No evaluable: ' . $e->getMessage(),
                    ];
                }
            }

            // ---------- 5. Resumen ----------
            $ok = $aviso = $error = $frag = 0;
            foreach ($out['tablas'] as $t) {
                if ($t['check'] === 'ok') { $ok++; }
                elseif ($t['check'] === 'aviso') { $aviso++; }
                else { $error++; }
                if ($t['libre_pct'] >= 20) { $frag++; }
            }
            $sinIndice = 0;
            foreach ($out['fks'] as $f) {
                if (!$f['tiene_indice']) { $sinIndice++; }
            }

            $out['resumen'] = [
                'tablas'        => count($out['tablas']),
                'check_ok'      => $ok,
                'check_aviso'   => $aviso,
                'check_error'   => $error,
                'fks'           => count($out['fks']),
                'fks_sin_indice'=> $sinIndice,
                'collation_bad' => count($out['collations']),
                'huerfanos'     => count($out['huerfanos']),
                'fragmentadas'  => $frag,
            ];
        } catch (Exception $e) {
            $out['success'] = false;
            $out['error'] = $e->getMessage();
        }

        $out['segundos'] = round(microtime(true) - $t0, 2);
        return $out;
    }
}

if (!function_exists('bd_reparar')) {
    function bd_reparar($pdo, array $op)
    {
        $t0 = microtime(true);
        $out = [
            'success' => true,
            'error'   => '',
            'pasos'   => [],
            'resumen' => [],
            'segundos'=> 0,
        ];

        $esc = function ($id) {
            return '`' . str_replace('`', '``', (string)$id) . '`';
        };

        $ejecutar = function ($sql) use ($pdo) {
            try {
                $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $msgs = [];
                $aviso = false;
                foreach ($filas as $f) {
                    $tipo = strtolower((string)($f['Msg_type'] ?? ''));
                    $texto = trim((string)($f['Msg_text'] ?? ''));
                    if ($texto !== '') {
                        $msgs[] = $texto;
                    }
                    if ($tipo === 'error') {
                        return ['estado' => 'error', 'mensaje' => $texto];
                    }
                    if ($tipo === 'warning') {
                        $aviso = true;
                    }
                }
                return ['estado' => $aviso ? 'aviso' : 'ok', 'mensaje' => $msgs ? end($msgs) : 'Ejecutado'];
            } catch (PDOException $e) {
                return ['estado' => 'error', 'mensaje' => $e->getMessage()];
            }
        };

        $listarTablas = function () use ($pdo) {
            return $pdo->query(
                "SELECT TABLE_NAME, ENGINE, DATA_LENGTH, INDEX_LENGTH, DATA_FREE
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                  ORDER BY TABLE_NAME"
            )->fetchAll(PDO::FETCH_ASSOC);
        };

        $res = [
            'reparadas' => 0, 'reparadas_fallidas' => 0,
            'optimizadas' => 0, 'optimizadas_fallidas' => 0,
            'analizadas' => 0, 'analizadas_fallidas' => 0,
            'indices_creados' => 0, 'indices_fallidos' => 0,
            'collations_unificadas' => 0, 'collations_fallidas' => 0,
            'huerfanos_borrados' => 0,
        ];

        // ---- Paso 1: Reparar tablas con errores/avisos de CHECK ----
        if (!empty($op['reparar_check'])) {
            $detalles = [];
            $reparadas = 0;
            $fallidas = 0;
            $sinErrores = 0;
            foreach ($listarTablas() as $t) {
                $nom = (string)$t['TABLE_NAME'];
                $motor = strtolower((string)($t['ENGINE'] ?? ''));
                $chk = $ejecutar('CHECK TABLE ' . $esc($nom));
                if ($chk['estado'] === 'ok') {
                    $sinErrores++;
                    continue;
                }
                $sqlRep = ($motor === 'myisam')
                    ? 'REPAIR TABLE ' . $esc($nom)
                    : 'ALTER TABLE ' . $esc($nom) . ' FORCE';
                $rep = $ejecutar($sqlRep);
                if ($rep['estado'] !== 'error') {
                    $reparadas++;
                    $detalles[] = ['tabla' => $nom, 'estado' => 'reparada', 'mensaje' => $chk['mensaje']];
                } else {
                    $fallidas++;
                    $detalles[] = ['tabla' => $nom, 'estado' => 'error', 'mensaje' => $rep['mensaje']];
                }
            }
            $out['pasos']['reparar_check'] = [
                'ejecutado' => true, 'sin_errores' => $sinErrores,
                'reparadas' => $reparadas, 'fallidas' => $fallidas, 'detalles' => $detalles,
            ];
            $res['reparadas'] = $reparadas;
            $res['reparadas_fallidas'] = $fallidas;
        }

        // ---- Paso 2: Optimizar tablas fragmentadas (data_free >= 5%) ----
        if (!empty($op['optimizar'])) {
            $detalles = [];
            $optimizadas = 0;
            $fallidas = 0;
            $omitidas = 0;
            foreach ($listarTablas() as $t) {
                $nom = (string)$t['TABLE_NAME'];
                $bytesTotal = (int)$t['DATA_LENGTH'] + (int)$t['INDEX_LENGTH'] + (int)$t['DATA_FREE'];
                $librePct = $bytesTotal > 0 ? 100 * (int)$t['DATA_FREE'] / $bytesTotal : 0.0;
                if ($librePct < 5.0) {
                    $omitidas++;
                    continue;
                }
                $r = $ejecutar('OPTIMIZE TABLE ' . $esc($nom));
                if ($r['estado'] !== 'error') {
                    $optimizadas++;
                    $detalles[] = ['tabla' => $nom, 'estado' => 'optimizada', 'mensaje' => round($librePct, 1) . '% libre antes'];
                } else {
                    $fallidas++;
                    $detalles[] = ['tabla' => $nom, 'estado' => 'error', 'mensaje' => $r['mensaje']];
                }
            }
            $out['pasos']['optimizar'] = [
                'ejecutado' => true, 'omitidas' => $omitidas,
                'optimizadas' => $optimizadas, 'fallidas' => $fallidas, 'detalles' => $detalles,
            ];
            $res['optimizadas'] = $optimizadas;
            $res['optimizadas_fallidas'] = $fallidas;
        }

        // ---- Paso 3: Estadísticas del optimizador (ANALYZE TABLE) ----
        if (!empty($op['analyze'])) {
            $detalles = [];
            $analizadas = 0;
            $fallidas = 0;
            foreach ($listarTablas() as $t) {
                $nom = (string)$t['TABLE_NAME'];
                $r = $ejecutar('ANALYZE TABLE ' . $esc($nom));
                if ($r['estado'] !== 'error') {
                    $analizadas++;
                } else {
                    $fallidas++;
                    $detalles[] = ['tabla' => $nom, 'estado' => 'error', 'mensaje' => $r['mensaje']];
                }
            }
            $out['pasos']['analyze'] = [
                'ejecutado' => true, 'analizadas' => $analizadas,
                'fallidas' => $fallidas, 'detalles' => $detalles,
            ];
            $res['analizadas'] = $analizadas;
            $res['analizadas_fallidas'] = $fallidas;
        }

        // ---- Paso 4: Crear índices faltantes para claves foráneas ----
        if (!empty($op['indices_fk'])) {
            $detalles = [];
            $creados = 0;
            $fallidos = 0;
            $fks = $pdo->query(
                "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
                  ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION"
            )->fetchAll(PDO::FETCH_ASSOC);
            $grupos = [];
            foreach ($fks as $f) {
                $clave = $f['TABLE_NAME'] . '::' . $f['CONSTRAINT_NAME'];
                $grupos[$clave]['tabla'] = (string)$f['TABLE_NAME'];
                $grupos[$clave]['constraint'] = (string)$f['CONSTRAINT_NAME'];
                $grupos[$clave]['columnas'][] = (string)$f['COLUMN_NAME'];
            }
            $stmtIdx = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1"
            );
            foreach ($grupos as $g) {
                $primera = $g['columnas'][0] ?? '';
                $stmtIdx->execute([$g['tabla'], $primera]);
                if ((int)$stmtIdx->fetchColumn() > 0) {
                    continue;
                }
                $nombreIdx = substr('fki_' . preg_replace('/[^A-Za-z0-9_]/', '_', $g['constraint']), 0, 64);
                $cols = implode(', ', array_map($esc, $g['columnas']));
                $r = $ejecutar('ALTER TABLE ' . $esc($g['tabla']) . ' ADD INDEX ' . $esc($nombreIdx) . ' (' . $cols . ')');
                if ($r['estado'] !== 'error') {
                    $creados++;
                    $detalles[] = ['tabla' => $g['tabla'], 'estado' => 'creado', 'mensaje' => $nombreIdx . ' (' . $cols . ')'];
                } else {
                    $fallidos++;
                    $detalles[] = ['tabla' => $g['tabla'], 'estado' => 'error', 'mensaje' => $r['mensaje']];
                }
            }
            $out['pasos']['indices_fk'] = [
                'ejecutado' => true, 'sin_faltantes' => count($grupos) - $creados - $fallidos,
                'creados' => $creados, 'fallidos' => $fallidos, 'detalles' => $detalles,
            ];
            $res['indices_creados'] = $creados;
            $res['indices_fallidos'] = $fallidos;
        }

        // ---- Paso 5: Unificar colaciones de texto a la mayoritaria ----
        if (!empty($op['unificar_collations'])) {
            $detalles = [];
            $unificadas = 0;
            $fallidas = 0;
            $colsTexto = $pdo->query(
                "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL"
            )->fetchAll(PDO::FETCH_ASSOC);
            $conteoColl = [];
            $porTabla = [];
            foreach ($colsTexto as $c) {
                $cl = (string)$c['COLLATION_NAME'];
                $conteoColl[$cl] = ($conteoColl[$cl] ?? 0) + 1;
            }
            if (count($conteoColl) > 1) {
                arsort($conteoColl);
                $mayoritaria = (string)array_key_first($conteoColl);
                $charset = explode('_', $mayoritaria)[0];
                foreach ($colsTexto as $c) {
                    if ((string)$c['COLLATION_NAME'] !== $mayoritaria) {
                        $porTabla[(string)$c['TABLE_NAME']][] = (string)$c['COLUMN_NAME'];
                    }
                }
                foreach ($porTabla as $tabla => $columnas) {
                    $r = $ejecutar('ALTER TABLE ' . $esc($tabla) . ' CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $mayoritaria);
                    if ($r['estado'] !== 'error') {
                        $unificadas++;
                        $detalles[] = ['tabla' => $tabla, 'estado' => 'unificada', 'mensaje' => implode(', ', $columnas) . ' → ' . $mayoritaria];
                    } else {
                        $fallidas++;
                        $detalles[] = ['tabla' => $tabla, 'estado' => 'error', 'mensaje' => $r['mensaje']];
                    }
                }
            }
            $out['pasos']['unificar_collations'] = [
                'ejecutado' => true, 'unificadas' => $unificadas,
                'fallidas' => $fallidas, 'detalles' => $detalles,
            ];
            $res['collations_unificadas'] = $unificadas;
            $res['collations_fallidas'] = $fallidas;
        }

        // ---- Paso 6: Eliminar filas huerfanas (hija sin padre) ----
        if (!empty($op['borrar_huerfanos'])) {
            $detalles = [];
            $borradas = 0;
            $grupos = [];
            $fks = $pdo->query(
                "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION,
                        REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
                  ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fks as $f) {
                $clave = $f['TABLE_NAME'] . '::' . $f['CONSTRAINT_NAME'];
                if (!isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'tabla'      => (string)$f['TABLE_NAME'],
                        'constraint' => (string)$f['CONSTRAINT_NAME'],
                        'columnas'   => [],
                        'padre'      => (string)$f['REFERENCED_TABLE_NAME'],
                        'padre_cols' => [],
                    ];
                }
                $grupos[$clave]['columnas'][] = (string)$f['COLUMN_NAME'];
                $grupos[$clave]['padre_cols'][] = (string)$f['REFERENCED_COLUMN_NAME'];
            }
            foreach ($grupos as $g) {
                $condOn = [];
                $condHija = [];
                foreach ($g['columnas'] as $i => $col) {
                    $padreCol = $g['padre_cols'][$i] ?? $col;
                    $condOn[] = 'h.' . $esc($col) . ' = p.' . $esc($padreCol);
                    $condHija[] = 'h.' . $esc($col) . ' IS NOT NULL';
                }
                $sql = 'DELETE h FROM ' . $esc($g['tabla']) . ' h
                          LEFT JOIN ' . $esc($g['padre']) . ' p ON ' . implode(' AND ', $condOn) . '
                         WHERE ' . implode(' AND ', $condHija) . ' AND p.' . $esc($g['padre_cols'][0]) . ' IS NULL';
                try {
                    $n = (int)$pdo->query($sql)->rowCount();
                    if ($n > 0) {
                        $borradas += $n;
                        $detalles[] = ['tabla' => $g['tabla'], 'estado' => 'eliminadas', 'mensaje' => $n . ' fila(s) huérfana(s) de ' . $g['constraint'] . ' (padre: ' . $g['padre'] . ')'];
                    }
                } catch (PDOException $e) {
                    $detalles[] = ['tabla' => $g['tabla'], 'estado' => 'error', 'mensaje' => $e->getMessage()];
                }
            }
            $out['pasos']['borrar_huerfanos'] = [
                'ejecutado' => true,
                'borradas' => $borradas,
                'detalles' => $detalles,
            ];
            $res['huerfanos_borrados'] = $borradas;
        }

        $res['fallidas'] = $res['reparadas_fallidas'] + $res['optimizadas_fallidas']
            + $res['analizadas_fallidas'] + $res['indices_fallidos']
            + ($res['collations_fallidas'] ?? 0);
        $out['resumen'] = $res;
        $out['segundos'] = round(microtime(true) - $t0, 2);
        return $out;
    }
}
