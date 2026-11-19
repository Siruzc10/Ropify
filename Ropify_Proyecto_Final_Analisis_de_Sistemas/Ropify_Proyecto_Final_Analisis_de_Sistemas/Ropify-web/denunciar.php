<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Denunciar';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$idPrenda = (int)($_GET['prenda'] ?? $_POST['prenda'] ?? 0);
$idUsuario = (int)($_GET['usuario'] ?? $_POST['usuario'] ?? 0);

$prenda = $idPrenda ? fila('SELECT ID_Prenda, Titulo, ID_Vendedor FROM prenda WHERE ID_Prenda = ?', [$idPrenda]) : null;
$denunciado = null;
if ($prenda) $denunciado = fila('SELECT ID_Usuario, AliasPublico FROM usuario WHERE ID_Usuario = ?', [$prenda['ID_Vendedor']]);
elseif ($idUsuario) $denunciado = fila('SELECT ID_Usuario, AliasPublico FROM usuario WHERE ID_Usuario = ?', [$idUsuario]);
if (!$prenda && !$denunciado) pagina_error(404, 'No encontrado', 'No encontramos lo que querés denunciar.');           // RN-12: debe referirse a una prenda o a un usuario
if ($denunciado && (int)$denunciado['ID_Usuario'] === $uid) pagina_error(403, 'No permitido', 'No podés denunciarte a vos mismo ni a tus propias publicaciones.');

$err = [];
if (es_post()) {
    verificar_csrf();
    $motivo = post('motivo'); $desc = post('descripcion');
    if (!in_array($motivo, MOTIVOS_DENUNCIA, true)) $err[] = 'Elegí el motivo de la denuncia.';
    if (largo($desc) > 500)                          $err[] = 'La descripción admite hasta 500 caracteres.';
    if ($motivo === 'Otro' && $desc === '')          $err[] = 'Si el motivo es «Otro», contanos qué pasó.';
    if (!$err && valor("SELECT COUNT(*) FROM denuncia WHERE ID_Denunciante = ? AND ID_Prenda <=> ? AND ID_Usuario_Denunciado <=> ? AND Estado IN ('Pendiente','En revision')",
            [$uid, $prenda ? $idPrenda : null, $denunciado ? (int)$denunciado['ID_Usuario'] : null])) {
        $err[] = 'Ya denunciaste esto y está en revisión. Gracias por avisarnos.';
    }
    if (!$err) {
        q("INSERT INTO denuncia (ID_Denunciante, ID_Prenda, ID_Usuario_Denunciado, Motivo, Descripcion, Fecha_Denuncia, Estado) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')",
          [$uid, $prenda ? $idPrenda : null, $denunciado ? (int)$denunciado['ID_Usuario'] : null, $motivo, $desc === '' ? null : $desc, ahora()]);
        aviso('Recibimos tu denuncia. Un administrador la va a revisar. ¡Gracias por cuidar la comunidad!');
        ir($prenda ? url('prenda.php?id=' . $idPrenda) : url('index.php'));
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="form-caja form-centrado">
  <h1>Denunciar <?= $prenda ? 'una publicación' : 'un usuario' ?></h1>
  <p class="muted">
    <?php if ($prenda): ?>Publicación: <b><?= e($prenda['Titulo']) ?></b><?= $denunciado ? ' · Vendedor: ' . e($denunciado['AliasPublico']) : '' ?>
    <?php else: ?>Usuario: <b><?= e($denunciado['AliasPublico']) ?></b><?php endif; ?>
  </p>
  <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post"><?= csrf_campo() ?>
    <input type="hidden" name="prenda" value="<?= $prenda ? $idPrenda : 0 ?>"><input type="hidden" name="usuario" value="<?= $denunciado ? (int)$denunciado['ID_Usuario'] : 0 ?>">
    <?= selector('Motivo', 'motivo', lista_a_opciones(MOTIVOS_DENUNCIA), post('motivo'), 'Elegir motivo…', ['required' => true]) ?>
    <?= area('Detalle (opcional)', 'descripcion', post('descripcion'), ['maxlength' => 500, 'rows' => 4, 'data-contador' => '#cont-den'], 'Contanos qué viste: cuanto más claro, más rápido lo resolvemos.') ?>
    <small class="ayuda" id="cont-den"></small>
    <p></p><div class="acciones"><button class="btn btn-peligro" type="submit">Enviar denuncia</button><a href="javascript:history.back()">Cancelar</a></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
