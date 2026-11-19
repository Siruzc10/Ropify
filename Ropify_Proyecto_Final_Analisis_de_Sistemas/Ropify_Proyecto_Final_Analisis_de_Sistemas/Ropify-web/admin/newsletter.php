<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Newsletter'; $activo = 'newsletter'; $subnav = 'admin';

$err = [];
if (es_post()) {                                                      // CU-37 · RF-35
    verificar_csrf();
    $asunto = post('asunto'); $cuerpo = post('cuerpo');
    if ($asunto === '' || largo($asunto) > 120)     $err[] = 'Escribí el asunto (hasta 120 caracteres).';
    if ($cuerpo === '' || largo($cuerpo) > 5000)    $err[] = 'Escribí el contenido (hasta 5000 caracteres).';
    $destinos = filas('SELECT Email FROM suscripcion_newsletter WHERE Activa = 1');
    if (!$destinos)                                 $err[] = 'No hay suscriptores activos.';
    if (!$err) {
        $enviados = 0;
        foreach ($destinos as $d) {
            $baja = url_abs('newsletter.php?baja=' . rawurlencode($d['Email']) . '&t=' . firma_baja($d['Email']));
            if (enviar_correo($d['Email'], $asunto, $cuerpo . "\n\n—\nPara darte de baja: " . $baja)) $enviados++;
        }
        registrar_admin('Newsletter enviado', 'newsletter', null, $asunto . ' · ' . $enviados . ' destinatarios');
        aviso('Newsletter enviado a ' . $enviados . ' ' . plural($enviados, 'suscriptor', 'suscriptores') . '.');
        ir(url('admin/newsletter.php'));
    }
}
$suscriptores = filas('SELECT * FROM suscripcion_newsletter ORDER BY Fecha_Alta DESC LIMIT 100');
$activos = (int)valor('SELECT COUNT(*) FROM suscripcion_newsletter WHERE Activa = 1');
require __DIR__ . '/../includes/header.php';
?>
<h1>Newsletter</h1>
<div class="dos-col">
  <div class="form-caja" style="max-width:none">
    <h2 style="margin-top:0">Enviar novedades (<?= $activos ?> <?= plural($activos, 'suscriptor activo', 'suscriptores activos') ?>)</h2>
    <?php if ($err): ?><ul class="errores"><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" data-confirmar="¿Enviar el newsletter a todos los suscriptores activos?"><?= csrf_campo() ?>
      <?= campo('Asunto', 'asunto', post('asunto'), ['maxlength' => 120, 'required' => true]) ?>
      <?= area('Contenido', 'cuerpo', post('cuerpo'), ['maxlength' => 5000, 'rows' => 8, 'required' => true], 'Cada correo incluye automáticamente un enlace de baja.') ?>
      <button class="btn" type="submit">Enviar a todos</button>
    </form>
  </div>
  <div>
    <h2 style="margin-top:0">Suscriptores</h2>
    <div class="tabla-scroll"><table style="min-width:0">
      <thead><tr><th>E-mail</th><th>Alta</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($suscriptores as $s): ?><tr><td><?= e($s['Email']) ?></td><td><?= e(fecha($s['Fecha_Alta'])) ?></td><td><?= $s['Activa'] ? badge('Activo', 'ok') : badge('Baja', 'neutro') ?></td></tr><?php endforeach; ?>
      <?php if (!$suscriptores): ?><tr><td colspan="3" class="muted">Todavía no hay suscriptores.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
