-- =====================================================================
--  ROPIFY - Base de datos 
--  Motor: MariaDB 10.x / MySQL (XAMPP) - InnoDB - utf8mb4
--  Importar desde phpMyAdmin (pestaña "Importar") o con:  mysql -u root < 01_ropify_estructura.sql
-- =====================================================================
CREATE DATABASE IF NOT EXISTS ropify
  DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_general_ci;
USE ropify;

CREATE TABLE usuario (
  ID_Usuario       INT(11)       NOT NULL,
  Nombre           VARCHAR(60)   NOT NULL,
  Apellido         VARCHAR(60)   NOT NULL,
  DNI              INT(8)        NOT NULL,
  Dni_Verificado   TINYINT(1)    NOT NULL,
  Email            VARCHAR(120)  NOT NULL,
  Contrasena_hash  VARCHAR(255)  NOT NULL,
  Telefono         INT(20)       NOT NULL,
  AliasPublico     VARCHAR(50)   NOT NULL,
  Biografia        VARCHAR(300)  NOT NULL,
  Fecha_Registro   DATETIME      NOT NULL,
  PRIMARY KEY (ID_Usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE categoria (
  ID_Categoria  INT(11)       NOT NULL AUTO_INCREMENT,
  Nombre        VARCHAR(50)   NOT NULL,
  Descripcion   VARCHAR(240)  NOT NULL,
  PRIMARY KEY (ID_Categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE direccion (
  ID_Direccion   INT(11)      NOT NULL AUTO_INCREMENT,
  ID_Usuario     INT(11)      NOT NULL,
  Calle          VARCHAR(100) NOT NULL,
  Numero         VARCHAR(10)  NOT NULL,
  Piso_depto     VARCHAR(20)  NOT NULL,
  Localidad      VARCHAR(80)  NOT NULL,
  Provincia      VARCHAR(60)  NOT NULL,
  Codigo_Postal  VARCHAR(10)  NOT NULL,
  PRIMARY KEY (ID_Direccion),
  KEY ID_Usuario (ID_Usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE prenda (
  ID_Prenda            INT(11)       NOT NULL AUTO_INCREMENT,
  ID_Vendedor          INT(11)       NOT NULL,
  ID_Categoria         INT(11)       NOT NULL,
  Titulo               VARCHAR(100)  DEFAULT NULL,
  Descripcion          TEXT          NOT NULL,
  Talle                VARCHAR(10)   NOT NULL,
  Color                VARCHAR(30)   NOT NULL,
  Material             VARCHAR(60)   NOT NULL,
  Estado_Conversacion  ENUM('Excelente','Muy bueno','Bueno','Decente') NOT NULL,
  Precio               DECIMAL(10,2) NOT NULL,
  Estado               ENUM('Pendiente','Aprobada','Rechazada','Vendida','Pausada') NOT NULL,
  FechaPublicacion     DATETIME      NOT NULL,
  PRIMARY KEY (ID_Prenda),
  KEY ID_Vendedor (ID_Vendedor),
  KEY ID_Categoria (ID_Categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE foto_prenda (
  ID_Foto    INT(11)      NOT NULL AUTO_INCREMENT,
  ID_Prenda  INT(11)      NOT NULL,
  URL        VARCHAR(255) NOT NULL,
  Orden      TINYINT(4)   NOT NULL,
  PRIMARY KEY (ID_Foto),
  KEY ID_Prenda (ID_Prenda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE pedido (
  ID_Pedido     INT(11)       NOT NULL AUTO_INCREMENT,
  ID_Comprador  INT(11)       NOT NULL,
  ID_Direccion  INT(11)       NOT NULL,
  Fecha_pedido  DATETIME      NOT NULL,
  Subtotal      DECIMAL(10,2) NOT NULL,
  Costo_Envio   DECIMAL(10,2) NOT NULL,
  Total         DECIMAL(10,2) NOT NULL,
  Metodo_Pago   VARCHAR(30)   NOT NULL,
  Estado        ENUM('Pendiente_Pago','Pagado','Enviado','Entregado','Cancelado') NOT NULL,
  PRIMARY KEY (ID_Pedido),
  KEY ID_Comprador (ID_Comprador),
  KEY ID_Direccion (ID_Direccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE detalle_pedido (
  ID_Pedido       INT(11)       NOT NULL,
  ID_Prenda       INT(11)       NOT NULL,
  Precio_Venta    DECIMAL(10,2) NOT NULL,
  Comision_Chuko  DECIMAL(10,2) NOT NULL,
  Monto_Vendedor  DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (ID_Pedido, ID_Prenda),
  KEY ID_Prenda (ID_Prenda),
  KEY ID_Pedido (ID_Pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
