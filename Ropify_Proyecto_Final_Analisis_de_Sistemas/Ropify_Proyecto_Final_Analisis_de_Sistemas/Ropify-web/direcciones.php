<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mis direcciones';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$editar = (int)($_GET['editar'] ?? 0);
$err = [];

if (es_post()) {
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id');
    if ($accion === 'alta' || $accion === 'modificar') {
        [$d, $err] = leer_direccion();
        if (!$err) {
            if ($accion === 'alta') {
                guardar_direccion($uid, $d);
                aviso('Dirección guardada.');
            } elseif (valor('SELECT COUNT(*) FROM direccion WHERE ID_Direccion = ? AND ID_Usuario = ?', [$id, $uid])) {
                q('UPDATE direccion SET Calle = ?, Numero = ?, Piso_depto = ?, Localidad = ?, Provincia = ?, Codigo_Postal = ? WHERE ID_Direccion = ? AND ID_Usuario = ?',
                  [$d['calle'], $d['numero'], $d['piso'] === '' ? null : $d['piso'], $d['localidad'], $d['provincia'], $d['cp'], $id, $uid]);
                aviso('Dirección actualizada.');
            }
            $volver = obtener('volver') !== '' ? destino_seguro(obtener('volver'), url('direcciones.php')) : url('direcciones.php');
            ir($volver);
        }
        $editar = $accion === 'modificar' ? $id : 0;
    } elseif ($accion === 'baja') {
        if (!valor('SELECT COUNT(*) FROM direccion WHERE ID_Direccion = ? AND ID_Usuario = ?', [$id, $uid])) {
            aviso('No encontramos esa dirección.', 'error');
        } elseif (valor('SELECT COUNT(*) FROM pedido WHERE ID_Direccion = ?', [$id])) {
            aviso('Esa dirección se usó en un pedido, por eso no se puede eliminar (queda como registro de la entrega).', 'warning');
        } else {
            q('DELETE FROM direccion WHERE ID_Direccion = ? AND ID_Usuario = ?', [$id, $uid]);
            aviso('Dirección eliminada.', 'info');
        }
        ir(url('direcciones.php'));
    }
}

$dirs = filas('SELECT * FROM direccion WHERE ID_Usuario = ? ORDER BY ID_Direccion', [$uid]);
$enEdicion = null;
foreach ($dirs as $d) if ((int)$d['ID_Direccion'] === $editar) $enEdicion = $d;
require __DIR__ . '/includes/header.php';
?>
<h1>Mis direcciones</h1>
<div class="dos-col">
  <div>
    <?php if (!$dirs): ?>
      <div class="vacio"><p>Todavía no cargaste direcciones de envío.</p></div>
    <?php endif; ?>
    <?php foreach ($dirs as $d): ?>
      <div class="caja">
        <p style="margin:0 0 10px"><b><?= e($d['Calle'] . ' ' . $d['Numero']) ?></b><?= $d['Piso_depto'] ? ' · ' . e($d['Piso_depto']) : '' ?><br>
          <span class="muted"><?= e($d['Localidad'] . ', ' . $d['Provincia'] . ' · CP ' . $d['Codigo_Postal']) ?></span></p>
        <div class="acciones">
          <a class="btn btn-chico btn-sec" href="<?= e(url('direcciones.php?editar=' . (int)$d['ID_Direccion'])) ?>">Editar</a>
          <?= boton_post('baja', ['id' => $d['ID_Direccion']], 'Eliminar', 'btn btn-chico btn-sec btn-peligro', '¿Eliminar esta dirección?') ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="form-caja" style="max-width:none">
    <h2 style="margin-top:0"><?= $enEdicion ? 'Editar dirección' : 'Agregar dirección' ?></h2>
    <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="<?= e(url('direcciones.php' . (obtener('volver') !== '' ? '?volver=' . rawurlencode(obtener('volver')) : ''))) ?>"><?= csrf_campo() ?>
      <input type="hidden" name="accion" value="<?= $enEdicion ? 'modificar' : 'alta' ?>">
      <?php if ($enEdicion): ?><input type="hidden" name="id" value="<?= (int)$enEdicion['ID_Direccion'] ?>"><?php endif; ?>
      <?= campos_direccion('dir_', $enEdicion ? ['calle' => $enEdicion['Calle'], 'numero' => $enEdicion['Numero'], 'piso' => $enEdicion['Piso_depto'] ?? '', 'localidad' => $enEdicion['Localidad'], 'provincia' => $enEdicion['Provincia'], 'cp' => $enEdicion['Codigo_Postal']] : []) ?>
      <div class="acciones"><button class="btn" type="submit"><?= $enEdicion ? 'Guardar cambios' : 'Guardar dirección' ?></button>
        <?php if ($enEdicion): ?><a href="<?= e(url('direcciones.php')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
