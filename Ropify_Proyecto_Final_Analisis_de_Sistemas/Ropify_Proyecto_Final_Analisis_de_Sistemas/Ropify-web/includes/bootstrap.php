<?php
declare(strict_types=1);

define('RAIZ', dirname(__DIR__));
require_once RAIZ . '/config.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/* ---------- Errores: nunca mostrar detalles técnicos al público ---------- */
set_exception_handler(static function (Throwable $t): void {
    error_log('[Ropify] ' . $t->getMessage() . ' en ' . $t->getFile() . ':' . $t->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Error | Ropify</title><style>body{font-family:system-ui,sans-serif;background:#E6F4F1;color:#134E4A;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px}'
       . 'div{background:#fff;border-radius:16px;padding:28px;max-width:560px;box-shadow:0 8px 30px rgba(19,78,74,.15)}a{color:#0F766E}</style></head><body><div>'
       . '<h1>Algo salió mal</h1><p>Tuvimos un problema técnico. Probá de nuevo en unos minutos.</p>';
    if (MODO_DEMO) {
        $m = htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<p><small>Detalle (solo visible en modo demo): ' . $m . '</small></p>';
        if (stripos($t->getMessage(), "doesn't exist") !== false || stripos($t->getMessage(), 'Unknown column') !== false) {
            echo '<p><b>¿Importaste los 4 scripts SQL?</b> Hay que importar 01, 02 y 04 (y opcionalmente 03) desde phpMyAdmin.</p>';
        }
    }
    echo '<p><a href="javascript:history.back()">Volver</a></p></div></body></html>';
});

/* ---------- Sesión y cabeceras de seguridad ---------- */
$_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $_https]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
// RNF-04: la sesión expira por inactividad
if (isset($_SESSION['uid'])) {
    if (time() - (int)($_SESSION['actividad'] ?? time()) > SESION_MINUTOS * 60) {
        unset($_SESSION['uid'], $_SESSION['actividad']);
        $_SESSION['avisos'][] = ['warning', 'Tu sesión expiró por inactividad. Volvé a ingresar.'];
    } else {
        $_SESSION['actividad'] = time();
    }
}

/* ---------- Base de datos (PDO + consultas preparadas, RNF-03) ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('No se pudo conectar a la base de datos. Revisá config.php / config.local.php y que MySQL esté encendido en XAMPP.');
        }
    }
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function fila(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function filas(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function valor(string $sql, array $params = []) { $r = q($sql, $params)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function marcadores(array $lista): string { return implode(',', array_fill(0, count($lista), '?')); }

/* ---------- Utilidades ---------- */
function ahora(): string { return date('Y-m-d H:i:s'); }
function hace(int $segundos): string { return date('Y-m-d H:i:s', time() - $segundos); }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function dinero($n): string
{
    $n = (float)$n;
    return '$' . number_format($n, fmod($n, 1.0) === 0.0 ? 0 : 2, ',', '.');
}
function fmt2($n): string { return number_format((float)$n, 2, '.', ''); }          // para guardar DECIMAL(10,2)
function fecha($f): string { return $f ? date('d/m/Y', strtotime((string)$f)) : ''; }
function fecha_hora($f): string { return $f ? date('d/m/Y H:i', strtotime((string)$f)) : ''; }
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function obtener(string $k): string { return trim((string)($_GET[$k] ?? '')); }
function largo(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function minusculas(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function mayusculas(string $s): string { return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s); }
function es_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function plural(int $n, string $uno, string $varios): string { return $n === 1 ? $uno : $varios; }

function base_url(): string
{
    static $b = null;
    if ($b === null) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (basename($dir) === 'admin') $dir = rtrim(dirname($dir), '/');
        $b = ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
    }
    return $b;
}
function url(string $ruta = ''): string { return base_url() . '/' . ltrim($ruta, '/'); }
function url_abs(string $ruta = ''): string
{
    if (URL_SITIO !== '') return rtrim(URL_SITIO, '/') . '/' . ltrim($ruta, '/');
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($ruta);
}
function ir(string $destino): void { header('Location: ' . $destino); exit; }
/** Solo permite volver a páginas internas simples (evita redirecciones abiertas). */
function destino_seguro(string $d, string $porDefecto): string
{
    return preg_match('#^[A-Za-z0-9_/]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $d) ? url($d) : $porDefecto;
}

/* ---------- Avisos (mensajes flash) ---------- */
function aviso(string $texto, string $tipo = 'ok'): void { $_SESSION['avisos'][] = [$tipo, $texto]; }
function avisos_html(): string
{
    $out = '';
    foreach ($_SESSION['avisos'] ?? [] as [$tipo, $texto]) {
        $out .= '<div class="aviso aviso-' . e($tipo) . '" role="status">' . e($texto) . '</div>';
    }
    unset($_SESSION['avisos']);
    return $out;
}

/* ---------- CSRF ---------- */
function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verificar_csrf(): void
{
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Solicitud inválida (token de seguridad). Volvé atrás, recargá la página y reintentá.');
    }
}

/* ---------- Usuario en sesión y roles ---------- */
function usuario(bool $refrescar = false): ?array
{
    static $u = false;
    if ($refrescar) $u = false;
    if ($u === false) {
        $u = null;
        if (isset($_SESSION['uid'])) {
            $u = fila('SELECT * FROM usuario WHERE ID_Usuario = ?', [(int)$_SESSION['uid']]);
            if ($u && $u['Estado_Cuenta'] !== 'Activa') {
                $suspendida = $u['Estado_Cuenta'] === 'Suspendida';
                $u = null;
                unset($_SESSION['uid']);
                aviso($suspendida ? 'Tu cuenta está suspendida. Escribinos desde la sección Ayuda.' : 'Esa cuenta ya no está activa.', 'error');
            } elseif (!$u) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $u;
}
function es_admin(?array $u = null): bool
{
    $u ??= usuario();
    return $u !== null && $u['Rol'] === 'Administrador';
}
function exigir_login(): array
{
    $u = usuario();
    if (!$u) {
        aviso('Ingresá a tu cuenta para continuar.', 'warning');
        $actual = ltrim(substr((string)($_SERVER['REQUEST_URI'] ?? ''), strlen(base_url())), '/');
        $_SESSION['volver'] = $actual;
        ir(url('login.php'));
    }
    return $u;
}
function exigir_admin(): array
{
    $u = exigir_login();
    if (!es_admin($u)) pagina_error(403, 'Acceso denegado', 'Esta sección es solo para administradores de Ropify.');
    return $u;
}
/** Muestra una página de error con el diseño del sitio y termina. */
function pagina_error(int $codigo, string $titulo, string $texto): void
{
    http_response_code($codigo);
    $activo = '';
    require RAIZ . '/includes/header.php';
    echo '<section class="vacio"><h1>' . e($titulo) . '</h1><p>' . e($texto) . '</p><p><a class="btn" href="' . e(url('index.php')) . '">Ir al inicio</a></p></section>';
    require RAIZ . '/includes/footer.php';
    exit;
}

/* ---------- Carrito (el modelo de datos no tiene tabla de carrito: vive en la sesión) ---------- */
function carrito(): array { return array_values(array_unique(array_map('intval', $_SESSION['carrito'] ?? []))); }
function carrito_guardar(array $ids): void { $_SESSION['carrito'] = array_values(array_unique(array_map('intval', $ids))); }

/* ---------- Catálogos fijos ---------- */
const PROVINCIAS = ['Buenos Aires','Ciudad Autónoma de Buenos Aires','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'];
const METODOS_PAGO = ['Mercado Pago', 'Tarjeta de crédito', 'Tarjeta de débito'];
const ESTADOS_PEDIDO = ['Pendiente_Pago' => 'Pendiente de pago', 'Pagado' => 'Pagado', 'Enviado' => 'Enviado', 'Entregado' => 'Entregado', 'Cancelado' => 'Cancelado'];
const ESTADOS_PRENDA = ['Pendiente' => 'En revisión', 'Aprobada' => 'Publicada', 'Rechazada' => 'Rechazada', 'Vendida' => 'Vendida', 'Pausada' => 'Pausada'];
const CONSERVACION = ['Excelente', 'Muy bueno', 'Bueno', 'Decente'];
const SERVICIOS_ENVIO = ['Andreani', 'Correo Argentino', 'OCA', 'Otro'];
const MOTIVOS_DENUNCIA = ['Estafa', 'Fotos falsas', 'Contenido inapropiado', 'Cuenta falsa', 'Producto falsificado', 'Otro'];
const TALLES_SUGERIDOS = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Único', '34', '36', '38', '40', '42', '44'];

/* ---------- Reglas de dinero (RN-05 y RN-07) ---------- */
/** @return array{0: float, 1: float} [comisión de la plataforma, monto del vendedor] */
function calcular_comision(float $precio): array
{
    $comision = round($precio * COMISION_ROPIFY, 2);
    return [$comision, round($precio - $comision, 2)];
}
function porcentaje_vendedor(): int { return (int)round((1 - COMISION_ROPIFY) * 100); }
function porcentaje_comision(): int { return (int)round(COMISION_ROPIFY * 100); }

/* ---------- Validaciones comunes ---------- */
function validar_password(string $p): ?string
{
    if (strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
        return 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
    }
    return null;
}
function validar_telefono(string $t): bool
{
    return (bool)preg_match('/^\+?[0-9 ()\-]{8,20}$/', $t) && strlen(preg_replace('/\D/', '', $t)) >= 8;
}

/* ---------- Correo y notificaciones (CU-01, CU-18, CU-27...) ---------- */
function enviar_correo(string $para, string $asunto, string $texto): bool
{
    $para = trim(str_replace(["\r", "\n"], '', $para));
    $asunto = trim(str_replace(["\r", "\n"], ' ', $asunto));
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;

    // Siempre queda registro en storage/correos.log (útil en XAMPP, donde mail() no suele estar configurado)
    $dir = RAIZ . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/correos.log',
        '[' . ahora() . '] PARA: ' . $para . ' | ASUNTO: ' . $asunto . "\n" . $texto . "\n" . str_repeat('-', 60) . "\n",
        FILE_APPEND | LOCK_EX);

    if (!MAIL_ACTIVO) return true;
    $cabeceras = 'From: Ropify <' . EMAIL_REMITENTE . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $texto, $cabeceras);
}
function notificar(int $idUsuario, string $asunto, string $texto): void
{
    $email = valor('SELECT Email FROM usuario WHERE ID_Usuario = ?', [$idUsuario]);
    if ($email) enviar_correo((string)$email, $asunto, $texto . "\n\n— Equipo Ropify · Tu estilo, entre personas");
}
/** Firma de los enlaces de baja del newsletter (no requiere sesión). */
function firma_baja(string $email): string { return hash_hmac('sha256', strtolower($email), CLAVE_APP); }
/** Recorta un texto a N caracteres. */
function recortar(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n); }
/** Auditoría de acciones de administración (G.5). La acción automática del sistema queda con ID_Admin NULL. */
function registrar_admin(string $accion, string $entidad, ?int $idEntidad, string $detalle = ''): void
{
    $u = usuario();
    q('INSERT INTO registro_admin (ID_Admin, Accion, Entidad, ID_Entidad, Detalle, Fecha) VALUES (?, ?, ?, ?, ?, ?)',
      [$u && es_admin($u) ? $u['ID_Usuario'] : null, $accion, $entidad, $idEntidad, recortar($detalle, 255), ahora()]);
}

require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/direccion.php';
require_once __DIR__ . '/imagenes.php';
require_once __DIR__ . '/pasarela.php';
require_once __DIR__ . '/pedidos.php';
