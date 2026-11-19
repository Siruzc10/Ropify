<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Recuperar contraseña';
$enlaceDemo = '';

if (es_post()) {
    verificar_csrf();
    $email = post('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        aviso('Ingresá un e-mail válido.', 'error');
        ir(url('recuperar.php'));
    }
    $u = fila("SELECT ID_Usuario, Nombre FROM usuario WHERE Email = ? AND Estado_Cuenta = 'Activa'", [$email]);
    if ($u) {
        // RF-04: enlace de un solo uso con vencimiento. Se guarda solo el hash del token.
        $token = bin2hex(random_bytes(32));
        q('UPDATE token_recuperacion SET Usado = 1 WHERE ID_Usuario = ? AND Usado = 0', [$u['ID_Usuario']]);
        q('INSERT INTO token_recuperacion (ID_Usuario, Token_Hash, Fecha_Creacion, Fecha_Expira, Usado) VALUES (?, ?, ?, ?, 0)',
          [$u['ID_Usuario'], hash('sha256', $token), ahora(), date('Y-m-d H:i:s', time() + TOKEN_RECUPERACION_MINUTOS * 60)]);
        $enlace = url_abs('restablecer.php?token=' . $token);
        enviar_correo($email, 'Ropify · Restablecé tu contraseña', "Hola " . $u['Nombre'] . ",\nPara elegir una contraseña nueva entrá a este enlace (sirve una sola vez y vence en " . TOKEN_RECUPERACION_MINUTOS . " minutos):\n$enlace\n\nSi no lo pediste vos, ignorá este mensaje.");
        if (MODO_DEMO) $_SESSION['enlace_demo'] = $enlace;
    }
    // CU-04 alt. 4.1: mismo mensaje exista o no la cuenta, para no revelar qué cuentas están registradas
    aviso('Si el e-mail está registrado, te enviamos un enlace para restablecer tu contraseña. Vence en ' . TOKEN_RECUPERACION_MINUTOS . ' minutos.', 'info');
    ir(url('recuperar.php'));
}
if (MODO_DEMO && !empty($_SESSION['enlace_demo'])) { $enlaceDemo = (string)$_SESSION['enlace_demo']; unset($_SESSION['enlace_demo']); }
require __DIR__ . '/includes/header.php';
?>
<div class="form-caja form-centrado">
  <h1>Recuperar contraseña</h1>
  <p class="muted">Ingresá el e-mail de tu cuenta y te enviamos un enlace para elegir una contraseña nueva.</p>
  <?php if ($enlaceDemo !== ''): ?>
    <div class="nota"><b>Modo demostración:</b> como en XAMPP no hay correo real, este es el enlace que se enviaría por e-mail:<br><a href="<?= e($enlaceDemo) ?>"><?= e($enlaceDemo) ?></a></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('recuperar.php')) ?>"><?= csrf_campo() ?>
    <?= campo('E-mail', 'email', '', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <div class="acciones"><button class="btn" type="submit">Enviar enlace</button><a href="<?= e(url('login.php')) ?>">Volver a ingresar</a></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
