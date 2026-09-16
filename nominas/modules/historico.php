<?php
// modules/historico.php - Histórico de Operaciones del Sistema
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/historico.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Solo los roles con acceso al histórico pueden ver este módulo
if (!in_array(permiso_rol_codigo(), ['Admin', 'Soft'], true)) {
    permiso_denegar_acceso('Histórico de Operaciones');
}

// ============================================================
// PARÁMETROS DE FILTRADO (todos opcionales, via GET)
// ============================================================
$filtro_operacion = $_GET['operacion'] ?? '';
$filtro_usuario  = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$filtro_desde    = $_GET['desde'] ?? '';
$filtro_hasta    = $_GET['hasta'] ?? '';
$filtro_busqueda = trim($_GET['busqueda'] ?? '');

// Paginación
$pagina = max(1, isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1);
$por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;
if ($por_pagina < 10) $por_pagina = 50;
if ($por_pagina > 500) $por_pagina = 200;
$offset = ($pagina - 1) * $por_pagina;

// ============================================================
// CONSTRUCCIÓN DE LA CONSULTA CON FILTROS
// ============================================================
$where = [];
$params = [];

if ($filtro_operacion !== '') {
    $where[] = "operacion = ?";
    $params[] = $filtro_operacion;
}
if ($filtro_usuario > 0) {
    $where[] = "usuario_id = ?";
    $params[] = $filtro_usuario;
}
if ($filtro_desde !== '') {
    $where[] = "DATE(fecha_hora) >= ?";
    $params[] = $filtro_desde;
}
if ($filtro_hasta !== '') {
    $where[] = "DATE(fecha_hora) <= ?";
    $params[] = $filtro_hasta;
}
if ($filtro_busqueda !== '') {
    $where[] = "(descripcion LIKE ? OR usuario_nombre LIKE ?)";
    $like = '%' . $filtro_busqueda . '%';
    $params[] = $like;
    $params[] = $like;
}
$where_sql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Total de registros
$stmt_tot = $pdo->prepare("SELECT COUNT(*) FROM historico_operaciones" . $where_sql);
$stmt_tot->execute($params);
$total_registros = (int)$stmt_tot->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;
$offset = ($pagina - 1) * $por_pagina;

// Registros de la página actual
$sql = "SELECT id, usuario_id, usuario_nombre, operacion, descripcion, ip_address, fecha_hora
        FROM historico_operaciones" . $where_sql . "
        ORDER BY fecha_hora DESC, id DESC
        LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos auxiliares para filtros
$tipos = obtenerTiposOperacion($pdo);
$usuarios = obtenerUsuariosParaFiltro($pdo);

// Estadísticas del histórico
$stats = obtenerEstadisticasHistorial($pdo);

$base_prefix = '';
$current_file = 'historico.php';
$modulo_nombre = 'Histórico de Operaciones';
$modulo_icono = 'fa-clock-rotate-left';
$sm_detalle_titulo = 'Registro';

