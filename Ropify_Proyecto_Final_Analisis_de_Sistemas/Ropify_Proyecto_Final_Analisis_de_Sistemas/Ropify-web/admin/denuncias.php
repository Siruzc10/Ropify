<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$aid = (int)$u['ID_Usuario'];
$titulo = 'Denuncias'; $activo = 'denuncias'; $subnav = 'admin';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (es_post()) {                                                      // CU-33 y CU-34 · RF-33
    verificar_csrf();
    $accion = post('accion');
    $d = fila('SELECT * FROM denuncia WHERE ID_Denuncia = ?', [$id]);
    if (!$d) {
        aviso('No encontramos esa denuncia.', 'error');
        ir(url('admin/denuncias.php'));
    }
    if ($accion === 'revisar' && in_array($d['Estado'], ['Pendiente', 'En revision'], true)) {
        q("UPDATE denuncia SET Estado = 'En revision', ID_Admin_Resolutor = ? WHERE ID_Denuncia = ?", [$aid, $id]);
        aviso('Denuncia #' . $id . ' en revisión.', 'info');
    } elseif ($accion === 'resolver' && in_array($d['Estado'], ['Pendiente', 'En revision'], true)) {
        $resultado = post('resultado'); $texto = post('resolucion'); $sancion = post('sancion');
        if (!in_array($resultado, ['Resuelta', 'Descartada'], true) || $texto === '' || largo($texto) > 255) {
            aviso('Elegí el resultado y escribí la resolución (hasta 255 caracteres).', 'error');
        } elseif ($resultado === 'Descartada' && $sancion !== '') {
            aviso('Una denuncia descartada no puede llevar sanción.', 'error');
        } else {
            $pdo = db(); $pdo->beginTransaction();
            $aplicada = '';
            try {
                if ($resultado === 'Resuelta' && $sancion === 'pausar' && $d['ID_Prenda']) {
                    q("UPDATE prenda SET Estado = 'Pausada' WHERE ID_Prenda = ? AND Estado = 'Aprobada'", [$d['ID_Prenda']]); $aplicada = 'Publicación pausada';
                } elseif ($resultado === 'Resuelta' && $sancion === 'rechazar' && $d['ID_Prenda']) {
                    q("UPDATE prenda SET Estado = 'Rechazada', Motivo_Rechazo = ? WHERE ID_Prenda = ? AND Estado IN ('Aprobada','Pausada','Pendiente')", ['Retirada por denuncia: ' . $texto, $d['ID_Prenda']]); $aplicada = 'Publicación rechazada';
                } elseif ($resultado === 'Resuelta' && $sancion === 'suspender') {
                    $objetivo = $d['ID_Usuario_Denunciado'] ?: ($d['ID_Prenda'] ? valor('SELECT ID_Vendedor FROM prenda WHERE ID_Prenda = ?', [$d['ID_Prenda']]) : null);
                    if ($objetivo && (int)$objetivo !== $aid && !es_admin(fila('SELECT Rol FROM usuario WHERE ID_Usuario = ?', [$objetivo]))) {
                        q("UPDATE usuario SET Estado_Cuenta = 'Suspendida' WHERE ID_Usuario = ?", [$objetivo]); $aplicada = 'Cuenta suspendida';
                        notificar((int)$objetivo, 'Ropify · Tu cuenta fue suspendida', "Suspendimos tu cuenta a raíz de una denuncia.\nMotivo: $texto\nPodés escribirnos desde Ayuda si creés que es un error.");
                    }
                }
                q('UPDATE denuncia SET Estado = ?, Resolucion = ?, ID_Admin_Resolutor = ?, Fecha_Resolucion = ? WHERE ID_Denuncia = ?',
                  [$resultado, ($aplicada !== '' ? $aplicada . '. ' : '') . $texto, $aid, ahora(), $id]);
                registrar_admin('Denuncia ' . minusculas($resultado), 'denuncia', $id, ($aplicada !== '' ? $aplicada . ': ' : '') . $texto);
                $pdo->commit();
            } catch (Throwable $t) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $t;
            }
            notificar((int)$d['ID_Denunciante'], 'Ropify · Resolvimos tu denuncia #' . $id, "Revisamos tu denuncia: $resultado.\n$texto\nGracias por ayudarnos a cuidar la comunidad.");
            if ($aplicada !== '' && $sancion !== 'suspender' && $d['ID_Usuario_Denunciado']) {
                notificar((int)$d['ID_Usuario_Denunciado'], 'Ropify · Moderamos una de tus publicaciones', "$aplicada.\nMotivo: $texto");
            }
            aviso('Denuncia #' . $id . ' ' . minusculas($resultado) . ($aplicada !== '' ? ' · ' . $aplicada : '') . '.');
            ir(url('admin/denuncias.php'));
        }
    }
    ir(url('admin/denuncias.php?id=' . $id));
}

