<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = exigir_admin();
$titulo = 'Liquidaciones'; $activo = 'liquidaciones'; $subnav = 'admin';

if (es_post()) {                                                      // CU-35 · RF-29 · US-15
    verificar_csrf();
    $accion = post('accion'); $vend = (int)post('vendedor');
    $vendedor = fila('SELECT ID_Usuario, AliasPublico, Alias_Cobro FROM usuario WHERE ID_Usuario = ?', [$vend]);
    if (!$vendedor) {
        aviso('No encontramos al vendedor.', 'error');
    } elseif ($accion === 'avisar') {                                 // CU-35 alt. 4.1: sin alias de cobro, queda pendiente y se avisa
        notificar($vend, 'Ropify · Cargá tu alias de cobro', 'Tenés ventas entregadas listas para liquidar, pero no cargaste tu alias de cobro. Cargalo en Mi perfil: ' . url_abs('perfil.php'));
        aviso('Avisamos a ' . $vendedor['AliasPublico'] . ' que falta su alias de cobro.', 'info');
    } elseif ($accion === 'liquidar') {
        $items = (array)($_POST['items'] ?? []); $comprobante = post('comprobante');
        if (empty($vendedor['Alias_Cobro']))                    aviso('El vendedor no tiene alias de cobro: la liquidación queda pendiente.', 'warning');
        elseif (!$items)                                        aviso('Elegí al menos una venta para liquidar.', 'error');
        elseif ($comprobante === '' || largo($comprobante) > 100) aviso('Ingresá el comprobante de la transferencia (hasta 100 caracteres).', 'error');
        else {
            $pdo = db(); $pdo->beginTransaction();
            $total = 0.0; $n = 0;
            try {
                foreach ($items as $it) {
                    [$idPe, $idPr] = array_map('intval', array_pad(explode(':', (string)$it), 2, 0));
                    $fila = fila("SELECT d.Monto_Vendedor FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido JOIN prenda p ON p.ID_Prenda = d.ID_Prenda
                        WHERE d.ID_Pedido = ? AND d.ID_Prenda = ? AND d.Liquidado = 0 AND pe.Estado = 'Entregado' AND p.ID_Vendedor = ?", [$idPe, $idPr, $vend]); // CP-33: solo «Entregado»
                    if (!$fila) continue;
                    $st = q('UPDATE detalle_pedido SET Liquidado = 1, Fecha_Liquidacion = ?, Comprobante_Liquidacion = ? WHERE ID_Pedido = ? AND ID_Prenda = ? AND Liquidado = 0', [ahora(), $comprobante, $idPe, $idPr]);
                    if ($st->rowCount() === 1) { $total += (float)$fila['Monto_Vendedor']; $n++; }
                }
                if ($n === 0) throw new RuntimeException('nada');
                registrar_admin('Liquidación', 'usuario', $vend, $n . ' ventas · ' . dinero($total) . ' · comprobante ' . $comprobante);
                $pdo->commit();
                notificar($vend, 'Ropify · Te transferimos ' . dinero($total), "Liquidamos $n " . plural($n, 'venta', 'ventas') . " por " . dinero($total) . " al alias " . $vendedor['Alias_Cobro'] . ".\nComprobante: $comprobante");
                aviso('Liquidación registrada: ' . dinero($total) . ' a ' . $vendedor['AliasPublico'] . '. Se le notificó.');
            } catch (Throwable $t) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                aviso('No había ventas liquidables entre las elegidas.', 'warning');
            }
        }
    }
    ir(url('admin/liquidaciones.php'));
}

