-- =====================================================================
--  ROPIFY - Datos de EJEMPLO (ficticios) para probar el modelo
--  Ejecutar DESPUÉS de 01 y 02. Todos los datos personales son inventados.
--  Contraseña de los usuarios de prueba: Demo1234!  
-- =====================================================================
USE ropify;

INSERT INTO categoria (Nombre, Descripcion) VALUES
 ('Remeras y tops',        'Remeras, musculosas, tops y camisetas'),
 ('Camisas y blusas',      'Camisas, blusas y chombas'),
 ('Pantalones y jeans',    'Jeans, joggers, pantalones de vestir y shorts'),
 ('Buzos y camperas',      'Buzos, sweaters, camperas y abrigos'),
 ('Vestidos y polleras',   'Vestidos, polleras y monos'),
 ('Calzado',               'Zapatillas, zapatos, botas y sandalias'),
 ('Accesorios',            'Gorros, bufandas, cinturones y bolsos');

INSERT INTO usuario (Nombre, Apellido, DNI, Dni_Verificado, Email, Contrasena_hash, Rol, Telefono, AliasPublico, Biografia) VALUES
 ('Admin',    'Ropify',  10000001, 1, 'admin@ropify.example',    '$2y$10$OAPCIotwsKnzHJ6gZc4ZYuqTgFQp9kDBhVrVKO1CnCIxhbdFc5Zd6', 'Administrador', '1100000001', 'ropify_admin', 'Cuenta de moderación'),
 ('Valentina','Demo',    10000002, 1, 'valentina@ropify.example','$2y$10$OAPCIotwsKnzHJ6gZc4ZYuqTgFQp9kDBhVrVKO1CnCIxhbdFc5Zd6', 'Usuario',       '1100000002', 'vale_closet',  'Vendo ropa en excelente estado'),
 ('Martín',   'Demo',    10000003, 1, 'martin@ropify.example',   '$2y$10$OAPCIotwsKnzHJ6gZc4ZYuqTgFQp9kDBhVrVKO1CnCIxhbdFc5Zd6', 'Usuario',       '1100000003', 'martin_demo',  NULL);

INSERT INTO direccion (ID_Usuario, Calle, Numero, Piso_depto, Localidad, Provincia, Codigo_Postal) VALUES
 (3, 'Av. Ejemplo', '1234', '2B', 'CABA', 'Ciudad Autónoma de Buenos Aires', 'C1000'),
 (2, 'Calle Falsa', '742',  NULL, 'Lanús', 'Buenos Aires', 'B1824');

INSERT INTO prenda (ID_Vendedor, ID_Categoria, Titulo, Descripcion, Talle, Color, Material, Estado_Conservacion, Precio, Estado) VALUES
 (2, 3, 'Jean mom azul',          'Jean tiro alto, usado pocas veces.',        '38', 'Azul',   'Denim',    'Muy bueno', 20000.00, 'Aprobada'),
 (2, 1, 'Remera oversize negra',  'Remera de algodón, sin detalles.',          'M',  'Negro',  'Algodón',  'Excelente', 8000.00,  'Aprobada'),
 (2, 4, 'Campera de cuero ecológico', 'Campera con forro interior.',           'L',  'Marrón', 'Cuero eco','Bueno',     35000.00, 'Pendiente');

INSERT INTO foto_prenda (ID_Prenda, URL, Orden) VALUES
 (1, 'uploads/prendas/1/frente.jpg', 1), (1, 'uploads/prendas/1/dorso.jpg', 2), (1, 'uploads/prendas/1/etiqueta.jpg', 3),
 (2, 'uploads/prendas/2/frente.jpg', 1), (2, 'uploads/prendas/2/dorso.jpg', 2), (2, 'uploads/prendas/2/etiqueta.jpg', 3),
 (3, 'uploads/prendas/3/frente.jpg', 1), (3, 'uploads/prendas/3/dorso.jpg', 2), (3, 'uploads/prendas/3/etiqueta.jpg', 3);

-- Pedido de ejemplo: Martín compra el jean (comisión de la plataforma 15 %, vendedor 85 %, envío a cargo del comprador)
INSERT INTO pedido (ID_Comprador, ID_Direccion, Subtotal, Costo_Envio, Total, Metodo_Pago, Estado) VALUES
 (3, 1, 20000.00, 4500.00, 24500.00, 'Mercado Pago', 'Pagado');
INSERT INTO detalle_pedido (ID_Pedido, ID_Prenda, Precio_Venta, Comision_Chuko, Monto_Vendedor) VALUES
 (1, 1, 20000.00, 3000.00, 17000.00);
UPDATE prenda SET Estado = 'Vendida' WHERE ID_Prenda = 1;

INSERT INTO favorito (ID_Usuario, ID_Prenda) VALUES (3, 2);
INSERT INTO mensaje (ID_Emisor, ID_Receptor, ID_Prenda, Contenido) VALUES (3, 2, 2, '¡Hola! ¿La remera tiene algún detalle de uso?');
INSERT INTO denuncia (ID_Denunciante, ID_Prenda, Motivo, Descripcion) VALUES (3, 3, 'Fotos falsas', 'Las fotos parecen sacadas de internet.');
INSERT INTO suscripcion_newsletter (Email) VALUES ('martin@ropify.example');
