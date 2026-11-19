<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Términos y condiciones';
require __DIR__ . '/includes/header.php';
?>
<div class="texto-legal">
<h1>Términos y condiciones de uso</h1>
<div class="nota"><b>Borrador académico.</b> Este texto fue elaborado por el equipo de Nucleo Dev como parte del trabajo final de Análisis de Sistemas. Antes de un lanzamiento comercial debe ser revisado por un profesional del derecho (ver factibilidad legal, Carpeta de Campo, parte D.3).</div>

<h2>1. Qué es Ropify</h2>
<p>Ropify es una plataforma web que permite a personas publicar ropa usada para venderla y a otras comprarla. Ropify facilita la publicación, la revisión de las publicaciones, el cobro y el seguimiento del envío. Cada prenda es una pieza única.</p>

<h2>2. Registro y edad mínima</h2>
<p>Para comprar o vender hay que crear una cuenta con datos verdaderos (nombre, apellido, DNI, e-mail, teléfono y un alias público). Solo pueden registrarse personas mayores de 18 años, o menores con autorización de un adulto responsable. El DNI, el e-mail y el alias no pueden repetirse. Cada persona es responsable de la confidencialidad de su contraseña.</p>

<h2>3. Publicaciones</h2>
<ul>
  <li>Cada publicación requiere al menos <?= MIN_FOTOS ?> fotos propias y reales de la prenda, con datos veraces (talle, color, material, estado de conservación y precio).</li>
  <li>Toda publicación queda «en revisión» hasta que un moderador la aprueba; puede ser rechazada con un motivo.</li>
  <li>Está prohibido publicar prendas falsificadas, usar fotos ajenas o publicar contenido inapropiado.</li>
  <li>Quien publica conserva los derechos de sus fotos y otorga a Ropify una licencia gratuita y no exclusiva para mostrarlas en la plataforma.</li>
</ul>

<h2>4. Precio, comisión y envío</h2>
<p>Ropify retiene una comisión del <?= porcentaje_comision() ?> % del precio de venta; el vendedor recibe el <?= porcentaje_vendedor() ?> % restante. El costo de envío está a cargo del comprador y se informa antes de pagar.</p>

<h2>5. Pago protegido y liquidación</h2>
<p>El comprador paga dentro de la plataforma mediante una pasarela de pagos externa; Ropify no almacena datos de tarjetas. El vendedor cobra (liquidación) cuando el pedido está «Entregado», es decir, cuando el comprador confirma la recepción o vence el plazo de <?= PLAZO_CONFIRMACION_DIAS ?> días desde el envío.</p>

<h2>6. Plazos y cancelaciones</h2>
<ul>
  <li>Los pedidos sin pagar se cancelan a las <?= PLAZO_PAGO_HORAS ?> horas.</li>
  <li>Si el vendedor no despacha dentro de <?= PLAZO_DESPACHO_DIAS ?> días de acreditado el pago, el pedido se cancela y se reintegra al comprador.</li>
  <li>El comprador puede cancelar mientras el pedido no haya sido enviado.</li>
</ul>

<h2>7. Derecho de arrepentimiento</h2>
<p>De acuerdo con la Ley 24.240 y la Resolución 424/2020, el comprador puede revocar la compra dentro de los <?= DIAS_ARREPENTIMIENTO ?> días corridos desde la entrega, usando el <a href="<?= e(url('arrepentimiento.php')) ?>">botón de arrepentimiento</a>.</p>

<h2>8. Denuncias y sanciones</h2>
<p>Cualquier usuario registrado puede denunciar publicaciones o usuarios por estafa, fotos falsas, contenido inapropiado, cuenta falsa o producto falsificado. Solo los administradores aplican sanciones: pausar o rechazar publicaciones y suspender cuentas.</p>

<h2>9. Responsabilidad</h2>
<p>Ropify revisa las publicaciones y protege el pago, pero la compraventa es entre personas. Ropify no garantiza la calidad de las prendas más allá de lo que surja de la revisión y de las fotos publicadas, ni se responsabiliza por demoras del servicio de correo.</p>

<h2>10. Cambios y ley aplicable</h2>
<p>Estos términos pueden actualizarse; se informará a los usuarios. Se rigen por las leyes de la República Argentina.</p>
<p class="muted">Última actualización: <?= date('d/m/Y') ?></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
