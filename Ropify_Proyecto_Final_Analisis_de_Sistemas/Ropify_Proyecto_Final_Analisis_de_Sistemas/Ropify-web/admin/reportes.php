<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Reportes'; $activo = 'reportes'; $subnav = 'admin';

$estadosVenta = "pe.Estado IN ('Pagado','Enviado','Entregado')";
$meses = filas("SELECT DATE_FORMAT(pe.Fecha_pedido, '%Y-%m') AS Mes, COUNT(DISTINCT pe.ID_Pedido) AS Pedidos, COUNT(*) AS Prendas,
        SUM(d.Precio_Venta) AS Ventas, SUM(d.Comision_Chuko) AS Comisiones, SUM(d.Monto_Vendedor) AS Vendedores
    FROM pedido pe JOIN detalle_pedido d ON d.ID_Pedido = pe.ID_Pedido WHERE $estadosVenta
    GROUP BY DATE_FORMAT(pe.Fecha_pedido, '%Y-%m') ORDER BY Mes DESC LIMIT 12");

if (obtener('csv') === '1') {                                         // exportación para planilla
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ropify_ventas_por_mes.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Mes', 'Pedidos', 'Prendas', 'Ventas', 'Comisiones', 'A vendedores'], ';');
    foreach ($meses as $m) fputcsv($o, [$m['Mes'], $m['Pedidos'], $m['Prendas'], $m['Ventas'], $m['Comisiones'], $m['Vendedores']], ';');
    fclose($o);
    exit;
}

$categorias = filas("SELECT c.Nombre, COUNT(*) AS Prendas, SUM(d.Precio_Venta) AS Ventas
    FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN categoria c ON c.ID_Categoria = p.ID_Categoria
    WHERE $estadosVenta GROUP BY c.ID_Categoria, c.Nombre ORDER BY Ventas DESC LIMIT 10");
$vendedores = filas("SELECT u.AliasPublico, COUNT(*) AS Prendas, SUM(d.Precio_Venta) AS Ventas
    FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor
    WHERE $estadosVenta GROUP BY u.ID_Usuario, u.AliasPublico ORDER BY Ventas DESC LIMIT 10");
$estados = filas('SELECT Estado, COUNT(*) AS Cantidad FROM pedido GROUP BY Estado');
$totalPedidos = array_sum(array_column($estados, 'Cantidad'));
$cancelados = 0; foreach ($estados as $e2) if ($e2['Estado'] === 'Cancelado') $cancelados = (int)$e2['Cantidad'];
require __DIR__ . '/../includes/header.php';
?>
<div class="seccion-cab" style="margin-top:0"><h1 style="margin:0">Reportes</h1><a class="btn btn-chico btn-sec" href="<?= e(url('admin/reportes.php?csv=1')) ?>">Descargar ventas por mes (CSV)</a></div>
<h2>Ventas por mes</h2>
<?php if ($meses): ?>
<div class="tabla-scroll"><table>
  <thead><tr><th>Mes</th><th class="num-der">Pedidos</th><th class="num-der">Prendas</th><th class="num-der">Ventas</th><th class="num-der">Comisión Ropify</th><th class="num-der">A vendedores</th></tr></thead>
  <tbody><?php foreach ($meses as $m): ?><tr><td><?= e($m['Mes']) ?></td><td class="num-der"><?= (int)$m['Pedidos'] ?></td><td class="num-der"><?= (int)$m['Prendas'] ?></td><td class="num-der"><?= dinero($m['Ventas']) ?></td><td class="num-der"><b><?= dinero($m['Comisiones']) ?></b></td><td class="num-der"><?= dinero($m['Vendedores']) ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<?php else: ?><div class="vacio"><p>Todavía no hay ventas pagadas.</p></div><?php endif; ?>
<div class="dos-col iguales">
  <div><h2>Categorías más vendidas</h2>
    <?php if ($categorias): ?><div class="tabla-scroll"><table style="min-width:0"><thead><tr><th>Categoría</th><th class="num-der">Prendas</th><th class="num-der">Ventas</th></tr></thead><tbody>
      <?php foreach ($categorias as $c): ?><tr><td><?= e($c['Nombre']) ?></td><td class="num-der"><?= (int)$c['Prendas'] ?></td><td class="num-der"><?= dinero($c['Ventas']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">Sin datos todavía.</p><?php endif; ?></div>
  <div><h2>Vendedores con más ventas</h2>
    <?php if ($vendedores): ?><div class="tabla-scroll"><table style="min-width:0"><thead><tr><th>Vendedor</th><th class="num-der">Prendas</th><th class="num-der">Ventas</th></tr></thead><tbody>
      <?php foreach ($vendedores as $c): ?><tr><td><?= e($c['AliasPublico']) ?></td><td class="num-der"><?= (int)$c['Prendas'] ?></td><td class="num-der"><?= dinero($c['Ventas']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">Sin datos todavía.</p><?php endif; ?></div>
</div>
<h2>Calidad del servicio</h2>
<div class="caja">
  <p style="margin:0">Pedidos totales: <b><?= (int)$totalPedidos ?></b> · Cancelados: <b><?= $cancelados ?></b><?= $totalPedidos ? ' (' . round($cancelados / $totalPedidos * 100, 1) . ' %)' : '' ?></p>
  <p class="muted" style="margin:6px 0 0"><small>Meta de la Carpeta de Campo (C.1.5): menos del 5 % de pedidos con incidencias.</small></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
