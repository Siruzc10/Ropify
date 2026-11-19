<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Tendencias'; $activo = 'tendencias';

$ver = obtener('ver');
$vistas = [
    'vistas'   => ['Más vistas', 'Las prendas que más miraron en Ropify.'],
    'deseadas' => ['Más deseadas', 'Las que más personas guardaron en su lista de deseados.'],
    'vendidas' => ['Vendidas recientemente', 'Prendas que ya encontraron dueño (cada prenda usada es única).'],
];
if (!isset($vistas[$ver])) $ver = 'vistas';

$deseados = '(SELECT COUNT(*) FROM favorito f WHERE f.ID_Prenda = p.ID_Prenda) AS Deseados';
if ($ver === 'vistas') {
    $lista = prendas(DONDE_CATALOGO, [], 'p.Visitas DESC, p.ID_Prenda DESC', 12, 0, $deseados);
} elseif ($ver === 'deseadas') {
    $lista = prendas(DONDE_CATALOGO, [], 'Deseados DESC, p.Visitas DESC, p.ID_Prenda DESC', 12, 0, $deseados);
} else {
    $lista = prendas("p.Estado = 'Vendida' AND u.Estado_Cuenta = 'Activa' AND EXISTS (
            SELECT 1 FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido WHERE d.ID_Prenda = p.ID_Prenda AND pe.Estado <> 'Cancelado')",
        [], 'p.FechaPublicacion DESC, p.ID_Prenda DESC', 12, 0, $deseados);
}
foreach ($lista as &$x) { $x['mostrar_visitas'] = $ver === 'vistas'; }
unset($x);

require __DIR__ . '/includes/header.php';
?>
<h1>Tendencias</h1>
<div class="tabs">
  <?php foreach ($vistas as $k => [$nombre]): ?>
    <a<?= $ver === $k ? ' class="activo" aria-current="page"' : '' ?> href="<?= e(url('tendencias.php?ver=' . $k)) ?>"><?= e($nombre) ?></a>
  <?php endforeach; ?>
</div>
<p class="muted"><?= e($vistas[$ver][1]) ?></p>
<?php if ($lista): ?>
  <?= grilla_prendas($lista) ?>
<?php else: ?>
  <div class="vacio"><p>Todavía no hay prendas para mostrar en esta lista.</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
