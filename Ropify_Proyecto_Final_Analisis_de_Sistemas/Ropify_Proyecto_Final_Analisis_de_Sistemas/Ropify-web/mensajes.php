<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Mensajes';
$u = exigir_login();
$uid = (int)$u['ID_Usuario'];

// ---- Enviar un mensaje (CU-15 · RF-31 · RN-09) ----
if (es_post()) {
    verificar_csrf();
    $para = (int)post('para'); $idPrenda = (int)post('prenda'); $texto = post('contenido');
    $destino = url('mensajes.php');
    $receptor = $para ? fila("SELECT ID_Usuario, AliasPublico FROM usuario WHERE ID_Usuario = ? AND Estado_Cuenta = 'Activa'", [$para]) : null;
    $prenda = $idPrenda ? fila('SELECT ID_Prenda, ID_Vendedor, Titulo FROM prenda WHERE ID_Prenda = ?', [$idPrenda]) : null;
    $error = '';
    if (!$receptor)                      $error = 'No encontramos a esa persona.';
    elseif ($para === $uid)              $error = 'No podés enviarte mensajes a vos mismo.';
    elseif ($texto === '' || largo($texto) > 1000) $error = 'Escribí un mensaje de hasta 1000 caracteres.';
    elseif ($idPrenda && !$prenda)       $error = 'La prenda ya no existe.';
    elseif ($prenda && (int)$prenda['ID_Vendedor'] !== $para && (int)$prenda['ID_Vendedor'] !== $uid) $error = 'Ese mensaje no corresponde a esa prenda.';
    if ($error) {
        aviso($error, 'error');
        ir($prenda ? url('prenda.php?id=' . $idPrenda) : $destino);
    }
    q('INSERT INTO mensaje (ID_Emisor, ID_Receptor, ID_Prenda, Contenido, Fecha_Envio, Leido) VALUES (?, ?, ?, ?, ?, 0)',
      [$uid, $para, $prenda ? $idPrenda : null, $texto, ahora()]);
    notificar($para, 'Ropify · Tenés un mensaje nuevo de ' . $u['AliasPublico'], ($prenda ? 'Sobre «' . $prenda['Titulo'] . "»:\n" : '') . "Ingresá a Ropify para leerlo y responder:\n" . url_abs('mensajes.php?con=' . $uid . '&prenda=' . $idPrenda));
    aviso('Mensaje enviado.');
    ir(url('mensajes.php?con=' . $para . '&prenda=' . $idPrenda));
}

$con = (int)($_GET['con'] ?? 0);
$idPrenda = (int)($_GET['prenda'] ?? 0);

