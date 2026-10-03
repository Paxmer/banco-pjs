-- =====================================================================
--  BANCO PJS - BANCA EN LÍNEA  |  Desarrollo Web  |  HTML5/CSS3/PHP/MySQL
--  Script 01: ESQUEMA + STORED PROCEDURES + ADMINISTRADOR INICIAL
--
--  Este script deja la BD "EN BLANCO" (sin cuentas, cajeros ni clientes),
--  con el único usuario administrador necesario para evaluar el sistema:
--      usuario: admin      clave: Admin2026!
--
--  USO LOCAL (WampServer):
--      1. Ejecutar 00_crear_base_y_usuario.sql  (crea la BD y el usuario de la app)
--      2. Importar ESTE archivo seleccionando la BD "banca_umg"
--  USO EN HOSTING: crear la BD desde el panel, seleccionarla en phpMyAdmin e
--      importar este archivo (phpMyAdmin soporta la directiva DELIMITER).
--
--  ¡ATENCIÓN! Este script hace DROP de las tablas/procedimientos del sistema
--  antes de crearlos: úselo sólo para instalar desde cero.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-06:00';   -- Guatemala (la aplicación usa la misma zona en cada conexión)
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS transferencias;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS cuentas_terceros;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS cuentas;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  TABLAS
-- ---------------------------------------------------------------------

