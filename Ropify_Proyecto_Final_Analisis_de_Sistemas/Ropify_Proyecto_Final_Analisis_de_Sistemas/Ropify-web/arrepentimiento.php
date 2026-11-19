<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Botón de arrepentimiento';
$u = exigir_login();
$err = [];

// Pedidos que todavía están dentro del plazo de arrepentimiento (Res. 424/2020): pagados, enviados o entregados
$elegibles = filas("SELECT pe.ID_Pedido, pe.Total, pe.Estado, COALESCE(pe.Fecha_Entrega, pe.Fecha_pedido) AS Base
    FROM pedido pe WHERE pe.ID_Comprador = ? AND pe.Estado IN ('Pagado','Enviado','Entregado') AND COALESCE(pe.Fecha_Entrega, pe.Fecha_pedido) >= ?
    ORDER BY pe.Fecha_pedido DESC", [$u['ID_Usuario'], hace(DIAS_ARREPENTIMIENTO * 86400)]);
$opciones = [];
foreach ($elegibles as $pe) $opciones[$pe['ID_Pedido']] = 'Pedido #' . $pe['ID_Pedido'] . ' · ' . dinero($pe['Total']) . ' · ' . ESTADOS_PEDIDO[$pe['Estado']];

if (es_post()) {
    verificar_csrf();
    $idp = (int)post('pedido'); $com = post('comentario');
    if (!isset($opciones[$idp])) $err[] = 'Elegí un pedido que esté dentro del plazo de ' . DIAS_ARREPENTIMIENTO . ' días.';
    if (largo($com) > 800)       $err[] = 'El comentario admite hasta 800 caracteres.';
    if (!$err && valor("SELECT COUNT(*) FROM consulta_ayuda WHERE ID_Pedido = ? AND Tipo = 'Arrepentimiento'", [$idp])) $err[] = 'Ya registramos una solicitud de arrepentimiento para ese pedido.';
    if (!$err) {
        q("INSERT INTO consulta_ayuda (ID_Usuario, ID_Pedido, Tipo, Nombre, Email, Mensaje, Fecha) VALUES (?, ?, 'Arrepentimiento', ?, ?, ?, ?)",
          [$u['ID_Usuario'], $idp, $u['Nombre'] . ' ' . $u['Apellido'], $u['Email'], $com !== '' ? $com : 'Solicitud de arrepentimiento (sin comentario).', ahora()]);
        enviar_correo($u['Email'], 'Ropify · Solicitud de arrepentimiento del pedido #' . $idp, "Registramos tu solicitud de arrepentimiento para el pedido #$idp. Un administrador se va a comunicar con vos para coordinar la devolución y el reintegro.");
        aviso('Registramos tu solicitud de arrepentimiento. Te contactamos para coordinar la devolución.');
        ir(url('compras.php'));
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Botón de arrepentimiento</h1>
<p class="muted">Podés revocar tu compra dentro de los <?= DIAS_ARREPENTIMIENTO ?> días corridos (Ley 24.240 y Res. 424/2020).</p>
<?php if (!$opciones): ?>
  <div class="vacio"><p>No tenés compras dentro del plazo de arrepentimiento.</p><a class="btn" href="<?= e(url('compras.php')) ?>">Ver mis compras</a></div>
<?php else: ?>
  <div class="form-caja">
    <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_campo() ?>
      <?= selector('Pedido', 'pedido', $opciones, post('pedido'), 'Elegir pedido…', ['required' => true]) ?>
      <?= area('Comentario (opcional)', 'comentario', post('comentario'), ['maxlength' => 800, 'rows' => 3]) ?>
      <button class="btn btn-peligro" type="submit">Quiero arrepentirme de la compra</button>
    </form>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
