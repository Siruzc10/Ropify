<?php
require_once __DIR__ . '/includes/bootstrap.php';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pe = $id ? fila('SELECT pe.*, d.Calle, d.Numero, d.Piso_depto, d.Localidad, d.Provincia, d.Codigo_Postal
    FROM pedido pe JOIN direccion d ON d.ID_Direccion = pe.ID_Direccion WHERE pe.ID_Pedido = ?', [$id]) : null;
if (!$pe || ((int)$pe['ID_Comprador'] !== $uid && !es_admin($u))) pagina_error(404, 'Pedido no encontrado', 'No encontramos ese pedido entre tus compras.');
$esComprador = (int)$pe['ID_Comprador'] === $uid;

if (es_post() && $esComprador) {
    verificar_csrf();
    $accion = post('accion');
    if ($accion === 'cancelar') {                                   // CU-20
        if (cancelar_pedido($id, post('motivo'))) {
            aviso($pe['Estado'] === 'Pagado' ? 'Pedido cancelado. Solicitamos el reintegro del importe.' : 'Pedido cancelado. Las prendas volvieron a estar disponibles.', 'info');
        } else {
            aviso('Ese pedido ya no se puede cancelar (ya fue enviado). Si hay un problema, escribinos desde Ayuda.', 'warning'); // CP-28
        }
    } elseif ($accion === 'recibido') {                             // CU-22
        $ok = confirmar_entrega($id);
        aviso($ok ? '¡Gracias por confirmar! Ya liberamos el pago al vendedor.' : 'Ese pedido no está en estado «Enviado».', $ok ? 'ok' : 'warning');
    }
    ir(url('pedido.php?id=' . $id));
}

$items = items_pedido($id);
$titulo = 'Pedido #' . $id;
$pasos = ['Pendiente_Pago' => 'Pedido creado', 'Pagado' => 'Pagado', 'Enviado' => 'Enviado', 'Entregado' => 'Entregado'];
$orden = array_keys($pasos);
$cancelado = $pe['Estado'] === 'Cancelado';
$idxActual = array_search($pe['Estado'], $orden, true);
$puedeArrepentirse = in_array($pe['Estado'], ['Pagado', 'Enviado', 'Entregado'], true) && strtotime((string)($pe['Fecha_Entrega'] ?: $pe['Fecha_pedido'])) >= time() - DIAS_ARREPENTIMIENTO * 86400;
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('compras.php')) ?>">← Mis compras</a></p>
<h1>Pedido #<?= $id ?> <?= badge_pedido($pe['Estado']) ?></h1>

<?php if (!$cancelado): ?>
<ol class="estados">
  <?php foreach ($pasos as $k => $nombre): ?><li class="<?= array_search($k, $orden, true) <= $idxActual ? 'hecho' : '' ?>"><?= e($nombre) ?></li><?php endforeach; ?>
</ol>
<?php endif; ?>

<div class="dos-col">
  <div>
    <div class="caja">
      <h2>Prendas</h2>
      <?php foreach ($items as $it): ?>
        <div style="display:flex;gap:12px;align-items:center;padding:8px 0;border-top:1px solid var(--linea)">
          <div style="width:60px;flex:none"><?= foto_html($it['Foto'], $it['Titulo']) ?></div>
          <div style="flex:1;min-width:0"><a href="<?= e(url('prenda.php?id=' . (int)$it['ID_Prenda'])) ?>"><b><?= e($it['Titulo']) ?></b></a><br><small>Vende: <?= e($it['AliasPublico']) ?> · Talle <?= e($it['Talle']) ?></small></div>
          <div><b><?= dinero($it['Precio_Venta']) ?></b></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="caja">
      <h2>Envío</h2>
      <p style="margin:0">Dirección: <?= e(texto_direccion($pe)) ?></p>
      <?php if ($pe['Codigo_Seguimiento']): ?>
        <p style="margin:8px 0 0"><b>Seguimiento:</b> <?= e(($pe['Servicio_Envio'] ? $pe['Servicio_Envio'] . ' · ' : '') . $pe['Codigo_Seguimiento']) ?><?= $pe['Fecha_Envio'] ? ' <small>(despachado el ' . e(fecha($pe['Fecha_Envio'])) . ')</small>' : '' ?></p>
      <?php elseif (!$cancelado): ?>
        <p class="muted" style="margin:8px 0 0">El vendedor todavía no registró el despacho. Lo hace con un código de seguimiento que vas a ver acá.</p>
      <?php endif; ?>
    </div>
  </div>
  <aside>
    <div class="caja">
      <h2>Importes</h2>
      <div class="linea-total"><span>Subtotal</span><span><?= dinero($pe['Subtotal']) ?></span></div>
      <div class="linea-total"><span>Envío</span><span><?= dinero($pe['Costo_Envio']) ?></span></div>
      <div class="linea-total fuerte"><span>Total</span><span><?= dinero($pe['Total']) ?></span></div>
      <p class="muted"><small>Medio de pago: <?= e($pe['Metodo_Pago']) ?><?= $pe['Fecha_Pago'] ? ' · pagado el ' . e(fecha($pe['Fecha_Pago'])) : '' ?></small></p>
    </div>
    <?php if ($esComprador): ?>
    <div class="caja">
      <h2>Acciones</h2>
      <div class="acciones" style="flex-direction:column;align-items:stretch">
        <?php if ($pe['Estado'] === 'Pendiente_Pago'): ?><a class="btn btn-ambar" href="<?= e(url('pago.php?ids=' . $id)) ?>">Pagar ahora</a><?php endif; ?>
        <?php if ($pe['Estado'] === 'Enviado'): ?>
          <form method="post" data-confirmar="¿Confirmás que recibiste la prenda? Con esto liberamos el pago al vendedor."><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="recibido"><button class="btn btn-bloque" type="submit">Confirmar recepción</button></form>
        <?php endif; ?>
        <?php if (in_array($pe['Estado'], ['Pendiente_Pago', 'Pagado'], true)): ?>
          <form method="post" data-confirmar="¿Cancelar este pedido?"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="cancelar">
            <?= campo('Motivo (opcional)', 'motivo', '', ['maxlength' => 200]) ?>
            <button class="btn btn-peligro btn-sec btn-bloque" type="submit">Cancelar pedido</button></form>
        <?php elseif (in_array($pe['Estado'], ['Enviado', 'Entregado'], true)): ?>
          <a class="btn btn-sec" href="<?= e(url('ayuda.php?tipo=reclamo&pedido=' . $id)) ?>">Hacer un reclamo</a>
        <?php endif; ?>
        <?php if ($puedeArrepentirse): ?><a class="btn btn-sec" href="<?= e(url('arrepentimiento.php')) ?>">Botón de arrepentimiento</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
