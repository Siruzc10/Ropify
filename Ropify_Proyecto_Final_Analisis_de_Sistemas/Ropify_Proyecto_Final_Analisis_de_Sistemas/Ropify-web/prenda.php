<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = $id ? fila(sql_prenda() . 'WHERE p.ID_Prenda = ?', [$id]) : null;
$u = usuario();
$esMia = $u && $p && (int)$u['ID_Usuario'] === (int)$p['ID_Vendedor'];

// Visible para todos: aprobadas y vendidas (con vendedor activo). Pendientes, rechazadas y pausadas: solo su dueño y los administradores.
$visible = $p && (
    (in_array($p['Estado'], ['Aprobada', 'Vendida'], true) && $p['EstadoVendedor'] === 'Activa') || $esMia || es_admin($u)
);
if (!$visible) pagina_error(404, 'Prenda no encontrada', 'La prenda que buscás no existe o ya no está publicada.');

if (es_post()) {
    verificar_csrf();
    $accion = post('accion');
    if ($accion === 'carrito') {
        if ($p['Estado'] !== 'Aprobada') aviso('Esa prenda ya no está disponible.', 'warning');
        elseif ($esMia)                  aviso('No podés comprar tu propia prenda.', 'warning'); // RN-09 / CP-22
        else { carrito_guardar(array_merge(carrito(), [$id])); aviso('Prenda agregada al carrito.'); ir(url('carrito.php')); }
    } elseif ($accion === 'favorito') {
        $u = exigir_login();
        if (valor('SELECT COUNT(*) FROM favorito WHERE ID_Usuario = ? AND ID_Prenda = ?', [$u['ID_Usuario'], $id])) {
            q('DELETE FROM favorito WHERE ID_Usuario = ? AND ID_Prenda = ?', [$u['ID_Usuario'], $id]);
            aviso('Quitada de tu lista de deseados.', 'info');
        } else {
            q('INSERT INTO favorito (ID_Usuario, ID_Prenda, Fecha_Agregado) VALUES (?, ?, ?)', [$u['ID_Usuario'], $id, ahora()]);
            aviso('Agregada a tu lista de deseados.');
        }
    }
    ir(url('prenda.php?id=' . $id));
}

// Contador de visitas (RF-18): una vez por sesión y solo para prendas publicadas que no son del propio vendedor
if ($p['Estado'] === 'Aprobada' && !$esMia && empty($_SESSION['vistas'][$id])) {
    $_SESSION['vistas'][$id] = 1;
    q('UPDATE prenda SET Visitas = Visitas + 1 WHERE ID_Prenda = ?', [$id]);
}

$fotos = filas('SELECT URL FROM foto_prenda WHERE ID_Prenda = ? ORDER BY Orden, ID_Foto', [$id]);
$esFav = $u && valor('SELECT COUNT(*) FROM favorito WHERE ID_Usuario = ? AND ID_Prenda = ?', [$u['ID_Usuario'], $id]);
$enCarrito = in_array($id, carrito(), true);
$vendedor = fila('SELECT AliasPublico, Biografia, Fecha_Registro, Dni_Verificado FROM usuario WHERE ID_Usuario = ?', [$p['ID_Vendedor']]);
$otras = prendas(DONDE_CATALOGO . ' AND p.ID_Vendedor = ? AND p.ID_Prenda <> ?', [$p['ID_Vendedor'], $id], 'p.FechaPublicacion DESC', 4);

$titulo = $p['Titulo'];
$descripcion = recortar($p['Descripcion'], 150);
require __DIR__ . '/includes/header.php';
?>
<p class="muted"><a href="<?= e(url('catalogo.php')) ?>">Catálogo</a> › <a href="<?= e(url('catalogo.php?cat=' . (int)$p['ID_Categoria'])) ?>"><?= e($p['Categoria']) ?></a> › <?= e($p['Titulo']) ?></p>

<?php if ($esMia && $p['Estado'] === 'Rechazada'): ?>
  <div class="aviso aviso-error"><b>Tu publicación fue rechazada.</b> Motivo: <?= e($p['Motivo_Rechazo'] ?: 'sin especificar') ?>. <a href="<?= e(url('vender.php?id=' . $id)) ?>">Corregila y volvé a enviarla</a>.</div>
<?php elseif ($esMia && $p['Estado'] === 'Pendiente'): ?>
  <div class="aviso aviso-warning">Esta prenda está <b>en revisión</b>: un moderador la va a aprobar antes de que se vea en el catálogo.</div>
<?php elseif ($p['Estado'] === 'Pausada'): ?>
  <div class="aviso aviso-info">Esta publicación está pausada y no se ve en el catálogo.</div>
<?php elseif ($p['Estado'] === 'Vendida'): ?>
  <div class="aviso aviso-info">Esta prenda ya no está disponible: fue vendida (cada prenda usada es una pieza única).</div>
<?php endif; ?>

