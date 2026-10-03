<?php
/**
 * Arranque común: toda página PHP lo incluye en su primera línea.
 * Carga configuración, utilidades, base de datos y autenticación.
 */
define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';

date_default_timezone_set(APP_TIMEZONE);

// Los errores técnicos nunca se muestran al usuario: se escriben en logs/.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', archivo_log_protegido('php_errores.log'));

set_exception_handler(function (Throwable $e) {
    log_error('Excepción no controlada: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    render_header('Error del sistema');
    echo '<section class="card narrow"><p class="arcade">Game over</p><h1>Ocurrió un error inesperado</h1>'
       . '<p>No pudimos completar la operación. Intente de nuevo en unos minutos; '
       . 'si el problema continúa, comuníquese con el administrador.</p>'
       . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Volver al inicio</a></p></section>';
    render_footer();
    exit;
});

enviar_cabeceras_seguridad();
sesion_iniciar();
