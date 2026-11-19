<?php
require_once __DIR__ . '/includes/bootstrap.php';
$activo = 'vender';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$prenda = null; $fotosActuales = [];
if ($id) {
    $prenda = fila('SELECT * FROM prenda WHERE ID_Prenda = ? AND ID_Vendedor = ?', [$id, $uid]);
    if (!$prenda) pagina_error(404, 'Prenda no encontrada', 'No encontramos esa publicación entre las tuyas.');
    if ($prenda['Estado'] === 'Vendida') { aviso('No se puede editar una prenda vendida.', 'warning'); ir(url('publicaciones.php')); } // US-09
    $fotosActuales = filas('SELECT * FROM foto_prenda WHERE ID_Prenda = ? ORDER BY Orden, ID_Foto', [$id]);
}
$editando = $prenda !== null;
$cats = filas('SELECT ID_Categoria, Nombre FROM categoria ORDER BY Nombre');
$err = [];
$v = [
    'titulo'   => $prenda['Titulo'] ?? '',   'desc'     => $prenda['Descripcion'] ?? '', 'cat'    => (string)($prenda['ID_Categoria'] ?? ''),
    'talle'    => $prenda['Talle'] ?? '',    'color'    => $prenda['Color'] ?? '',        'material' => $prenda['Material'] ?? '',
    'estado'   => $prenda['Estado_Conservacion'] ?? '', 'precio' => $prenda ? (string)(int)$prenda['Precio'] : '',
];

