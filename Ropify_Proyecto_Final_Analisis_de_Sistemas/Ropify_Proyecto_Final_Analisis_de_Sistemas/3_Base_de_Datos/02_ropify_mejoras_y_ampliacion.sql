-- =====================================================================
--  ROPIFY - Mejoras al modelo original + ampliación (nuevas tablas)
--  Ejecutar DESPUÉS de 01_ropify_estructura.sql
--  Resultado: modelo de datos de diseño (v2) con 11 tablas.
-- =====================================================================
USE ropify;

-- ---------------------------------------------------------------------
-- 1) Ajustes sobre las tablas originales
-- ---------------------------------------------------------------------
ALTER TABLE usuario
  MODIFY ID_Usuario     INT(11)      NOT NULL AUTO_INCREMENT,           -- el original no era autoincremental
  MODIFY Telefono       VARCHAR(20)  NOT NULL,                           -- INT no alcanza para prefijos (+54 9 ...)
  MODIFY Biografia      VARCHAR(300) NULL DEFAULT NULL,                  -- dato opcional
  MODIFY Dni_Verificado TINYINT(1)   NOT NULL DEFAULT 0,
  MODIFY Fecha_Registro DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN Alias_Cobro   VARCHAR(40) NULL DEFAULT NULL AFTER Biografia,   -- alias CBU/CVU para recibir liquidaciones
  ADD COLUMN Rol           ENUM('Usuario','Administrador') NOT NULL DEFAULT 'Usuario' AFTER Contrasena_hash,
  ADD COLUMN Estado_Cuenta ENUM('Activa','Suspendida','Eliminada') NOT NULL DEFAULT 'Activa' AFTER Rol,
  ADD UNIQUE KEY uq_usuario_email (Email),
  ADD UNIQUE KEY uq_usuario_dni   (DNI),
  ADD UNIQUE KEY uq_usuario_alias (AliasPublico);

ALTER TABLE categoria
  ADD UNIQUE KEY uq_categoria_nombre (Nombre);

ALTER TABLE direccion
  MODIFY Piso_depto VARCHAR(20) NULL DEFAULT NULL;                       -- una casa no tiene piso/depto

ALTER TABLE prenda
  CHANGE Estado_Conversacion Estado_Conservacion
         ENUM('Excelente','Muy bueno','Bueno','Decente') NOT NULL,       -- corrección de nombre (Conservación)
  MODIFY Titulo           VARCHAR(100) NOT NULL,
  MODIFY Estado           ENUM('Pendiente','Aprobada','Rechazada','Vendida','Pausada') NOT NULL DEFAULT 'Pendiente',
  MODIFY FechaPublicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN Motivo_Rechazo VARCHAR(255) NULL DEFAULT NULL AFTER Estado;

ALTER TABLE pedido
  MODIFY Fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY Estado ENUM('Pendiente_Pago','Pagado','Enviado','Entregado','Cancelado') NOT NULL DEFAULT 'Pendiente_Pago',
  ADD COLUMN Codigo_Seguimiento VARCHAR(50) NULL DEFAULT NULL AFTER Estado,
  ADD COLUMN Fecha_Pago    DATETIME NULL DEFAULT NULL,
  ADD COLUMN Fecha_Envio   DATETIME NULL DEFAULT NULL,
  ADD COLUMN Fecha_Entrega DATETIME NULL DEFAULT NULL;

ALTER TABLE detalle_pedido
  ADD COLUMN Liquidado              TINYINT(1)   NOT NULL DEFAULT 0,        -- liquidación al vendedor (85 %)
  ADD COLUMN Fecha_Liquidacion      DATETIME     NULL DEFAULT NULL,
  ADD COLUMN Comprobante_Liquidacion VARCHAR(100) NULL DEFAULT NULL;

-- ---------------------------------------------------------------------
-- 2) Reglas de integridad (CHECK)
-- ---------------------------------------------------------------------
ALTER TABLE prenda          ADD CONSTRAINT chk_prenda_precio  CHECK (Precio > 0);
ALTER TABLE pedido          ADD CONSTRAINT chk_pedido_total   CHECK (Total = Subtotal + Costo_Envio);
ALTER TABLE detalle_pedido  ADD CONSTRAINT chk_detalle_montos CHECK (Precio_Venta > 0 AND Comision_Chuko >= 0
                                                                      AND Monto_Vendedor = Precio_Venta - Comision_Chuko);

