<?php
/**
 * Configuración de la aplicación.
 *
 * LOCAL (WampServer): los valores por defecto funcionan tal cual.
 * HOSTING GRATUITO : copie "config.ejemplo.php" como "config.local.php" en esta
 *                    misma carpeta y escriba allí los datos de su hosting.
 *                    "config.local.php" tiene prioridad sobre este archivo.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// --- Conexión a MySQL ---------------------------------------------------
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_NAME') || define('DB_NAME', 'banca_umg');
defined('DB_USER') || define('DB_USER', 'banca_app');
defined('DB_PASS') || define('DB_PASS', 'BancaApp#2026');

// --- Aplicación ----------------------------------------------------------
defined('APP_NOMBRE')   || define('APP_NOMBRE', 'Banco PJS');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'America/Guatemala');
defined('DB_TIMEZONE')  || define('DB_TIMEZONE', '-06:00');   // misma zona que APP_TIMEZONE
defined('SESION_INACTIVIDAD') || define('SESION_INACTIVIDAD', 1200); // segundos (20 min)