// Acciones POST (eliminar registro / vaciar historial)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'eliminar_registro' && isset($_POST['id'])) {
        $rid = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM historico_operaciones WHERE id = ?")->execute([$rid]);
            registrarOperacion('ELIMINAR_REGISTRO_HISTORIAL', 'Se eliminó un registro del histórico (ID ' . $rid . ').');
            $mensaje = 'Registro eliminado correctamente';
        } catch (PDOException $e) {
            $mensaje = 'Error al eliminar el registro';
        }
    } elseif ($_POST['accion'] === 'vaciar_historial') {
        try {
            $pdo->exec("DELETE FROM historico_operaciones");
            registrarOperacion('LIMPIAR_HISTORICO', 'Se vació el histórico de operaciones del sistema.');
            $mensaje = 'Histórico vaciado correctamente';
        } catch (PDOException $e) {
            $mensaje = 'Error al vaciar el histórico';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Histórico de Operaciones - SISGESNOM</title>
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/win11.css">
    <style>
        .main-container { margin-left: 16.25rem; }
        @media (max-width: 768px) { .main-container { margin-left: 0; } }
        .ops-badge { display:inline-flex; align-items:center; gap:0.4rem; font-size:0.78rem; border-radius:999px; padding:0.28rem 0.72rem; font-weight:600; }
        .table-historico td, .table-historico th { vertical-align:middle; padding:0.55rem 0.72rem; font-size:0.8rem; }
        .desc-cell { max-width:26rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    </style>
</head>
<body>
<div class="win11-bg"></div>
<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral"><i class="fas fa-bars"></i></button>
            <div class="page-title">
                <h1><i class="fas fa-clock-rotate-left me-2" style="color: #60a5fa;"></i>Histórico de Operaciones</h1>
                <p><i class="fas fa-history me-1"></i> Registro de actividades, accesos y modificaciones del sistema</p>
            </div>
        </div>
        <div class="btn-group" role="group" aria-label="Acciones del histórico">
            <a class="btn-win btn-win-primary" download href="?exportar=pdf" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf me-1"></i> PDF</a>
            <a class="btn-win btn-win-success" download href="?exportar=xlsx" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel me-1"></i> XLSX</a>
            <a class="btn-win btn-win-secondary" download href="?exportar=docx" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word me-1"></i> DOCX</a>
            <a class="btn-win btn-win-secondary" download href="?exportar=txt" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt me-1"></i> TXT</a>
            <button class="btn-win btn-win-danger" id="btnVaciar" data-tooltip="Vaciar todo el histórico" data-tooltip-theme="danger"><i class="fas fa-broom me-1"></i> Vaciar</button>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert" style="background:rgba(16,185,129,0.12); border:0.0625rem solid rgba(16,185,129,0.3); color:#d1fae5; border-radius:0.75rem;">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endif; ?>

    <!-- ============ FILTROS ============ -->
    <div class="glass-card fade-in-up" style="margin:1rem 1.25rem; padding:0.875rem;" id="filtrosCard">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3 col-lg-2">
                <label class="form-label mb-1">Tipo de Operación <i class="fas fa-tag" style="color:#60a5fa; font-size:0.7rem;"></i></label>
                <select name="operacion" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($filtro_operacion === $t) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label mb-1">Usuario <i class="fas fa-user" style="color:#60a5fa; font-size:0.7rem;"></i></label>
                <select name="usuario" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo ($filtro_usuario === (int)$u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre_completo'] . ' (' . $u['usuario'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Desde <i class="fas fa-calendar" style="color:#60a5fa; font-size:0.7rem;"></i></label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_desde); ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Hasta <i class="fas fa-calendar" style="color:#60a5fa; font-size:0.7rem;"></i></label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Búsqueda <i class="fas fa-search" style="color:#60a5fa; font-size:0.7rem;"></i></label>
                <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Buscar en descripción o usuario..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn-win btn-win-primary w-100"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="historico.php" class="btn-win btn-win-secondary" data-tooltip="Limpiar filtros" data-tooltip-theme="secondary"><i class="fas fa-eraser"></i></a>
            </div>
        </form>
    </div>

    <!-- ============ TABLA ============ -->
    <div class="glass-card fade-in-up" style="margin:0 1.25rem 1rem; padding:0.875rem;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div>
                <h6 class="m-0"><i class="fas fa-rotate me-2" style="color:#60a5fa;"></i>Actividades registradas</h6>
                <small class="text-muted"><i class="fas fa-users me-1"></i><?php echo number_format($stats['usuarios_activos'], 0); ?> usuarios · <?php echo $stats['tipos_unicos']; ?> tipos · <b><?php echo number_format($total_registros, 0); ?></b> operaciones</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <select class="form-select form-select-sm" id="selectPorPagina" onchange="var u=new URLSearchParams(location.search);u.set('por_pagina',this.value);u.set('pagina','1');location.search=u;">
                    <option value="50" <?php echo ($por_pagina==50)?'selected':''; ?>>50 / pág</option>
                    <option value="100" <?php echo ($por_pagina==100)?'selected':''; ?>>100 / pág</option>
                    <option value="200" <?php echo ($por_pagina==200)?'selected':''; ?>>200 / pág</option>
                </select>
            </div>
        </div>

        <?php if (empty($registros)): ?>
        <div class="text-center py-5" style="color:#9ca3af;">
            <i class="fas fa-inbox fa-4x mb-3" style="color:rgba(255,255,255,0.12);"></i>
            <p class="m-0">No se encontraron operaciones para los filtros seleccionados.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-historico" id="tablaHistorico">
                <thead class="table-secondary" style="--bs-table-bg: rgba(30,30,40,0.85);">
                    <tr>
                        <th>#</th>
                        <th>Operación</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>IP</th>
                        <th>Fecha / Hora</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $i => $r): 
                    $icono = obtenerIconoTipo($r['operacion']);
                    ?>
                    <tr>
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td>
                            <span class="ops-badge" style="background:rgba(96,165,250,0.12); color:#60a5fa; border:0.0625rem solid rgba(96,165,250,0.3);">
                                <i class="fas <?php echo $icono['icono']; ?>"></i><?php echo htmlspecialchars($r['operacion']); ?>
                            </span>
                        </td>
                        <td class="desc-cell" data-tooltip="<?php echo htmlspecialchars($r['descripcion']); ?>" data-tooltip-theme="primary" style="max-width:26rem;">
                            <?php echo htmlspecialchars(mb_substr($r['descripcion'] ?? '', 0, 120)); ?>
                        </td>
                        <td><i class="fas fa-user-circle me-1" style="color:#a78bfa;"></i><?php echo htmlspecialchars($r['usuario_nombre'] ?: ('#ID ' . $r['usuario_id'])); ?></td>
                        <td><span class="text-muted" style="font-family:monospace; font-size:0.72rem;"><?php echo htmlspecialchars($r['ip_address'] ?? '-'); ?></span></td>
                        <td><i class="fas fa-clock me-1" style="color:#34d399;"></i><?php echo formatoFechaHora12h($r['fecha_hora']); ?></td>
                        <td class="text-center">
                            <button class="btn-win btn-win-sm btn-win-primary" onclick="verDetalle(<?php echo (int)$r['id']; ?>)" data-tooltip="Ver detalle" data-tooltip-theme="primary"><i class="fas fa-eye"></i></button>
                            <button class="btn-win btn-win-sm btn-win-danger" onclick="fnEliminarRegistro(<?php echo (int)$r['id']; ?>, <?php echo json_encode($r['descripcion']); ?>)" data-tooltip="Eliminar registro" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============ PAGINACIÓN ============ -->
        <?php if ($total_paginas > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php
                $p_params = $_GET;
                $range = 4;
                $inicio = max(1, $pagina - $range);
                $fin = min($total_paginas, $pagina + $range);
                $fn_pag = function($p) use ($p_params) {
                    $p_params['pagina'] = $p;
                    return '?' . http_build_query($p_params);
                };
                if ($pagina > 1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo $fn_pag($pagina-1); ?>"><i class="fas fa-chevron-left"></i></a></li>
                <?php endif; 
                if ($inicio > 1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo $fn_pag(1); ?>">1</a></li>
                <?php if ($inicio > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif;
                for ($p = $inicio; $p <= $fin; $p++): ?>
                <li class="page-item <?php echo ($p == $pagina) ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $fn_pag($p); ?>"><?php echo $p; ?></a></li>
                <?php endfor;
                if ($fin < $total_paginas): 
                    if ($fin < $total_paginas - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?php echo $fn_pag($total_paginas); ?>"><?php echo $total_paginas; ?></a></li>
                <?php endif;
                if ($pagina < $total_paginas): ?>
                <li class="page-item"><a class="page-link" href="<?php echo $fn_pag($pagina+1); ?>"><i class="fas fa-chevron-right"></i></a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- ============ MODAL VER DETALLE ============ -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#17171f; border:0.0625rem solid rgba(255,255,255,0.12); border-radius:1rem;">
            <div class="modal-header" style="border-bottom:0.0625rem solid rgba(255,255,255,0.08);">
                <h5 class="modal-title"><i class="fas fa-file-lines me-2" style="color:#60a5fa;"></i>Detalle de la operación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modalDetalleBody"></div>
            <div class="modal-footer" style="border-top:0.0625rem solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win btn-win-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script>
// Render de detalle en modal
function verDetalle(id) {
    fetch('../ajax/get_historico_registro.php?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success) {
                Swal.fire({ icon:'error', title:'Error', text: res.message || 'No se pudo cargar el detalle', background:'#17171f', color:'#fff' });
                return;
            }
            var d = res.data;
            var icono = obtenerIconoServ(d.operacion);
            var filas = [
                ['Operación', '<span style="color:#60a5fa;">' + icono + ' ' + escH(d.operacion) + '</span>'],
                ['Descripción', escH(d.descripcion)],
                ['Usuario', escH(d.usuario_nombre || ('#ID ' + d.usuario_id))],
                ['Dirección IP', escH(d.ip_address || '-')],
                ['Fecha y Hora', escH(d.fecha_hora_formateada)]
            ];
            var html = '<table class="table table-sm text-white-50">';
            filas.forEach(function(f){
                html += '<tr><td style="width:38%; color:#94a3b8; font-size:0.78rem;">' + f[0] + '</td><td class="text-end" style="font-size:0.82rem;">' + f[1] + '</td></tr>';
            });
            html += '</table>';
            document.getElementById('modalDetalleBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        })
        .catch(function(){ Swal.fire({ icon:'error', title:'Error', text:'Error de red al obtener el detalle', background:'#17171f', color:'#fff' }); });
}
function obtenerIconoServ(op){ var o=(op||'').toUpperCase(); if(o.indexOf('LOGIN')!==-1)return '<i class="fas fa-sign-in-alt" style="color:#34d399;"></i>'; if(o.indexOf('LOGOUT')!==-1)return '<i class="fas fa-sign-out-alt" style="color:#fbbf24;"></i>'; if(o.indexOf('ELIMINAR')!==-1)return '<i class="fas fa-trash" style="color:#f87171;"></i>'; if(o.indexOf('CREAR')!==-1||o.indexOf('ALTA')!==-1||o.indexOf('INSERT')!==-1)return '<i class="fas fa-plus-circle" style="color:#34d399;"></i>'; if(o.indexOf('EDITAR')!==-1||o.indexOf('ACTUALIZAR')!==-1)return '<i class="fas fa-edit" style="color:#60a5fa;"></i>'; return '<i class="fas fa-circle-info" style="color:#94a3b8;"></i>'; }
function escH(s){ return (s==null?'':String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')); }
// Eliminar un registro
function fnEliminarRegistro(id, desc){
    Swal.fire({
        title:'¿Eliminar registro?', html:'Se eliminará del histórico la operación:<br><i class="fas fa-quote-left text-muted"></i> ' + escH(desc) + ' <i class="fas fa-quote-right text-muted"></i>', icon:'warning', showCancelButton:true, confirmButtonText:'<i class="fas fa-trash me-1"></i>Sí, eliminar', cancelButtonText:'Cancelar', confirmButtonColor:'#ef4444', background:'#17171f', color:'#fff'
    }).then(function(r){
        if (!r.isConfirmed) return;
        var fd = new FormData();
        fd.append('accion','eliminar_registro'); fd.append('id', id);
        fetch(window.location.pathname, { method:'POST', body: fd })
            .then(function(){ location.reload(); });
    });
}
// Vaciar histórico
document.getElementById('btnVaciar').addEventListener('click', function(){
    Swal.fire({
        title:'¿Vaciar todo el histórico?', html:'Se eliminarán <b>todas</b> las operaciones registradas. Esta acción no se puede deshacer.', icon:'warning', showCancelButton:true, confirmButtonText:'<i class="fas fa-broom me-1"></i>Sí, vaciar todo', cancelButtonText:'Cancelar', confirmButtonColor:'#ef4444', background:'#17171f', color:'#fff'
    }).then(function(r){
        if (!r.isConfirmed) return;
        var fd = new FormData();
        fd.append('accion','vaciar_historial');
        fetch(window.location.pathname, { method:'POST', body: fd })
            .then(function(){ location.reload(); });
    });
});
</script>
</body>
</html>
</content>