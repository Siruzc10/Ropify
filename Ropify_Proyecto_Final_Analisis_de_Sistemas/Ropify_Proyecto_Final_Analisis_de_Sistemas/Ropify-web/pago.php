<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Pago';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

// Pedidos a pagar: deben ser del comprador y seguir «Pendiente_Pago»
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? $_POST['ids'] ?? ''))))));
$pedidos = [];
foreach ($ids as $id) {
    $pe = fila("SELECT * FROM pedido WHERE ID_Pedido = ? AND ID_Comprador = ? AND Estado = 'Pendiente_Pago'", [$id, $uid]);
    if ($pe) $pedidos[] = $pe;
}
if (!$pedidos) { aviso('No hay pedidos pendientes de pago para mostrar.', 'info'); ir(url('compras.php')); }
$ids = array_map(static fn($p) => (int)$p['ID_Pedido'], $pedidos);
$total = array_sum(array_map(static fn($p) => (float)$p['Total'], $pedidos));

if (es_post()) {
    verificar_csrf();
    if (!MODO_DEMO) { aviso('La pasarela de pagos real todavía no está configurada.', 'error'); ir(url('compras.php')); }
    $resultado = post('resultado');
    if ($resultado === 'aprobado') {
        $n = pasarela_confirmar_pago($ids, $uid, 'SIM-' . strtoupper(bin2hex(random_bytes(4))));
        aviso($n > 0 ? '¡Pago aprobado! El vendedor ya fue avisado y va a despachar tu pedido.' : 'Ese pago ya estaba acreditado.', $n > 0 ? 'ok' : 'info');
        ir(url(count($ids) === 1 ? 'pedido.php?id=' . $ids[0] : 'compras.php'));
    } elseif ($resultado === 'rechazado') {
        // CU-19 alt. 4.1 / CP-25: informa el motivo, el pedido sigue «Pendiente_Pago» y se puede reintentar
        $nuevo = post('metodo');
        if (in_array($nuevo, METODOS_PAGO, true)) q('UPDATE pedido SET Metodo_Pago = ? WHERE ID_Pedido IN (' . marcadores($ids) . ")", array_merge([$nuevo], $ids));
        aviso('El pago fue rechazado (simulación: fondos insuficientes). Podés reintentar o elegir otro medio de pago.', 'error');
        ir(url('pago.php?ids=' . implode(',', $ids)));
    }
    ir(url('compras.php'));
}
require __DIR__ . '/includes/header.php';
?>
<h1>Pagar</h1>
<div class="dos-col">
  <div class="caja simulador">
    <h2>Pasarela de pagos (simulador)</h2>
    <p>Esta pantalla representa el checkout de la pasarela (por ejemplo, Mercado Pago). En producción acá se redirige al sitio de la pasarela, y Ropify <b>nunca</b> recibe ni guarda datos de tarjeta.</p>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="ids" value="<?= e(implode(',', $ids)) ?>">
      <?= selector('Medio de pago', 'metodo', lista_a_opciones(METODOS_PAGO), $pedidos[0]['Metodo_Pago'], '') ?>
      <div class="acciones">
        <button class="btn btn-ambar" type="submit" name="resultado" value="aprobado">Simular pago aprobado</button>
        <button class="btn btn-sec" type="submit" name="resultado" value="rechazado">Simular pago rechazado</button>
        <a href="<?= e(url('compras.php')) ?>">Pagar más tarde</a>
      </div>
    </form>
  </div>
  <aside class="caja resumen">
    <h2>Resumen</h2>
    <?php foreach ($pedidos as $pe): ?><div class="linea-total"><span>Pedido #<?= (int)$pe['ID_Pedido'] ?></span><span><?= dinero($pe['Total']) ?></span></div><?php endforeach; ?>
    <div class="linea-total fuerte"><span>Total</span><span><?= dinero($total) ?></span></div>
    <p class="muted"><small>Tenés <?= PLAZO_PAGO_HORAS ?> horas para pagar; después el pedido se cancela y las prendas vuelven al catálogo.</small></p>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
