-- =====================================================================
--  Script 00 (SOLO LOCAL / WampServer): crea la base de datos y el usuario
--  de la aplicación. En un hosting gratuito la BD y el usuario se crean desde
--  el panel de control; no es necesario ejecutar este archivo allí.
--
--  El usuario "banca_app" sólo tiene permiso EXECUTE: la aplicación PHP no
--  puede hacer SELECT/INSERT/UPDATE directos sobre las tablas, únicamente
--  invocar los stored procedures.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS banca_umg
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'banca_app'@'localhost' IDENTIFIED BY 'BancaApp#2026';
CREATE USER IF NOT EXISTS 'banca_app'@'127.0.0.1' IDENTIFIED BY 'BancaApp#2026';
GRANT EXECUTE ON banca_umg.* TO 'banca_app'@'localhost';
GRANT EXECUTE ON banca_umg.* TO 'banca_app'@'127.0.0.1';
FLUSH PRIVILEGES;
