<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mi perfil';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];
$err = [];

if (es_post()) {
    verificar_csrf();
    $accion = post('accion');

    if ($accion === 'datos') { // CU-12 · RF-05
        $d = ['nombre' => post('nombre'), 'apellido' => post('apellido'), 'email' => post('email'), 'telefono' => post('telefono'),
              'alias' => post('alias'), 'bio' => post('bio'), 'cobro' => post('cobro')];
        if ($d['nombre'] === '' || largo($d['nombre']) > 60)     $err[] = 'Ingresá tu nombre (hasta 60 caracteres).';
        if ($d['apellido'] === '' || largo($d['apellido']) > 60) $err[] = 'Ingresá tu apellido (hasta 60 caracteres).';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || largo($d['email']) > 120) $err[] = 'El e-mail no es válido.';
        elseif (valor('SELECT COUNT(*) FROM usuario WHERE Email = ? AND ID_Usuario <> ?', [$d['email'], $uid])) $err[] = 'Ese e-mail ya está registrado en otra cuenta.'; // CP-37
        if (!validar_telefono($d['telefono']))                   $err[] = 'El teléfono no es válido.';
        if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $d['alias'])) $err[] = 'Alias inválido (3 a 50 caracteres: letras, números, punto o guión bajo).';
        elseif (valor('SELECT COUNT(*) FROM usuario WHERE AliasPublico = ? AND ID_Usuario <> ?', [$d['alias'], $uid])) $err[] = 'Ese alias ya está en uso.';
        if (largo($d['bio']) > 300)                              $err[] = 'La biografía admite hasta 300 caracteres.';
        if ($d['cobro'] !== '' && !preg_match('/^[A-Za-z0-9.\-_]{6,40}$/', $d['cobro'])) $err[] = 'El alias de cobro debe tener entre 6 y 40 caracteres (letras, números, punto o guión), o ser tu CBU/CVU de 22 dígitos.';
        if (!$err) {
            q('UPDATE usuario SET Nombre = ?, Apellido = ?, Email = ?, Telefono = ?, AliasPublico = ?, Biografia = ?, Alias_Cobro = ? WHERE ID_Usuario = ?',
              [$d['nombre'], $d['apellido'], $d['email'], $d['telefono'], $d['alias'], $d['bio'] === '' ? null : $d['bio'], $d['cobro'] === '' ? null : $d['cobro'], $uid]);
            aviso('Tus datos se actualizaron.');
            ir(url('perfil.php'));
        }
    } elseif ($accion === 'password') {
        $actual = (string)($_POST['actual'] ?? ''); $n1 = (string)($_POST['nueva'] ?? ''); $n2 = (string)($_POST['nueva2'] ?? '');
        if (!password_verify($actual, $u['Contrasena_hash'])) $err[] = 'La contraseña actual no es correcta.';
        if ($e = validar_password($n1))                       $err[] = $e;
        if ($n1 !== $n2)                                      $err[] = 'Las contraseñas nuevas no coinciden.';
        if (!$err) {
            q('UPDATE usuario SET Contrasena_hash = ? WHERE ID_Usuario = ?', [password_hash($n1, PASSWORD_BCRYPT), $uid]);
            session_regenerate_id(true);
            aviso('Contraseña actualizada.');
            ir(url('perfil.php'));
        }
    } elseif ($accion === 'baja') { // baja lógica (opuesto de «Registrándose», CU-12)
        $actual = (string)($_POST['actual'] ?? '');
        $activos = (int)valor("SELECT COUNT(DISTINCT pe.ID_Pedido) FROM pedido pe LEFT JOIN detalle_pedido d ON d.ID_Pedido = pe.ID_Pedido LEFT JOIN prenda p ON p.ID_Prenda = d.ID_Prenda
            WHERE (pe.ID_Comprador = ? OR p.ID_Vendedor = ?) AND pe.Estado IN ('Pendiente_Pago','Pagado','Enviado')", [$uid, $uid]);
        $porCobrar = (int)valor("SELECT COUNT(*) FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido
            WHERE p.ID_Vendedor = ? AND pe.Estado = 'Entregado' AND d.Liquidado = 0", [$uid]);
        if (!password_verify($actual, $u['Contrasena_hash'])) $err[] = 'La contraseña no es correcta.';
        if (es_admin($u))                                     $err[] = 'Las cuentas de administrador no se pueden dar de baja desde acá.';
        if ($activos > 0)                                     $err[] = 'Tenés pedidos en curso (como comprador o vendedor). Esperá a que terminen para dar de baja la cuenta.';
        if ($porCobrar > 0)                                   $err[] = 'Tenés ventas entregadas pendientes de liquidación. Cobralas antes de dar de baja la cuenta.';
        if (!$err) {
            q("UPDATE prenda SET Estado = 'Pausada' WHERE ID_Vendedor = ? AND Estado = 'Aprobada'", [$uid]);
            q("UPDATE usuario SET Estado_Cuenta = 'Eliminada' WHERE ID_Usuario = ?", [$uid]);
            notificar($uid, 'Ropify · Tu cuenta fue dada de baja', 'Dimos de baja tu cuenta. Conservamos únicamente los registros de operaciones que la ley nos obliga a guardar.');
            $_SESSION['uid'] = null; unset($_SESSION['uid']);
            aviso('Tu cuenta fue dada de baja. ¡Gracias por haber sido parte de Ropify!', 'info');
            ir(url('index.php'));
        }
    }
    if ($err) foreach ($err as $m) aviso($m, 'error');
    $u = usuario(true);
}
$nVentas = (int)valor("SELECT COUNT(*) FROM prenda WHERE ID_Vendedor = ? AND Estado = 'Aprobada'", [$uid]);
$nCompras = (int)valor('SELECT COUNT(*) FROM pedido WHERE ID_Comprador = ?', [$uid]);
require __DIR__ . '/includes/header.php';
?>
<h1>Mi perfil</h1>
<div class="metricas">
  <a class="metrica" href="<?= e(url('compras.php')) ?>"><b><?= $nCompras ?></b><span>Compras</span></a>
  <a class="metrica" href="<?= e(url('publicaciones.php')) ?>"><b><?= $nVentas ?></b><span>Prendas en venta</span></a>
  <a class="metrica" href="<?= e(url('direcciones.php')) ?>"><b><?= (int)valor('SELECT COUNT(*) FROM direccion WHERE ID_Usuario = ?', [$uid]) ?></b><span>Direcciones</span></a>
  <a class="metrica" href="<?= e(url('mensajes.php')) ?>"><b><?= (int)valor('SELECT COUNT(*) FROM mensaje WHERE ID_Receptor = ? AND Leido = 0', [$uid]) ?></b><span>Mensajes sin leer</span></a>
</div>

<div class="dos-col iguales">
  <div class="form-caja" style="max-width:none">
    <h2 style="margin-top:0">Mis datos</h2>
    <p>DNI <b><?= e($u['DNI']) ?></b> — <?= $u['Dni_Verificado'] ? '<span class="verificado">✔ identidad verificada</span>' : '<span class="muted">sin verificar (un administrador puede verificar tu identidad)</span>' ?></p>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="datos">
      <div class="fila-campos">
        <?= campo('Nombre', 'nombre', $_POST['nombre'] ?? $u['Nombre'], ['maxlength' => 60, 'required' => true]) ?>
        <?= campo('Apellido', 'apellido', $_POST['apellido'] ?? $u['Apellido'], ['maxlength' => 60, 'required' => true]) ?>
      </div>
      <?= campo('E-mail', 'email', $_POST['email'] ?? $u['Email'], ['type' => 'email', 'maxlength' => 120, 'required' => true]) ?>
      <div class="fila-campos">
        <?= campo('Teléfono', 'telefono', $_POST['telefono'] ?? $u['Telefono'], ['type' => 'tel', 'maxlength' => 20, 'required' => true]) ?>
        <?= campo('Alias público', 'alias', $_POST['alias'] ?? $u['AliasPublico'], ['maxlength' => 50, 'required' => true]) ?>
      </div>
      <?= area('Biografía (opcional)', 'bio', $_POST['bio'] ?? ($u['Biografia'] ?? ''), ['maxlength' => 300, 'rows' => 3, 'data-contador' => '#cont-bio'], 'Se muestra en tu perfil público.') ?>
      <small class="ayuda" id="cont-bio"></small>
      <?= campo('Alias de cobro (CBU/CVU) para vendedores', 'cobro', $_POST['cobro'] ?? ($u['Alias_Cobro'] ?? ''), ['maxlength' => 40], 'Acá te transferimos el ' . porcentaje_vendedor() . ' % de tus ventas entregadas. Solo lo ve el equipo de Ropify.') ?>
      <p></p><button class="btn" type="submit">Guardar cambios</button>
    </form>
  </div>

  <div>
    <div class="form-caja" style="max-width:none">
      <h2 style="margin-top:0">Cambiar contraseña</h2>
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="password">
        <?= campo('Contraseña actual', 'actual', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <?= campo('Nueva contraseña', 'nueva', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password'], 'Mínimo 8 caracteres, con letras y números.') ?>
        <?= campo('Repetir nueva contraseña', 'nueva2', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <button class="btn btn-sec" type="submit">Cambiar contraseña</button>
      </form>
    </div>
    <details class="caja">
      <summary><b>Dar de baja mi cuenta</b></summary>
      <p class="muted" style="margin-top:10px">Es una baja lógica: tu cuenta y tus publicaciones dejan de verse. Se conservan los registros de operaciones que exige la ley (Ley 25.326, derecho de supresión).</p>
      <form method="post" data-confirmar="¿Seguro que querés dar de baja tu cuenta?"><?= csrf_campo() ?><input type="hidden" name="accion" value="baja">
        <?= campo('Confirmá con tu contraseña', 'actual', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <button class="btn btn-peligro btn-sec" type="submit">Dar de baja</button>
      </form>
    </details>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
