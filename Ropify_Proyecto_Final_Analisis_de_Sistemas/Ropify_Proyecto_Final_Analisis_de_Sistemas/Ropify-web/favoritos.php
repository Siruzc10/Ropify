<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Lista de deseados';
$u = exigir_login();

if (es_post()) {
    verificar_csrf();
    if (post('accion') === 'quitar') {
        q('DELETE FROM favorito WHERE ID_Usuario = ? AND ID_Prenda = ?', [$u['ID_Usuario'], (int)post('id')]);
        aviso('Quitada de tu lista de deseados.', 'info');
    }
    ir(url('favoritos.php'));
}
$lista = filas(sql_prenda('fv.Fecha_Agregado') . 'JOIN favorito fv ON fv.ID_Prenda = p.ID_Prenda
    WHERE fv.ID_Usuario = ? AND p.Estado IN (\'Aprobada\', \'Vendida\') AND u.Estado_Cuenta = \'Activa\' ORDER BY fv.Fecha_Agregado DESC', [$u['ID_Usuario']]);
require __DIR__ . '/includes/header.php';
?>
<h1>Lista de deseados</h1>
<?php if ($lista): ?>
  <div class="grilla">
    <?php foreach ($lista as $p): ?>
      <div><?= tarjeta_prenda($p) ?><p style="margin:6px 0 0"><?= boton_post('quitar', ['id' => $p['ID_Prenda']], '♥ Quitar de deseados', 'btn btn-chico btn-sec btn-bloque') ?></p></div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="vacio"><p>Todavía no guardaste prendas. Entrá a una prenda y tocá «Guardar en deseados».</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
