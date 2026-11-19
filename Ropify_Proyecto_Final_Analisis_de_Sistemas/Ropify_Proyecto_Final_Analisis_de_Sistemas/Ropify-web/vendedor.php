<?php
require_once __DIR__ . '/includes/bootstrap.php';
$activo = 'vendedores';
$id = (int)($_GET['id'] ?? 0);
// RF-07 / CP-39: el perfil público NO expone DNI, e-mail ni teléfono.
$v = $id ? fila("SELECT ID_Usuario, AliasPublico, Biografia, Fecha_Registro, Dni_Verificado FROM usuario WHERE ID_Usuario = ? AND Estado_Cuenta = 'Activa'", [$id]) : null;
$u = usuario();

if ($id && !$v) pagina_error(404, 'Vendedor no encontrado', 'Ese perfil no existe o la cuenta no está activa.');

if (!$v) {
    $titulo = 'Vendedores';
    $lista = filas("SELECT u.ID_Usuario, u.AliasPublico, u.Biografia, u.Dni_Verificado, COUNT(*) AS Prendas
        FROM usuario u JOIN prenda p ON p.ID_Vendedor = u.ID_Usuario
        WHERE p.Estado = 'Aprobada' AND u.Estado_Cuenta = 'Activa'
        GROUP BY u.ID_Usuario, u.AliasPublico, u.Biografia, u.Dni_Verificado ORDER BY u.AliasPublico");
    require __DIR__ . '/includes/header.php';
    ?>
    <h1>Vendedores</h1>
    <?php if ($lista): ?>
      <div class="tres">
      <?php foreach ($lista as $x): ?>
        <a class="dato" style="text-decoration:none;color:inherit" href="<?= e(url('vendedor.php?id=' . (int)$x['ID_Usuario'])) ?>">
          <span class="avatar"><?= e(iniciales($x['AliasPublico'])) ?></span>
          <h3><?= e($x['AliasPublico']) ?> <?= $x['Dni_Verificado'] ? '<span class="verificado">✔</span>' : '' ?></h3>
          <p class="muted"><?= e($x['Biografia'] ?? '') ?></p>
          <small><?= (int)$x['Prendas'] ?> <?= plural((int)$x['Prendas'], 'prenda', 'prendas') ?> en venta</small>
        </a>
      <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="vacio"><p>Todavía no hay vendedores con prendas publicadas.</p></div>
    <?php endif;
    require __DIR__ . '/includes/footer.php';
    exit;
}

$prendas = prendas(DONDE_CATALOGO . ' AND p.ID_Vendedor = ?', [$id], 'p.FechaPublicacion DESC', 40);
$ventas = (int)valor("SELECT COUNT(*) FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido
    WHERE p.ID_Vendedor = ? AND pe.Estado IN ('Pagado','Enviado','Entregado')", [$id]);
$titulo = $v['AliasPublico'];
require __DIR__ . '/includes/header.php';
?>
<div class="caja">
  <div class="vendedor-caja" style="margin:0">
    <span class="avatar"><?= e(iniciales($v['AliasPublico'])) ?></span>
    <div>
      <h1 style="margin:0"><?= e($v['AliasPublico']) ?></h1>
      <?php if ($v['Dni_Verificado']): ?><span class="verificado">✔ Identidad verificada</span><br><?php endif; ?>
      <small>En Ropify desde <?= e(date('Y', strtotime((string)$v['Fecha_Registro']))) ?> · <?= $ventas ?> <?= plural($ventas, 'venta', 'ventas') ?> · <?= count($prendas) ?> <?= plural(count($prendas), 'prenda', 'prendas') ?> en venta</small>
    </div>
  </div>
  <?php if (!empty($v['Biografia'])): ?><p style="margin:12px 0 0"><?= nl2br(e($v['Biografia'])) ?></p><?php endif; ?>
  <?php if ($u && (int)$u['ID_Usuario'] !== $id): ?><p style="margin:12px 0 0"><small><a href="<?= e(url('denunciar.php?usuario=' . $id)) ?>">⚑ Denunciar este perfil</a></small></p><?php endif; ?>
</div>
<h2>Sus prendas</h2>
<?php if ($prendas): ?><?= grilla_prendas($prendas) ?><?php else: ?><div class="vacio"><p>No tiene prendas en venta en este momento.</p></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
