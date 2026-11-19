<?php
/**
 * Fotos de prendas (CU-24 · RF-10 · G.5 «Archivos subidos»).
 * Valida el contenido real del archivo (no la extensión), limita el tamaño, renombra,
 * redimensiona y comprime a JPG de ≈300 KB como máximo (RNF-07) cuando GD está disponible.
 */

function mime_imagen(string $ruta): ?string
{
    $info = @getimagesize($ruta);
    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
    return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : null;
}

/**
 * Revisa los archivos subidos en $_FILES['fotos'].
 * @return array{0: array<int,string>, 1: array<int,string>} [rutas temporales válidas, mensajes de error]
 */
function revisar_fotos_subidas(string $campo = 'fotos'): array
{
    $validas = []; $err = [];
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'])) return [$validas, $err];
    $maxBytes = MAX_FOTO_MB * 1024 * 1024;
    foreach ($f['name'] as $i => $nombre) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'][$i] === UPLOAD_ERR_INI_SIZE || $f['error'][$i] === UPLOAD_ERR_FORM_SIZE || $f['size'][$i] > $maxBytes) {
            $err[] = 'La foto «' . $nombre . '» supera los ' . MAX_FOTO_MB . ' MB.';
            continue;
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) {
            $err[] = 'No pudimos recibir la foto «' . $nombre . '».';
            continue;
        }
        if (!mime_imagen($f['tmp_name'][$i])) {
            $err[] = 'El archivo «' . $nombre . '» no es una imagen permitida (solo JPG, PNG o WebP).';
            continue;
        }
        $validas[] = $f['tmp_name'][$i];
    }
    return [$validas, $err];
}

/**
 * Guarda una foto ya validada. $destinoBase va SIN extensión.
 * @return string|null extensión con la que quedó guardada, o null si falló.
 */
function guardar_foto(string $tmp, string $destinoBase): ?string
{
    $mime = mime_imagen($tmp);
    if (!$mime) return null;

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $datos = @file_get_contents($tmp);
        $img = $datos !== false ? @imagecreatefromstring($datos) : false;
        if ($img !== false) {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $ex = @exif_read_data($tmp);
                $ang = [3 => 180, 6 => -90, 8 => 90][(int)($ex['Orientation'] ?? 1)] ?? 0;
                if ($ang !== 0) { $rot = @imagerotate($img, $ang, 0); if ($rot !== false) $img = $rot; }
            }
            $w = imagesx($img); $h = imagesy($img);
            $k = min(1.0, 1280 / max($w, $h));
            $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
            $lienzo = imagecreatetruecolor($nw, $nh);
            imagefilledrectangle($lienzo, 0, 0, $nw, $nh, imagecolorallocate($lienzo, 255, 255, 255)); // fondo blanco (PNG con transparencia)
            imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ruta = $destinoBase . '.jpg';
            foreach ([82, 72, 62, 50] as $calidad) {
                if (!imagejpeg($lienzo, $ruta, $calidad)) return null;
                clearstatcache(true, $ruta);
                if (filesize($ruta) <= 300 * 1024) break;
            }
            return 'jpg';
        }
    }
    // Sin GD (o formato no soportado por GD): se guarda tal cual, con extensión segura.
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    return move_uploaded_file($tmp, $destinoBase . '.' . $ext) ? $ext : null;
}

/** Carpeta de fotos de una prenda: uploads/prendas/{ID_Prenda}/ (G.1). */
function carpeta_fotos(int $idPrenda): string { return RAIZ . '/uploads/prendas/' . $idPrenda; }

/** Borra del disco una foto, solo si su ruta es la esperada. */
function borrar_archivo_foto(string $rutaRelativa): void
{
    if (strpos($rutaRelativa, 'uploads/prendas/') !== 0 || strpos($rutaRelativa, '..') !== false) return;
    $abs = RAIZ . '/' . $rutaRelativa;
    if (is_file($abs)) @unlink($abs);
}
