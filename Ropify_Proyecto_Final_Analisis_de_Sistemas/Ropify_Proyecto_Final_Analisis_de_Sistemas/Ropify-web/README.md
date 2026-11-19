# Ropify · Sitio web (Sprints 6 a 9)

Plataforma de compra y venta de ropa usada entre personas — **«Tu estilo, entre personas»**.
PHP 8 + PDO + MariaDB/MySQL, pensado para correr en **XAMPP**. Sin librerías externas: el sitio funciona sin internet.

## Puesta en marcha (XAMPP)

1. Copiar la carpeta `Ropify-web` dentro de `C:\xampp\htdocs\`.
2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.
3. En phpMyAdmin → **Importar**, en este orden (carpeta `3_Base_de_Datos`):
   `01_ropify_estructura.sql` → `02_ropify_mejoras_y_ampliacion.sql` → `04_ropify_extras_web.sql` → *(opcional, datos de prueba)* `03_ropify_datos_de_ejemplo.sql`.
4. (Opcional) copiar `config.local.php.example` como `config.local.php` y ajustar usuario/clave de la base.
5. Abrir <http://localhost/Ropify-web/>.

Si el sitio muestra «Unknown column» o «doesn't exist», falta importar alguno de los scripts (casi siempre el 04).

## Cuentas de prueba (script 03) — contraseña `Demo1234!`

| Rol | E-mail | Qué mostrar |
|---|---|---|
| Administrador | `admin@ropify.example` | Panel, moderación (la campera está pendiente), denuncia pendiente, usuarios, liquidaciones |
| Vendedora | `valentina@ropify.example` | Mis publicaciones; tiene un pedido **pagado por despachar** (Mis ventas) |
| Comprador | `martin@ropify.example` | Mis compras, deseados, mensajes |

Las fotos de las 3 prendas de ejemplo son ilustraciones («imagen de ejemplo») generadas para la demo.

## Guion de demostración sugerido

1. **Visitante**: inicio → catálogo con filtros (talle M, precio 5.000–15.000) → detalle → tendencias → ayuda.
2. **Registro** con DNI de 7/8 dígitos → login → (probar 5 contraseñas erróneas: bloqueo temporal).
3. **Recuperar contraseña**: en modo demo el enlace aparece en pantalla (y en `storage/correos.log`).
4. **Vendedor**: publicar prenda con 3 fotos → queda «En revisión».
5. **Admin**: Moderación → aprobar / rechazar (el motivo es obligatorio).
6. **Comprador**: agregar al carrito → checkout (dirección + medio de pago) → **pasarela simulada** (aprobado / rechazado).
7. **Vendedor**: Mis ventas → despachar con servicio y código de seguimiento.
8. **Comprador**: confirmar recepción → pedido «Entregado».
9. **Admin**: Liquidaciones → cargar comprobante → el vendedor cobra el 85 %.
10. Mensajes, denuncia → Admin → Denuncias → sanción; Usuarios → verificar DNI / suspender; Reportes.

## Qué está simulado (y cómo pasar a producción)

| Tema | En esta entrega | Para producción |
|---|---|---|
| Pasarela de pagos | **Simulador** (`pago.php`). Ropify no maneja datos de tarjeta. | Integrar Mercado Pago: crear la preferencia en `pago.php`, endpoint de notificación que llame a `pasarela_confirmar_pago()` y reintegros en `pasarela_reintegrar()` (todo aislado en `includes/pasarela.php`). Requiere cuenta y credenciales. |
| E-mails | Se registran en `storage/correos.log` (en XAMPP `mail()` no suele estar configurado). | `MAIL_ACTIVO = true` y SMTP del hosting, o un servicio transaccional. |
| Textos legales | Borrador académico (`terminos.php`, `privacidad.php`). | Revisión legal; inscripción de la base ante la AAIP; registro de marca. |
| HTTPS / backups | No aplica en local. | SSL en el hosting y copias diarias (RNF-02, RNF-12). |
| Administradores | Se crean cambiando `Rol` a `Administrador` en la tabla `usuario` (por seguridad no hay pantalla para auto-promoverse). | — |

En `config.php`: `MODO_DEMO` (true/false), comisión (`COMISION_ROPIFY`, 15 %), costo de envío (`COSTO_ENVIO`), plazos y límites.

## Reglas de negocio implementadas

- Prenda única (RN-01) · mínimo 3 fotos y aprobación previa (RN-02) · estados de prenda y pedido (RN-03, RN-04).
- Comisión 15 % / vendedor 85 % por ítem (RN-05, RN-07) · envío a cargo del comprador, informado antes de pagar (RN-06).
- El vendedor se liquida solo con pedidos **Entregados** (RN-08) · no se compra lo propio ni se envían mensajes a uno mismo (RN-09).
- DNI, e-mail y alias únicos (RN-10) · denuncias sobre prenda o usuario, sanciones solo de administradores (RN-12) · estados de conservación (RN-13).
- Plazo de despacho de 5 días con cancelación y reintegro automáticos (RN-14); pedidos sin pagar se cancelan a las 24 h; entrega automática a los 10 días del envío. Se ejecuta solo (cada pocos minutos) y se puede forzar en *Admin → Pedidos*.
- Un carrito con varios vendedores genera **un pedido por vendedor**, cada uno con su envío y su código de seguimiento.
- Editar una prenda publicada: si cambian fotos o datos (menos el precio) vuelve a moderación.

## Trazabilidad con la Carpeta de Campo

| Historia | Archivos |
|---|---|
| US-01 Registro (CU-01/02) | `registro.php` |
| US-02 Sesión (CU-03) | `login.php`, `logout.php` |
| US-03 Recuperar cuenta (CU-04) | `recuperar.php`, `restablecer.php` |
| US-04 Perfil y direcciones (CU-12/13) | `perfil.php`, `direcciones.php` |
| US-23 Perfil de vendedor (CU-09) | `vendedor.php` |
| US-05 Publicar (CU-23/24) | `vender.php`, `includes/imagenes.php` |
| US-06 Moderar (CU-29) | `admin/moderacion.php` |
| US-07 Buscar y filtrar (CU-05/06) | `catalogo.php` |
| US-08 Detalle (CU-07) | `prenda.php` |
| US-09 Gestionar publicaciones (CU-25) | `publicaciones.php`, `vender.php?id=` |
| US-10 Categorías (CU-30) | `admin/categorias.php` |
| US-11 Carrito y pedido (CU-17/18) | `carrito.php`, `checkout.php` |
| US-12 Pago (CU-19) | `pago.php`, `includes/pasarela.php` |
| US-13 Seguimiento y recepción (CU-20/21/22) | `compras.php`, `pedido.php` |
| US-14 Despacho (CU-26/27) | `ventas.php` |
| US-15 Liquidación (CU-28/35) | `liquidacion.php`, `admin/liquidaciones.php` |
| US-16 Deseados (CU-14) | `favoritos.php`, `prenda.php` |
| US-17 Mensajes (CU-15) | `mensajes.php` |
| US-18 / US-19 Denuncias (CU-16, 33, 34) | `denunciar.php`, `admin/denuncias.php` |
| US-20 Verificar identidad (CU-32) y usuarios (CU-31) | `admin/usuarios.php` |
| US-21 Tendencias, newsletter, ayuda (CU-08, 10, 11) | `tendencias.php`, `newsletter.php`, `ayuda.php` |
| US-22 Panel (CU-36) y newsletter (CU-37) | `admin/index.php`, `admin/reportes.php`, `admin/newsletter.php` |
| Legales (Ley 24.240 / 25.326) | `terminos.php`, `privacidad.php`, `arrepentimiento.php` |

## Seguridad (G.5)

Contraseñas con `password_hash` (bcrypt) · consultas preparadas (PDO) · escape de toda salida · token CSRF en cada formulario ·
bloqueo tras 5 intentos fallidos · enlace de recuperación de un solo uso (solo se guarda su hash) · sesión con expiración por inactividad y cookies HttpOnly/SameSite ·
control de acceso por rol en el servidor · fotos validadas por contenido, re-codificadas y sin ejecución de scripts en `uploads/` · auditoría de acciones de administración (`registro_admin`).

## Estructura

```
Ropify-web/
├─ index.php, catalogo.php, prenda.php, ...   páginas del sitio
├─ admin/                                      panel de administración
├─ includes/                                   núcleo (bootstrap, vistas, pedidos, pasarela, imágenes, header/footer)
├─ assets/                                     css, js, logos
├─ uploads/prendas/{ID_Prenda}/                fotos subidas
├─ storage/correos.log                         e-mails simulados (se crea solo)
└─ config.php / config.local.php               configuración
```
