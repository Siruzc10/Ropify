<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Consultas y reclamos'; $activo = 'consultas'; $subnav = 'admin';

if (es_post()) {
    verificar_csrf();
    $id = (int)post('id');
    if (post('accion') === 'atender') {
        q("UPDATE consulta_ayuda SET Estado = 'Atendida' WHERE ID_Consulta = ?", [$id]);
        registrar_admin('Consulta atendida', 'consulta', $id);
        aviso('Consulta #' . $id . ' marcada como atendida.');
    } elseif (post('accion') === 'responder') {
        $c = fila('SELECT * FROM consulta_ayuda WHERE ID_Consulta = ?', [$id]); $texto = post('respuesta');
        if ($c && $texto !== '' && largo($texto) <= 1000) {
            enviar_correo($c['Email'], 'Ropify · Respuesta a tu ' . minusculas($c['Tipo']), $texto . "\n\n— Equipo Ropify");
            q("UPDATE consulta_ayuda SET Estado = 'Atendida' WHERE ID_Consulta = ?", [$id]);
            registrar_admin('Consulta respondida', 'consulta', $id);
            aviso('Respuesta enviada a ' . $c['Email'] . '.');
        } else aviso('Escribí la respuesta (hasta 1000 caracteres).', 'error');
    }
    ir(url('admin/consultas.php' . (obtener('ver') !== '' ? '?ver=' . rawurlencode(obtener('ver')) : '')));
}
$ver = obtener('ver') === 'atendidas' ? 'atendidas' : 'pendientes';
$lista = filas('SELECT * FROM consulta_ayuda WHERE Estado = ? ORDER BY (Tipo = \'Arrepentimiento\') DESC, Fecha ASC LIMIT 100', [$ver === 'pendientes' ? 'Pendiente' : 'Atendida']);
require __DIR__ . '/../includes/header.php';
?>
<h1>Consultas, reclamos y arrepentimientos</h1>
<div class="tabs"><a<?= $ver === 'pendientes' ? ' class="activo"' : '' ?> href="<?= e(url('admin/consultas.php')) ?>">Pendientes</a><a<?= $ver === 'atendidas' ? ' class="activo"' : '' ?> href="<?= e(url('admin/consultas.php?ver=atendidas')) ?>">Atendidas</a></div>
<?php if (!$lista): ?><div class="vacio"><p>No hay consultas en esta lista.</p></div><?php endif; ?>
<?php foreach ($lista as $c): ?>
  <div class="caja">
    <p style="margin:0 0 6px"><?= badge($c['Tipo'], $c['Tipo'] === 'Consulta' ? 'neutro' : 'aviso') ?> <b>#<?= (int)$c['ID_Consulta'] ?></b> · <?= e($c['Nombre']) ?> (<a href="mailto:<?= e($c['Email']) ?>"><?= e($c['Email']) ?></a>) · <small><?= e(fecha_hora($c['Fecha'])) ?></small>
      <?= $c['ID_Pedido'] ? ' · <a href="' . e(url('admin/pedidos.php')) . '">Pedido #' . (int)$c['ID_Pedido'] . '</a>' : '' ?></p>
    <p><?= nl2br(e($c['Mensaje'])) ?></p>
    <?php if ($c['Estado'] === 'Pendiente'): ?>
      <form method="post" style="max-width:none"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$c['ID_Consulta'] ?>">
        <?= area('Respuesta por e-mail', 'respuesta', '', ['maxlength' => 1000, 'rows' => 2]) ?>
        <div class="acciones"><button class="btn btn-chico" type="submit" name="accion" value="responder">Responder y cerrar</button><button class="btn btn-chico btn-sec" type="submit" name="accion" value="atender">Marcar atendida</button></div>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
