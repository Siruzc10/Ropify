/* Ropify · JavaScript mínimo, sin dependencias. El sitio funciona también sin JS (salvo el menú móvil). */
(function () {
  'use strict';

  // Menú móvil
  var boton = document.querySelector('.menu-boton');
  var menu = document.getElementById('menu-principal');
  if (boton && menu) {
    boton.addEventListener('click', function () {
      var abierto = menu.classList.toggle('abierto');
      boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
  }

  // Menú de cuenta: se cierra al hacer clic afuera
  document.addEventListener('click', function (ev) {
    document.querySelectorAll('details.cuenta[open]').forEach(function (d) {
      if (!d.contains(ev.target)) d.removeAttribute('open');
    });
  });

  // Confirmaciones: <form data-confirmar="¿Seguro?">
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = f.getAttribute && f.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  // Imágenes rotas: se muestra el ícono de percha
  document.addEventListener('error', function (ev) {
    var img = ev.target;
    if (img && img.tagName === 'IMG' && img.closest('.foto')) {
      var cont = img.closest('.foto');
      cont.classList.add('sin-foto');
      img.remove();
    }
  }, true);

  // Galería del detalle de prenda
  var principal = document.getElementById('foto-principal');
  document.querySelectorAll('.miniaturas button[data-src]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (principal) { principal.src = b.getAttribute('data-src'); }
      document.querySelectorAll('.miniaturas button').forEach(function (x) { x.classList.remove('activa'); });
      b.classList.add('activa');
    });
  });

  // Vista previa de fotos al publicar (CU-23 paso 6)
  var entrada = document.querySelector('input[type=file][data-preview]');
  if (entrada) {
    var destino = document.querySelector(entrada.getAttribute('data-preview'));
    var min = parseInt(entrada.getAttribute('data-min') || '0', 10);
    var max = parseInt(entrada.getAttribute('data-max') || '99', 10);
    var maxMb = parseInt(entrada.getAttribute('data-mb') || '5', 10);
    entrada.addEventListener('change', function () {
      if (!destino) return;
      destino.innerHTML = '';
      var archivos = Array.prototype.slice.call(entrada.files);
      archivos.forEach(function (a) {
        if (!/^image\//.test(a.type)) return;
        var im = document.createElement('img');
        im.alt = 'Vista previa de ' + a.name;
        im.src = URL.createObjectURL(a);
        destino.appendChild(im);
      });
      var aviso = document.getElementById('aviso-fotos');
      if (aviso) {
        var pesadas = archivos.filter(function (a) { return a.size > maxMb * 1024 * 1024; }).length;
        var msg = archivos.length + ' foto(s) elegida(s).';
        if (archivos.length > max) msg += ' El máximo es ' + max + '.';
        if (pesadas) msg += ' ' + pesadas + ' supera(n) los ' + maxMb + ' MB.';
        aviso.textContent = msg;
      }
    });
  }

  // Contador de caracteres: <textarea data-contador="#id">
  document.querySelectorAll('textarea[data-contador]').forEach(function (t) {
    var c = document.querySelector(t.getAttribute('data-contador'));
    if (!c) return;
    var act = function () { c.textContent = t.value.length + ' / ' + t.getAttribute('maxlength'); };
    t.addEventListener('input', act); act();
  });

  // Seleccionar/deseleccionar todos: <input type=checkbox data-todos="nombre">
  document.querySelectorAll('input[data-todos]').forEach(function (m) {
    m.addEventListener('change', function () {
      document.querySelectorAll('input[name="' + m.getAttribute('data-todos') + '"]').forEach(function (c) { c.checked = m.checked; });
    });
  });
})();
