<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Catálogo'; $activo = 'catalogo';

$busca  = obtener('q');
$cat    = (int)($_GET['cat'] ?? 0);
$talle  = obtener('talle');
$color  = obtener('color');
$estado = obtener('estado');
$min    = obtener('min');
$max    = obtener('max');
$orden  = obtener('orden');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 12;

$ordenes = [
    'recientes'   => ['Más recientes', 'p.FechaPublicacion DESC, p.ID_Prenda DESC'],
    'precio_asc'  => ['Menor precio', 'p.Precio ASC, p.ID_Prenda DESC'],
    'precio_desc' => ['Mayor precio', 'p.Precio DESC, p.ID_Prenda DESC'],
];
if (!isset($ordenes[$orden])) $orden = 'recientes';

$where = [DONDE_CATALOGO]; $par = [];
if ($busca !== '') {
    $like = '%' . addcslashes($busca, '%_\\') . '%';
    $where[] = '(p.Titulo LIKE ? OR p.Descripcion LIKE ? OR p.Material LIKE ? OR p.Color LIKE ? OR c.Nombre LIKE ?)';
    array_push($par, $like, $like, $like, $like, $like);
}
if ($cat > 0)      { $where[] = 'p.ID_Categoria = ?'; $par[] = $cat; }
if ($talle !== '') { $where[] = 'p.Talle = ?'; $par[] = $talle; }
if ($color !== '') { $where[] = 'p.Color = ?'; $par[] = $color; }
if (in_array($estado, CONSERVACION, true)) { $where[] = 'p.Estado_Conservacion = ?'; $par[] = $estado; } else { $estado = ''; }
if ($min !== '' && is_numeric($min) && (float)$min >= 0) { $where[] = 'p.Precio >= ?'; $par[] = (float)$min; } else { $min = ''; }
if ($max !== '' && is_numeric($max) && (float)$max >= 0) { $where[] = 'p.Precio <= ?'; $par[] = (float)$max; } else { $max = ''; }

$condicion = implode(' AND ', $where);
$total = contar_prendas($condicion, $par);
$paginasMax = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $paginasMax);
$lista = prendas($condicion, $par, $ordenes[$orden][1], $porPagina, ($pagina - 1) * $porPagina);
$hayPrendas = $total > 0 || contar_prendas(DONDE_CATALOGO) > 0;

$cats    = filas('SELECT ID_Categoria, Nombre FROM categoria ORDER BY Nombre');
$talles  = filas("SELECT DISTINCT p.Talle FROM prenda p JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor WHERE " . DONDE_CATALOGO . " ORDER BY p.Talle");
$colores = filas("SELECT DISTINCT p.Color FROM prenda p JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor WHERE " . DONDE_CATALOGO . " ORDER BY p.Color");
$filtrando = $busca !== '' || $cat || $talle !== '' || $color !== '' || $estado !== '' || $min !== '' || $max !== '';

$params = array_filter(['q' => $busca, 'cat' => $cat ?: '', 'talle' => $talle, 'color' => $color, 'estado' => $estado, 'min' => $min, 'max' => $max, 'orden' => $orden === 'recientes' ? '' : $orden],
    static fn($v) => $v !== '' && $v !== null);

require __DIR__ . '/includes/header.php';
?>
<h1>Catálogo</h1>
<form class="filtros" method="get" action="<?= e(url('catalogo.php')) ?>">
  <details<?= $filtrando ? ' open' : '' ?>>
    <summary>Buscar y filtrar<?= $filtrando ? ' (activos)' : '' ?></summary>
    <div class="filtros-grilla">
      <?= campo('Buscar', 'q', $busca, ['type' => 'search', 'placeholder' => 'Jean, campera, algodón…']) ?>
      <?= selector('Categoría', 'cat', array_column($cats, 'Nombre', 'ID_Categoria'), $cat ?: '', 'Todas') ?>
      <?= selector('Talle', 'talle', lista_a_opciones(array_column($talles, 'Talle')), $talle, 'Todos') ?>
      <?= selector('Color', 'color', lista_a_opciones(array_column($colores, 'Color')), $color, 'Todos') ?>
      <?= selector('Estado de conservación', 'estado', lista_a_opciones(CONSERVACION), $estado, 'Todos') ?>
      <?= campo('Precio mínimo ($)', 'min', $min, ['type' => 'number', 'min' => 0, 'inputmode' => 'numeric']) ?>
      <?= campo('Precio máximo ($)', 'max', $max, ['type' => 'number', 'min' => 0, 'inputmode' => 'numeric']) ?>
      <?= selector('Ordenar por', 'orden', array_map(static fn($o) => $o[0], $ordenes), $orden, '') ?>
    </div>
    <div class="acciones"><button class="btn" type="submit">Aplicar filtros</button><a class="btn btn-sec" href="<?= e(url('catalogo.php')) ?>">Limpiar</a></div>
  </details>
</form>

<div class="catalogo-cab"><p class="muted"><?= $total ?> <?= plural($total, 'prenda', 'prendas') ?><?= $filtrando ? ' con esos filtros' : '' ?></p></div>
<?php if ($lista): ?>
  <?= grilla_prendas($lista) ?>
  <?= paginacion_html($total, $porPagina, $pagina, $params, 'catalogo.php') ?>
<?php elseif (!$hayPrendas): ?>
  <div class="vacio"><h2>Aún no hay publicaciones</h2><p>Cuando los vendedores publiquen y los moderadores aprueben las prendas, las vas a ver acá.</p><a class="btn" href="<?= e(url('vender.php')) ?>">Publicar una prenda</a></div>
<?php else: ?>
  <div class="vacio"><h2>No encontramos prendas con esos filtros</h2><p>Probá quitar algún filtro o buscar con otras palabras.</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Quitar filtros</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
