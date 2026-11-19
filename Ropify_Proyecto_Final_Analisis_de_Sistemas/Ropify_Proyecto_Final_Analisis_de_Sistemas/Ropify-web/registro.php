<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (usuario()) ir(url('perfil.php'));
$titulo = 'Crear cuenta';
$err = [];
$v = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'telefono' => '', 'alias' => ''];

if (es_post()) {
    verificar_csrf();
    foreach ($v as $k => $_) $v[$k] = post($k);
    $pass = (string)($_POST['password'] ?? ''); $pass2 = (string)($_POST['password2'] ?? '');
    $terminos = isset($_POST['terminos']);

    if ($v['nombre'] === '' || largo($v['nombre']) > 60)       $err[] = 'Ingresá tu nombre (hasta 60 caracteres).';
    if ($v['apellido'] === '' || largo($v['apellido']) > 60)   $err[] = 'Ingresá tu apellido (hasta 60 caracteres).';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || largo($v['email']) > 120) $err[] = 'El e-mail no es válido.';       // CP-03
    if (!preg_match('/^\d{7,8}$/', $v['dni']))                  $err[] = 'DNI inválido: debe tener 7 u 8 números, sin puntos.'; // CP-04
    if (!validar_telefono($v['telefono']))                      $err[] = 'El teléfono no es válido (ej: 11 2345 6789 o +54 9 11 2345 6789).';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $v['alias']))    $err[] = 'El alias público debe tener 3 a 50 caracteres: letras, números, punto o guión bajo.';
    if ($e = validar_password($pass))                           $err[] = $e;                                                     // CP-05
    if ($pass !== $pass2)                                       $err[] = 'Las contraseñas no coinciden.';
    if (!$terminos)                                             $err[] = 'Tenés que aceptar los Términos y Condiciones y la Política de Privacidad.'; // CP-06

    if (!$err) { // RF-02 / RN-10: unicidad
        if (valor('SELECT COUNT(*) FROM usuario WHERE DNI = ?', [(int)$v['dni']]))                     $err[] = 'Ya existe una cuenta con ese DNI.';
        if (valor('SELECT COUNT(*) FROM usuario WHERE Email = ?', [$v['email']]))                      $err[] = 'Ya existe una cuenta con ese e-mail.';
        if (valor('SELECT COUNT(*) FROM usuario WHERE AliasPublico = ?', [$v['alias']]))               $err[] = 'Ese alias público ya está en uso.';
    }
    if (!$err) {
        try {
            q("INSERT INTO usuario (Nombre, Apellido, DNI, Dni_Verificado, Email, Contrasena_hash, Rol, Estado_Cuenta, Telefono, AliasPublico, Fecha_Registro)
               VALUES (?, ?, ?, 0, ?, ?, 'Usuario', 'Activa', ?, ?, ?)",
              [$v['nombre'], $v['apellido'], (int)$v['dni'], $v['email'], password_hash($pass, PASSWORD_BCRYPT), $v['telefono'], $v['alias'], ahora()]);
            enviar_correo($v['email'], '¡Bienvenido/a a Ropify!', "Hola " . $v['nombre'] . ",\ntu cuenta ya está creada. Ya podés comprar y vender ropa usada en Ropify.\n\nTu estilo, entre personas.\n" . url_abs('catalogo.php'));
            aviso('¡Cuenta creada! Ingresá con tu e-mail y tu contraseña.');
            ir(url('login.php'));
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;           // choque de claves únicas por una carrera
            $err[] = 'Ese DNI, e-mail o alias ya está registrado.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="form-caja ancha form-centrado">
  <h1>Crear cuenta</h1>
  <p class="muted">Para comprar o vender necesitás una cuenta. Explorar el catálogo es libre.</p>
  <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('registro.php')) ?>" novalidate><?= csrf_campo() ?>
    <div class="fila-campos">
      <?= campo('Nombre', 'nombre', $v['nombre'], ['maxlength' => 60, 'required' => true, 'autocomplete' => 'given-name']) ?>
      <?= campo('Apellido', 'apellido', $v['apellido'], ['maxlength' => 60, 'required' => true, 'autocomplete' => 'family-name']) ?>
    </div>
    <div class="fila-campos">
      <?= campo('DNI', 'dni', $v['dni'], ['inputmode' => 'numeric', 'maxlength' => 8, 'required' => true], 'Sin puntos. Se valida que no esté repetido.') ?>
      <?= campo('Teléfono', 'telefono', $v['telefono'], ['type' => 'tel', 'maxlength' => 20, 'required' => true, 'autocomplete' => 'tel']) ?>
    </div>
    <?= campo('E-mail', 'email', $v['email'], ['type' => 'email', 'maxlength' => 120, 'required' => true, 'autocomplete' => 'email']) ?>
    <?= campo('Alias público', 'alias', $v['alias'], ['maxlength' => 50, 'required' => true, 'autocomplete' => 'username'], 'Es el nombre que ven los demás. Letras, números, punto o guión bajo.') ?>
    <div class="fila-campos">
      <?= campo('Contraseña', 'password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password'], 'Mínimo 8 caracteres, con letras y números.') ?>
      <?= campo('Repetir contraseña', 'password2', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    </div>
    <label class="check"><input type="checkbox" name="terminos" value="1"<?= isset($_POST['terminos']) ? ' checked' : '' ?>>
      <span>Soy mayor de 18 años y acepto los <a href="<?= e(url('terminos.php')) ?>" target="_blank" rel="noopener">Términos y Condiciones</a> y la <a href="<?= e(url('privacidad.php')) ?>" target="_blank" rel="noopener">Política de Privacidad</a>.</span></label>
    <div class="acciones"><button class="btn" type="submit">Registrarme</button><a href="<?= e(url('login.php')) ?>">Ya tengo cuenta</a></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
