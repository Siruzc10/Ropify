<?php
/** Direcciones de envío (tabla direccion · CU-13 · RF-06). */

/** Lee y valida los campos de dirección desde $_POST con el prefijo dado. */
function leer_direccion(string $pre = 'dir_'): array
{
    $d = [];
    foreach (['calle', 'numero', 'piso', 'localidad', 'provincia', 'cp'] as $k) $d[$k] = post($pre . $k);
    $err = [];
    if ($d['calle'] === '' || largo($d['calle']) > 100)        $err[] = 'Ingresá la calle (hasta 100 caracteres).';
    if ($d['numero'] === '' || largo($d['numero']) > 10)       $err[] = 'Ingresá la altura (hasta 10 caracteres).';
    if (largo($d['piso']) > 20)                                $err[] = 'Piso/depto: hasta 20 caracteres.';
    if ($d['localidad'] === '' || largo($d['localidad']) > 80) $err[] = 'Ingresá la localidad (hasta 80 caracteres).';
    if (!in_array($d['provincia'], PROVINCIAS, true))          $err[] = 'Elegí una provincia.';
    if ($d['cp'] === '' || largo($d['cp']) > 10)               $err[] = 'Ingresá el código postal (hasta 10 caracteres).';
    return [$d, $err];
}
/** Piso/depto es opcional (una casa no lo tiene): se guarda NULL cuando viene vacío (CP-38). */
function guardar_direccion(int $idUsuario, array $d): int
{
    q('INSERT INTO direccion (ID_Usuario, Calle, Numero, Piso_depto, Localidad, Provincia, Codigo_Postal) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [$idUsuario, $d['calle'], $d['numero'], $d['piso'] === '' ? null : $d['piso'], $d['localidad'], $d['provincia'], $d['cp']]);
    return (int)db()->lastInsertId();
}
function texto_direccion(array $d): string
{
    $piso = ($d['Piso_depto'] ?? '') !== '' && $d['Piso_depto'] !== null ? ' (' . $d['Piso_depto'] . ')' : '';
    return $d['Calle'] . ' ' . $d['Numero'] . $piso . ', ' . $d['Localidad'] . ', ' . $d['Provincia'] . ' · CP ' . $d['Codigo_Postal'];
}
/** Campos del formulario de dirección. $v usa las claves calle, numero, piso, localidad, provincia, cp. */
function campos_direccion(string $pre = 'dir_', array $v = []): string
{
    $g = static fn(string $k): string => (string)($v[$k] ?? ($_POST[$pre . $k] ?? ''));
    return '<div class="fila-campos">'
        . campo('Calle', $pre . 'calle', $g('calle'), ['maxlength' => 100, 'autocomplete' => 'address-line1'])
        . campo('Altura', $pre . 'numero', $g('numero'), ['maxlength' => 10])
        . '</div><div class="fila-campos">'
        . campo('Piso / depto (opcional)', $pre . 'piso', $g('piso'), ['maxlength' => 20])
        . campo('Código postal', $pre . 'cp', $g('cp'), ['maxlength' => 10, 'autocomplete' => 'postal-code'])
        . '</div><div class="fila-campos">'
        . campo('Localidad', $pre . 'localidad', $g('localidad'), ['maxlength' => 80, 'autocomplete' => 'address-level2'])
        . selector('Provincia', $pre . 'provincia', lista_a_opciones(PROVINCIAS), $g('provincia'), 'Elegir provincia')
        . '</div>';
}
