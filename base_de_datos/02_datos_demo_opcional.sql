-- =====================================================================
--  Script 02 (OPCIONAL): datos de demostración para probar el sistema
--  localmente. NO se incluye en la entrega "BD en blanco".
--  Ejecutar DESPUÉS de 01_esquema_y_procedimientos.sql, sobre la BD banca_umg.
--
--  Crea (usando los mismos stored procedures que usa la aplicación):
--    Cajero   : cajero1            / Cajero2026!
--    Cuentas  : 1001 Juan Pérez    (DPI 2001234560101)  Q 5,000.00
--               1002 María López   (DPI 2009876540101)  Q 3,000.00
--               1003 Carlos Ramírez(DPI 2005550000101)  Q 1,500.00
--    Clientes : juan@PJS.com.gt    / Cliente2026!   (cuenta 1001)
--               maria@PJS.com.gt   / Cliente2026!   (cuenta 1002)
--               carlos@PJS.com.gt  / Cliente2026!   (cuenta 1003)
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '-06:00';   -- Guatemala (la aplicación usa la misma zona)

INSERT INTO usuarios (rol, nombre_completo, username, password_hash)
VALUES ('CAJERO', 'Ana Cajera Demo', 'cajero1',
        '$2y$10$sMAaxs7CQOuZqKpNjELJ8uYh.niTtazzRzlJNIYLVrRMswZTQZJpO');
SET @cajero = LAST_INSERT_ID();

CALL sp_cuenta_crear(@cajero, 'Juan Pérez',      '1001', '2001234560101', 5000.00);
CALL sp_cuenta_crear(@cajero, 'María López',     '1002', '2009876540101', 3000.00);
CALL sp_cuenta_crear(@cajero, 'Carlos Ramírez',  '1003', '2005550000101', 1500.00);

CALL sp_registrar_usuario('1001', 'juan@PJS.com.gt',   '2001234560101',
     '$2y$10$0WLL23/AcZSG0.F8kUR0le0kjZtNqKGE3/vlsKyPE40z02hzRfoui');
CALL sp_registrar_usuario('1002', 'maria@PJS.com.gt', '2009876540101',
     '$2y$10$0WLL23/AcZSG0.F8kUR0le0kjZtNqKGE3/vlsKyPE40z02hzRfoui');
CALL sp_registrar_usuario('1003', 'carlos@PJS.com.gt', '2005550000101',
     '$2y$10$0WLL23/AcZSG0.F8kUR0le0kjZtNqKGE3/vlsKyPE40z02hzRfoui');