$estado = obtener('estado') !== '' ? obtener('estado') : 'abiertas';
$dondeEstado = $estado === 'abiertas' ? "d.Estado IN ('Pendiente','En revision')" : ($estado === 'cerradas' ? "d.Estado IN ('Resuelta','Descartada')" : '1 = 1');
$lista = filas("SELECT d.*, a.AliasPublico AS Denunciante, p.Titulo AS TituloPrenda, x.AliasPublico AS Denunciado
    FROM denuncia d JOIN usuario a ON a.ID_Usuario = d.ID_Denunciante LEFT JOIN prenda p ON p.ID_Prenda = d.ID_Prenda LEFT JOIN usuario x ON x.ID_Usuario = d.ID_Usuario_Denunciado
    WHERE $dondeEstado ORDER BY d.Fecha_Denuncia ASC LIMIT 100");
$det = null;
if ($id) {
    $det = fila('SELECT d.*, a.AliasPublico AS Denunciante, p.Titulo AS TituloPrenda, p.Estado AS EstadoPrenda, x.AliasPublico AS Denunciado, x.Estado_Cuenta AS EstadoDenunciado
        FROM denuncia d JOIN usuario a ON a.ID_Usuario = d.ID_Denunciante LEFT JOIN prenda p ON p.ID_Prenda = d.ID_Prenda LEFT JOIN usuario x ON x.ID_Usuario = d.ID_Usuario_Denunciado WHERE d.ID_Denuncia = ?', [$id]);
}
require __DIR__ . '/../includes/header.php';
?>
<h1>Denuncias</h1>
<div class="tabs">
  <?php foreach (['abiertas' => 'Abiertas', 'cerradas' => 'Resueltas y descartadas', 'todas' => 'Todas'] as $k => $n): ?><a<?= $estado === $k ? ' class="activo"' : '' ?> href="<?= e(url('admin/denuncias.php?estado=' . $k)) ?>"><?= e($n) ?></a><?php endforeach; ?>
</div>
<div class="dos-col">
  <div>
    <?php if (!$lista): ?><div class="vacio"><p>No hay denuncias en esta lista.</p></div><?php endif; ?>
    <?php foreach ($lista as $d): ?>
      <a class="conversacion<?= $id === (int)$d['ID_Denuncia'] ? ' no-leida' : '' ?>" href="<?= e(url('admin/denuncias.php?estado=' . $estado . '&id=' . (int)$d['ID_Denuncia'])) ?>">
        <span class="cuerpo"><b>#<?= (int)$d['ID_Denuncia'] ?> · <?= e($d['Motivo']) ?></b>
          <p class="muted"><?= $d['TituloPrenda'] ? 'Prenda: ' . e($d['TituloPrenda']) : 'Usuario: ' . e($d['Denunciado'] ?? '—') ?> · por <?= e($d['Denunciante']) ?></p></span>
        <small><?= e(fecha($d['Fecha_Denuncia'])) ?></small>
        <?= badge($d['Estado'], in_array($d['Estado'], ['Pendiente', 'En revision'], true) ? 'aviso' : ($d['Estado'] === 'Resuelta' ? 'ok' : 'neutro')) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div>
  <?php if ($det): ?>
    <div class="caja">
      <h2 style="margin-top:0">Denuncia #<?= (int)$det['ID_Denuncia'] ?> <?= badge($det['Estado'], in_array($det['Estado'], ['Pendiente', 'En revision'], true) ? 'aviso' : 'neutro') ?></h2>
      <p><b>Motivo:</b> <?= e($det['Motivo']) ?><br><b>Denunciante:</b> <?= e($det['Denunciante']) ?> · <?= e(fecha_hora($det['Fecha_Denuncia'])) ?></p>
      <?php if ($det['TituloPrenda']): ?><p><b>Prenda:</b> <a href="<?= e(url('prenda.php?id=' . (int)$det['ID_Prenda'])) ?>"><?= e($det['TituloPrenda']) ?></a> (<?= e(ESTADOS_PRENDA[$det['EstadoPrenda']] ?? $det['EstadoPrenda']) ?>)</p><?php endif; ?>
      <?php if ($det['Denunciado']): ?><p><b>Usuario denunciado:</b> <a href="<?= e(url('vendedor.php?id=' . (int)$det['ID_Usuario_Denunciado'])) ?>"><?= e($det['Denunciado']) ?></a> (<?= e($det['EstadoDenunciado']) ?>)</p><?php endif; ?>
      <?php if ($det['Descripcion']): ?><p><b>Detalle:</b> <?= nl2br(e($det['Descripcion'])) ?></p><?php endif; ?>
      <?php if (in_array($det['Estado'], ['Pendiente', 'En revision'], true)): ?>
        <?php if ($det['Estado'] === 'Pendiente'): ?><?= boton_post('revisar', ['id' => $det['ID_Denuncia']], 'Tomar en revisión', 'btn btn-sec') ?><?php endif; ?>
        <form method="post" style="margin-top:12px"><?= csrf_campo() ?><input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?= (int)$det['ID_Denuncia'] ?>">
          <?= selector('Resultado', 'resultado', ['Resuelta' => 'Resuelta (la denuncia procede)', 'Descartada' => 'Descartada (no procede)'], 'Resuelta', '') ?>
          <?= selector('Sanción (solo si procede)', 'sancion', array_filter(['' => 'Sin sanción', 'pausar' => $det['ID_Prenda'] ? 'Pausar la publicación' : null, 'rechazar' => $det['ID_Prenda'] ? 'Rechazar la publicación' : null, 'suspender' => 'Suspender la cuenta del denunciado'], static fn($v) => $v !== null), '', '') ?>
          <?= area('Resolución (obligatoria)', 'resolucion', '', ['maxlength' => 255, 'rows' => 3, 'required' => true]) ?>
          <button class="btn" type="submit">Registrar resolución</button>
        </form>
      <?php else: ?>
        <p><b>Resolución:</b> <?= e($det['Resolucion']) ?><br><small><?= e(fecha_hora($det['Fecha_Resolucion'])) ?></small></p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="vacio"><p>Elegí una denuncia de la lista para revisarla.</p></div>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