$filas = filas("SELECT d.ID_Pedido, d.ID_Prenda, d.Precio_Venta, d.Comision_Chuko, d.Monto_Vendedor, p.Titulo, p.ID_Vendedor, u.AliasPublico, u.Nombre, u.Apellido, u.Alias_Cobro, pe.Fecha_Entrega
    FROM detalle_pedido d JOIN pedido pe ON pe.ID_Pedido = d.ID_Pedido JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor
    WHERE pe.Estado = 'Entregado' AND d.Liquidado = 0 ORDER BY u.AliasPublico, pe.Fecha_Entrega");
$porVendedor = [];
foreach ($filas as $f) $porVendedor[(int)$f['ID_Vendedor']][] = $f;
$historial = filas('SELECT p.ID_Vendedor, u.AliasPublico, d.Comprobante_Liquidacion, d.Fecha_Liquidacion, COUNT(*) AS Ventas, SUM(d.Monto_Vendedor) AS Total
    FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor
    WHERE d.Liquidado = 1 GROUP BY p.ID_Vendedor, u.AliasPublico, d.Comprobante_Liquidacion, d.Fecha_Liquidacion ORDER BY d.Fecha_Liquidacion DESC LIMIT 20');
require __DIR__ . '/../includes/header.php';
?>
<h1>Liquidaciones a vendedores</h1>
<p class="muted">Ventas «Entregadas» que todavía no se pagaron al vendedor. Se le transfiere el <?= porcentaje_vendedor() ?> % del precio (RN-05). Hacé la transferencia y cargá el comprobante.</p>
<?php if (!$porVendedor): ?><div class="vacio"><p>No hay liquidaciones pendientes.</p></div><?php endif; ?>
<?php foreach ($porVendedor as $idv => $items): $v = $items[0]; $total = array_sum(array_map(static fn($i) => (float)$i['Monto_Vendedor'], $items)); ?>
  <div class="caja">
    <h2 style="margin-top:0"><?= e($v['AliasPublico']) ?> <small>· <?= e($v['Nombre'] . ' ' . $v['Apellido']) ?></small></h2>
    <p style="margin:0 0 10px"><b>Alias de cobro:</b> <?= $v['Alias_Cobro'] ? e($v['Alias_Cobro']) : '<span style="color:var(--error)">no cargado</span>' ?></p>
    <form method="post" style="max-width:none"><?= csrf_campo() ?><input type="hidden" name="vendedor" value="<?= $idv ?>">
      <div class="tabla-scroll"><table>
        <thead><tr><th><input type="checkbox" data-todos="items[]" checked aria-label="Elegir todas"></th><th>Pedido</th><th>Prenda</th><th class="num-der">Precio</th><th class="num-der">Comisión</th><th class="num-der">Al vendedor</th><th>Entregado</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr><td><input type="checkbox" name="items[]" value="<?= (int)$i['ID_Pedido'] . ':' . (int)$i['ID_Prenda'] ?>" checked aria-label="Liquidar"></td><td>#<?= (int)$i['ID_Pedido'] ?></td><td><?= e($i['Titulo']) ?></td>
            <td class="num-der"><?= dinero($i['Precio_Venta']) ?></td><td class="num-der"><?= dinero($i['Comision_Chuko']) ?></td><td class="num-der"><b><?= dinero($i['Monto_Vendedor']) ?></b></td><td><?= e(fecha($i['Fecha_Entrega'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p><b>Total a transferir (todas): <?= dinero($total) ?></b></p>
      <?php if ($v['Alias_Cobro']): ?>
        <div class="fila-campos"><?= campo('Comprobante de la transferencia', 'comprobante', '', ['maxlength' => 100, 'placeholder' => 'N.º de operación']) ?></div>
        <button class="btn" type="submit" name="accion" value="liquidar">Marcar como liquidado</button>
      <?php else: ?>
        <button class="btn btn-sec" type="submit" name="accion" value="avisar">Avisar al vendedor que falta su alias de cobro</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>
<?php if ($historial): ?>
<h2>Últimas liquidaciones</h2>
<div class="tabla-scroll"><table>
  <thead><tr><th>Fecha</th><th>Vendedor</th><th>Ventas</th><th class="num-der">Total</th><th>Comprobante</th></tr></thead>
  <tbody><?php foreach ($historial as $h): ?><tr><td><?= e(fecha_hora($h['Fecha_Liquidacion'])) ?></td><td><?= e($h['AliasPublico']) ?></td><td><?= (int)$h['Ventas'] ?></td><td class="num-der"><?= dinero($h['Total']) ?></td><td><?= e($h['Comprobante_Liquidacion']) ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
