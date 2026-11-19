<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Finalizar compra';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

$items = [];
foreach (carrito() as $idp) {
    $p = fila(sql_prenda() . "WHERE p.ID_Prenda = ? AND p.Estado = 'Aprobada' AND u.Estado_Cuenta = 'Activa'", [$idp]);
    if ($p && (int)$p['ID_Vendedor'] !== $uid) $items[] = $p;
}
if (count($items) !== count(carrito())) {
    if (carrito()) aviso('Alguna prenda de tu carrito ya no está disponible y se quitó.', 'warning');
    carrito_guardar(array_map(static fn($p) => (int)$p['ID_Prenda'], $items));
}
if (!$items) { aviso('Tu carrito está vacío.', 'info'); ir(url('carrito.php')); }

$grupos = [];
foreach ($items as $p) $grupos[(int)$p['ID_Vendedor']][] = $p;
$subtotal = array_sum(array_map(static fn($p) => (float)$p['Precio'], $items));
$envio = COSTO_ENVIO * count($grupos);
$dirs = filas('SELECT * FROM direccion WHERE ID_Usuario = ? ORDER BY ID_Direccion', [$uid]);
$err = [];

if (es_post()) {
    verificar_csrf();
    $metodo = post('metodo');
    if (!in_array($metodo, METODOS_PAGO, true)) $err[] = 'Elegí un método de pago.';

    // CU-18 paso 4 / CP-23: sin dirección cargada hay que cargar una antes de continuar
    $idDir = post('direccion'); $nueva = null;
    if ($idDir === 'nueva' || !$dirs) {
        [$nueva, $e2] = leer_direccion();
        $err = array_merge($err, $e2);
    } elseif (!valor('SELECT COUNT(*) FROM direccion WHERE ID_Direccion = ? AND ID_Usuario = ?', [(int)$idDir, $uid])) {
        $err[] = 'Elegí una dirección de envío.';
    }

    if (!$err) {
        $pdo = db(); $pdo->beginTransaction();
        $creados = [];
        try {
            $dirId = $nueva ? guardar_direccion($uid, $nueva) : (int)$idDir;
            foreach ($grupos as $idVendedor => $lista) {          // un pedido por vendedor
                $sub = array_sum(array_map(static fn($p) => (float)$p['Precio'], $lista));
                q("INSERT INTO pedido (ID_Comprador, ID_Direccion, Fecha_pedido, Subtotal, Costo_Envio, Total, Metodo_Pago, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente_Pago')",
                  [$uid, $dirId, ahora(), fmt2($sub), fmt2(COSTO_ENVIO), fmt2($sub + COSTO_ENVIO), $metodo]);   // RN-07: Total = Subtotal + Envío
                $idPedido = (int)$pdo->lastInsertId();
                foreach ($lista as $p) {
                    [$comision, $monto] = calcular_comision((float)$p['Precio']);                                  // RN-05 / RF-22
                    q('INSERT INTO detalle_pedido (ID_Pedido, ID_Prenda, Precio_Venta, Comision_Chuko, Monto_Vendedor) VALUES (?, ?, ?, ?, ?)',
                      [$idPedido, $p['ID_Prenda'], fmt2($p['Precio']), fmt2($comision), fmt2($monto)]);
                    // Reserva atómica: solo pasa a «Vendida» si seguía «Aprobada» (CP-21: dos compradores, una prenda)
                    $st = q("UPDATE prenda SET Estado = 'Vendida' WHERE ID_Prenda = ? AND Estado = 'Aprobada'", [$p['ID_Prenda']]);
                    if ($st->rowCount() !== 1) throw new RuntimeException('no disponible');
                }
                $creados[] = $idPedido;
            }
            $pdo->commit();
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $creados = [];
            $err[] = $t instanceof RuntimeException
                ? 'Otra persona compró una de las prendas justo antes que vos. Revisá tu carrito.'
                : 'No pudimos registrar la compra, reintentá en unos minutos.';
            if (!($t instanceof RuntimeException)) error_log('[Ropify] checkout: ' . $t->getMessage());
        }
        if ($creados) {
            carrito_guardar([]);
            $lineas = '';
            foreach ($creados as $idp) $lineas .= '· Pedido #' . $idp . ' — total ' . dinero(valor('SELECT Total FROM pedido WHERE ID_Pedido = ?', [$idp])) . "\n";
            enviar_correo($u['Email'], 'Ropify · Comprobante de tu compra', "¡Gracias por tu compra!\n$lineas\nTenés " . PLAZO_PAGO_HORAS . " horas para pagar. Seguilo desde: " . url_abs('compras.php'));
            ir(url('pago.php?ids=' . implode(',', $creados)));
        }
    }
}
require __DIR__ . '/includes/header.php';
$sel = post('direccion') !== '' ? post('direccion') : ($dirs ? (string)$dirs[0]['ID_Direccion'] : 'nueva');
?>
<h1>Finalizar compra</h1>
<form method="post" action="<?= e(url('checkout.php')) ?>"><?= csrf_campo() ?>
<?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<div class="dos-col">
  <div>
    <div class="caja">
      <h2>1. Dirección de envío</h2>
      <?php foreach ($dirs as $d): ?>
        <label class="opcion"><input type="radio" name="direccion" value="<?= (int)$d['ID_Direccion'] ?>"<?= $sel === (string)$d['ID_Direccion'] ? ' checked' : '' ?>>
          <span><b><?= e($d['Calle'] . ' ' . $d['Numero']) ?></b><?= $d['Piso_depto'] ? ' · ' . e($d['Piso_depto']) : '' ?><br><small><?= e($d['Localidad'] . ', ' . $d['Provincia'] . ' · CP ' . $d['Codigo_Postal']) ?></small></span></label>
      <?php endforeach; ?>
      <?php if ($dirs): ?>
        <label class="opcion"><input type="radio" name="direccion" value="nueva"<?= $sel === 'nueva' ? ' checked' : '' ?>><span><b>Usar una dirección nueva</b></span></label>
      <?php else: ?>
        <p class="muted">Todavía no tenés direcciones guardadas: cargá la de envío.</p>
        <input type="hidden" name="direccion" value="nueva">
      <?php endif; ?>
      <details<?= (!$dirs || $sel === 'nueva' || $err) ? ' open' : '' ?>><summary>Datos de la dirección nueva</summary><div style="margin-top:12px"><?= campos_direccion() ?></div></details>
    </div>
    <div class="caja">
      <h2>2. Método de pago</h2>
      <?php foreach (METODOS_PAGO as $i => $m): ?>
        <label class="opcion"><input type="radio" name="metodo" value="<?= e($m) ?>"<?= post('metodo') === $m || (post('metodo') === '' && $i === 0) ? ' checked' : '' ?>><span><b><?= e($m) ?></b></span></label>
      <?php endforeach; ?>
      <p class="muted"><small>El pago se procesa en la pasarela: Ropify no recibe ni guarda los datos de tu tarjeta. El vendedor cobra recién cuando confirmás la recepción.</small></p>
    </div>
  </div>
  <aside class="caja resumen">
    <h2>Tu pedido</h2>
    <?php foreach ($grupos as $idv => $lista): ?>
      <p class="muted" style="margin:8px 0 2px">Vendedor: <?= e($lista[0]['AliasPublico']) ?></p>
      <?php foreach ($lista as $p): ?><div class="linea-total"><span><?= e($p['Titulo']) ?></span><span><?= dinero($p['Precio']) ?></span></div><?php endforeach; ?>
    <?php endforeach; ?>
    <div class="linea-total" style="border-top:1px solid var(--linea);margin-top:8px"><span>Subtotal</span><span><?= dinero($subtotal) ?></span></div>
    <div class="linea-total"><span>Envío <?= count($grupos) > 1 ? '(' . count($grupos) . ' pedidos)' : '' ?></span><span><?= dinero($envio) ?></span></div>
    <div class="linea-total fuerte"><span>Total a pagar</span><span><?= dinero($subtotal + $envio) ?></span></div>
    <p></p><button class="btn btn-ambar btn-bloque" type="submit">Confirmar y pagar</button>
    <p style="margin-top:10px"><a href="<?= e(url('carrito.php')) ?>">← Volver al carrito</a></p>
  </aside>
</div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