-- Usuarios del sistema (los tres roles en una sola tabla). El cajero y el
-- administrador no tienen cuenta bancaria; el cliente SIEMPRE tiene una y
-- cada cuenta admite como máximo un usuario cliente (UNIQUE id_cuenta).
CREATE TABLE usuarios (
    id_usuario      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rol             ENUM('ADMIN','CAJERO','CLIENTE') NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    username        VARCHAR(100) NOT NULL,            -- cliente: correo electrónico
    password_hash   VARCHAR(255) NOT NULL,            -- password_hash() de PHP (bcrypt)
    bloqueado       TINYINT(1)   NOT NULL DEFAULT 0,
    id_cuenta       INT UNSIGNED NULL,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuarios_username (username),
    UNIQUE KEY uq_usuarios_cuenta (id_cuenta),
    KEY ix_usuarios_rol_fecha (rol, fecha_creacion),
    CONSTRAINT ck_usuarios_cuenta_rol CHECK (
        (rol = 'CLIENTE' AND id_cuenta IS NOT NULL) OR
        (rol <> 'CLIENTE' AND id_cuenta IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas bancarias (las crea un cajero). El saldo nunca puede ser negativo.
CREATE TABLE cuentas (
    id_cuenta      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_cuenta  VARCHAR(20)  NOT NULL,
    nombre_cuenta  VARCHAR(100) NOT NULL,
    dpi            CHAR(13)     NOT NULL,
    saldo          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    id_cajero      INT UNSIGNED NOT NULL,             -- cajero que la creó
    fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cuenta),
    UNIQUE KEY uq_cuentas_numero (numero_cuenta),
    KEY ix_cuentas_fecha (fecha_creacion),
    CONSTRAINT ck_cuentas_saldo CHECK (saldo >= 0),
    CONSTRAINT fk_cuentas_cajero FOREIGN KEY (id_cajero) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_cuenta FOREIGN KEY (id_cuenta) REFERENCES cuentas (id_cuenta);

-- Cuentas de terceros: pertenecen ÚNICAMENTE al usuario que las registra
-- (id_usuario). Un mismo usuario no puede repetir cuenta ni alias.
CREATE TABLE cuentas_terceros (
    id_tercero                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario                 INT UNSIGNED NOT NULL,
    id_cuenta_destino          INT UNSIGNED NOT NULL,
    alias                      VARCHAR(50)  NOT NULL,
    monto_maximo               DECIMAL(15,2) NOT NULL,
    max_transacciones_diarias  SMALLINT UNSIGNED NOT NULL,
    fecha_creacion             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_tercero),
    UNIQUE KEY uq_terceros_usuario_cuenta (id_usuario, id_cuenta_destino),
    UNIQUE KEY uq_terceros_usuario_alias (id_usuario, alias),
    CONSTRAINT ck_terceros_monto CHECK (monto_maximo > 0),
    CONSTRAINT ck_terceros_max_tx CHECK (max_transacciones_diarias >= 1),
    CONSTRAINT fk_terceros_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_terceros_cuenta FOREIGN KEY (id_cuenta_destino) REFERENCES cuentas (id_cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transferencias realizadas (cabecera de la operación; sólo existen las exitosas).
CREATE TABLE transferencias (
    id_transferencia  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cuenta_origen  INT UNSIGNED NOT NULL,
    id_cuenta_destino INT UNSIGNED NOT NULL,
    id_tercero        INT UNSIGNED NOT NULL,
    monto             DECIMAL(15,2) NOT NULL,
    fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_transferencia),
    KEY ix_transf_tercero_fecha (id_tercero, fecha),
    KEY ix_transf_fecha (fecha),
    CONSTRAINT ck_transf_monto CHECK (monto > 0),
    CONSTRAINT ck_transf_cuentas CHECK (id_cuenta_origen <> id_cuenta_destino),
    CONSTRAINT fk_transf_origen FOREIGN KEY (id_cuenta_origen) REFERENCES cuentas (id_cuenta),
    CONSTRAINT fk_transf_destino FOREIGN KEY (id_cuenta_destino) REFERENCES cuentas (id_cuenta),
    CONSTRAINT fk_transf_tercero FOREIGN KEY (id_tercero) REFERENCES cuentas_terceros (id_tercero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Libro de movimientos: cada operación deja una línea por cuenta afectada.
-- (Una transferencia genera 2 líneas: débito en origen y crédito en destino.)
CREATE TABLE movimientos (
    id_movimiento    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cuenta        INT UNSIGNED NOT NULL,
    tipo             ENUM('APERTURA','DEPOSITO','RETIRO','TRANSFERENCIA_ENVIADA','TRANSFERENCIA_RECIBIDA') NOT NULL,
    naturaleza       ENUM('CREDITO','DEBITO') NOT NULL,
    monto            DECIMAL(15,2) NOT NULL,
    saldo_resultante DECIMAL(15,2) NOT NULL,
    id_transferencia INT UNSIGNED NULL,
    id_cajero        INT UNSIGNED NULL,
    descripcion      VARCHAR(255) NOT NULL,
    fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_movimiento),
    KEY ix_mov_cuenta_fecha (id_cuenta, fecha),
    KEY ix_mov_tipo_fecha (tipo, fecha),
    CONSTRAINT ck_mov_monto CHECK (monto > 0),
    CONSTRAINT fk_mov_cuenta FOREIGN KEY (id_cuenta) REFERENCES cuentas (id_cuenta),
    CONSTRAINT fk_mov_transf FOREIGN KEY (id_transferencia) REFERENCES transferencias (id_transferencia),
    CONSTRAINT fk_mov_cajero FOREIGN KEY (id_cajero) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  STORED PROCEDURES
--  Convención: los procedimientos de operación devuelven UNA fila con
--      codigo  (0 = éxito, >0 = error de negocio, 99 = error técnico)
--      mensaje (texto para el usuario)  [+ columnas adicionales en éxito]
--  En el código 99 se añade la columna "detalle" (sólo para el log del
--  servidor; PHP nunca la muestra al usuario).
--  Monto máximo por operación: 999,999,999.99
-- ---------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_login_obtener;
DROP PROCEDURE IF EXISTS sp_sesion_validar;
DROP PROCEDURE IF EXISTS sp_registrar_usuario;
DROP PROCEDURE IF EXISTS sp_cajero_listar;
DROP PROCEDURE IF EXISTS sp_cajero_crear;
DROP PROCEDURE IF EXISTS sp_cajero_cambiar_estado;
DROP PROCEDURE IF EXISTS sp_monitor_estadisticas;
DROP PROCEDURE IF EXISTS sp_cuenta_crear;
DROP PROCEDURE IF EXISTS sp_deposito;
DROP PROCEDURE IF EXISTS sp_retiro;
DROP PROCEDURE IF EXISTS sp_cliente_resumen;
DROP PROCEDURE IF EXISTS sp_tercero_crear;
DROP PROCEDURE IF EXISTS sp_tercero_listar;
DROP PROCEDURE IF EXISTS sp_transferir;
DROP PROCEDURE IF EXISTS sp_estado_cuenta;

DELIMITER $$

-- ---------- AUTENTICACIÓN ----------

-- Devuelve el usuario (con su hash) para que PHP ejecute password_verify().
CREATE PROCEDURE sp_login_obtener(IN p_rol VARCHAR(10), IN p_username VARCHAR(100))
BEGIN
    SELECT id_usuario, rol, nombre_completo, username, password_hash, bloqueado, id_cuenta
    FROM usuarios
    WHERE rol = p_rol AND username = p_username
    LIMIT 1;
END$$

-- Se invoca en cada petición protegida: confirma que la sesión sigue siendo
-- válida (el usuario existe, tiene ese rol y no fue bloqueado).
CREATE PROCEDURE sp_sesion_validar(IN p_id_usuario INT UNSIGNED, IN p_rol VARCHAR(10))
BEGIN
    SELECT id_usuario, rol, nombre_completo, username, bloqueado, id_cuenta
    FROM usuarios
    WHERE id_usuario = p_id_usuario AND rol = p_rol AND bloqueado = 0;
END$$

-- Registro de un nuevo usuario cliente sobre una cuenta ya creada por un cajero.
CREATE PROCEDURE sp_registrar_usuario(
    IN p_numero_cuenta VARCHAR(20), IN p_correo VARCHAR(100),
    IN p_dpi VARCHAR(13), IN p_password_hash VARCHAR(255))
main: BEGIN
    DECLARE v_id_cuenta INT UNSIGNED DEFAULT NULL;
    DECLARE v_dpi CHAR(13) DEFAULT NULL;
    DECLARE v_nombre VARCHAR(100) DEFAULT NULL;
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR 1062 BEGIN
        ROLLBACK;
        SELECT 4 AS codigo, 'Ya existe un usuario registrado con ese correo o para esa cuenta.' AS mensaje;
    END;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF p_numero_cuenta IS NULL OR p_numero_cuenta NOT REGEXP '^[0-9]{4,20}$' THEN
        SELECT 5 AS codigo, 'El número de cuenta no es válido.' AS mensaje; LEAVE main;
    END IF;
    IF p_correo IS NULL OR CHAR_LENGTH(p_correo) > 100
       OR p_correo NOT REGEXP '^[^@ ]+@[^@ ]+[.][^@ ]+$' THEN
        SELECT 5 AS codigo, 'El correo electrónico no es válido.' AS mensaje; LEAVE main;
    END IF;
    IF p_dpi IS NULL OR p_dpi NOT REGEXP '^[0-9]{13}$' THEN
        SELECT 5 AS codigo, 'El DPI debe tener exactamente 13 dígitos.' AS mensaje; LEAVE main;
    END IF;
    IF p_password_hash IS NULL OR CHAR_LENGTH(p_password_hash) < 20 THEN
        SELECT 5 AS codigo, 'La contraseña no es válida.' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    -- Se bloquea la fila de la cuenta para evitar dos registros simultáneos.
    SELECT id_cuenta, dpi, nombre_cuenta INTO v_id_cuenta, v_dpi, v_nombre
    FROM cuentas WHERE numero_cuenta = p_numero_cuenta FOR UPDATE;

    IF v_id_cuenta IS NULL THEN
        ROLLBACK;
        SELECT 1 AS codigo, 'La cuenta bancaria no existe. Debe crearla primero en una agencia con un cajero.' AS mensaje;
        LEAVE main;
    END IF;
    IF v_dpi <> p_dpi THEN
        ROLLBACK;
        SELECT 2 AS codigo, 'El DPI no coincide con el registrado en la cuenta.' AS mensaje;
        LEAVE main;
    END IF;
    IF EXISTS (SELECT 1 FROM usuarios WHERE id_cuenta = v_id_cuenta) THEN
        ROLLBACK;
        SELECT 3 AS codigo, 'Esta cuenta ya tiene un usuario registrado.' AS mensaje;
        LEAVE main;
    END IF;
    IF EXISTS (SELECT 1 FROM usuarios WHERE username = p_correo) THEN
        ROLLBACK;
        SELECT 4 AS codigo, 'Ya existe un usuario registrado con ese correo electrónico.' AS mensaje;
        LEAVE main;
    END IF;

    INSERT INTO usuarios (rol, nombre_completo, username, password_hash, id_cuenta)
    VALUES ('CLIENTE', v_nombre, p_correo, p_password_hash, v_id_cuenta);
    COMMIT;
    SELECT 0 AS codigo, 'Usuario registrado correctamente. Ya puede iniciar sesión.' AS mensaje;
END$$

-- ---------- ADMINISTRADOR: CAJEROS ----------

CREATE PROCEDURE sp_cajero_listar()
BEGIN
    SELECT id_usuario, nombre_completo, username, bloqueado, fecha_creacion
    FROM usuarios
    WHERE rol = 'CAJERO'
    ORDER BY fecha_creacion DESC, id_usuario DESC;
END$$

CREATE PROCEDURE sp_cajero_crear(
    IN p_nombre VARCHAR(100), IN p_username VARCHAR(100), IN p_password_hash VARCHAR(255))
main: BEGIN
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR 1062 BEGIN
        ROLLBACK;
        SELECT 3 AS codigo, 'Ese nombre de usuario ya existe.' AS mensaje;
    END;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF p_nombre IS NULL OR CHAR_LENGTH(TRIM(p_nombre)) < 3 OR CHAR_LENGTH(p_nombre) > 100 THEN
        SELECT 1 AS codigo, 'El nombre completo debe tener entre 3 y 100 caracteres.' AS mensaje; LEAVE main;
    END IF;
    IF p_username IS NULL OR p_username NOT REGEXP '^[A-Za-z0-9_.-]{3,30}$' THEN
        SELECT 2 AS codigo, 'El usuario debe tener de 3 a 30 caracteres (letras, números, punto, guion o guion bajo).' AS mensaje; LEAVE main;
    END IF;
    IF p_password_hash IS NULL OR CHAR_LENGTH(p_password_hash) < 20 THEN
        SELECT 4 AS codigo, 'La clave no es válida.' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    IF EXISTS (SELECT 1 FROM usuarios WHERE username = p_username) THEN
        ROLLBACK;
        SELECT 3 AS codigo, 'Ese nombre de usuario ya existe.' AS mensaje;
        LEAVE main;
    END IF;
    INSERT INTO usuarios (rol, nombre_completo, username, password_hash)
    VALUES ('CAJERO', TRIM(p_nombre), p_username, p_password_hash);
    COMMIT;
    SELECT 0 AS codigo, 'Cajero creado correctamente.' AS mensaje;
END$$

-- Bloquea (1) o desbloquea (0). Sólo actúa sobre usuarios con rol CAJERO.
CREATE PROCEDURE sp_cajero_cambiar_estado(IN p_id_usuario INT UNSIGNED, IN p_bloqueado TINYINT)
main: BEGIN
    DECLARE v_err TEXT DEFAULT '';
    DECLARE v_existe INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF p_bloqueado IS NULL OR p_bloqueado NOT IN (0, 1) THEN
        SELECT 2 AS codigo, 'Estado no válido.' AS mensaje; LEAVE main;
    END IF;
    START TRANSACTION;
    SELECT COUNT(*) INTO v_existe FROM usuarios WHERE id_usuario = p_id_usuario AND rol = 'CAJERO' FOR UPDATE;
    IF v_existe = 0 THEN
        ROLLBACK;
        SELECT 1 AS codigo, 'El cajero no existe.' AS mensaje; LEAVE main;
    END IF;
    UPDATE usuarios SET bloqueado = p_bloqueado WHERE id_usuario = p_id_usuario AND rol = 'CAJERO';
    COMMIT;
    SELECT 0 AS codigo,
           IF(p_bloqueado = 1, 'Cajero bloqueado correctamente.', 'Cajero desbloqueado correctamente.') AS mensaje;
END$$

-- ---------- ADMINISTRADOR: MONITOR ----------
-- p_periodo = 'HOY' (desde las 00:00 del día actual) o 'TOTAL' (histórico).
-- Los usuarios con rol CAJERO/ADMIN NO se cuentan como usuarios cliente.
CREATE PROCEDURE sp_monitor_estadisticas(IN p_periodo VARCHAR(10))
BEGIN
    DECLARE v_desde DATETIME;
    IF p_periodo = 'TOTAL' THEN
        SET v_desde = '1000-01-01 00:00:00';
    ELSE
        SET v_desde = CAST(CURDATE() AS DATETIME);
    END IF;

    SELECT
        (SELECT COUNT(*) FROM cuentas WHERE fecha_creacion >= v_desde)                       AS cuentas_creadas,
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'CLIENTE' AND fecha_creacion >= v_desde)  AS usuarios_cliente,
        (SELECT COUNT(*) FROM movimientos WHERE tipo = 'DEPOSITO' AND fecha >= v_desde)
        + (SELECT COUNT(*) FROM movimientos WHERE tipo = 'RETIRO' AND fecha >= v_desde)
        + (SELECT COUNT(*) FROM transferencias WHERE fecha >= v_desde)                       AS transacciones,
        (SELECT COUNT(*) FROM movimientos WHERE tipo = 'DEPOSITO' AND fecha >= v_desde)      AS depositos,
        (SELECT COUNT(*) FROM movimientos WHERE tipo = 'RETIRO' AND fecha >= v_desde)        AS retiros,
        (SELECT COUNT(*) FROM transferencias WHERE fecha >= v_desde)                         AS transferencias,
        (SELECT COALESCE(SUM(monto), 0) FROM movimientos WHERE tipo = 'DEPOSITO' AND fecha >= v_desde) AS monto_depositos,
        (SELECT COALESCE(SUM(monto), 0) FROM movimientos WHERE tipo = 'RETIRO' AND fecha >= v_desde)   AS monto_retiros;
END$$

-- ---------- CAJERO ----------

CREATE PROCEDURE sp_cuenta_crear(
    IN p_id_cajero INT UNSIGNED, IN p_nombre VARCHAR(100), IN p_numero VARCHAR(20),
    IN p_dpi VARCHAR(13), IN p_monto_inicial DECIMAL(15,2))
main: BEGIN
    DECLARE v_id_cuenta INT UNSIGNED;
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR 1062 BEGIN
        ROLLBACK;
        SELECT 3 AS codigo, 'Ya existe una cuenta con ese número.' AS mensaje;
    END;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_cajero AND rol = 'CAJERO' AND bloqueado = 0) THEN
        SELECT 6 AS codigo, 'El cajero no está autorizado para esta operación.' AS mensaje; LEAVE main;
    END IF;
    IF p_nombre IS NULL OR CHAR_LENGTH(TRIM(p_nombre)) < 3 OR CHAR_LENGTH(p_nombre) > 100 THEN
        SELECT 1 AS codigo, 'El nombre de la cuenta debe tener entre 3 y 100 caracteres.' AS mensaje; LEAVE main;
    END IF;
    IF p_numero IS NULL OR p_numero NOT REGEXP '^[0-9]{4,20}$' THEN
        SELECT 2 AS codigo, 'El número de cuenta debe tener de 4 a 20 dígitos.' AS mensaje; LEAVE main;
    END IF;
    IF p_dpi IS NULL OR p_dpi NOT REGEXP '^[0-9]{13}$' THEN
        SELECT 4 AS codigo, 'El DPI debe tener exactamente 13 dígitos.' AS mensaje; LEAVE main;
    END IF;
    IF p_monto_inicial IS NULL OR p_monto_inicial < 0 OR p_monto_inicial > 999999999.99 THEN
        SELECT 5 AS codigo, 'El monto inicial no es válido (debe ser 0 o mayor).' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    IF EXISTS (SELECT 1 FROM cuentas WHERE numero_cuenta = p_numero) THEN
        ROLLBACK;
        SELECT 3 AS codigo, 'Ya existe una cuenta con ese número.' AS mensaje; LEAVE main;
    END IF;
    INSERT INTO cuentas (numero_cuenta, nombre_cuenta, dpi, saldo, id_cajero)
    VALUES (p_numero, TRIM(p_nombre), p_dpi, p_monto_inicial, p_id_cajero);
    SET v_id_cuenta = LAST_INSERT_ID();
    IF p_monto_inicial > 0 THEN
        INSERT INTO movimientos (id_cuenta, tipo, naturaleza, monto, saldo_resultante, id_cajero, descripcion)
        VALUES (v_id_cuenta, 'APERTURA', 'CREDITO', p_monto_inicial, p_monto_inicial, p_id_cajero,
                'Apertura de cuenta (monto inicial)');
    END IF;
    COMMIT;
    SELECT 0 AS codigo, 'Cuenta creada correctamente.' AS mensaje,
           p_numero AS numero_cuenta, p_monto_inicial AS saldo;
END$$

CREATE PROCEDURE sp_deposito(
    IN p_id_cajero INT UNSIGNED, IN p_numero VARCHAR(20), IN p_monto DECIMAL(15,2))
main: BEGIN
    DECLARE v_id_cuenta INT UNSIGNED DEFAULT NULL;
    DECLARE v_saldo DECIMAL(15,2);
    DECLARE v_nombre VARCHAR(100);
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_cajero AND rol = 'CAJERO' AND bloqueado = 0) THEN
        SELECT 6 AS codigo, 'El cajero no está autorizado para esta operación.' AS mensaje; LEAVE main;
    END IF;
    IF p_numero IS NULL OR p_numero NOT REGEXP '^[0-9]{4,20}$' THEN
        SELECT 2 AS codigo, 'El número de cuenta no es válido.' AS mensaje; LEAVE main;
    END IF;
    IF p_monto IS NULL OR p_monto <= 0 OR p_monto > 999999999.99 THEN
        SELECT 3 AS codigo, 'El monto debe ser mayor que cero.' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    SELECT id_cuenta, saldo, nombre_cuenta INTO v_id_cuenta, v_saldo, v_nombre
    FROM cuentas WHERE numero_cuenta = p_numero FOR UPDATE;
    IF v_id_cuenta IS NULL THEN
        ROLLBACK;
        SELECT 1 AS codigo, 'La cuenta no existe.' AS mensaje; LEAVE main;
    END IF;

    SET v_saldo = v_saldo + p_monto;
    UPDATE cuentas SET saldo = v_saldo WHERE id_cuenta = v_id_cuenta;
    INSERT INTO movimientos (id_cuenta, tipo, naturaleza, monto, saldo_resultante, id_cajero, descripcion)
    VALUES (v_id_cuenta, 'DEPOSITO', 'CREDITO', p_monto, v_saldo, p_id_cajero, 'Depósito en ventanilla');
    COMMIT;
    SELECT 0 AS codigo, 'Depósito realizado correctamente.' AS mensaje,
           p_numero AS numero_cuenta, v_nombre AS nombre_cuenta, v_saldo AS saldo_actual;
END$$

CREATE PROCEDURE sp_retiro(
    IN p_id_cajero INT UNSIGNED, IN p_numero VARCHAR(20), IN p_monto DECIMAL(15,2))
main: BEGIN
    DECLARE v_id_cuenta INT UNSIGNED DEFAULT NULL;
    DECLARE v_saldo DECIMAL(15,2);
    DECLARE v_nombre VARCHAR(100);
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_cajero AND rol = 'CAJERO' AND bloqueado = 0) THEN
        SELECT 6 AS codigo, 'El cajero no está autorizado para esta operación.' AS mensaje; LEAVE main;
    END IF;
    IF p_numero IS NULL OR p_numero NOT REGEXP '^[0-9]{4,20}$' THEN
        SELECT 2 AS codigo, 'El número de cuenta no es válido.' AS mensaje; LEAVE main;
    END IF;
    IF p_monto IS NULL OR p_monto <= 0 OR p_monto > 999999999.99 THEN
        SELECT 3 AS codigo, 'El monto debe ser mayor que cero.' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    SELECT id_cuenta, saldo, nombre_cuenta INTO v_id_cuenta, v_saldo, v_nombre
    FROM cuentas WHERE numero_cuenta = p_numero FOR UPDATE;
    IF v_id_cuenta IS NULL THEN
        ROLLBACK;
        SELECT 1 AS codigo, 'La cuenta no existe.' AS mensaje; LEAVE main;
    END IF;
    IF v_saldo < p_monto THEN
        ROLLBACK;
        SELECT 4 AS codigo, 'Saldo insuficiente para realizar el retiro.' AS mensaje; LEAVE main;
    END IF;

    SET v_saldo = v_saldo - p_monto;
    UPDATE cuentas SET saldo = v_saldo WHERE id_cuenta = v_id_cuenta;
    INSERT INTO movimientos (id_cuenta, tipo, naturaleza, monto, saldo_resultante, id_cajero, descripcion)
    VALUES (v_id_cuenta, 'RETIRO', 'DEBITO', p_monto, v_saldo, p_id_cajero, 'Retiro en ventanilla');
    COMMIT;
    SELECT 0 AS codigo, 'Retiro realizado correctamente.' AS mensaje,
           p_numero AS numero_cuenta, v_nombre AS nombre_cuenta, v_saldo AS saldo_actual;
END$$

-- ---------- CLIENTE ----------

-- Datos de la cuenta del usuario autenticado (el id sale de la sesión).
CREATE PROCEDURE sp_cliente_resumen(IN p_id_usuario INT UNSIGNED)
BEGIN
    SELECT c.numero_cuenta, c.nombre_cuenta, c.saldo, u.username
    FROM usuarios u
    JOIN cuentas c ON c.id_cuenta = u.id_cuenta
    WHERE u.id_usuario = p_id_usuario AND u.rol = 'CLIENTE';
END$$

CREATE PROCEDURE sp_tercero_crear(
    IN p_id_usuario INT UNSIGNED, IN p_numero VARCHAR(20), IN p_monto_maximo DECIMAL(15,2),
    IN p_max_tx INT, IN p_alias VARCHAR(50))
main: BEGIN
    DECLARE v_cuenta_propia INT UNSIGNED DEFAULT NULL;
    DECLARE v_destino INT UNSIGNED DEFAULT NULL;
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR 1062 BEGIN
        ROLLBACK;
        SELECT 4 AS codigo, 'Ya registró esa cuenta o ese alias en su lista de terceros.' AS mensaje;
    END;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'Ocurrió un error inesperado. Intente de nuevo.' AS mensaje, v_err AS detalle;
    END;

    SELECT id_cuenta INTO v_cuenta_propia FROM usuarios
    WHERE id_usuario = p_id_usuario AND rol = 'CLIENTE' AND bloqueado = 0;
    IF v_cuenta_propia IS NULL THEN
        SELECT 7 AS codigo, 'Usuario no autorizado.' AS mensaje; LEAVE main;
    END IF;
    IF p_numero IS NULL OR p_numero NOT REGEXP '^[0-9]{4,20}$' THEN
        SELECT 5 AS codigo, 'El número de cuenta no es válido.' AS mensaje; LEAVE main;
    END IF;
    IF p_monto_maximo IS NULL OR p_monto_maximo <= 0 OR p_monto_maximo > 999999999.99 THEN
        SELECT 5 AS codigo, 'El monto máximo debe ser mayor que cero.' AS mensaje; LEAVE main;
    END IF;
    IF p_max_tx IS NULL OR p_max_tx < 1 OR p_max_tx > 1000 THEN
        SELECT 5 AS codigo, 'La cantidad máxima de transacciones diarias debe estar entre 1 y 1000.' AS mensaje; LEAVE main;
    END IF;
    IF p_alias IS NULL OR CHAR_LENGTH(TRIM(p_alias)) < 2 OR CHAR_LENGTH(p_alias) > 50 THEN
        SELECT 5 AS codigo, 'El alias debe tener entre 2 y 50 caracteres.' AS mensaje; LEAVE main;
    END IF;

    SELECT id_cuenta INTO v_destino FROM cuentas WHERE numero_cuenta = p_numero;
    IF v_destino IS NULL THEN
        SELECT 1 AS codigo, 'La cuenta bancaria no existe.' AS mensaje; LEAVE main;
    END IF;
    IF v_destino = v_cuenta_propia THEN
        SELECT 2 AS codigo, 'No puede agregar su propia cuenta como cuenta de tercero.' AS mensaje; LEAVE main;
    END IF;

    START TRANSACTION;
    IF EXISTS (SELECT 1 FROM cuentas_terceros
               WHERE id_usuario = p_id_usuario AND (id_cuenta_destino = v_destino OR alias = TRIM(p_alias))) THEN
        ROLLBACK;
        SELECT 4 AS codigo, 'Ya registró esa cuenta o ese alias en su lista de terceros.' AS mensaje; LEAVE main;
    END IF;
    INSERT INTO cuentas_terceros (id_usuario, id_cuenta_destino, alias, monto_maximo, max_transacciones_diarias)
    VALUES (p_id_usuario, v_destino, TRIM(p_alias), p_monto_maximo, p_max_tx);
    COMMIT;
    SELECT 0 AS codigo, 'Cuenta de tercero agregada correctamente.' AS mensaje;
END$$

-- Sólo devuelve los terceros del usuario recibido (p_id_usuario sale de la sesión).
CREATE PROCEDURE sp_tercero_listar(IN p_id_usuario INT UNSIGNED)
BEGIN
    SELECT t.id_tercero, t.alias, c.numero_cuenta, c.nombre_cuenta,
           t.monto_maximo, t.max_transacciones_diarias,
           (SELECT COUNT(*) FROM transferencias tr
             WHERE tr.id_tercero = t.id_tercero
               AND tr.fecha >= CAST(CURDATE() AS DATETIME)) AS usadas_hoy
    FROM cuentas_terceros t
    JOIN cuentas c ON c.id_cuenta = t.id_cuenta_destino
    WHERE t.id_usuario = p_id_usuario
    ORDER BY t.alias;
END$$

-- TRANSFERENCIA A TERCERO - operación atómica.
-- Débito origen + crédito destino + registro de transferencia + 2 movimientos
-- se ejecutan en UNA transacción: o se aplican todos (COMMIT) o ninguno (ROLLBACK).
-- La cuenta origen se obtiene del usuario autenticado y el tercero se valida
-- contra ese mismo usuario (propiedad), por lo que un id manipulado no sirve.
CREATE PROCEDURE sp_transferir(
    IN p_id_usuario INT UNSIGNED, IN p_id_tercero INT UNSIGNED, IN p_monto DECIMAL(15,2))
main: BEGIN
    DECLARE v_origen INT UNSIGNED DEFAULT NULL;
    DECLARE v_destino INT UNSIGNED DEFAULT NULL;
    DECLARE v_monto_max DECIMAL(15,2);
    DECLARE v_max_tx SMALLINT UNSIGNED;
    DECLARE v_alias VARCHAR(50);
    DECLARE v_num_destino VARCHAR(20);
    DECLARE v_nom_destino VARCHAR(100);
    DECLARE v_num_origen VARCHAR(20);
    DECLARE v_nom_origen VARCHAR(100);
    DECLARE v_saldo_origen DECIMAL(15,2);
    DECLARE v_saldo_destino DECIMAL(15,2);
    DECLARE v_usadas INT DEFAULT 0;
    DECLARE v_bloqueadas INT DEFAULT 0;
    DECLARE v_id_transf INT UNSIGNED;
    DECLARE v_err TEXT DEFAULT '';
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
        GET DIAGNOSTICS CONDITION 1 v_err = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 99 AS codigo, 'La transferencia falló y no se aplicó ningún cambio. Intente de nuevo.' AS mensaje,
               v_err AS detalle;
    END;

    IF p_monto IS NULL OR p_monto <= 0 OR p_monto > 999999999.99 THEN
        SELECT 3 AS codigo, 'El monto debe ser mayor que cero.' AS mensaje; LEAVE main;
    END IF;

    -- READ COMMITTED: cada lectura ve el último dato confirmado. Con el nivel por defecto
    -- (REPEATABLE READ) una transferencia concurrente podría leer un saldo anterior a obtener
    -- el bloqueo y perder una actualización. Además los saldos se leen con FOR UPDATE.
    SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
    START TRANSACTION;

    -- Cuenta origen = cuenta del usuario autenticado (nunca viene del navegador).
    SELECT id_cuenta INTO v_origen FROM usuarios
    WHERE id_usuario = p_id_usuario AND rol = 'CLIENTE' AND bloqueado = 0;
    IF v_origen IS NULL THEN
        ROLLBACK;
        SELECT 7 AS codigo, 'Usuario no autorizado.' AS mensaje; LEAVE main;
    END IF;

    -- Propiedad del tercero: debe pertenecer a ESTE usuario.
    SELECT id_cuenta_destino, monto_maximo, max_transacciones_diarias, alias
      INTO v_destino, v_monto_max, v_max_tx, v_alias
    FROM cuentas_terceros
    WHERE id_tercero = p_id_tercero AND id_usuario = p_id_usuario;
    IF v_destino IS NULL THEN
        ROLLBACK;
        SELECT 1 AS codigo, 'La cuenta de tercero seleccionada no existe o no le pertenece.' AS mensaje; LEAVE main;
    END IF;
    IF v_destino = v_origen THEN
        ROLLBACK;
        SELECT 2 AS codigo, 'No puede transferir a su propia cuenta.' AS mensaje; LEAVE main;
    END IF;

    -- Bloqueo de ambas cuentas siempre en el mismo orden (evita deadlocks).
    SELECT COUNT(*) INTO v_bloqueadas FROM cuentas WHERE id_cuenta IN (v_origen, v_destino) FOR UPDATE;

    SELECT numero_cuenta, nombre_cuenta, saldo INTO v_num_origen, v_nom_origen, v_saldo_origen
    FROM cuentas WHERE id_cuenta = v_origen FOR UPDATE;
    SELECT numero_cuenta, nombre_cuenta, saldo INTO v_num_destino, v_nom_destino, v_saldo_destino
    FROM cuentas WHERE id_cuenta = v_destino FOR UPDATE;
    IF v_num_destino IS NULL THEN
        ROLLBACK;
        SELECT 4 AS codigo, 'La cuenta destino no existe.' AS mensaje; LEAVE main;
    END IF;

    IF p_monto > v_monto_max THEN
        ROLLBACK;
        SELECT 5 AS codigo,
               CONCAT('El monto supera el máximo permitido para esta cuenta (Q ', FORMAT(v_monto_max, 2), ').') AS mensaje;
        LEAVE main;
    END IF;

    SELECT COUNT(*) INTO v_usadas FROM transferencias
    WHERE id_tercero = p_id_tercero AND fecha >= CAST(CURDATE() AS DATETIME);
    IF v_usadas >= v_max_tx THEN
        ROLLBACK;
        SELECT 6 AS codigo,
               CONCAT('Se alcanzó el límite de ', v_max_tx, ' transferencia(s) diaria(s) para esta cuenta.') AS mensaje;
        LEAVE main;
    END IF;

    IF v_saldo_origen < p_monto THEN
        ROLLBACK;
        SELECT 8 AS codigo, 'Saldo insuficiente para realizar la transferencia.' AS mensaje; LEAVE main;
    END IF;

    -- ---- Operaciones de escritura (todas dentro de la transacción) ----
    SET v_saldo_origen  = v_saldo_origen  - p_monto;
    SET v_saldo_destino = v_saldo_destino + p_monto;

    UPDATE cuentas SET saldo = v_saldo_origen  WHERE id_cuenta = v_origen;
    UPDATE cuentas SET saldo = v_saldo_destino WHERE id_cuenta = v_destino;

    INSERT INTO transferencias (id_cuenta_origen, id_cuenta_destino, id_tercero, monto)
    VALUES (v_origen, v_destino, p_id_tercero, p_monto);
    SET v_id_transf = LAST_INSERT_ID();

    INSERT INTO movimientos (id_cuenta, tipo, naturaleza, monto, saldo_resultante, id_transferencia, descripcion)
    VALUES (v_origen, 'TRANSFERENCIA_ENVIADA', 'DEBITO', p_monto, v_saldo_origen, v_id_transf,
            CONCAT('Transferencia a ', v_alias, ' (cuenta ', v_num_destino, ' - ', v_nom_destino, ')'));
    INSERT INTO movimientos (id_cuenta, tipo, naturaleza, monto, saldo_resultante, id_transferencia, descripcion)
    VALUES (v_destino, 'TRANSFERENCIA_RECIBIDA', 'CREDITO', p_monto, v_saldo_destino, v_id_transf,
            CONCAT('Transferencia recibida de la cuenta ', v_num_origen, ' (', v_nom_origen, ')'));

    COMMIT;
    SELECT 0 AS codigo, 'Transferencia realizada correctamente.' AS mensaje,
           v_id_transf AS id_transferencia, v_alias AS alias,
           v_num_destino AS numero_cuenta_destino, v_saldo_origen AS saldo_actual;
END$$

-- Estado de cuenta: SOLO los movimientos de la cuenta del usuario autenticado.
-- El procedimiento no recibe número de cuenta, por lo que es imposible pedir
-- el estado de cuenta de otra persona manipulando parámetros.
CREATE PROCEDURE sp_estado_cuenta(IN p_id_usuario INT UNSIGNED)
BEGIN
    SELECT m.id_movimiento, m.fecha, m.tipo, m.naturaleza, m.monto,
           m.saldo_resultante, m.descripcion, m.id_transferencia
    FROM movimientos m
    JOIN usuarios u ON u.id_cuenta = m.id_cuenta
    WHERE u.id_usuario = p_id_usuario AND u.rol = 'CLIENTE'
    ORDER BY m.fecha DESC, m.id_movimiento DESC;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
--  DATOS INICIALES (BD "en blanco"): únicamente el administrador.
--  Clave: Admin2026!  (almacenada con password_hash / bcrypt)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (rol, nombre_completo, username, password_hash)
VALUES ('ADMIN', 'Administrador del Sistema', 'admin',
        '$2y$10$tiCj30C1HOgMDQJG/R68B.C5vidLcwNa03xjBU.z6/q.gdLRSBmZq');
