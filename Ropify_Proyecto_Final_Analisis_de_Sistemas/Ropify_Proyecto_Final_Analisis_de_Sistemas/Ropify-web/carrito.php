<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Carrito';
$u = usuario();

/** Prendas del carrito que siguen disponibles y no son del propio comprador (CU-17, CU-18 paso 2, RN-09). */
function items_carrito(?array $u): array
{
    $items = [];
    foreach (carrito() as $idp) {
        $p = fila(sql_prenda() . "WHERE p.ID_Prenda = ? AND p.Estado = 'Aprobada' AND u.Estado_Cuenta = 'Activa'", [$idp]);
        if ($p && !($u && (int)$p['ID_Vendedor'] === (int)$u['ID_Usuario'])) $items[] = $p;
    }
    return $items;
}

if (es_post()) {
    verificar_csrf();
    if (post('accion') === 'quitar') {
        $x = (int)post('id');
        carrito_guardar(array_filter(carrito(), static fn($i) => $i !== $x));
    } elseif (post('accion') === 'vaciar') {
        carrito_guardar([]);
    }
    ir(url('carrito.php'));
}

$items = items_carrito($u);
if (count($items) !== count(carrito())) {
    if (carrito()) aviso('Alguna prenda de tu carrito ya no está disponible y se quitó.', 'warning'); // CU-18 alt. 2.1
    carrito_guardar(array_map(static fn($p) => (int)$p['ID_Prenda'], $items));
}
$grupos = [];
foreach ($items as $p) $grupos[(int)$p['ID_Vendedor']][] = $p;
$subtotal = array_sum(array_map(static fn($p) => (float)$p['Precio'], $items));
$envio = COSTO_ENVIO * count($grupos);
require __DIR__ . '/includes/header.php';
?>
<h1>Carrito</h1>
<?php if (!$items): ?>
  <div class="vacio"><p>Tu carrito está vacío.</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a></div>
<?php else: ?>
<div class="dos-col">
  <div>
    <?php foreach ($grupos as $idv => $lista): ?>
      <div class="caja">
        <p class="muted" style="margin-top:0">Vendedor: <a href="<?= e(url('vendedor.php?id=' . $idv)) ?>"><?= e($lista[0]['AliasPublico']) ?></a></p>
        <?php foreach ($lista as $p): ?>
          <div style="display:flex;gap:12px;align-items:center;padding:8px 0;border-top:1px solid var(--linea)">
            <div style="width:64px;flex:none"><?= foto_html($p['Foto'], $p['Titulo']) ?></div>
            <div style="flex:1;min-width:0"><a href="<?= e(url('prenda.php?id=' . (int)$p['ID_Prenda'])) ?>"><b><?= e($p['Titulo']) ?></b></a><br><small>Talle <?= e($p['Talle']) ?> · <?= e($p['Estado_Conservacion']) ?></small></div>
            <div class="precio"><?= dinero($p['Precio']) ?></div>
            <?= boton_post('quitar', ['id' => $p['ID_Prenda']], 'Quitar', 'btn btn-chico btn-sec') ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if (count($grupos) > 1): ?>
      <div class="nota">Tu carrito tiene prendas de <?= count($grupos) ?> vendedores: se genera <b>un pedido por vendedor</b>, cada uno con su envío (<?= dinero(COSTO_ENVIO) ?>).</div>
    <?php endif; ?>
  </div>
  <aside class="caja resumen">
    <h2>Resumen</h2>
    <div class="linea-total"><span>Subtotal (<?= count($items) ?> <?= plural(count($items), 'prenda', 'prendas') ?>)</span><span><?= dinero($subtotal) ?></span></div>
    <div class="linea-total"><span>Envío (a cargo del comprador)</span><span><?= dinero($envio) ?></span></div>
    <div class="linea-total fuerte"><span>Total</span><span><?= dinero($subtotal + $envio) ?></span></div>
    <p></p>
    <a class="btn btn-ambar btn-bloque" href="<?= e(url('checkout.php')) ?>"><?= $u ? 'Finalizar compra' : 'Ingresar para comprar' ?></a>
    <p style="margin-top:10px"><a href="<?= e(url('catalogo.php')) ?>">← Seguir comprando</a></p>
  </aside>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
