<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mis compras';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$filtro = obtener('estado');
if (!isset(ESTADOS_PEDIDO[$filtro])) $filtro = '';

$pedidos = filas('SELECT * FROM pedido WHERE ID_Comprador = ?' . ($filtro !== '' ? ' AND Estado = ?' : '') . ' ORDER BY Fecha_pedido DESC, ID_Pedido DESC',
    $filtro !== '' ? [$uid, $filtro] : [$uid]);
$porPagar = array_values(array_filter($pedidos, static fn($p) => $p['Estado'] === 'Pendiente_Pago'));
require __DIR__ . '/includes/header.php';
?>
<h1>Mis compras</h1>
<div class="tabs">
  <a<?= $filtro === '' ? ' class="activo"' : '' ?> href="<?= e(url('compras.php')) ?>">Todas</a>
  <?php foreach (ESTADOS_PEDIDO as $k => $nombre): ?><a<?= $filtro === $k ? ' class="activo"' : '' ?> href="<?= e(url('compras.php?estado=' . $k)) ?>"><?= e($nombre) ?></a><?php endforeach; ?>
</div>
<?php if ($porPagar && $filtro === ''): ?>
  <div class="aviso aviso-warning">Tenés <?= count($porPagar) ?> <?= plural(count($porPagar), 'pedido', 'pedidos') ?> pendiente(s) de pago.
    <a href="<?= e(url('pago.php?ids=' . implode(',', array_map(static fn($p) => (int)$p['ID_Pedido'], $porPagar)))) ?>">Pagar ahora</a></div>
<?php endif; ?>
<?php if ($pedidos): ?>
  <?php foreach ($pedidos as $pe): $its = items_pedido((int)$pe['ID_Pedido']); ?>
    <div class="caja">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center">
        <div><b>Pedido #<?= (int)$pe['ID_Pedido'] ?></b> <small>· <?= e(fecha($pe['Fecha_pedido'])) ?></small></div>
        <div><?= badge_pedido($pe['Estado']) ?></div>
      </div>
      <div style="display:flex;gap:8px;margin:10px 0;overflow-x:auto">
        <?php foreach ($its as $it): ?>
          <a href="<?= e(url('prenda.php?id=' . (int)$it['ID_Prenda'])) ?>" style="width:64px;flex:none" title="<?= e($it['Titulo']) ?>"><?= foto_html($it['Foto'], $it['Titulo']) ?></a>
        <?php endforeach; ?>
      </div>
      <p style="margin:0 0 10px"><?= count($its) ?> <?= plural(count($its), 'prenda', 'prendas') ?> · Total <b><?= dinero($pe['Total']) ?></b>
        <?php if ($pe['Codigo_Seguimiento']): ?><br><small>Seguimiento: <?= e(($pe['Servicio_Envio'] ? $pe['Servicio_Envio'] . ' · ' : '') . $pe['Codigo_Seguimiento']) ?></small><?php endif; ?></p>
      <div class="acciones">
        <a class="btn btn-chico" href="<?= e(url('pedido.php?id=' . (int)$pe['ID_Pedido'])) ?>">Ver detalle</a>
        <?php if ($pe['Estado'] === 'Pendiente_Pago'): ?><a class="btn btn-chico btn-ambar" href="<?= e(url('pago.php?ids=' . (int)$pe['ID_Pedido'])) ?>">Pagar</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="vacio"><p>No hay compras<?= $filtro !== '' ? ' en ese estado' : ' todavía' ?>.</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