-- ---------------------------------------------------------------------
-- 3) Claves foráneas 
-- ---------------------------------------------------------------------
ALTER TABLE direccion
  ADD CONSTRAINT fk_direccion_usuario FOREIGN KEY (ID_Usuario)   REFERENCES usuario(ID_Usuario)     ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE prenda
  ADD CONSTRAINT fk_prenda_vendedor   FOREIGN KEY (ID_Vendedor)  REFERENCES usuario(ID_Usuario)     ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_prenda_categoria  FOREIGN KEY (ID_Categoria) REFERENCES categoria(ID_Categoria) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE foto_prenda
  ADD CONSTRAINT fk_foto_prenda       FOREIGN KEY (ID_Prenda)    REFERENCES prenda(ID_Prenda)       ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE pedido
  ADD CONSTRAINT fk_pedido_comprador  FOREIGN KEY (ID_Comprador) REFERENCES usuario(ID_Usuario)     ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_pedido_direccion  FOREIGN KEY (ID_Direccion) REFERENCES direccion(ID_Direccion) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE detalle_pedido
  ADD CONSTRAINT fk_detalle_pedido    FOREIGN KEY (ID_Pedido)    REFERENCES pedido(ID_Pedido)       ON UPDATE CASCADE ON DELETE CASCADE,
  ADD CONSTRAINT fk_detalle_prenda    FOREIGN KEY (ID_Prenda)    REFERENCES prenda(ID_Prenda)       ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- 4) Tablas nuevas 
-- ---------------------------------------------------------------------
CREATE TABLE favorito (                      -- Lista de deseados
  ID_Usuario     INT(11)  NOT NULL,
  ID_Prenda      INT(11)  NOT NULL,
  Fecha_Agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ID_Usuario, ID_Prenda),
  KEY idx_favorito_prenda (ID_Prenda),
  CONSTRAINT fk_favorito_usuario FOREIGN KEY (ID_Usuario) REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_favorito_prenda  FOREIGN KEY (ID_Prenda)  REFERENCES prenda(ID_Prenda)   ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE mensaje (                       -- Contacto directo comprador - vendedor
  ID_Mensaje   INT(11)       NOT NULL AUTO_INCREMENT,
  ID_Emisor    INT(11)       NOT NULL,
  ID_Receptor  INT(11)       NOT NULL,
  ID_Prenda    INT(11)       NULL DEFAULT NULL,
  Contenido    VARCHAR(1000) NOT NULL,
  Fecha_Envio  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Leido        TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (ID_Mensaje),
  KEY idx_mensaje_receptor (ID_Receptor),
  KEY idx_mensaje_emisor   (ID_Emisor),
  KEY idx_mensaje_prenda   (ID_Prenda),
  CONSTRAINT chk_mensaje_distintos CHECK (ID_Emisor <> ID_Receptor),
  CONSTRAINT fk_mensaje_emisor   FOREIGN KEY (ID_Emisor)   REFERENCES usuario(ID_Usuario) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_mensaje_receptor FOREIGN KEY (ID_Receptor) REFERENCES usuario(ID_Usuario) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_mensaje_prenda   FOREIGN KEY (ID_Prenda)   REFERENCES prenda(ID_Prenda)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE denuncia (                      -- Sistema de denuncias y moderación
  ID_Denuncia           INT(11)      NOT NULL AUTO_INCREMENT,
  ID_Denunciante        INT(11)      NOT NULL,
  ID_Prenda             INT(11)      NULL DEFAULT NULL,
  ID_Usuario_Denunciado INT(11)      NULL DEFAULT NULL,
  Motivo                ENUM('Estafa','Fotos falsas','Contenido inapropiado','Cuenta falsa','Producto falsificado','Otro') NOT NULL,
  Descripcion           VARCHAR(500) NULL DEFAULT NULL,
  Fecha_Denuncia        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Estado                ENUM('Pendiente','En revision','Resuelta','Descartada') NOT NULL DEFAULT 'Pendiente',
  ID_Admin_Resolutor    INT(11)      NULL DEFAULT NULL,
  Resolucion            VARCHAR(255) NULL DEFAULT NULL,
  Fecha_Resolucion      DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (ID_Denuncia),
  KEY idx_denuncia_denunciante (ID_Denunciante),
  KEY idx_denuncia_prenda      (ID_Prenda),
  KEY idx_denuncia_denunciado  (ID_Usuario_Denunciado),
  KEY idx_denuncia_admin       (ID_Admin_Resolutor),
  CONSTRAINT chk_denuncia_objetivo CHECK (ID_Prenda IS NOT NULL OR ID_Usuario_Denunciado IS NOT NULL),
  CONSTRAINT fk_denuncia_denunciante FOREIGN KEY (ID_Denunciante)        REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_denuncia_prenda      FOREIGN KEY (ID_Prenda)             REFERENCES prenda(ID_Prenda)   ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_denuncia_denunciado  FOREIGN KEY (ID_Usuario_Denunciado) REFERENCES usuario(ID_Usuario) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_denuncia_admin       FOREIGN KEY (ID_Admin_Resolutor)    REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE suscripcion_newsletter (        -- Suscripción a novedades
  ID_Suscripcion INT(11)      NOT NULL AUTO_INCREMENT,
  Email          VARCHAR(120) NOT NULL,
  Fecha_Alta     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Activa         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (ID_Suscripcion),
  UNIQUE KEY uq_newsletter_email (Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
