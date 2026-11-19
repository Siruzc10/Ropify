<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Panel de administración'; $activo = 'panel'; $subnav = 'admin';

$mes = date('Y-m-01 00:00:00');
$m = [
    'pendientes'  => (int)valor("SELECT COUNT(*) FROM prenda WHERE Estado = 'Pendiente'"),
    'denuncias'   => (int)valor("SELECT COUNT(*) FROM denuncia WHERE Estado IN ('Pendiente','En revision')"),
    'porPagar'    => (int)valor("SELECT COUNT(*) FROM pedido WHERE Estado = 'Pendiente_Pago'"),
    'porDespachar'=> (int)valor("SELECT COUNT(*) FROM pedido WHERE Estado = 'Pagado'"),
    'enviados'    => (int)valor("SELECT COUNT(*) FROM pedido WHERE Estado = 'Enviado'"),
    'liquidables' => (int)valor("SELECT COUNT(*) FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE pe.Estado = 'Entregado' AND d.Liquidado = 0"),
    'consultas'   => (int)valor("SELECT COUNT(*) FROM consulta_ayuda WHERE Estado = 'Pendiente'"),
    'usuarios'    => (int)valor("SELECT COUNT(*) FROM usuario WHERE Estado_Cuenta = 'Activa'"),
    'prendas'     => (int)valor("SELECT COUNT(*) FROM prenda WHERE Estado = 'Aprobada'"),
    'suscriptos'  => (int)valor('SELECT COUNT(*) FROM suscripcion_newsletter WHERE Activa = 1'),
];
$ventasMes = fila("SELECT COUNT(*) AS Items, COALESCE(SUM(d.Precio_Venta), 0) AS Ventas, COALESCE(SUM(d.Comision_Chuko), 0) AS Comisiones
    FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE pe.Estado IN ('Pagado','Enviado','Entregado') AND pe.Fecha_pedido >= ?", [$mes]);
$totalComisiones = (float)valor("SELECT COALESCE(SUM(d.Comision_Chuko), 0) FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE pe.Estado IN ('Pagado','Enviado','Entregado')");
$aLiquidar = (float)valor("SELECT COALESCE(SUM(d.Monto_Vendedor), 0) FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE pe.Estado = 'Entregado' AND d.Liquidado = 0");
$equilibrio = 87; // ventas mensuales para cubrir costos fijos (Carpeta de Campo, D.2.4)
$pct = min(100, (int)round($ventasMes['Items'] / $equilibrio * 100));
$ultimos = filas('SELECT r.*, u.AliasPublico FROM registro_admin r LEFT JOIN usuario u ON u.ID_Usuario = r.ID_Admin ORDER BY r.ID_Registro DESC LIMIT 8');
require __DIR__ . '/../includes/header.php';
?>
<h1>Panel de control</h1>
<p class="muted">Pendientes y métricas de la plataforma (CU-36).</p>
<div class="metricas">
  <a class="metrica<?= $m['pendientes'] ? ' alerta' : '' ?>" href="<?= e(url('admin/moderacion.php')) ?>"><b><?= $m['pendientes'] ?></b><span>Prendas por revisar</span></a>
  <a class="metrica<?= $m['denuncias'] ? ' alerta' : '' ?>" href="<?= e(url('admin/denuncias.php')) ?>"><b><?= $m['denuncias'] ?></b><span>Denuncias abiertas</span></a>
  <a class="metrica<?= $m['liquidables'] ? ' alerta' : '' ?>" href="<?= e(url('admin/liquidaciones.php')) ?>"><b><?= $m['liquidables'] ?></b><span>Ventas por liquidar (<?= dinero($aLiquidar) ?>)</span></a>
  <a class="metrica<?= $m['consultas'] ? ' alerta' : '' ?>" href="<?= e(url('admin/consultas.php')) ?>"><b><?= $m['consultas'] ?></b><span>Consultas sin atender</span></a>
</div>
<div class="metricas">
  <a class="metrica" href="<?= e(url('admin/pedidos.php?estado=Pendiente_Pago')) ?>"><b><?= $m['porPagar'] ?></b><span>Pedidos por pagar</span></a>
  <a class="metrica" href="<?= e(url('admin/pedidos.php?estado=Pagado')) ?>"><b><?= $m['porDespachar'] ?></b><span>Pagados, por despachar</span></a>
  <a class="metrica" href="<?= e(url('admin/pedidos.php?estado=Enviado')) ?>"><b><?= $m['enviados'] ?></b><span>En camino</span></a>
  <a class="metrica" href="<?= e(url('admin/usuarios.php')) ?>"><b><?= $m['usuarios'] ?></b><span>Usuarios activos</span></a>
</div>
<div class="dos-col iguales">
  <div class="caja">
    <h2>Este mes</h2>
    <div class="linea-total"><span>Prendas vendidas</span><b><?= (int)$ventasMes['Items'] ?></b></div>
    <div class="linea-total"><span>Ventas (sin envío)</span><b><?= dinero($ventasMes['Ventas']) ?></b></div>
    <div class="linea-total"><span>Comisiones (<?= porcentaje_comision() ?> %)</span><b><?= dinero($ventasMes['Comisiones']) ?></b></div>
    <div class="linea-total"><span>Comisiones acumuladas</span><b><?= dinero($totalComisiones) ?></b></div>
    <p class="muted" style="margin:12px 0 0"><small>Punto de equilibrio: <?= $equilibrio ?> ventas por mes (factibilidad financiera, D.2.4).</small></p>
    <div class="barra" aria-label="Avance hacia el punto de equilibrio"><i style="width:<?= $pct ?>%"></i></div>
    <small><?= (int)$ventasMes['Items'] ?> de <?= $equilibrio ?> (<?= $pct ?> %)</small>
  </div>
  <div class="caja">
    <h2>Plataforma</h2>
    <div class="linea-total"><span>Prendas publicadas</span><b><?= $m['prendas'] ?></b></div>
    <div class="linea-total"><span>Suscriptores al newsletter</span><b><?= $m['suscriptos'] ?></b></div>
    <h3 style="margin-top:16px">Últimas acciones registradas</h3>
    <?php if ($ultimos): ?>
      <?php foreach ($ultimos as $r): ?><p style="margin:0 0 6px"><small><?= e(fecha_hora($r['Fecha'])) ?> · <b><?= e($r['AliasPublico'] ?? 'Sistema') ?></b> — <?= e($r['Accion']) ?> (<?= e($r['Entidad'] . ($r['ID_Entidad'] ? ' #' . $r['ID_Entidad'] : '')) ?>)</small></p><?php endforeach; ?>
    <?php else: ?><p class="muted">Todavía no hay acciones registradas.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
