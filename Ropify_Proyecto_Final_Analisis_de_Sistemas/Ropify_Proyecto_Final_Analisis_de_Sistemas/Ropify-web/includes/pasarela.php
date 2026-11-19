<?php
/**
 * Pasarela de pagos (CU-19 · RF-23 · RNF-05).
 *
 * En esta entrega la pasarela es un SIMULADOR (ver pago.php): Ropify nunca recibe ni guarda
 * datos de tarjeta. Todo el contacto con la pasarela está aislado en este archivo, tal como
 * pide la factibilidad técnica (D.1): para pasar a Mercado Pago real solo hay que reemplazar
 *   · pago.php  -> crear la «preferencia» y redirigir al checkout de Mercado Pago, y
 *   · un endpoint de notificación (webhook) que verifique el pago y llame a pasarela_confirmar_pago(),
 *   · pasarela_reintegrar() -> llamar a la API de reintegros.
 * Hace falta una cuenta de Mercado Pago con credenciales (Access Token) y un sitio público con HTTPS.
 */

/**
 * Marca como «Pagado» los pedidos del comprador que seguían «Pendiente_Pago».
 * Es idempotente: si la notificación llega dos veces, el cobro no se duplica (CP-26).
 * @return int cantidad de pedidos que pasaron a Pagado
 */
function pasarela_confirmar_pago(array $idsPedido, int $idComprador, string $referencia): int
{
    $ok = 0;
    foreach ($idsPedido as $id) {
        $id = (int)$id;
        $st = q("UPDATE pedido SET Estado = 'Pagado', Fecha_Pago = ? WHERE ID_Pedido = ? AND ID_Comprador = ? AND Estado = 'Pendiente_Pago'",
                [ahora(), $id, $idComprador]);
        if ($st->rowCount() !== 1) continue;
        $ok++;
        $total = (float)valor('SELECT Total FROM pedido WHERE ID_Pedido = ?', [$id]);
        notificar($idComprador, 'Ropify · Pago acreditado (pedido #' . $id . ')',
            "Recibimos tu pago de " . dinero($total) . " (ref. $referencia).\nEl vendedor ya fue avisado y tiene " . PLAZO_DESPACHO_DIAS . " días para despachar tu pedido.\nSeguilo desde: " . url_abs('pedido.php?id=' . $id));
        foreach (filas('SELECT DISTINCT p.ID_Vendedor FROM detalle_pedido d JOIN prenda p ON p.ID_Prenda = d.ID_Prenda WHERE d.ID_Pedido = ?', [$id]) as $v) {
            notificar((int)$v['ID_Vendedor'], 'Ropify · Vendiste una prenda (pedido #' . $id . ')',
                "Tenés un pedido pagado para despachar. Ingresá a Mis ventas, enviá la prenda y cargá el código de seguimiento dentro de " . PLAZO_DESPACHO_DIAS . " días.\n" . url_abs('ventas.php'));
        }
    }
    return $ok;
}

/** Solicita el reintegro del importe de un pedido pagado (CU-20 paso 6). En el simulador siempre «funciona». */
function pasarela_reintegrar(array $pedido): bool
{
    // Producción: llamar a la API de reintegros de la pasarela con la referencia del pago.
    // Si fallara, devolver false: el pedido queda registrado para gestión manual (CU-20 alt. 6.1).
    registrar_admin('Reintegro solicitado', 'pedido', (int)$pedido['ID_Pedido'], 'Importe ' . dinero($pedido['Total']) . ' (simulado)');
    return true;
}
