<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Baja desde el enlace del e-mail (CU-37): newsletter.php?baja=EMAIL&t=FIRMA
if (isset($_GET['baja'])) {
    $email = obtener('baja');
    if (hash_equals(firma_baja($email), obtener('t'))) {
        q('UPDATE suscripcion_newsletter SET Activa = 0 WHERE Email = ?', [$email]);
        aviso('Listo: te diste de baja del newsletter.', 'info');
    } else {
        aviso('El enlace de baja no es válido.', 'error');
    }
    ir(url('index.php'));
}

if (es_post()) {
    verificar_csrf();
    $email = post('email');
    $volver = destino_seguro(post('volver'), url('index.php'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || largo($email) > 120) {
        aviso('Ingresá un e-mail válido para suscribirte.', 'error');
    } else {
        $ya = fila('SELECT ID_Suscripcion, Activa FROM suscripcion_newsletter WHERE Email = ?', [$email]);
        if ($ya && $ya['Activa']) {
            aviso('Ese e-mail ya está suscripto al newsletter.', 'info');
        } else {
            if ($ya) q('UPDATE suscripcion_newsletter SET Activa = 1 WHERE ID_Suscripcion = ?', [$ya['ID_Suscripcion']]);
            else     q('INSERT INTO suscripcion_newsletter (Email, Fecha_Alta, Activa) VALUES (?, ?, 1)', [$email, ahora()]);
            enviar_correo($email, 'Ropify · Te suscribiste al newsletter', "¡Gracias por sumarte!\nVas a recibir las novedades de Ropify.\nPara darte de baja: " . url_abs('newsletter.php?baja=' . rawurlencode($email) . '&t=' . firma_baja($email)));
            aviso('¡Listo! Te suscribiste al newsletter.');
        }
    }
    ir($volver);
}
ir(url('index.php'));
