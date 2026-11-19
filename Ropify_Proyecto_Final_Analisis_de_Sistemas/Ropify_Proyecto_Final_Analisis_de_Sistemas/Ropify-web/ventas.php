<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mis ventas';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

// Pedidos que contienen al menos una prenda mía
const SQL_MIS_PEDIDOS = 'pe.ID_Pedido IN (SELECT d2.ID_Pedido FROM detalle_pedido d2 JOIN prenda p2 ON p2.ID_Prenda = d2.ID_Prenda WHERE p2.ID_Vendedor = ?)';

if (es_post() && post('accion') === 'despachar') {                    // CU-27 · RF-28
    verificar_csrf();
    $id = (int)post('id'); $servicio = post('servicio'); $codigo = post('codigo');
    $pe = valor('SELECT COUNT(*) FROM pedido pe WHERE pe.ID_Pedido = ? AND ' . SQL_MIS_PEDIDOS, [$id, $uid]) ? fila('SELECT * FROM pedido WHERE ID_Pedido = ?', [$id]) : null;
    if (!$pe)                                         aviso('No encontramos ese pedido entre tus ventas.', 'error');
    elseif ($pe['Estado'] !== 'Pagado')               aviso('Ese pedido no está en estado «Pagado», no se puede despachar.', 'warning');
    elseif (!in_array($servicio, SERVICIOS_ENVIO, true)) aviso('Elegí el servicio de envío.', 'error');
    elseif ($codigo === '' || largo($codigo) > 50)     aviso('Ingresá el código de seguimiento (hasta 50 caracteres): sin código no se puede marcar como enviado.', 'error'); // CP-30
    else {
        $st = q("UPDATE pedido SET Estado = 'Enviado', Codigo_Seguimiento = ?, Servicio_Envio = ?, Fecha_Envio = ? WHERE ID_Pedido = ? AND Estado = 'Pagado'", [$codigo, $servicio, ahora(), $id]);
        if ($st->rowCount() === 1) {
            notificar((int)$pe['ID_Comprador'], 'Ropify · Tu pedido #' . $id . ' va en camino', "El vendedor despachó tu pedido con $servicio.\nCódigo de seguimiento: $codigo\nCuando lo recibas, confirmalo desde: " . url_abs('pedido.php?id=' . $id));
            aviso('Pedido #' . $id . ' marcado como enviado. Avisamos al comprador.');
        } else {
            aviso('El pedido cambió de estado mientras tanto. Recargá la página.', 'warning');
        }
    }
    ir(url('ventas.php'));
}

