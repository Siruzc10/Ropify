<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Pedidos'; $activo = 'pedidos'; $subnav = 'admin';

if (es_post()) {
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id');
    if ($accion === 'cancelar') {                                     // incidencias: el admin puede cancelar pedidos aún no enviados (con reintegro si estaba pagado)
        if (cancelar_pedido($id, 'Cancelado por un administrador. ' . post('motivo'))) {
            registrar_admin('Pedido cancelado', 'pedido', $id, post('motivo'));
            aviso('Pedido #' . $id . ' cancelado; las prendas volvieron a la venta.', 'info');
        } else {
            aviso('Ese pedido ya no se puede cancelar (ya fue enviado o está cerrado).', 'warning');
        }
    } elseif ($accion === 'mantenimiento') {
        $r = mantenimiento();
        aviso('Mantenimiento ejecutado · sin pago: ' . $r['sin_pago'] . ' · sin despacho: ' . $r['sin_despacho'] . ' · entregas automáticas: ' . $r['entregados'], 'info');
    }
    ir(url('admin/pedidos.php' . (obtener('estado') !== '' ? '?estado=' . rawurlencode(obtener('estado')) : '')));
}
$estado = obtener('estado'); if (!isset(ESTADOS_PEDIDO[$estado])) $estado = '';
$lista = filas('SELECT pe.*, c.AliasPublico AS Comprador,
        (SELECT GROUP_CONCAT(DISTINCT v.AliasPublico SEPARATOR \', \') FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN usuario v ON v.ID_Usuario = p.ID_Vendedor WHERE d.ID_Pedido = pe.ID_Pedido) AS Vendedores,
        (SELECT COUNT(*) FROM detalle_pedido d WHERE d.ID_Pedido = pe.ID_Pedido) AS Prendas
    FROM pedido pe JOIN usuario c ON c.ID_Usuario = pe.ID_Comprador' . ($estado !== '' ? ' WHERE pe.Estado = ?' : '') . ' ORDER BY pe.Fecha_pedido DESC LIMIT 100', $estado !== '' ? [$estado] : []);
require __DIR__ . '/../includes/header.php';
?>
<div class="seccion-cab" style="margin-top:0"><h1 style="margin:0">Pedidos</h1>
  <?= boton_post('mantenimiento', [], 'Ejecutar mantenimiento ahora', 'btn btn-chico btn-sec') ?></div>
<p class="muted">El mantenimiento cancela pedidos sin pagar (<?= PLAZO_PAGO_HORAS ?> h), sin despachar (<?= PLAZO_DESPACHO_DIAS ?> días, RN-14) y da por entregados los enviados sin reclamo (<?= PLAZO_CONFIRMACION_DIAS ?> días, RN-08). Corre solo cada pocos minutos; acá podés forzarlo.</p>
<div class="tabs">
  <a<?= $estado === '' ? ' class="activo"' : '' ?> href="<?= e(url('admin/pedidos.php')) ?>">Todos</a>
  <?php foreach (ESTADOS_PEDIDO as $k => $n): ?><a<?= $estado === $k ? ' class="activo"' : '' ?> href="<?= e(url('admin/pedidos.php?estado=' . $k)) ?>"><?= e($n) ?></a><?php endforeach; ?>
</div>
<div class="tabla-scroll"><table>
  <thead><tr><th>Pedido</th><th>Comprador</th><th>Vendedor</th><th class="num-der">Total</th><th>Estado</th><th>Acción</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $pe): ?>
    <tr>
      <td><b>#<?= (int)$pe['ID_Pedido'] ?></b><br><small><?= e(fecha($pe['Fecha_pedido'])) ?> · <?= (int)$pe['Prendas'] ?> <?= plural((int)$pe['Prendas'], 'prenda', 'prendas') ?></small></td>
      <td><?= e($pe['Comprador']) ?></td><td><?= e($pe['Vendedores'] ?? '—') ?></td>
      <td class="num-der"><?= dinero($pe['Total']) ?><br><small><?= e($pe['Metodo_Pago']) ?></small></td>
      <td><?= badge_pedido($pe['Estado']) ?><?= $pe['Codigo_Seguimiento'] ? '<br><small>' . e(($pe['Servicio_Envio'] ? $pe['Servicio_Envio'] . ' · ' : '') . $pe['Codigo_Seguimiento']) . '</small>' : '' ?></td>
      <td><?php if (in_array($pe['Estado'], ['Pendiente_Pago', 'Pagado'], true)): ?>
        <form method="post" class="en-linea" data-confirmar="¿Cancelar el pedido #<?= (int)$pe['ID_Pedido'] ?>?<?= $pe['Estado'] === 'Pagado' ? ' Se solicitará el reintegro.' : '' ?>"><?= csrf_campo() ?><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id" value="<?= (int)$pe['ID_Pedido'] ?>">
          <input name="motivo" placeholder="Motivo" maxlength="150" style="width:130px;min-height:34px;padding:5px 8px"><button class="btn btn-chico btn-peligro btn-sec" type="submit">Cancelar</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="6" class="muted">No hay pedidos.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
