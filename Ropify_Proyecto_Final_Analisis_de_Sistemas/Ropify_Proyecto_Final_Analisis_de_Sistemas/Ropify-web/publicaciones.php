<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mis publicaciones';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

if (es_post()) {                                                      // CU-25 · RF-12
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id');
    $p = fila('SELECT * FROM prenda WHERE ID_Prenda = ? AND ID_Vendedor = ?', [$id, $uid]);
    if (!$p) {
        aviso('No encontramos esa publicación.', 'error');
    } elseif ($accion === 'pausar' || $accion === 'reactivar') {
        [$de, $a] = $accion === 'pausar' ? ['Aprobada', 'Pausada'] : ['Pausada', 'Aprobada'];
        $st = q('UPDATE prenda SET Estado = ? WHERE ID_Prenda = ? AND ID_Vendedor = ? AND Estado = ?', [$a, $id, $uid, $de]);
        aviso($st->rowCount() ? ($a === 'Pausada' ? 'Publicación pausada: ya no se ve en el catálogo.' : 'Publicación reactivada.') : 'No se pudo cambiar el estado.', $st->rowCount() ? 'ok' : 'warning');
    } elseif ($accion === 'eliminar') {
        if ($p['Estado'] === 'Vendida' || valor('SELECT COUNT(*) FROM detalle_pedido WHERE ID_Prenda = ?', [$id]) || valor('SELECT COUNT(*) FROM denuncia WHERE ID_Prenda = ?', [$id])) {
            aviso('Esta prenda tiene ventas o denuncias asociadas, por eso no se puede eliminar. Podés pausarla.', 'warning');
        } else {
            $rutas = array_column(filas('SELECT URL FROM foto_prenda WHERE ID_Prenda = ?', [$id]), 'URL');
            q('DELETE FROM prenda WHERE ID_Prenda = ? AND ID_Vendedor = ?', [$id, $uid]);
            foreach ($rutas as $r) borrar_archivo_foto($r);
            @rmdir(carpeta_fotos($id));
            aviso('Publicación eliminada.', 'info');
        }
    }
    ir(url('publicaciones.php'));
}

$lista = filas('SELECT p.*, c.Nombre AS Categoria,
    (SELECT f.URL FROM foto_prenda f WHERE f.ID_Prenda = p.ID_Prenda ORDER BY f.Orden, f.ID_Foto LIMIT 1) AS Foto,
    (SELECT COUNT(*) FROM favorito fv WHERE fv.ID_Prenda = p.ID_Prenda) AS Deseados
    FROM prenda p JOIN categoria c ON c.ID_Categoria = p.ID_Categoria WHERE p.ID_Vendedor = ? ORDER BY p.FechaPublicacion DESC, p.ID_Prenda DESC', [$uid]);
require __DIR__ . '/includes/header.php';
?>
<div class="seccion-cab" style="margin-top:0"><h1 style="margin:0">Mis publicaciones</h1><a class="btn btn-ambar" href="<?= e(url('vender.php')) ?>">+ Publicar prenda</a></div>
<?php if ($lista): ?>
<div class="tabla-scroll"><table>
  <thead><tr><th>Prenda</th><th>Precio</th><th>Estado</th><th>Visitas / ♥</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $p): ?>
    <tr>
      <td><div style="display:flex;gap:10px;align-items:center"><div style="width:48px;flex:none"><?= foto_html($p['Foto'], $p['Titulo']) ?></div>
        <div><a href="<?= e(url('prenda.php?id=' . (int)$p['ID_Prenda'])) ?>"><b><?= e($p['Titulo']) ?></b></a><br><small><?= e($p['Categoria']) ?> · Talle <?= e($p['Talle']) ?></small>
        <?php if ($p['Estado'] === 'Rechazada' && $p['Motivo_Rechazo']): ?><br><small style="color:var(--error)">Motivo: <?= e($p['Motivo_Rechazo']) ?></small><?php endif; ?></div></div></td>
      <td class="num-der"><?= dinero($p['Precio']) ?></td>
      <td><?= badge_prenda($p['Estado']) ?></td>
      <td><?= (int)$p['Visitas'] ?> / <?= (int)$p['Deseados'] ?></td>
      <td><div class="acciones">
        <?php if ($p['Estado'] !== 'Vendida'): ?><a class="btn btn-chico btn-sec" href="<?= e(url('vender.php?id=' . (int)$p['ID_Prenda'])) ?>">Editar</a><?php endif; ?>
        <?php if ($p['Estado'] === 'Aprobada'): ?><?= boton_post('pausar', ['id' => $p['ID_Prenda']], 'Pausar') ?><?php endif; ?>
        <?php if ($p['Estado'] === 'Pausada'): ?><?= boton_post('reactivar', ['id' => $p['ID_Prenda']], 'Reactivar') ?><?php endif; ?>
        <?php if ($p['Estado'] !== 'Vendida'): ?><?= boton_post('eliminar', ['id' => $p['ID_Prenda']], 'Eliminar', 'btn btn-chico btn-sec btn-peligro', '¿Eliminar esta publicación? No se puede deshacer.') ?><?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php else: ?>
  <div class="vacio"><p>Todavía no publicaste prendas.</p><a class="btn" href="<?= e(url('vender.php')) ?>">Publicar mi primera prenda</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
