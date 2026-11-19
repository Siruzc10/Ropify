<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Nueva contraseña';
$token = obtener('token') !== '' ? obtener('token') : post('token');
$fila = $token !== '' ? fila('SELECT * FROM token_recuperacion WHERE Token_Hash = ? AND Usado = 0 AND Fecha_Expira > ?', [hash('sha256', $token), ahora()]) : null;
$err = [];

if ($fila && es_post()) {
    verificar_csrf();
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if ($e = validar_password($p1)) $err[] = $e;                        // CU-04 alt. 8.1
    if ($p1 !== $p2)                $err[] = 'Las contraseñas no coinciden.';
    if (!$err) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            // el UPDATE condicionado garantiza que el enlace sirva una sola vez
            $st = q('UPDATE token_recuperacion SET Usado = 1 WHERE ID_Token = ? AND Usado = 0', [$fila['ID_Token']]);
            if ($st->rowCount() !== 1) throw new RuntimeException('token usado');
            q('UPDATE usuario SET Contrasena_hash = ? WHERE ID_Usuario = ?', [password_hash($p1, PASSWORD_BCRYPT), $fila['ID_Usuario']]);
            $pdo->commit();
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            aviso('Ese enlace ya fue usado. Pedí uno nuevo.', 'error');
            ir(url('recuperar.php'));
        }
        $email = (string)valor('SELECT Email FROM usuario WHERE ID_Usuario = ?', [$fila['ID_Usuario']]);
        q('DELETE FROM intento_login WHERE Email = ?', [minusculas($email)]);
        notificar((int)$fila['ID_Usuario'], 'Ropify · Cambiaste tu contraseña', 'Tu contraseña se actualizó correctamente. Si no fuiste vos, escribinos desde Ayuda.');
        aviso('Contraseña actualizada. Ya podés ingresar.');
        ir(url('login.php'));
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="form-caja form-centrado">
<?php if (!$fila): ?>
  <h1>Enlace no válido</h1>
  <p>Este enlace venció o ya se usó. Podés pedir uno nuevo.</p>
  <a class="btn" href="<?= e(url('recuperar.php')) ?>">Pedir un enlace nuevo</a>
<?php else: ?>
  <h1>Elegí tu nueva contraseña</h1>
  <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('restablecer.php')) ?>"><?= csrf_campo() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?= campo('Nueva contraseña', 'password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password'], 'Mínimo 8 caracteres, con letras y números.') ?>
    <?= campo('Repetir nueva contraseña', 'password2', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <button class="btn" type="submit">Guardar contraseña</button>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
