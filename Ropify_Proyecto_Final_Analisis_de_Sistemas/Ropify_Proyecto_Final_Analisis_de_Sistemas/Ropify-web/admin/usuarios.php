<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$aid = (int)$u['ID_Usuario'];
$titulo = 'Usuarios'; $activo = 'usuarios'; $subnav = 'admin';

if (es_post()) {                                                      // CU-31 y CU-32 · RF-08 y RF-34
    verificar_csrf();
    $accion = post('accion'); $id = (int)post('id');
    $x = fila('SELECT * FROM usuario WHERE ID_Usuario = ?', [$id]);
    if (!$x) {
        aviso('No encontramos ese usuario.', 'error');
    } elseif ($accion === 'verificar') {
        q('UPDATE usuario SET Dni_Verificado = 1 WHERE ID_Usuario = ?', [$id]);
        registrar_admin('Identidad verificada', 'usuario', $id, $x['AliasPublico']);
        notificar($id, 'Ropify · Verificamos tu identidad', 'Un administrador verificó tu DNI. Ahora tu perfil muestra la marca «Identidad verificada».');
        aviso('Identidad de ' . $x['AliasPublico'] . ' verificada. Se le notificó.');
    } elseif ($accion === 'suspender' || $accion === 'reactivar') {
        if ($id === $aid || $x['Rol'] === 'Administrador') {
            aviso('No se pueden suspender cuentas de administradores (ni la propia).', 'error');
        } elseif ($accion === 'suspender' && $x['Estado_Cuenta'] === 'Activa') {
            $motivo = post('motivo');
            if ($motivo === '' || largo($motivo) > 200) { aviso('Escribí el motivo de la suspensión (hasta 200 caracteres).', 'error'); }
            else {
                q("UPDATE usuario SET Estado_Cuenta = 'Suspendida' WHERE ID_Usuario = ?", [$id]);
                registrar_admin('Cuenta suspendida', 'usuario', $id, $x['AliasPublico'] . ': ' . $motivo);
                notificar($id, 'Ropify · Tu cuenta fue suspendida', "Suspendimos tu cuenta.\nMotivo: $motivo\nPodés escribirnos desde Ayuda.");
                aviso('Cuenta de ' . $x['AliasPublico'] . ' suspendida.', 'info');
            }
        } elseif ($accion === 'reactivar' && $x['Estado_Cuenta'] === 'Suspendida') {
            q("UPDATE usuario SET Estado_Cuenta = 'Activa' WHERE ID_Usuario = ?", [$id]);
            registrar_admin('Cuenta reactivada', 'usuario', $id, $x['AliasPublico']);
            notificar($id, 'Ropify · Reactivamos tu cuenta', 'Tu cuenta volvió a estar activa. ¡Gracias por tu paciencia!');
            aviso('Cuenta de ' . $x['AliasPublico'] . ' reactivada.');
        }
    }
    ir(url('admin/usuarios.php' . (obtener('q') !== '' ? '?q=' . rawurlencode(obtener('q')) : '')));
}

$busca = obtener('q'); $estado = obtener('estado');
$where = ['1 = 1']; $par = [];
if ($busca !== '') {
    $l = '%' . addcslashes($busca, '%_\\') . '%';
    $where[] = '(AliasPublico LIKE ? OR Email LIKE ? OR Nombre LIKE ? OR Apellido LIKE ? OR CAST(DNI AS CHAR) LIKE ?)';
    array_push($par, $l, $l, $l, $l, $l);
}
if (in_array($estado, ['Activa', 'Suspendida', 'Eliminada'], true)) { $where[] = 'Estado_Cuenta = ?'; $par[] = $estado; } else { $estado = ''; }
$lista = filas('SELECT u.*, (SELECT COUNT(*) FROM prenda p WHERE p.ID_Vendedor = u.ID_Usuario) AS Prendas,
        (SELECT COUNT(*) FROM pedido pe WHERE pe.ID_Comprador = u.ID_Usuario) AS Compras
    FROM usuario u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.Fecha_Registro DESC LIMIT 100', $par);
require __DIR__ . '/../includes/header.php';
?>
<h1>Usuarios y verificación</h1>
<form class="filtros" method="get"><div class="filtros-grilla" style="margin-top:0">
  <?= campo('Buscar (alias, nombre, e-mail o DNI)', 'q', $busca, ['type' => 'search']) ?>
  <?= selector('Estado de la cuenta', 'estado', lista_a_opciones(['Activa', 'Suspendida', 'Eliminada']), $estado, 'Todos') ?>
  <div class="campo"><label>&nbsp;</label><button class="btn" type="submit">Buscar</button></div>
</div></form>
<div class="tabla-scroll"><table>
  <thead><tr><th>Usuario</th><th>Contacto</th><th>DNI</th><th>Actividad</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $x): ?>
    <tr>
      <td><b><?= e($x['AliasPublico']) ?></b><?= $x['Rol'] === 'Administrador' ? ' ' . badge('Admin', 'info') : '' ?><br><small><?= e($x['Nombre'] . ' ' . $x['Apellido']) ?> · desde <?= e(fecha($x['Fecha_Registro'])) ?></small></td>
      <td><small><?= e($x['Email']) ?><br><?= e($x['Telefono']) ?></small></td>
      <td><?= e($x['DNI']) ?><br><?= $x['Dni_Verificado'] ? '<span class="verificado">✔ verificado</span>' : '<small class="muted">sin verificar</small>' ?></td>
      <td><small><?= (int)$x['Prendas'] ?> prendas<br><?= (int)$x['Compras'] ?> compras</small></td>
      <td><?= badge($x['Estado_Cuenta'], $x['Estado_Cuenta'] === 'Activa' ? 'ok' : ($x['Estado_Cuenta'] === 'Suspendida' ? 'error' : 'neutro')) ?></td>
      <td>
        <?php if (!$x['Dni_Verificado'] && $x['Estado_Cuenta'] === 'Activa'): ?><?= boton_post('verificar', ['id' => $x['ID_Usuario']], 'Verificar DNI', 'btn btn-chico') ?><?php endif; ?>
        <?php if ($x['Rol'] !== 'Administrador' && $x['Estado_Cuenta'] === 'Activa'): ?>
          <form method="post" class="en-linea" data-confirmar="¿Suspender esta cuenta?"><?= csrf_campo() ?><input type="hidden" name="accion" value="suspender"><input type="hidden" name="id" value="<?= (int)$x['ID_Usuario'] ?>">
            <input name="motivo" placeholder="Motivo" maxlength="200" required style="width:140px;min-height:34px;padding:5px 8px"><button class="btn btn-chico btn-peligro btn-sec" type="submit">Suspender</button></form>
        <?php elseif ($x['Estado_Cuenta'] === 'Suspendida'): ?><?= boton_post('reactivar', ['id' => $x['ID_Usuario']], 'Reactivar', 'btn btn-chico') ?><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="6" class="muted">No hay usuarios con esos criterios.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
