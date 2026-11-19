<?php
/**
 * Configuración de Ropify.
 * NO subas contraseñas reales a GitHub: copiá config.local.php.example como
 * config.local.php (está ignorado por Git) y cambiá ahí lo que necesites.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// --- Base de datos (valores por defecto de XAMPP) ---
defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_NAME')    || define('DB_NAME', 'ropify');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// --- Reglas de negocio (Carpeta de Campo, parte E.4) ---
defined('COMISION_ROPIFY')        || define('COMISION_ROPIFY', 0.15); // RN-05: 15 % plataforma, 85 % vendedor
defined('COSTO_ENVIO')            || define('COSTO_ENVIO', 4500);     // RN-06: a cargo del comprador (por pedido)
defined('MIN_FOTOS')              || define('MIN_FOTOS', 3);          // RN-02
defined('MAX_FOTOS')              || define('MAX_FOTOS', 8);
defined('MAX_FOTO_MB')            || define('MAX_FOTO_MB', 5);        // F.6 CP-17
defined('PLAZO_PAGO_HORAS')       || define('PLAZO_PAGO_HORAS', 24);  // CU-18 alt. 9.1
defined('PLAZO_DESPACHO_DIAS')    || define('PLAZO_DESPACHO_DIAS', 5);  // RN-14
defined('PLAZO_CONFIRMACION_DIAS')|| define('PLAZO_CONFIRMACION_DIAS', 10); // RN-08 (entrega automática)
defined('DIAS_ARREPENTIMIENTO')   || define('DIAS_ARREPENTIMIENTO', 10);    // Res. 424/2020

// --- Seguridad (G.5) ---
defined('MAX_INTENTOS_LOGIN') || define('MAX_INTENTOS_LOGIN', 5);   // RF-03
defined('BLOQUEO_MINUTOS')    || define('BLOQUEO_MINUTOS', 15);
defined('SESION_MINUTOS')     || define('SESION_MINUTOS', 30);      // RNF-04: expira por inactividad
defined('TOKEN_RECUPERACION_MINUTOS') || define('TOKEN_RECUPERACION_MINUTOS', 60); // RF-04
defined('CLAVE_APP') || define('CLAVE_APP', 'cambiar-esta-clave-en-config.local.php'); // firma de los enlaces de baja

// --- Entorno ---
// MODO_DEMO = true: la pasarela es un simulador, los e-mails se guardan en storage/correos.log
// y el enlace de recuperación de contraseña también se muestra en pantalla.
// En producción: false (y configurar MAIL_ACTIVO y la pasarela real).
defined('MODO_DEMO')       || define('MODO_DEMO', true);
defined('MAIL_ACTIVO')     || define('MAIL_ACTIVO', false);   // true = intenta enviar con mail() del servidor
defined('EMAIL_REMITENTE') || define('EMAIL_REMITENTE', 'no-responder@ropify.example');
defined('EMAIL_AYUDA')     || define('EMAIL_AYUDA', '');      // opcional: se muestra en Ayuda
defined('URL_SITIO')       || define('URL_SITIO', '');        // opcional: https://www.tudominio.com.ar/ (para enlaces en e-mails)
