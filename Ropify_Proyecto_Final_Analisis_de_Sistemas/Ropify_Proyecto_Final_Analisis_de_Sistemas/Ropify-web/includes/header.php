<?php
require_once __DIR__ . '/bootstrap.php';
$u = usuario();
$titulo = $titulo ?? 'Ropify';
$activo = $activo ?? '';
$subnav = $subnav ?? '';
$descripcion = $descripcion ?? 'Ropify: comprá y vendé ropa usada en buen estado entre personas, con publicaciones revisadas, pago protegido y envío con seguimiento.';
$nCarrito = count(carrito());
$noLeidos = $u ? (int)valor('SELECT COUNT(*) FROM mensaje WHERE ID_Receptor = ? AND Leido = 0', [$u['ID_Usuario']]) : 0;
mantenimiento_si_corresponde($subnav === 'admin' && $activo === 'panel');
$cls = static fn(string $k): string => $activo === $k ? ' class="activo" aria-current="page"' : '';
$css = url('assets/css/style.css') . '?v=' . (int)@filemtime(RAIZ . '/assets/css/style.css');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> | Ropify</title>
<meta name="description" content="<?= e($descripcion) ?>">
<meta name="theme-color" content="#0F766E">
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body>
<a class="salto" href="#contenido">Saltar al contenido</a>
<header class="cabecera">
  <div class="cabecera-fila">
    <a class="marca" href="<?= e(url('index.php')) ?>" aria-label="Ropify, ir al inicio"><img src="<?= e(url('assets/img/logo.png')) ?>" alt="Ropify" width="124" height="44"></a>
    <button class="menu-boton" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menú"><span></span><span></span><span></span></button>
    <nav id="menu-principal" class="menu" aria-label="Principal">
      <ul class="menu-links">
        <li><a<?= $cls('inicio') ?> href="<?= e(url('index.php')) ?>">Inicio</a></li>
        <li><a<?= $cls('catalogo') ?> href="<?= e(url('catalogo.php')) ?>">Catálogo</a></li>
        <li><a<?= $cls('tendencias') ?> href="<?= e(url('tendencias.php')) ?>">Tendencias</a></li>
        <li><a<?= $cls('vender') ?> href="<?= e(url('vender.php')) ?>">Vender</a></li>
        <li><a<?= $cls('ayuda') ?> href="<?= e(url('ayuda.php')) ?>">Ayuda</a></li>
      </ul>
      <form class="buscador" role="search" method="get" action="<?= e(url('catalogo.php')) ?>">
        <input type="search" name="q" placeholder="Buscar prendas…" aria-label="Buscar prendas" value="<?= e($activo === 'catalogo' ? obtener('q') : '') ?>">
        <button type="submit" aria-label="Buscar">🔍</button>
      </form>
      <ul class="menu-acciones">
        <li><a class="icono" href="<?= e(url('favoritos.php')) ?>" title="Lista de deseados" aria-label="Lista de deseados">♡</a></li>
        <?php if ($u): ?><li><a class="icono" href="<?= e(url('mensajes.php')) ?>" title="Mensajes" aria-label="Mensajes">✉<?php if ($noLeidos): ?><span class="contador"><?= $noLeidos ?></span><?php endif; ?></a></li><?php endif; ?>
        <li><a class="icono" href="<?= e(url('carrito.php')) ?>" title="Carrito" aria-label="Carrito">🛒<?php if ($nCarrito): ?><span class="contador"><?= $nCarrito ?></span><?php endif; ?></a></li>
        <li>
          <details class="cuenta">
            <summary aria-label="Mi cuenta"><span class="avatar"><?= $u ? e(iniciales($u['AliasPublico'])) : '👤' ?></span></summary>
            <div class="cuenta-menu">
              <?php if ($u): ?>
                <p class="cuenta-nombre"><b><?= e($u['AliasPublico']) ?></b><small><?= e($u['Email']) ?></small></p>
                <a href="<?= e(url('perfil.php')) ?>">Mi perfil</a>
                <a href="<?= e(url('direcciones.php')) ?>">Mis direcciones</a>
                <a href="<?= e(url('compras.php')) ?>">Mis compras</a>
                <a href="<?= e(url('ventas.php')) ?>">Mis ventas</a>
                <a href="<?= e(url('publicaciones.php')) ?>">Mis publicaciones</a>
                <a href="<?= e(url('favoritos.php')) ?>">Lista de deseados</a>
                <a href="<?= e(url('mensajes.php')) ?>">Mensajes<?= $noLeidos ? ' (' . $noLeidos . ')' : '' ?></a>
                <a href="<?= e(url('liquidacion.php')) ?>">Mi liquidación</a>
                <?php if (es_admin($u)): ?><a class="destacado" href="<?= e(url('admin/index.php')) ?>">Panel de administración</a><?php endif; ?>
                <a href="<?= e(url('logout.php')) ?>">Cerrar sesión</a>
              <?php else: ?>
                <a href="<?= e(url('login.php')) ?>">Ingresar</a>
                <a href="<?= e(url('registro.php')) ?>">Crear cuenta</a>
                <a href="<?= e(url('recuperar.php')) ?>">Recuperar contraseña</a>
              <?php endif; ?>
            </div>
          </details>
        </li>
      </ul>
    </nav>
  </div>
  <?php if ($subnav === 'admin' && es_admin($u)):
    $nPend = (int)valor("SELECT COUNT(*) FROM prenda WHERE Estado = 'Pendiente'");
    $nDen  = (int)valor("SELECT COUNT(*) FROM denuncia WHERE Estado IN ('Pendiente','En revision')");
    $nCons = (int)valor("SELECT COUNT(*) FROM consulta_ayuda WHERE Estado = 'Pendiente'");
    $adm = static fn(string $k): string => $activo === $k ? ' class="activo" aria-current="page"' : '';
    $cont = static fn(int $n): string => $n > 0 ? '<span class="contador">' . $n . '</span>' : ''; ?>
  <nav class="subnav" aria-label="Administración"><div class="subnav-fila">
    <a<?= $adm('panel') ?> href="<?= e(url('admin/index.php')) ?>">Panel</a>
    <a<?= $adm('moderacion') ?> href="<?= e(url('admin/moderacion.php')) ?>">Moderación<?= $cont($nPend) ?></a>
    <a<?= $adm('denuncias') ?> href="<?= e(url('admin/denuncias.php')) ?>">Denuncias<?= $cont($nDen) ?></a>
    <a<?= $adm('usuarios') ?> href="<?= e(url('admin/usuarios.php')) ?>">Usuarios</a>
    <a<?= $adm('categorias') ?> href="<?= e(url('admin/categorias.php')) ?>">Categorías</a>
    <a<?= $adm('pedidos') ?> href="<?= e(url('admin/pedidos.php')) ?>">Pedidos</a>
    <a<?= $adm('liquidaciones') ?> href="<?= e(url('admin/liquidaciones.php')) ?>">Liquidaciones</a>
    <a<?= $adm('consultas') ?> href="<?= e(url('admin/consultas.php')) ?>">Consultas<?= $cont($nCons) ?></a>
    <a<?= $adm('newsletter') ?> href="<?= e(url('admin/newsletter.php')) ?>">Newsletter</a>
    <a<?= $adm('reportes') ?> href="<?= e(url('admin/reportes.php')) ?>">Reportes</a>
  </div></nav>
  <?php endif; ?>
</header>
<?php if (MODO_DEMO): ?><div class="franja-demo">Modo demostración: la pasarela de pagos es un simulador y los e-mails se guardan en <code>storage/correos.log</code>.</div><?php endif; ?>
<div class="contenedor avisos"><?= avisos_html() ?></div>
<main id="contenido" class="contenedor">