<div class="detalle">
  <div>
    <div class="galeria-principal">
      <div class="foto<?= $fotos ? '' : ' sin-foto' ?>"><?php if ($fotos): ?><img id="foto-principal" src="<?= e(url_foto($fotos[0]['URL'])) ?>" alt="<?= e($p['Titulo']) ?>"><?php endif; ?></div>
    </div>
    <?php if (count($fotos) > 1): ?>
    <div class="miniaturas">
      <?php foreach ($fotos as $i => $f): ?>
        <button type="button" class="<?= $i === 0 ? 'activa' : '' ?>" data-src="<?= e(url_foto($f['URL'])) ?>" aria-label="Ver foto <?= $i + 1 ?>"><img src="<?= e(url_foto($f['URL'])) ?>" alt="Foto <?= $i + 1 ?> de <?= e($p['Titulo']) ?>" loading="lazy"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <h1><?= e($p['Titulo']) ?></h1>
    <p class="precio-grande"><?= dinero($p['Precio']) ?></p>
    <p class="muted">+ envío a cargo del comprador: <?= dinero(COSTO_ENVIO) ?></p>

    <dl class="ficha">
      <dt>Categoría</dt><dd><?= e($p['Categoria']) ?></dd>
      <dt>Talle</dt><dd><?= e($p['Talle']) ?></dd>
      <dt>Estado</dt><dd><?= e($p['Estado_Conservacion']) ?></dd>
      <dt>Color</dt><dd><?= e($p['Color']) ?></dd>
      <dt>Material</dt><dd><?= e($p['Material']) ?></dd>
      <dt>Publicada</dt><dd><?= e(fecha($p['FechaPublicacion'])) ?></dd>
    </dl>
    <p><?= nl2br(e($p['Descripcion'])) ?></p>

    <div class="acciones">
      <?php if ($p['Estado'] === 'Aprobada' && !$esMia): ?>
        <?php if ($enCarrito): ?>
          <a class="btn btn-ambar" href="<?= e(url('carrito.php')) ?>">Ya está en tu carrito · Ir al carrito</a>
        <?php else: ?>
          <form method="post" class="en-linea"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="carrito"><button class="btn btn-ambar" type="submit">Agregar al carrito</button></form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($esMia): ?>
        <?php if ($p['Estado'] !== 'Vendida'): ?><a class="btn btn-sec" href="<?= e(url('vender.php?id=' . $id)) ?>">Editar mi publicación</a><?php endif; ?>
      <?php else: ?>
        <form method="post" class="en-linea"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="favorito"><button class="btn btn-sec" type="submit"><?= $esFav ? '♥ En deseados (quitar)' : '♡ Guardar en deseados' ?></button></form>
      <?php endif; ?>
    </div>

    <div class="vendedor-caja">
      <span class="avatar"><?= e(iniciales($vendedor['AliasPublico'])) ?></span>
      <div>
        <b><a href="<?= e(url('vendedor.php?id=' . (int)$p['ID_Vendedor'])) ?>"><?= e($vendedor['AliasPublico']) ?></a></b>
        <?php if ($vendedor['Dni_Verificado']): ?><span class="verificado"> ✔ Identidad verificada</span><?php endif; ?><br>
        <small>En Ropify desde <?= e(date('Y', strtotime((string)$vendedor['Fecha_Registro']))) ?></small>
      </div>
    </div>

    <ul class="garantias">
      <li>Publicación revisada por el equipo de Ropify.</li>
      <li>Pago protegido: el vendedor cobra cuando confirmás la recepción.</li>
      <li>Envío con código de seguimiento.</li>
    </ul>

    <?php if (!$esMia && $p['Estado'] === 'Aprobada'): ?>
      <details class="caja">
        <summary><b>Escribirle al vendedor</b></summary>
        <?php if ($u): ?>
          <form method="post" action="<?= e(url('mensajes.php')) ?>" style="margin-top:12px"><?= csrf_campo() ?>
            <input type="hidden" name="accion" value="enviar"><input type="hidden" name="prenda" value="<?= $id ?>"><input type="hidden" name="para" value="<?= (int)$p['ID_Vendedor'] ?>">
            <?= area('Tu consulta', 'contenido', '', ['maxlength' => 1000, 'required' => true, 'rows' => 3, 'placeholder' => '¿Tiene algún detalle de uso? ¿Cuánto mide?']) ?>
            <button class="btn" type="submit">Enviar mensaje</button>
          </form>
        <?php else: ?>
          <p style="margin-top:12px"><a href="<?= e(url('login.php')) ?>">Ingresá</a> o <a href="<?= e(url('registro.php')) ?>">creá una cuenta</a> para escribirle al vendedor.</p>
        <?php endif; ?>
      </details>
    <?php endif; ?>

    <?php if (!$esMia): ?>
      <p><small><a href="<?= e(url('denunciar.php?prenda=' . $id)) ?>">⚑ Denunciar esta publicación</a></small></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($otras): ?>
  <div class="seccion-cab"><h2>Más de <?= e($vendedor['AliasPublico']) ?></h2></div>
  <?= grilla_prendas($otras) ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
