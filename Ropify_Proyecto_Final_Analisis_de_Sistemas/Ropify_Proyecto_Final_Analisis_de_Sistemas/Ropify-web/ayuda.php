<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Ayuda'; $activo = 'ayuda';
$u = usuario();
$err = [];
$v = ['nombre' => $u ? $u['Nombre'] . ' ' . $u['Apellido'] : '', 'email' => $u['Email'] ?? '', 'tipo' => obtener('tipo') === 'reclamo' ? 'Reclamo' : 'Consulta', 'mensaje' => '', 'pedido' => (string)(int)($_GET['pedido'] ?? 0)];

if (es_post()) {
    verificar_csrf();
    foreach (['nombre', 'email', 'tipo', 'mensaje', 'pedido'] as $k) $v[$k] = post($k);
    if (post('web') !== '') { aviso('¡Gracias! Recibimos tu consulta.'); ir(url('ayuda.php')); } // campo trampa para bots
    if ($v['nombre'] === '' || largo($v['nombre']) > 100)                 $err[] = 'Ingresá tu nombre (hasta 100 caracteres).';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || largo($v['email']) > 120) $err[] = 'El e-mail no es válido.';
    if (!in_array($v['tipo'], ['Consulta', 'Reclamo'], true))             $err[] = 'Elegí el tipo de mensaje.';
    if ($v['mensaje'] === '' || largo($v['mensaje']) > 1000)              $err[] = 'Escribí tu mensaje (hasta 1000 caracteres).';
    $idPedido = null;
    if ($v['pedido'] !== '' && (int)$v['pedido'] > 0) {
        // Solo se asocia el pedido si es del usuario (o es una consulta de un usuario sin sesión: se ignora)
        $idPedido = $u ? (valor('SELECT ID_Pedido FROM pedido WHERE ID_Pedido = ? AND ID_Comprador = ?', [(int)$v['pedido'], $u['ID_Usuario']]) ?: null) : null;
        if ($idPedido === null) $err[] = 'No encontramos ese pedido entre tus compras.';
    }
    if (!$err) {
        q('INSERT INTO consulta_ayuda (ID_Usuario, ID_Pedido, Tipo, Nombre, Email, Mensaje, Fecha) VALUES (?, ?, ?, ?, ?, ?, ?)',
          [$u['ID_Usuario'] ?? null, $idPedido, $v['tipo'], $v['nombre'], $v['email'], $v['mensaje'], ahora()]);
        enviar_correo($v['email'], 'Ropify · Recibimos tu ' . minusculas($v['tipo']), "Hola " . $v['nombre'] . ",\nrecibimos tu mensaje y te respondemos dentro de las 48 horas.\n\nTu mensaje:\n" . $v['mensaje'] . "\n\n— Equipo Ropify");
        aviso('¡Gracias! Recibimos tu mensaje y te respondemos en 48 horas.');
        ir(url('ayuda.php'));
    }
}

$faq = [
    ['¿Cómo se revisan las prendas?', 'Cada vendedor sube un mínimo de ' . MIN_FOTOS . ' fotos y un moderador de Ropify revisa la publicación (fotos, descripción y precio) antes de que aparezca en el catálogo. Intentamos hacerlo en 24 horas hábiles.'],
    ['¿Cuánto me queda si vendo?', 'El ' . porcentaje_vendedor() . ' % del precio de venta. Ropify retiene el ' . porcentaje_comision() . ' %. El envío lo paga el comprador, no se descuenta de tu parte.'],
    ['¿Quién paga el envío?', 'El comprador. El costo (' . dinero(COSTO_ENVIO) . ' por pedido) se informa antes de pagar. El vendedor despacha y carga el código de seguimiento, que el comprador ve en su pedido.'],
    ['¿Cuándo cobra el vendedor?', 'Cuando el pedido figura como «Entregado»: el comprador confirma que recibió la prenda o, si pasan ' . PLAZO_CONFIRMACION_DIAS . ' días desde el envío sin reclamos, se confirma automáticamente. Después Ropify liquida el ' . porcentaje_vendedor() . ' % al alias de cobro del vendedor.'],
    ['¿Qué pasa si el vendedor no despacha?', 'Si no despacha dentro de ' . PLAZO_DESPACHO_DIAS . ' días de acreditado el pago, el pedido se cancela y se te reintegra el importe.'],
    ['¿Puedo cancelar una compra?', 'Sí, mientras el pedido no haya sido enviado. Entrá a Mis compras, abrí el pedido y tocá «Cancelar pedido». Si ya estaba pagado, se reintegra el importe.'],
    ['Olvidé mi contraseña', 'Usá la opción «Recuperar contraseña». Te enviamos un enlace de un solo uso que vence a los ' . TOKEN_RECUPERACION_MINUTOS . ' minutos.'],
    ['¿Por qué me piden el DNI?', 'Para evitar cuentas falsas. Se valida el formato y que no esté repetido, y un administrador puede verificar tu identidad (verás la marca «Identidad verificada»). Tus datos personales nunca se muestran en tu perfil público.'],
    ['¿Quiero arrepentirme de una compra?', 'Tenés ' . DIAS_ARREPENTIMIENTO . ' días corridos para revocar la compra (Ley 24.240). Usá el <a href="arrepentimiento.php">botón de arrepentimiento</a>.'],
];
require __DIR__ . '/includes/header.php';
?>
<h1>Ayuda al cliente</h1>
<div class="dos-col">
  <div>
    <h2 style="margin-top:0">Preguntas frecuentes</h2>
    <?php foreach ($faq as [$preg, $resp]): ?>
      <details class="faq"><summary><?= e($preg) ?></summary><div><?= str_replace('href="arrepentimiento.php"', 'href="' . e(url('arrepentimiento.php')) . '"', $resp) ?></div></details>
    <?php endforeach; ?>
  </div>
  <div>
    <div class="form-caja">
      <h2 style="margin-top:0">Escribinos</h2>
      <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" action="<?= e(url('ayuda.php')) ?>"><?= csrf_campo() ?>
        <div class="oculto-bot" aria-hidden="true"><label>No completar<input name="web" tabindex="-1" autocomplete="off"></label></div>
        <?= campo('Nombre', 'nombre', $v['nombre'], ['maxlength' => 100, 'required' => true, 'autocomplete' => 'name']) ?>
        <?= campo('E-mail', 'email', $v['email'], ['type' => 'email', 'maxlength' => 120, 'required' => true, 'autocomplete' => 'email']) ?>
        <?= selector('Tipo de mensaje', 'tipo', ['Consulta' => 'Consulta o sugerencia', 'Reclamo' => 'Reclamo por una compra'], $v['tipo'], '') ?>
        <?php if ($u): ?><?= campo('N.º de pedido (solo para reclamos)', 'pedido', $v['pedido'] === '0' ? '' : $v['pedido'], ['type' => 'number', 'min' => 1]) ?><?php endif; ?>
        <?= area('Mensaje', 'mensaje', $v['mensaje'], ['maxlength' => 1000, 'required' => true, 'rows' => 5, 'data-contador' => '#cont-ayuda']) ?>
        <small class="ayuda" id="cont-ayuda"></small>
        <p></p><button class="btn" type="submit">Enviar</button>
      </form>
      <?php if (EMAIL_AYUDA !== ''): ?><p class="muted" style="margin-top:14px">También podés escribir a <a href="mailto:<?= e(EMAIL_AYUDA) ?>"><?= e(EMAIL_AYUDA) ?></a>.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
