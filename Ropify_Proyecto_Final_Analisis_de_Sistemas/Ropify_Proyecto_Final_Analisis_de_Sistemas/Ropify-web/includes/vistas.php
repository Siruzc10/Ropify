<?php
/** Componentes HTML reutilizables. Los mismos componentes en todo el sitio (G.4: consistencia). */

function atributos_html(array $attr): string
{
    $out = '';
    foreach ($attr as $k => $v) {
        if ($v === true) $out .= ' ' . $k;
        elseif ($v !== false && $v !== null) $out .= ' ' . $k . '="' . e($v) . '"';
    }
    return $out;
}
function id_campo(string $nombre): string { return 'f_' . preg_replace('/[^A-Za-z0-9_]/', '_', $nombre); }

function campo(string $etiqueta, string $nombre, $valor = '', array $attr = [], string $ayuda = ''): string
{
    $tipo = (string)($attr['type'] ?? 'text');
    unset($attr['type']);
    $id = id_campo($nombre);
    return '<div class="campo"><label for="' . $id . '">' . e($etiqueta) . '</label>'
         . '<input id="' . $id . '" name="' . e($nombre) . '" type="' . e($tipo) . '" value="' . e($valor) . '"' . atributos_html($attr) . '>'
         . ($ayuda !== '' ? '<small class="ayuda">' . e($ayuda) . '</small>' : '') . '</div>';
}
function area(string $etiqueta, string $nombre, $valor = '', array $attr = [], string $ayuda = ''): string
{
    $id = id_campo($nombre);
    return '<div class="campo"><label for="' . $id . '">' . e($etiqueta) . '</label>'
         . '<textarea id="' . $id . '" name="' . e($nombre) . '"' . atributos_html($attr) . '>' . e($valor) . '</textarea>'
         . ($ayuda !== '' ? '<small class="ayuda">' . e($ayuda) . '</small>' : '') . '</div>';
}
/** $opciones: [valor => texto]. */
function selector(string $etiqueta, string $nombre, array $opciones, $seleccionado = '', string $vacio = 'Elegir…', array $attr = []): string
{
    $id = id_campo($nombre);
    $html = '<div class="campo"><label for="' . $id . '">' . e($etiqueta) . '</label><select id="' . $id . '" name="' . e($nombre) . '"' . atributos_html($attr) . '>';
    if ($vacio !== '') $html .= '<option value="">' . e($vacio) . '</option>';
    foreach ($opciones as $val => $txt) {
        $html .= '<option value="' . e($val) . '"' . ((string)$val === (string)$seleccionado ? ' selected' : '') . '>' . e($txt) . '</option>';
    }
    return $html . '</select></div>';
}
/** Convierte ['a','b'] en ['a' => 'a', 'b' => 'b'] para usar con selector(). */
function lista_a_opciones(array $lista): array { return array_combine($lista, $lista) ?: []; }

/* ---------- Estados ---------- */
function badge(string $texto, string $tipo = 'neutro'): string { return '<span class="badge badge-' . e($tipo) . '">' . e($texto) . '</span>'; }
function badge_pedido(string $estado): string
{
    $tipos = ['Pendiente_Pago' => 'aviso', 'Pagado' => 'info', 'Enviado' => 'info', 'Entregado' => 'ok', 'Cancelado' => 'error'];
    return badge(ESTADOS_PEDIDO[$estado] ?? $estado, $tipos[$estado] ?? 'neutro');
}
function badge_prenda(string $estado): string
{
    $tipos = ['Pendiente' => 'aviso', 'Aprobada' => 'ok', 'Rechazada' => 'error', 'Vendida' => 'neutro', 'Pausada' => 'info'];
    return badge(ESTADOS_PRENDA[$estado] ?? $estado, $tipos[$estado] ?? 'neutro');
}

/* ---------- Prendas ---------- */
const SQL_PRENDA_BASE = ' FROM prenda p
    JOIN categoria c ON c.ID_Categoria = p.ID_Categoria
    JOIN usuario u   ON u.ID_Usuario = p.ID_Vendedor ';
/** Solo prendas visibles en el catálogo: aprobadas y de vendedores con cuenta activa (RF-14). */
const DONDE_CATALOGO = "p.Estado = 'Aprobada' AND u.Estado_Cuenta = 'Activa'";