$filtro = obtener('estado');
if (!isset(ESTADOS_PEDIDO[$filtro])) $filtro = '';
$pedidos = filas('SELECT pe.*, u.Nombre, u.Apellido, u.AliasPublico AS AliasComprador, d.Calle, d.Numero, d.Piso_depto, d.Localidad, d.Provincia, d.Codigo_Postal
    FROM pedido pe JOIN usuario u ON u.ID_Usuario = pe.ID_Comprador JOIN direccion d ON d.ID_Direccion = pe.ID_Direccion
    WHERE ' . SQL_MIS_PEDIDOS . ($filtro !== '' ? ' AND pe.Estado = ?' : '') . ' ORDER BY (pe.Estado = \'Pagado\') DESC, pe.Fecha_pedido DESC',
    $filtro !== '' ? [$uid, $filtro] : [$uid]);
$porDespachar = (int)valor("SELECT COUNT(*) FROM pedido pe WHERE pe.Estado = 'Pagado' AND " . SQL_MIS_PEDIDOS, [$uid]);
require __DIR__ . '/includes/header.php';
?>
<h1>Mis ventas</h1>
<?php if (empty($u['Alias_Cobro'])): ?><div class="aviso aviso-warning">Cargá tu alias de cobro para que podamos liquidarte. <a href="<?= e(url('perfil.php')) ?>">Ir a Mi perfil</a>.</div><?php endif; ?>
<div class="tabs">
  <a<?= $filtro === '' ? ' class="activo"' : '' ?> href="<?= e(url('ventas.php')) ?>">Todas</a>
  <a<?= $filtro === 'Pagado' ? ' class="activo"' : '' ?> href="<?= e(url('ventas.php?estado=Pagado')) ?>">Por despachar<?= $porDespachar ? ' (' . $porDespachar . ')' : '' ?></a>
  <?php foreach (['Enviado', 'Entregado', 'Pendiente_Pago', 'Cancelado'] as $k): ?><a<?= $filtro === $k ? ' class="activo"' : '' ?> href="<?= e(url('ventas.php?estado=' . $k)) ?>"><?= e(ESTADOS_PEDIDO[$k]) ?></a><?php endforeach; ?>
</div>
<?php if (!$pedidos): ?>
  <div class="vacio"><p><?= $filtro === 'Pagado' ? 'No tenés envíos pendientes.' : 'No hay ventas para mostrar.' ?></p></div>
<?php endif; ?>
<?php foreach ($pedidos as $pe):
    $mias = filas('SELECT d.*, p.Titulo, p.Talle FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda WHERE d.ID_Pedido = ? AND p.ID_Vendedor = ?', [$pe['ID_Pedido'], $uid]);
    $monto = array_sum(array_map(static fn($i) => (float)$i['Monto_Vendedor'], $mias));
    $limite = strtotime((string)($pe['Fecha_Pago'] ?: $pe['Fecha_pedido'])) + PLAZO_DESPACHO_DIAS * 86400;
?>
  <div class="caja">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px"><b>Pedido #<?= (int)$pe['ID_Pedido'] ?> <small>· <?= e(fecha($pe['Fecha_pedido'])) ?></small></b><?= badge_pedido($pe['Estado']) ?></div>
    <?php foreach ($mias as $it): ?><div class="linea-total"><span><?= e($it['Titulo']) ?> <small>(talle <?= e($it['Talle']) ?>)</small></span><span><?= dinero($it['Precio_Venta']) ?></span></div><?php endforeach; ?>
    <div class="linea-total"><span class="muted">Comisión Ropify (<?= porcentaje_comision() ?> %)</span><span class="muted">− <?= dinero(array_sum(array_map(static fn($i) => (float)$i['Comision_Chuko'], $mias))) ?></span></div>
    <div class="linea-total fuerte"><span>Vos cobrás</span><span><?= dinero($monto) ?></span></div>
    <?php if (in_array($pe['Estado'], ['Pagado', 'Enviado', 'Entregado'], true)): ?>
      <p class="muted" style="margin:10px 0 0"><b>Enviar a:</b> <?= e($pe['Nombre'] . ' ' . $pe['Apellido']) ?> (<?= e($pe['AliasComprador']) ?>) · <?= e(texto_direccion($pe)) ?></p>
    <?php endif; ?>
    <?php if ($pe['Estado'] === 'Pagado'): ?>
      <p class="aviso aviso-warning" style="margin:10px 0">Despachalo antes del <b><?= e(date('d/m/Y', $limite)) ?></b>; si no, el pedido se cancela y se reintegra al comprador.</p>
      <form method="post" style="max-width:none"><?= csrf_campo() ?><input type="hidden" name="accion" value="despachar"><input type="hidden" name="id" value="<?= (int)$pe['ID_Pedido'] ?>">
        <div class="fila-campos">
          <?= selector('Servicio de envío', 'servicio', lista_a_opciones(SERVICIOS_ENVIO), '', 'Elegir…', ['required' => true]) ?>
          <?= campo('Código de seguimiento', 'codigo', '', ['maxlength' => 50, 'required' => true]) ?>
        </div>
        <button class="btn" type="submit">Marcar como enviado</button>
      </form>
    <?php elseif ($pe['Codigo_Seguimiento']): ?>
      <p class="muted" style="margin:10px 0 0">Seguimiento: <?= e(($pe['Servicio_Envio'] ? $pe['Servicio_Envio'] . ' · ' : '') . $pe['Codigo_Seguimiento']) ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