if (es_post()) {
    verificar_csrf();
    foreach ($v as $k => $_) $v[$k] = post($k);
    $v['talle'] = mayusculas($v['talle']);
    $v['color'] = function_exists('mb_convert_case') ? mb_convert_case($v['color'], MB_CASE_TITLE, 'UTF-8') : ucwords($v['color']);

    if ($v['titulo'] === '' || largo($v['titulo']) > 100)    $err[] = 'Ingresá un título (hasta 100 caracteres).';
    if ($v['desc'] === '' || largo($v['desc']) > 2000)       $err[] = 'Ingresá la descripción (hasta 2000 caracteres): detalles de uso, medidas, fallas.';
    if (!valor('SELECT COUNT(*) FROM categoria WHERE ID_Categoria = ?', [(int)$v['cat']])) $err[] = 'Elegí una categoría.';
    if ($v['talle'] === '' || largo($v['talle']) > 10)       $err[] = 'Ingresá el talle (hasta 10 caracteres).';
    if ($v['color'] === '' || largo($v['color']) > 30)       $err[] = 'Ingresá el color (hasta 30 caracteres).';
    if ($v['material'] === '' || largo($v['material']) > 60) $err[] = 'Ingresá el material (hasta 60 caracteres).';
    if (!in_array($v['estado'], CONSERVACION, true))         $err[] = 'Elegí el estado de conservación.';
    if (!preg_match('/^\d{1,8}$/', $v['precio']) || (int)$v['precio'] < 1) $err[] = 'Ingresá un precio válido en pesos, mayor a cero.'; // CP-16

    // Fotos nuevas
    [$tmpValidas, $errFotos] = revisar_fotos_subidas('fotos');                                                                    // CP-17
    $err = array_merge($err, $errFotos);
    // Fotos existentes: cuáles se quitan y en qué orden quedan
    $quitar = array_map('intval', (array)($_POST['quitar'] ?? []));
    $ordenes = (array)($_POST['orden'] ?? []);
    $mantener = [];
    foreach ($fotosActuales as $f) {
        if (in_array((int)$f['ID_Foto'], $quitar, true)) continue;
        $mantener[] = ['foto' => $f, 'orden' => (int)($ordenes[$f['ID_Foto']] ?? $f['Orden'])];
    }
    $totalFotos = count($mantener) + count($tmpValidas);
    if (!$errFotos && $totalFotos < MIN_FOTOS) $err[] = 'Subí al menos ' . MIN_FOTOS . ' fotos (te faltan ' . (MIN_FOTOS - $totalFotos) . ').';  // CP-15
    if ($totalFotos > MAX_FOTOS)               $err[] = 'Podés tener hasta ' . MAX_FOTOS . ' fotos por prenda.';

    if (!$err) {
        $precio = (int)$v['precio'];
        $pdo = db(); $pdo->beginTransaction();
        $archivosNuevos = []; $archivosABorrar = [];
        try {
            if ($editando) {
                $idp = $id;
                $cambioContenido = $v['titulo'] !== $prenda['Titulo'] || $v['desc'] !== $prenda['Descripcion'] || (int)$v['cat'] !== (int)$prenda['ID_Categoria']
                    || $v['talle'] !== $prenda['Talle'] || $v['color'] !== $prenda['Color'] || $v['material'] !== $prenda['Material']
                    || $v['estado'] !== $prenda['Estado_Conservacion'] || $quitar !== [] || $tmpValidas !== [];
                $cambioPrecio = (float)$prenda['Precio'] !== (float)$precio;
                $nuevoEstado = $prenda['Estado'];
                // Si cambia el contenido (o estaba rechazada y se corrige) vuelve a revisión, para que no se pueda cambiar lo aprobado sin control
                if ($cambioContenido || ($prenda['Estado'] === 'Rechazada' && $cambioPrecio)) $nuevoEstado = 'Pendiente';
                q('UPDATE prenda SET ID_Categoria = ?, Titulo = ?, Descripcion = ?, Talle = ?, Color = ?, Material = ?, Estado_Conservacion = ?, Precio = ?, Estado = ?, Motivo_Rechazo = ?
                   WHERE ID_Prenda = ? AND ID_Vendedor = ? AND Estado <> \'Vendida\'',
                  [(int)$v['cat'], $v['titulo'], $v['desc'], $v['talle'], $v['color'], $v['material'], $v['estado'], fmt2($precio), $nuevoEstado,
                   $nuevoEstado === 'Pendiente' ? null : $prenda['Motivo_Rechazo'], $idp, $uid]);
                foreach ($quitar as $idf) {
                    foreach ($fotosActuales as $f) if ((int)$f['ID_Foto'] === $idf) $archivosABorrar[] = $f['URL'];
                    q('DELETE FROM foto_prenda WHERE ID_Foto = ? AND ID_Prenda = ?', [$idf, $idp]);
                }
                foreach ($mantener as $m) q('UPDATE foto_prenda SET Orden = ? WHERE ID_Foto = ? AND ID_Prenda = ?', [max(1, min(99, $m['orden'])), $m['foto']['ID_Foto'], $idp]);
                $siguiente = $mantener ? max(array_column($mantener, 'orden')) + 1 : 1;
            } else {
                q("INSERT INTO prenda (ID_Vendedor, ID_Categoria, Titulo, Descripcion, Talle, Color, Material, Estado_Conservacion, Precio, Estado, FechaPublicacion)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)",                                                          // RN-02 / RF-11: nace «Pendiente»
                  [$uid, (int)$v['cat'], $v['titulo'], $v['desc'], $v['talle'], $v['color'], $v['material'], $v['estado'], fmt2($precio), ahora()]);
                $idp = (int)$pdo->lastInsertId();
                $nuevoEstado = 'Pendiente';
                $siguiente = 1;
            }
            if ($tmpValidas) {
                $dir = carpeta_fotos($idp);
                if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo crear la carpeta de fotos');
                foreach ($tmpValidas as $tmp) {
                    $base = 'f_' . bin2hex(random_bytes(6));
                    $ext = guardar_foto($tmp, $dir . '/' . $base);
                    if (!$ext) throw new RuntimeException('No se pudo guardar una foto');
                    $rel = 'uploads/prendas/' . $idp . '/' . $base . '.' . $ext;
                    $archivosNuevos[] = RAIZ . '/' . $rel;
                    q('INSERT INTO foto_prenda (ID_Prenda, URL, Orden) VALUES (?, ?, ?)', [$idp, $rel, $siguiente++]);
                }
            }
            $pdo->commit();
            foreach ($archivosABorrar as $rutaVieja) borrar_archivo_foto($rutaVieja);
            if ($editando) {
                aviso($nuevoEstado === 'Pendiente' && $prenda['Estado'] !== 'Pendiente'
                    ? 'Guardamos los cambios. Como modificaste la publicación, vuelve a revisión antes de mostrarse en el catálogo.'
                    : 'Publicación actualizada.');
            } else {
                aviso('¡Prenda enviada a revisión! Un moderador la revisa (en 24 horas hábiles) y te avisamos el resultado.');
            }
            ir(url('publicaciones.php'));
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($archivosNuevos as $a) if (is_file($a)) @unlink($a);
            error_log('[Ropify] vender: ' . $t->getMessage());
            $err[] = 'No pudimos guardar la prenda, reintentá. Si las fotos son muy pesadas, probá con menos.';
        }
    }
}
$titulo = $editando ? 'Editar publicación' : 'Publicar una prenda';
require __DIR__ . '/includes/header.php';
?>
<h1><?= $editando ? 'Editar publicación' : 'Publicar una prenda' ?></h1>
<p class="muted">Subí al menos <?= MIN_FOTOS ?> fotos reales de la prenda. Un moderador la revisa antes de publicarla. Si se vende, te quedás con el <?= porcentaje_vendedor() ?> % del precio (Ropify retiene el <?= porcentaje_comision() ?> %).</p>
<?php if (empty($u['Alias_Cobro'])): ?><div class="aviso aviso-warning">Para cobrar tus ventas necesitamos tu alias de cobro. <a href="<?= e(url('perfil.php')) ?>">Cargalo en Mi perfil</a> (podés publicar igual).</div><?php endif; ?>
<?php if ($editando && $prenda['Estado'] === 'Rechazada'): ?><div class="aviso aviso-error"><b>Motivo del rechazo:</b> <?= e($prenda['Motivo_Rechazo'] ?: 'sin especificar') ?></div><?php endif; ?>
<?php if ($editando && $prenda['Estado'] !== 'Pendiente'): ?><div class="nota">Si cambiás las fotos o cualquier dato (menos el precio), la publicación vuelve a revisión. Cambiar solo el precio se refleja enseguida.</div><?php endif; ?>

