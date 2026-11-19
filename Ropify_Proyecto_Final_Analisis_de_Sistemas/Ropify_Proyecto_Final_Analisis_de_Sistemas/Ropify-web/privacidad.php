<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Política de privacidad';
require __DIR__ . '/includes/header.php';
?>
<div class="texto-legal">
<h1>Política de privacidad</h1>
<div class="nota"><b>Borrador académico</b> basado en la Ley 25.326 de Protección de los Datos Personales. Debe ser revisado por un profesional antes del lanzamiento comercial, junto con la inscripción de la base de datos ante la AAIP cuando corresponda.</div>

<h2>1. Qué datos recopilamos</h2>
<p>Nombre, apellido, DNI, e-mail, teléfono, alias público, biografía opcional, direcciones de envío, alias de cobro (solo si sos vendedor), las prendas y fotos que publicás, tus pedidos y los mensajes que intercambiás con otros usuarios.</p>

<h2>2. Para qué los usamos</h2>
<ul>
  <li>Crear y proteger tu cuenta y validar que el DNI no esté repetido.</li>
  <li>Procesar compras, envíos y liquidaciones a vendedores.</li>
  <li>Moderar publicaciones, atender denuncias y prevenir fraudes.</li>
  <li>Enviarte e-mails del servicio (confirmaciones, avisos de pedidos) y, si te suscribís, novedades.</li>
</ul>

<h2>3. Qué es público y qué no</h2>
<p>Tu perfil público muestra solo tu alias, tu biografía y tus prendas aprobadas. <b>Nunca se muestran tu DNI, e-mail ni teléfono.</b> Tu dirección solo se le informa al vendedor del pedido, para poder enviarte la prenda.</p>

<h2>4. Seguridad</h2>
<p>Las contraseñas se guardan cifradas (hash) y nunca en texto plano. Usamos consultas seguras, control de acceso por roles y sesiones que expiran por inactividad. Los datos de tarjetas los procesa la pasarela de pagos: Ropify no los almacena.</p>

<h2>5. Tus derechos</h2>
<p>Podés acceder, rectificar y suprimir tus datos. Desde <a href="<?= e(url('perfil.php')) ?>">Mi perfil</a> podés modificar tus datos y dar de baja tu cuenta (baja lógica: conservamos los registros de operaciones que la ley nos obliga a guardar). Para otros pedidos escribinos desde <a href="<?= e(url('ayuda.php')) ?>">Ayuda</a>. La AAIP es el órgano de control de la Ley 25.326 y atiende denuncias y reclamos por incumplimiento de las normas de protección de datos personales.</p>

<h2>6. Cesión a terceros</h2>
<p>Compartimos solo lo necesario con la pasarela de pagos, el servicio de correo y las empresas de envío. No vendemos tus datos.</p>

<h2>7. Newsletter</h2>
<p>Podés darte de baja en cualquier momento desde el enlace que incluye cada correo.</p>
<p class="muted">Última actualización: <?= date('d/m/Y') ?></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
