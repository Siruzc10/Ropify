<?php
/** Lógica de pedidos (RN-04, RN-08, RN-14 · CU-18 a CU-22, CU-27). */

/** Prendas de un pedido, con vendedor y foto. */
function items_pedido(int $idPedido): array
{
    return filas('SELECT d.*, p.Titulo, p.Talle, p.ID_Vendedor, u.AliasPublico,
        (SELECT f.URL FROM foto_prenda f WHERE f.ID_Prenda = p.ID_Prenda ORDER BY f.Orden, f.ID_Foto LIMIT 1) AS Foto
        FROM detalle_pedido d
        JOIN prenda p ON p.ID_Prenda = d.ID_Prenda
        JOIN usuario u ON u.ID_Usuario = p.ID_Vendedor
        WHERE d.ID_Pedido = ?', [$idPedido]);
}

/**
 * Cancela un pedido «Pendiente_Pago» o «Pagado» (aún no enviado), devuelve las prendas a la venta
 * y, si ya estaba pagado, solicita el reintegro. Devuelve false si el estado ya no lo permite.
 */
function cancelar_pedido(int $idPedido, string $motivo = ''): bool
{
    $pe = fila('SELECT * FROM pedido WHERE ID_Pedido = ?', [$idPedido]);
    if (!$pe || !in_array($pe['Estado'], ['Pendiente_Pago', 'Pagado'], true)) return false;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = q("UPDATE pedido SET Estado = 'Cancelado' WHERE ID_Pedido = ? AND Estado IN ('Pendiente_Pago', 'Pagado')", [$idPedido]);
        if ($st->rowCount() !== 1) { $pdo->rollBack(); return false; }
        q("UPDATE prenda SET Estado = 'Aprobada' WHERE Estado = 'Vendida' AND ID_Prenda IN (SELECT ID_Prenda FROM detalle_pedido WHERE ID_Pedido = ?)", [$idPedido]);
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $t;
    }

    $textoMotivo = $motivo !== '' ? "\nMotivo: $motivo" : '';
    if ($pe['Estado'] === 'Pagado') {
        if (!pasarela_reintegrar($pe)) {
            registrar_admin('Reintegro fallido', 'pedido', $idPedido, 'Gestionar el reintegro manualmente');
        }
        $textoMotivo .= "\nTe vamos a reintegrar " . dinero($pe['Total']) . ' por el mismo medio de pago.';
    }
    notificar((int)$pe['ID_Comprador'], 'Ropify · Pedido #' . $idPedido . ' cancelado', 'Tu pedido #' . $idPedido . ' fue cancelado.' . $textoMotivo);
    if ($pe['Estado'] === 'Pagado') {
        foreach (filas('SELECT DISTINCT p.ID_Vendedor FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda WHERE d.ID_Pedido = ?', [$idPedido]) as $v) {
            notificar((int)$v['ID_Vendedor'], 'Ropify · Pedido #' . $idPedido . ' cancelado', 'El pedido #' . $idPedido . ' fue cancelado y la prenda volvió a estar disponible en el catálogo.' . $textoMotivo);
        }
    }
    return true;
}

/** El comprador (o el sistema, vencido el plazo) confirma la recepción: Enviado -> Entregado (CU-22, RN-08). */
function confirmar_entrega(int $idPedido): bool
{
    $st = q("UPDATE pedido SET Estado = 'Entregado', Fecha_Entrega = ? WHERE ID_Pedido = ? AND Estado = 'Enviado'", [ahora(), $idPedido]);
    if ($st->rowCount() !== 1) return false;
    foreach (filas('SELECT DISTINCT p.ID_Vendedor FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda WHERE d.ID_Pedido = ?', [$idPedido]) as $v) {
        notificar((int)$v['ID_Vendedor'], 'Ropify · Pedido #' . $idPedido . ' entregado', 'El pedido #' . $idPedido . ' figura como entregado. Tu venta ya está disponible para liquidar: seguila desde ' . url_abs('liquidacion.php'));
    }
    return true;
}

/**
 * Tareas automáticas de las reglas de negocio:
 *  · pedido sin pagar pasadas PLAZO_PAGO_HORAS horas  -> se cancela y libera las prendas (CU-18 alt. 9.1)
 *  · pedido pagado sin despachar pasados PLAZO_DESPACHO_DIAS días -> se cancela y se reintegra (RN-14)
 *  · pedido enviado sin confirmar pasados PLAZO_CONFIRMACION_DIAS días -> se da por entregado (RN-08)
 * @return array{sin_pago:int, sin_despacho:int, entregados:int}
 */
function mantenimiento(): array
{
    $r = ['sin_pago' => 0, 'sin_despacho' => 0, 'entregados' => 0];

    foreach (filas("SELECT ID_Pedido FROM pedido WHERE Estado = 'Pendiente_Pago' AND Fecha_pedido < ?", [hace(PLAZO_PAGO_HORAS * 3600)]) as $p) {
        if (cancelar_pedido((int)$p['ID_Pedido'], 'No se acreditó el pago dentro de ' . PLAZO_PAGO_HORAS . ' horas.')) $r['sin_pago']++;
    }
    foreach (filas("SELECT ID_Pedido FROM pedido WHERE Estado = 'Pagado' AND COALESCE(Fecha_Pago, Fecha_pedido) < ?", [hace(PLAZO_DESPACHO_DIAS * 86400)]) as $p) {
        if (cancelar_pedido((int)$p['ID_Pedido'], 'El vendedor no despachó el pedido dentro de ' . PLAZO_DESPACHO_DIAS . ' días.')) $r['sin_despacho']++;
    }
    foreach (filas("SELECT ID_Pedido FROM pedido WHERE Estado = 'Enviado' AND COALESCE(Fecha_Envio, Fecha_pedido) < ?", [hace(PLAZO_CONFIRMACION_DIAS * 86400)]) as $p) {
        if (confirmar_entrega((int)$p['ID_Pedido'])) $r['entregados']++;
    }
    if (array_sum($r) > 0) {
        registrar_admin('Mantenimiento automático', 'pedido', null, 'Sin pago: ' . $r['sin_pago'] . ' · sin despacho: ' . $r['sin_despacho'] . ' · entregas automáticas: ' . $r['entregados']);
    }
    return $r;
}
/** Ejecuta el mantenimiento como mucho una vez cada 5 minutos por sesión; nunca rompe la página. */
function mantenimiento_si_corresponde(bool $forzar = false): void
{
    if (!$forzar && time() - (int)($_SESSION['mantenimiento'] ?? 0) < 300) return;
    $_SESSION['mantenimiento'] = time();
    try { mantenimiento(); } catch (Throwable $t) { error_log('[Ropify] mantenimiento: ' . $t->getMessage()); }
}