<div class="form-caja ancha">
  <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('vender.php')) ?>" enctype="multipart/form-data"><?= csrf_campo() ?>
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <?= campo('Título', 'titulo', $v['titulo'], ['maxlength' => 100, 'required' => true, 'placeholder' => 'Ej: Jean mom azul tiro alto']) ?>
    <?= area('Descripción y estado de uso', 'desc', $v['desc'], ['maxlength' => 2000, 'rows' => 4, 'required' => true], 'Contá detalles de uso, medidas aproximadas y cualquier falla.') ?>
    <div class="fila-campos">
      <?= selector('Categoría', 'cat', array_column($cats, 'Nombre', 'ID_Categoria'), $v['cat'], 'Elegir…', ['required' => true]) ?>
      <?= selector('Estado de conservación', 'estado', lista_a_opciones(CONSERVACION), $v['estado'], 'Elegir…', ['required' => true]) ?>
    </div>
    <div class="fila-campos">
      <?= campo('Talle', 'talle', $v['talle'], ['maxlength' => 10, 'required' => true, 'list' => 'talles']) ?>
      <?= campo('Color principal', 'color', $v['color'], ['maxlength' => 30, 'required' => true]) ?>
    </div>
    <datalist id="talles"><?php foreach (TALLES_SUGERIDOS as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    <div class="fila-campos">
      <?= campo('Material / composición', 'material', $v['material'], ['maxlength' => 60, 'required' => true, 'placeholder' => 'Ej: Algodón']) ?>
      <?= campo('Precio (ARS)', 'precio', $v['precio'], ['type' => 'number', 'min' => 1, 'step' => 1, 'inputmode' => 'numeric', 'required' => true], 'Pesos enteros, mayor a cero.') ?>
    </div>

    <h2 style="margin-top:8px">Fotos</h2>
    <?php if ($editando && $fotosActuales): ?>
      <p class="muted">Fotos actuales. La de menor número es la principal. Marcá las que quieras quitar.</p>
      <div class="fotos-edicion">
        <?php foreach ($fotosActuales as $f): ?>
          <div class="foto-edit">
            <img src="<?= e(url_foto($f['URL'])) ?>" alt="Foto <?= (int)$f['Orden'] ?>" loading="lazy">
            <label>Orden <input type="number" name="orden[<?= (int)$f['ID_Foto'] ?>]" value="<?= (int)($_POST['orden'][$f['ID_Foto']] ?? $f['Orden']) ?>" min="1" max="99"></label>
            <label class="check" style="margin:6px 0 0"><input type="checkbox" name="quitar[]" value="<?= (int)$f['ID_Foto'] ?>"<?= in_array((string)$f['ID_Foto'], array_map('strval', (array)($_POST['quitar'] ?? [])), true) ? ' checked' : '' ?>> Quitar</label>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="campo">
      <label for="fotos"><?= $editando ? 'Agregar fotos nuevas' : 'Fotos de la prenda' ?> (mínimo <?= MIN_FOTOS ?> en total, máximo <?= MAX_FOTOS ?>)</label>
      <input id="fotos" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple<?= $editando ? '' : ' required' ?>
             data-preview="#vista" data-min="<?= MIN_FOTOS ?>" data-max="<?= MAX_FOTOS ?>" data-mb="<?= MAX_FOTO_MB ?>">
      <small class="ayuda">JPG, PNG o WebP de hasta <?= MAX_FOTO_MB ?> MB cada una. La primera será la foto principal. Las achicamos automáticamente.</small>
      <small class="ayuda" id="aviso-fotos" aria-live="polite"></small>
      <div id="vista" class="preview-fotos"></div>
    </div>
    <div class="acciones"><button class="btn btn-ambar" type="submit"><?= $editando ? 'Guardar cambios' : 'Enviar a revisión' ?></button><a href="<?= e(url('publicaciones.php')) ?>">Cancelar</a></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
