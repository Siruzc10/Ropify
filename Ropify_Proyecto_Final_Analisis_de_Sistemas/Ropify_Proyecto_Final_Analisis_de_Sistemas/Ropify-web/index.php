<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Inicio'; $activo = 'inicio';

$categorias = filas("SELECT c.ID_Categoria, c.Nombre, COUNT(p.ID_Prenda) AS Cantidad
    FROM categoria c LEFT JOIN prenda p ON p.ID_Categoria = c.ID_Categoria AND p.Estado = 'Aprobada'
    GROUP BY c.ID_Categoria, c.Nombre ORDER BY c.Nombre");
$ultimas = prendas(DONDE_CATALOGO, [], 'p.FechaPublicacion DESC, p.ID_Prenda DESC', 8);
$deseadas = prendas(DONDE_CATALOGO, [], 'Deseados DESC, p.Visitas DESC, p.ID_Prenda DESC', 4, 0,
    '(SELECT COUNT(*) FROM favorito f WHERE f.ID_Prenda = p.ID_Prenda) AS Deseados');

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <img class="hero-isotipo" src="<?= e(url('assets/img/isotipo.png')) ?>" alt="" width="230" height="230">
  <h1>Tu estilo, entre personas</h1>
  <p>Comprá y vendé ropa usada en buen estado. Cada publicación se revisa antes de salir, el pago queda protegido y el envío tiene seguimiento.</p>
  <form class="hero-busqueda" method="get" action="<?= e(url('catalogo.php')) ?>" role="search">
    <input type="search" name="q" placeholder="Buscá jeans, camperas, remeras…" aria-label="Buscar prendas">
    <button type="submit">Buscar</button>
  </form>
  <div class="acciones">
    <a class="btn btn-ambar" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a>
    <a class="btn btn-sec" href="<?= e(url('vender.php')) ?>">Quiero vender</a>
  </div>
</section>

<?php if ($categorias): ?>
<h2 class="oculto">Categorías</h2>
<div class="chips">
  <?php foreach ($categorias as $c): ?>
    <a class="chip" href="<?= e(url('catalogo.php?cat=' . (int)$c['ID_Categoria'])) ?>"><?= e($c['Nombre']) ?> <small>(<?= (int)$c['Cantidad'] ?>)</small></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="seccion-cab"><h2>Últimas prendas</h2><a href="<?= e(url('catalogo.php')) ?>">Ver todo →</a></div>
<?php if ($ultimas): ?>
  <?= grilla_prendas($ultimas) ?>
<?php else: ?>
  <div class="vacio"><p>Todavía no hay prendas publicadas.</p><a class="btn" href="<?= e(url('vender.php')) ?>">Publicá la primera</a></div>
<?php endif; ?>

<?php if ($deseadas): ?>
<div class="seccion-cab"><h2>Las más deseadas</h2><a href="<?= e(url('tendencias.php')) ?>">Ver tendencias →</a></div>
<?= grilla_prendas($deseadas) ?>
<?php endif; ?>

<h2>Cómo funciona</h2>
<div class="tres">
  <div class="dato"><span class="num">1</span><h3>Publicá o elegí</h3><p class="muted">El vendedor sube al menos <?= MIN_FOTOS ?> fotos, el talle, el estado y el precio. El comprador filtra por categoría, talle y precio.</p></div>
  <div class="dato"><span class="num">2</span><h3>Revisamos y pagás seguro</h3><p class="muted">Un moderador aprueba cada prenda. El pago se hace dentro de Ropify y se libera al vendedor cuando recibís la prenda.</p></div>
  <div class="dato"><span class="num">3</span><h3>Envío con seguimiento</h3><p class="muted">El vendedor despacha con código de seguimiento y vos confirmás la recepción. El envío lo paga el comprador.</p></div>
</div>

<div class="banda">
  <div><h2>¿Tenés ropa que ya no usás?</h2><p class="muted">Te quedás con el <?= porcentaje_vendedor() ?>% del precio de venta. Ropify retiene el <?= porcentaje_comision() ?>%.</p></div>
  <a class="btn btn-ambar" href="<?= e(url('vender.php')) ?>">Publicar una prenda</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