function sql_prenda(string $extra = ''): string
{
    return 'SELECT p.*, c.Nombre AS Categoria, u.AliasPublico, u.Estado_Cuenta AS EstadoVendedor, u.Dni_Verificado AS VendedorVerificado,
        (SELECT f.URL FROM foto_prenda f WHERE f.ID_Prenda = p.ID_Prenda ORDER BY f.Orden, f.ID_Foto LIMIT 1) AS Foto'
        . ($extra !== '' ? ', ' . $extra : '') . SQL_PRENDA_BASE;
}
function prendas(string $where, array $params = [], string $orden = 'p.FechaPublicacion DESC, p.ID_Prenda DESC', int $limite = 100, int $offset = 0, string $extra = ''): array
{
    return filas(sql_prenda($extra) . ($where !== '' ? 'WHERE ' . $where . ' ' : '') . 'ORDER BY ' . $orden . ' LIMIT ' . $limite . ' OFFSET ' . $offset, $params);
}
function contar_prendas(string $where, array $params = []): int
{
    return (int)valor('SELECT COUNT(*)' . SQL_PRENDA_BASE . ($where !== '' ? 'WHERE ' . $where : ''), $params);
}
function url_foto(string $ruta): string { return preg_match('#^https?://#', $ruta) ? $ruta : url($ruta); }
function foto_html(?string $ruta, string $alt = '', string $clase = ''): string
{
    $img = $ruta ? '<img src="' . e(url_foto($ruta)) . '" alt="' . e($alt) . '" loading="lazy">' : '';
    return '<div class="foto' . ($ruta ? '' : ' sin-foto') . ($clase !== '' ? ' ' . e($clase) : '') . '">' . $img . '</div>';
}
function tarjeta_prenda(array $p): string
{
    $extra = '';
    if (isset($p['Deseados'])) $extra .= '<span title="Deseados">♥ ' . (int)$p['Deseados'] . '</span>';
    if (isset($p['Visitas']) && ($p['mostrar_visitas'] ?? false)) $extra .= '<span title="Visitas">👁 ' . (int)$p['Visitas'] . '</span>';
    $vendida = $p['Estado'] === 'Vendida' ? '<span class="cinta">Vendida</span>' : '';
    return '<a class="tarjeta" href="' . e(url('prenda.php?id=' . (int)$p['ID_Prenda'])) . '">'
        . '<div class="tarjeta-foto">' . foto_html($p['Foto'] ?? null, (string)$p['Titulo']) . $vendida . '</div>'
        . '<div class="tarjeta-cuerpo"><span class="precio">' . dinero($p['Precio']) . '</span>'
        . '<h3>' . e($p['Titulo']) . '</h3>'
        . '<p class="meta">Talle ' . e($p['Talle']) . ' · ' . e($p['Estado_Conservacion']) . '</p>'
        . '<p class="meta">' . e($p['Categoria']) . ' · ' . e($p['AliasPublico']) . '</p>'
        . ($extra !== '' ? '<p class="meta stats">' . $extra . '</p>' : '') . '</div></a>';
}
function grilla_prendas(array $lista): string
{
    $h = '<div class="grilla">';
    foreach ($lista as $p) $h .= tarjeta_prenda($p);
    return $h . '</div>';
}

/* ---------- Paginación ---------- */
function paginacion_html(int $total, int $porPagina, int $pagina, array $paramsBase = [], string $archivo = ''): string
{
    $paginas = (int)ceil($total / max(1, $porPagina));
    if ($paginas <= 1) return '';
    $enlace = static function (int $n, string $texto, bool $actual = false) use ($paramsBase, $archivo): string {
        $p = $paramsBase;
        $p['pagina'] = $n;
        $href = ($archivo !== '' ? $archivo : basename((string)$_SERVER['SCRIPT_NAME'])) . '?' . http_build_query($p);
        return $actual ? '<span class="pag actual" aria-current="page">' . $texto . '</span>' : '<a class="pag" href="' . e($href) . '">' . $texto . '</a>';
    };
    $h = '<nav class="paginacion" aria-label="Paginación">';
    if ($pagina > 1) $h .= $enlace($pagina - 1, '‹ Anterior');
    $desde = max(1, $pagina - 2);
    $hasta = min($paginas, $pagina + 2);
    for ($i = $desde; $i <= $hasta; $i++) $h .= $enlace($i, (string)$i, $i === $pagina);
    if ($pagina < $paginas) $h .= $enlace($pagina + 1, 'Siguiente ›');
    return $h . '</nav>';
}

/** Botón dentro de un formulario mínimo (acciones por POST con CSRF). */
function boton_post(string $accion, array $campos, string $texto, string $clase = 'btn btn-chico btn-sec', string $confirmar = '', string $destino = ''): string
{
    $h = '<form method="post" class="en-linea"' . ($destino !== '' ? ' action="' . e($destino) . '"' : '') . ($confirmar !== '' ? ' data-confirmar="' . e($confirmar) . '"' : '') . '>' . csrf_campo()
       . '<input type="hidden" name="accion" value="' . e($accion) . '">';
    foreach ($campos as $k => $v) $h .= '<input type="hidden" name="' . e((string)$k) . '" value="' . e($v) . '">';
    return $h . '<button type="submit" class="' . e($clase) . '">' . e($texto) . '</button></form>';
}
/** Iniciales para avatares. */
function iniciales(string $alias): string { return mayusculas(function_exists('mb_substr') ? mb_substr($alias, 0, 2, 'UTF-8') : substr($alias, 0, 2)); }
