<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (usuario()) ir(url('perfil.php'));
$titulo = 'Ingresar';
$error = '';
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

if (es_post()) {
    verificar_csrf();
    $email = post('email'); $pass = (string)($_POST['password'] ?? '');

    // RF-03 / CP-08: bloqueo temporal tras MAX_INTENTOS_LOGIN intentos fallidos
    $fallidos = (int)valor('SELECT COUNT(*) FROM intento_login WHERE Email = ? AND Fecha > ?', [minusculas($email), hace(BLOQUEO_MINUTOS * 60)]);
    if ($fallidos >= MAX_INTENTOS_LOGIN) {
        $error = 'Demasiados intentos fallidos. Por seguridad, esperá ' . BLOQUEO_MINUTOS . ' minutos antes de volver a intentar.';
    } else {
        $u = fila('SELECT * FROM usuario WHERE Email = ?', [$email]);
        // password_verify se ejecuta siempre, para no revelar qué cuentas existen por el tiempo de respuesta
        $hash = $u['Contrasena_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ1Hq3Jx9yH4C0qWk0oH1lZ8m7xq5q5K';
        $correcta = password_verify($pass, $hash) && $u;
        if ($correcta && $u['Estado_Cuenta'] === 'Activa') {
            q('DELETE FROM intento_login WHERE Email = ?', [minusculas($email)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['ID_Usuario'];
            $_SESSION['actividad'] = time();
            $volver = (string)($_SESSION['volver'] ?? ''); unset($_SESSION['volver']);
            aviso('¡Hola, ' . $u['AliasPublico'] . '!');
            ir(destino_seguro($volver, url(es_admin($u) ? 'admin/index.php' : 'perfil.php')));
        }
        if ($correcta && $u['Estado_Cuenta'] === 'Suspendida') {
            $error = 'Tu cuenta está suspendida. Escribinos desde la sección Ayuda para resolverlo.';
        } else {
            q('INSERT INTO intento_login (Email, IP, Fecha) VALUES (?, ?, ?)', [minusculas($email), $ip, ahora()]);
            $error = 'E-mail o contraseña incorrectos.';  // mensaje genérico
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="form-caja form-centrado">
  <h1>Ingresar</h1>
  <?php if ($error): ?><p class="errores" style="padding-left:16px"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('login.php')) ?>"><?= csrf_campo() ?>
    <?= campo('E-mail', 'email', post('email'), ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'autofocus' => true]) ?>
    <?= campo('Contraseña', 'password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <div class="acciones"><button class="btn" type="submit">Ingresar</button><a href="<?= e(url('recuperar.php')) ?>">Olvidé mi contraseña</a></div>
  </form>
  <p style="margin-top:16px">¿Primera vez? <a href="<?= e(url('registro.php')) ?>">Creá tu cuenta</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
