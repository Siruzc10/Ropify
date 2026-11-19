</main>
<footer class="pie">
  <div class="contenedor pie-grilla">
    <div class="pie-marca">
      <img src="<?= e(url('assets/img/logo_slogan.png')) ?>" alt="Ropify · Tu estilo, entre personas" width="190" height="60" loading="lazy">
      <p>Ropa usada en buen estado, revisada antes de publicarse. Pago protegido y envío con seguimiento.</p>
    </div>
    <div>
      <h4>Comprar</h4>
      <a href="<?= e(url('catalogo.php')) ?>">Catálogo</a>
      <a href="<?= e(url('tendencias.php')) ?>">Tendencias</a>
      <a href="<?= e(url('vendedor.php')) ?>">Vendedores</a>
      <a href="<?= e(url('carrito.php')) ?>">Carrito</a>
    </div>
    <div>
      <h4>Vender</h4>
      <a href="<?= e(url('vender.php')) ?>">Publicar una prenda</a>
      <a href="<?= e(url('ventas.php')) ?>">Mis ventas</a>
      <a href="<?= e(url('liquidacion.php')) ?>">Mi liquidación</a>
    </div>
    <div>
      <h4>Ayuda y legales</h4>
      <a href="<?= e(url('ayuda.php')) ?>">Preguntas frecuentes</a>
      <a href="<?= e(url('terminos.php')) ?>">Términos y condiciones</a>
      <a href="<?= e(url('privacidad.php')) ?>">Política de privacidad</a>
      <a href="<?= e(url('arrepentimiento.php')) ?>">Botón de arrepentimiento</a>
    </div>
    <form class="pie-news" method="post" action="<?= e(url('newsletter.php')) ?>">
      <h4>Novedades por e-mail</h4>
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="<?= e(ltrim(substr((string)($_SERVER['REQUEST_URI'] ?? ''), strlen(base_url())), '/')) ?>">
      <div class="input-boton">
        <input type="email" name="email" placeholder="tu@email.com" aria-label="Tu e-mail" required maxlength="120">
        <button type="submit" class="btn btn-ambar btn-chico">Suscribirme</button>
      </div>
    </form>
  </div>
  <div class="pie-base"><div class="contenedor">
    <span>© <?= date('Y') ?> Ropify · «Tu estilo, entre personas»</span>
    <span>Proyecto de Análisis de Sistemas · E.T. N° 29 «Reconquista de Buenos Aires» · Desarrollado por Nucleo Dev</span>
  </div></div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int)@filemtime(RAIZ . '/assets/js/app.js') ?>" defer></script>
</body>
</html>