// ---- Vista de una conversación ----
if ($con) {
    $otro = fila("SELECT ID_Usuario, AliasPublico FROM usuario WHERE ID_Usuario = ?", [$con]);
    if (!$otro || $con === $uid) pagina_error(404, 'Conversación no encontrada', 'No encontramos esa conversación.');
    $prenda = $idPrenda ? fila('SELECT ID_Prenda, Titulo, ID_Vendedor FROM prenda WHERE ID_Prenda = ?', [$idPrenda]) : null;
    $pid = $prenda ? $idPrenda : null;
    $mensajes = filas('SELECT * FROM mensaje WHERE ((ID_Emisor = ? AND ID_Receptor = ?) OR (ID_Emisor = ? AND ID_Receptor = ?)) AND ID_Prenda <=> ? ORDER BY ID_Mensaje',
        [$uid, $con, $con, $uid, $pid]);
    q('UPDATE mensaje SET Leido = 1 WHERE ID_Receptor = ? AND ID_Emisor = ? AND ID_Prenda <=> ? AND Leido = 0', [$uid, $con, $pid]);
    $titulo = 'Mensajes con ' . $otro['AliasPublico'];
    require __DIR__ . '/includes/header.php';
    ?>
    <p><a href="<?= e(url('mensajes.php')) ?>">← Todos los mensajes</a></p>
    <h1>Conversación con <?= e($otro['AliasPublico']) ?></h1>
    <?php if ($prenda): ?><p class="muted">Sobre la prenda: <a href="<?= e(url('prenda.php?id=' . (int)$prenda['ID_Prenda'])) ?>"><?= e($prenda['Titulo']) ?></a></p><?php endif; ?>
    <div class="caja">
      <?php if ($mensajes): ?>
        <div class="hilo">
          <?php foreach ($mensajes as $m): ?>
            <div class="burbuja<?= (int)$m['ID_Emisor'] === $uid ? ' mia' : '' ?>"><?= nl2br(e($m['Contenido'])) ?><small><?= e(fecha_hora($m['Fecha_Envio'])) ?></small></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Todavía no hay mensajes. Escribí el primero.</p>
      <?php endif; ?>
      <form method="post"><?= csrf_campo() ?>
        <input type="hidden" name="accion" value="enviar"><input type="hidden" name="para" value="<?= $con ?>"><input type="hidden" name="prenda" value="<?= (int)$idPrenda ?>">
        <?= area('Tu mensaje', 'contenido', '', ['maxlength' => 1000, 'required' => true, 'rows' => 3]) ?>
        <div class="acciones"><button class="btn" type="submit">Enviar</button>
          <a href="<?= e(url('denunciar.php?usuario=' . $con)) ?>"><small>⚑ Denunciar a este usuario</small></a></div>
      </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---- Bandeja: conversaciones agrupadas por persona y prenda ----
$convs = filas('SELECT m.ID_Prenda, IF(m.ID_Emisor = ?, m.ID_Receptor, m.ID_Emisor) AS Otro, MAX(m.ID_Mensaje) AS Ultimo,
        SUM(CASE WHEN m.ID_Receptor = ? AND m.Leido = 0 THEN 1 ELSE 0 END) AS NoLeidos
    FROM mensaje m WHERE m.ID_Emisor = ? OR m.ID_Receptor = ?
    GROUP BY m.ID_Prenda, Otro ORDER BY Ultimo DESC', [$uid, $uid, $uid, $uid]);
foreach ($convs as &$c) {
    $c['mensaje'] = fila('SELECT Contenido, Fecha_Envio, ID_Emisor FROM mensaje WHERE ID_Mensaje = ?', [$c['Ultimo']]);
    $c['alias'] = (string)valor('SELECT AliasPublico FROM usuario WHERE ID_Usuario = ?', [$c['Otro']]);
    $c['prenda'] = $c['ID_Prenda'] ? (string)valor('SELECT Titulo FROM prenda WHERE ID_Prenda = ?', [$c['ID_Prenda']]) : '';
}
unset($c);
require __DIR__ . '/includes/header.php';
?>
<h1>Mensajes</h1>
<?php if ($convs): ?>
  <?php foreach ($convs as $c): ?>
    <a class="conversacion<?= (int)$c['NoLeidos'] > 0 ? ' no-leida' : '' ?>" href="<?= e(url('mensajes.php?con=' . (int)$c['Otro'] . '&prenda=' . (int)$c['ID_Prenda'])) ?>">
      <span class="avatar"><?= e(iniciales($c['alias'])) ?></span>
      <span class="cuerpo">
        <b><?= e($c['alias']) ?></b><?= $c['prenda'] !== '' ? ' · <small>' . e($c['prenda']) . '</small>' : '' ?>
        <p class="muted"><?= (int)$c['mensaje']['ID_Emisor'] === $uid ? 'Vos: ' : '' ?><?= e($c['mensaje']['Contenido']) ?></p>
      </span>
      <small><?= e(fecha($c['mensaje']['Fecha_Envio'])) ?></small>
      <?php if ((int)$c['NoLeidos'] > 0): ?><span class="badge badge-aviso"><?= (int)$c['NoLeidos'] ?> nuevo(s)</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
<?php else: ?>
  <div class="vacio"><p>No tenés mensajes. Para consultar por una prenda, entrá a su página y escribile al vendedor.</p><a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver catálogo</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
