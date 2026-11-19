<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mi liquidación';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

// CU-28 / RF-30: lo que el vendedor cobra (85 %) y en qué estado está
$base = 'FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE p.ID_Vendedor = ? ';
$enCurso   = (float)valor("SELECT COALESCE(SUM(d.Monto_Vendedor), 0) $base AND pe.Estado IN ('Pagado','Enviado')", [$uid]);
$porCobrar = (float)valor("SELECT COALESCE(SUM(d.Monto_Vendedor), 0) $base AND pe.Estado = 'Entregado' AND d.Liquidado = 0", [$uid]);
$cobrado   = (float)valor("SELECT COALESCE(SUM(d.Monto_Vendedor), 0) $base AND d.Liquidado = 1", [$uid]);
$detalle = filas("SELECT d.*, p.Titulo, pe.Estado AS EstadoPedido, pe.Fecha_Entrega $base AND pe.Estado <> 'Cancelado' AND pe.Estado <> 'Pendiente_Pago' ORDER BY pe.Fecha_pedido DESC, d.ID_Pedido DESC", [$uid]);
require __DIR__ . '/includes/header.php';
?>
<h1>Mi liquidación</h1>
<?php if (empty($u['Alias_Cobro'])): ?>
  <div class="aviso aviso-warning"><b>Falta tu alias de cobro.</b> Sin él no podemos transferirte el dinero. <a href="<?= e(url('perfil.php')) ?>">Cargalo en Mi perfil</a>.</div>
<?php else: ?>
  <p class="muted">Te transferimos al alias <b><?= e($u['Alias_Cobro']) ?></b> el <?= porcentaje_vendedor() ?> % de cada venta una vez que el pedido figura como «Entregado».</p>
<?php endif; ?>
<div class="metricas">
  <div class="metrica"><b><?= dinero($enCurso) ?></b><span>En camino (pagado, aún sin entregar)</span></div>
  <div class="metrica alerta"><b><?= dinero($porCobrar) ?></b><span>Entregado, pendiente de liquidar</span></div>
  <div class="metrica"><b><?= dinero($cobrado) ?></b><span>Ya liquidado</span></div>
</div>
<?php if ($detalle): ?>
<div class="tabla-scroll"><table>
  <thead><tr><th>Pedido</th><th>Prenda</th><th class="num-der">Precio</th><th class="num-der">Comisión</th><th class="num-der">Cobrás</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($detalle as $d): ?>
    <tr>
      <td>#<?= (int)$d['ID_Pedido'] ?></td><td><?= e($d['Titulo']) ?></td>
      <td class="num-der"><?= dinero($d['Precio_Venta']) ?></td><td class="num-der">− <?= dinero($d['Comision_Chuko']) ?></td><td class="num-der"><b><?= dinero($d['Monto_Vendedor']) ?></b></td>
      <td><?php if ($d['Liquidado']): ?><?= badge('Liquidado el ' . fecha($d['Fecha_Liquidacion']), 'ok') ?><?= $d['Comprobante_Liquidacion'] ? '<br><small>Comprobante: ' . e($d['Comprobante_Liquidacion']) . '</small>' : '' ?>
          <?php elseif ($d['EstadoPedido'] === 'Entregado'): ?><?= badge('Por liquidar', 'aviso') ?>
          <?php else: ?><?= badge(ESTADOS_PEDIDO[$d['EstadoPedido']], 'info') ?><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php else: ?>
  <div class="vacio"><p>Todavía no tenés ventas pagadas.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
