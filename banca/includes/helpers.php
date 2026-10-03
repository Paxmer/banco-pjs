<?php
/**
 * Utilidades generales: rutas, escape de HTML, mensajes flash, validaciones
 * del lado del servidor y registro de errores.
 */

// --- Rutas ---------------------------------------------------------------
// BASE_URL = carpeta pública de la aplicación (sin barra final), calculada a
// partir de la URL actual; así funciona en http://localhost/banca, en un
// VirtualHost o en la raíz de un hosting sin tocar código.
(function () {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(admin|cajero|cliente)$#', '', $dir);
    define('BASE_URL', $dir);
})();

function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

function redirect(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function enviar_cabeceras_seguridad(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    // Google Fonts (Press Start 2P y VT323) es lo único externo; si no carga, el sitio usa fuentes monoespaciadas.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

// --- Salida segura -------------------------------------------------------
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- Mensajes flash (se muestran una vez tras redirigir) -----------------
function flash_set(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['tipo']) . '" role="alert">' . e($f['mensaje']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// --- Entrada -------------------------------------------------------------
/** Valor de $_POST como texto recortado ('' si no existe o no es texto). */
function post(string $campo): string
{
    $v = $_POST[$campo] ?? '';
    return is_string($v) ? trim($v) : '';
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

// --- Formato -------------------------------------------------------------
function fmt_q($valor): string
{
    return 'Q ' . number_format((float) $valor, 2, '.', ',');
}

function fmt_fecha(string $fechaSql): string
{
    return date('d/m/Y H:i', strtotime($fechaSql));
}

// --- Validaciones del servidor ------------------------------------------
const MONTO_MAXIMO = '999999999.99';

/** Devuelve el monto normalizado con 2 decimales ("1250.50") o null si no es válido. */
function normalizar_monto(string $texto): ?string
{
    if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $texto)) {
        return null;
    }
    return number_format((float) $texto, 2, '.', '');
}

function es_numero_cuenta(string $v): bool
{
    return (bool) preg_match('/^[0-9]{4,20}$/', $v);
}

function es_dpi(string $v): bool
{
    return (bool) preg_match('/^[0-9]{13}$/', $v);
}

function es_correo(string $v): bool
{
    return strlen($v) <= 100 && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

/** Política de clave: 8-72 caracteres, al menos una letra y un número. */
function error_clave(string $clave): ?string
{
    if (strlen($clave) < 8 || strlen($clave) > 72) {
        return 'La contraseña debe tener entre 8 y 72 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) {
        return 'La contraseña debe incluir al menos una letra y un número.';
    }
    return null;
}

// --- Registro de errores técnicos ---------------------------------------
/**
 * Ruta de un archivo de log "protegido": se guarda como .log.php y su primera línea es
 * código PHP que corta la ejecución. Así, si el hosting no respeta los .htaccess y alguien
 * abre el archivo por HTTP, el servidor devuelve 403 en lugar de mostrar el contenido.
 */
function archivo_log_protegido(string $nombre): string
{
    $ruta = APP_ROOT . '/logs/' . $nombre . '.php';
    if (!is_file($ruta)) {
        @file_put_contents($ruta, "<?php http_response_code(403); exit; ?>\n");
    }
    return $ruta;
}

function log_error(string $mensaje): void
{
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
    $archivo = archivo_log_protegido('app.log');
    if (@file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX) === false) {
        error_log(trim($linea)); // respaldo si la carpeta no es escribible (hosting)
    }
}
