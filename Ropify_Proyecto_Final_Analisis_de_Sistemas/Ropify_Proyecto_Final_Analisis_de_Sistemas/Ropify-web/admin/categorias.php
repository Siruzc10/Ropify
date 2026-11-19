<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Categorías'; $activo = 'categorias'; $subnav = 'admin';
$err = [];

if (es_post()) {                                                      // CU-30 · RF-17 · US-10
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id'); $nombre = post('nombre'); $desc = post('descripcion');
    if ($accion === 'alta' || $accion === 'modificar') {
        if ($nombre === '' || largo($nombre) > 50)   $err[] = 'El nombre es obligatorio (hasta 50 caracteres).';
        if ($desc === '' || largo($desc) > 240)      $err[] = 'La descripción es obligatoria (hasta 240 caracteres).';
        if (!$err && valor('SELECT COUNT(*) FROM categoria WHERE Nombre = ? AND ID_Categoria <> ?', [$nombre, $id])) $err[] = 'Ya existe una categoría con ese nombre.';
        if (!$err) {
            if ($accion === 'alta') {
                q('INSERT INTO categoria (Nombre, Descripcion) VALUES (?, ?)', [$nombre, $desc]);
                registrar_admin('Categoría creada', 'categoria', (int)db()->lastInsertId(), $nombre);
                aviso('Categoría creada.');
            } else {
                q('UPDATE categoria SET Nombre = ?, Descripcion = ? WHERE ID_Categoria = ?', [$nombre, $desc, $id]);
                registrar_admin('Categoría modificada', 'categoria', $id, $nombre);
                aviso('Categoría actualizada.');
            }
            ir(url('admin/categorias.php'));
        }
    } elseif ($accion === 'baja') {
        // US-10: no se elimina una categoría con prendas asociadas
        if (valor('SELECT COUNT(*) FROM prenda WHERE ID_Categoria = ?', [$id])) {
            aviso('No se puede eliminar: la categoría tiene prendas asociadas.', 'warning');
        } else {
            q('DELETE FROM categoria WHERE ID_Categoria = ?', [$id]);
            registrar_admin('Categoría eliminada', 'categoria', $id);
            aviso('Categoría eliminada.', 'info');
        }
        ir(url('admin/categorias.php'));
    }
}
$cats = filas('SELECT c.*, (SELECT COUNT(*) FROM prenda p WHERE p.ID_Categoria = c.ID_Categoria) AS Prendas FROM categoria c ORDER BY c.Nombre');
$edit = null; foreach ($cats as $c) if ((int)$c['ID_Categoria'] === (int)($_GET['editar'] ?? 0)) $edit = $c;
require __DIR__ . '/../includes/header.php';
?>
<h1>Categorías</h1>
<div class="dos-col">
  <div class="tabla-scroll"><table>
    <thead><tr><th>Categoría</th><th>Prendas</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr><td><b><?= e($c['Nombre']) ?></b><br><small><?= e($c['Descripcion']) ?></small></td><td><?= (int)$c['Prendas'] ?></td>
        <td><div class="acciones"><a class="btn btn-chico btn-sec" href="<?= e(url('admin/categorias.php?editar=' . (int)$c['ID_Categoria'])) ?>">Editar</a>
          <?php if ((int)$c['Prendas'] === 0): ?><?= boton_post('baja', ['id' => $c['ID_Categoria']], 'Eliminar', 'btn btn-chico btn-sec btn-peligro', '¿Eliminar la categoría?') ?><?php endif; ?></div></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="form-caja" style="max-width:none">
    <h2 style="margin-top:0"><?= $edit ? 'Editar categoría' : 'Nueva categoría' ?></h2>
    <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="<?= $edit ? 'modificar' : 'alta' ?>"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['ID_Categoria'] ?>"><?php endif; ?>
      <?= campo('Nombre', 'nombre', $_POST['nombre'] ?? ($edit['Nombre'] ?? ''), ['maxlength' => 50, 'required' => true]) ?>
      <?= area('Descripción (qué prendas incluye)', 'descripcion', $_POST['descripcion'] ?? ($edit['Descripcion'] ?? ''), ['maxlength' => 240, 'rows' => 3, 'required' => true]) ?>
      <div class="acciones"><button class="btn" type="submit"><?= $edit ? 'Guardar' : 'Crear categoría' ?></button><?php if ($edit): ?><a href="<?= e(url('admin/categorias.php')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
