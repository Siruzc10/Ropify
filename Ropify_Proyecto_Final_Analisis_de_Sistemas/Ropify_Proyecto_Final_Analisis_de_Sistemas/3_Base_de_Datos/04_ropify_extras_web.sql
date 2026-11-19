-- =====================================================================
--  ROPIFY - Complemento para la implementación web (Sprints 6 a 9)
--  Ejecutar DESPUÉS de 01 y 02 (el 03 es opcional y puede ir antes o después).
--  Se puede ejecutar más de una vez: usa IF NOT EXISTS.
--
--  Por qué existe: la Carpeta de Campo exige comportamientos que el modelo de
--  11 tablas no puede guardar por sí solo:
--    · RF-03   bloqueo temporal tras 5 intentos fallidos      -> intento_login
--    · RF-04   enlace de recuperación de un solo uso (60 min) -> token_recuperacion
--    · RF-36   formulario de contacto y reclamos de Ayuda      -> consulta_ayuda
--              (incluye el botón de arrepentimiento, Res. 424/2020)
--    · G.5     auditoría de moderación, sanciones y liquidaciones -> registro_admin
--    · RF-18   tendencias «más vistas»                          -> prenda.Visitas
--    · RF-28   servicio de envío (Andreani, OCA...) junto al código -> pedido.Servicio_Envio
-- =====================================================================
USE ropify;

ALTER TABLE prenda
  ADD COLUMN IF NOT EXISTS Visitas INT(11) NOT NULL DEFAULT 0 AFTER Motivo_Rechazo;

ALTER TABLE pedido
  ADD COLUMN IF NOT EXISTS Servicio_Envio VARCHAR(40) NULL DEFAULT NULL AFTER Codigo_Seguimiento;

CREATE TABLE IF NOT EXISTS intento_login (          -- RF-03: intentos fallidos de acceso
  ID_Intento  INT(11)      NOT NULL AUTO_INCREMENT,
  Email       VARCHAR(120) NOT NULL,
  IP          VARCHAR(45)  NOT NULL,
  Fecha       DATETIME     NOT NULL,
  PRIMARY KEY (ID_Intento),
  KEY idx_intento_email (Email, Fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS token_recuperacion (     -- RF-04: se guarda solo el hash del token
  ID_Token       INT(11)     NOT NULL AUTO_INCREMENT,
  ID_Usuario     INT(11)     NOT NULL,
  Token_Hash     CHAR(64)    NOT NULL,
  Fecha_Creacion DATETIME    NOT NULL,
  Fecha_Expira   DATETIME    NOT NULL,
  Usado          TINYINT(1)  NOT NULL DEFAULT 0,
  PRIMARY KEY (ID_Token),
  UNIQUE KEY uq_token_hash (Token_Hash),
  KEY idx_token_usuario (ID_Usuario),
  CONSTRAINT fk_token_usuario FOREIGN KEY (ID_Usuario) REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS consulta_ayuda (         -- RF-36 + botón de arrepentimiento
  ID_Consulta  INT(11)       NOT NULL AUTO_INCREMENT,
  ID_Usuario   INT(11)       NULL DEFAULT NULL,
  ID_Pedido    INT(11)       NULL DEFAULT NULL,
  Tipo         ENUM('Consulta','Reclamo','Arrepentimiento') NOT NULL DEFAULT 'Consulta',
  Nombre       VARCHAR(100)  NOT NULL,
  Email        VARCHAR(120)  NOT NULL,
  Mensaje      VARCHAR(1000) NOT NULL,
  Fecha        DATETIME      NOT NULL,
  Estado       ENUM('Pendiente','Atendida') NOT NULL DEFAULT 'Pendiente',
  PRIMARY KEY (ID_Consulta),
  KEY idx_consulta_usuario (ID_Usuario),
  KEY idx_consulta_pedido  (ID_Pedido),
  CONSTRAINT fk_consulta_usuario FOREIGN KEY (ID_Usuario) REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_consulta_pedido  FOREIGN KEY (ID_Pedido)  REFERENCES pedido(ID_Pedido)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS registro_admin (         -- G.5: auditoría (quién, qué y cuándo)
  ID_Registro INT(11)      NOT NULL AUTO_INCREMENT,
  ID_Admin    INT(11)      NULL DEFAULT NULL,         -- NULL = acción automática del sistema
  Accion      VARCHAR(60)  NOT NULL,
  Entidad     VARCHAR(30)  NOT NULL,
  ID_Entidad  INT(11)      NULL DEFAULT NULL,
  Detalle     VARCHAR(255) NULL DEFAULT NULL,
  Fecha       DATETIME     NOT NULL,
  PRIMARY KEY (ID_Registro),
  KEY idx_registro_admin (ID_Admin),
  KEY idx_registro_fecha (Fecha),
  CONSTRAINT fk_registro_admin FOREIGN KEY (ID_Admin) REFERENCES usuario(ID_Usuario) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
