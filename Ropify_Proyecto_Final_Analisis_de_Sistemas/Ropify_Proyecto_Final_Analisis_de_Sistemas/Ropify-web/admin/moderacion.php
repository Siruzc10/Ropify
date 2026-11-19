<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Moderación'; $activo = 'moderacion'; $subnav = 'admin';

if (es_post()) {                                                      // CU-29 · RF-13
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id');
    $p = fila('SELECT ID_Prenda, ID_Vendedor, Titulo FROM prenda WHERE ID_Prenda = ?', [$id]);
    if (!$p) {
        aviso('No encontramos esa prenda.', 'error');
    } elseif ($accion === 'aprobar') {
        $st = q("UPDATE prenda SET Estado = 'Aprobada', Motivo_Rechazo = NULL, FechaPublicacion = ? WHERE ID_Prenda = ? AND Estado = 'Pendiente'", [ahora(), $id]);
        if ($st->rowCount()) {
            registrar_admin('Prenda aprobada', 'prenda', $id, $p['Titulo']);
            notificar((int)$p['ID_Vendedor'], 'Ropify · Tu prenda fue aprobada', 'Tu publicación «' . $p['Titulo'] . '» ya está visible en el catálogo: ' . url_abs('prenda.php?id=' . $id));
            aviso('Prenda #' . $id . ' aprobada: ya se ve en el catálogo.');
        } else aviso('Esa prenda ya fue revisada.', 'warning');
    } elseif ($accion === 'rechazar') {
        $motivo = post('motivo');
        if ($motivo === '' || largo($motivo) > 255) {                // CP-19: el rechazo exige motivo
            aviso('Para rechazar tenés que escribir el motivo (hasta 255 caracteres).', 'error');
        } else {
            $st = q("UPDATE prenda SET Estado = 'Rechazada', Motivo_Rechazo = ? WHERE ID_Prenda = ? AND Estado = 'Pendiente'", [$motivo, $id]);
            if ($st->rowCount()) {
                registrar_admin('Prenda rechazada', 'prenda', $id, $motivo);
                notificar((int)$p['ID_Vendedor'], 'Ropify · Tu prenda fue rechazada', 'Tu publicación «' . $p['Titulo'] . "» no fue aprobada.\nMotivo: $motivo\nPodés corregirla y volver a enviarla: " . url_abs('vender.php?id=' . $id));
                aviso('Prenda #' . $id . ' rechazada. Se notificó al vendedor.', 'info');
            } else aviso('Esa prenda ya fue revisada.', 'warning');
        }
    }
    ir(url('admin/moderacion.php'));
}

$pend = prendas("p.Estado = 'Pendiente'", [], 'p.FechaPublicacion ASC, p.ID_Prenda ASC', 50);      // de la más antigua a la más reciente
require __DIR__ . '/../includes/header.php';
?>
<h1>Moderación de publicaciones</h1>
<p class="muted">Revisá fotos, descripción y precio según las reglas de uso. Objetivo: responder en 24 horas hábiles.</p>
<?php if (!$pend): ?>
  <div class="vacio"><h2>No hay publicaciones pendientes 🎉</h2><p>Cuando un vendedor publique una prenda, va a aparecer acá.</p></div>
<?php endif; ?>
<?php foreach ($pend as $p):
    $fotos = filas('SELECT URL FROM foto_prenda WHERE ID_Prenda = ? ORDER BY Orden, ID_Foto', [$p['ID_Prenda']]); ?>
  <div class="caja">
    <div class="dos-col">
      <div>
        <div class="miniaturas" style="flex-wrap:wrap;overflow:visible">
          <?php foreach ($fotos as $i => $f): ?><a href="<?= e(url_foto($f['URL'])) ?>" target="_blank" rel="noopener" title="Abrir foto <?= $i + 1 ?> en grande"><img src="<?= e(url_foto($f['URL'])) ?>" alt="Foto <?= $i + 1 ?> de <?= e($p['Titulo']) ?>" style="width:110px;height:138px;object-fit:cover;border-radius:10px;border:1px solid var(--linea)" loading="lazy"></a><?php endforeach; ?>
        </div>
        <?php if (count($fotos) < MIN_FOTOS): ?><p class="aviso aviso-error">Tiene menos de <?= MIN_FOTOS ?> fotos.</p><?php endif; ?>
      </div>
      <div>
        <h2 style="margin-top:0"><?= e($p['Titulo']) ?> · <?= dinero($p['Precio']) ?></h2>
        <p class="muted"><?= e($p['Categoria']) ?> · Talle <?= e($p['Talle']) ?> · <?= e($p['Color']) ?> · <?= e($p['Material']) ?> · <?= e($p['Estado_Conservacion']) ?><br>
          Vendedor: <a href="<?= e(url('vendedor.php?id=' . (int)$p['ID_Vendedor'])) ?>"><?= e($p['AliasPublico']) ?></a> <?= $p['VendedorVerificado'] ? '<span class="verificado">✔</span>' : '' ?> · Enviada el <?= e(fecha_hora($p['FechaPublicacion'])) ?></p>
        <p><?= nl2br(e($p['Descripcion'])) ?></p>
        <div class="acciones">
          <?= boton_post('aprobar', ['id' => $p['ID_Prenda']], 'Aprobar', 'btn') ?>
        </div>
        <form method="post" style="margin-top:12px"><?= csrf_campo() ?><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id" value="<?= (int)$p['ID_Prenda'] ?>">
          <?= campo('Motivo del rechazo (obligatorio para rechazar)', 'motivo', '', ['maxlength' => 255, 'placeholder' => 'Ej: Las fotos no muestran la prenda completa']) ?>
          <button class="btn btn-peligro btn-sec" type="submit">Rechazar</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
