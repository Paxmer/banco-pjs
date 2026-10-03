<?php
/**
 * Autenticación, sesiones, autorización por rol y protección CSRF.
 * Roles: ADMIN, CAJERO, CLIENTE.
 */

const PANEL_POR_ROL = ['ADMIN' => 'admin/index.php', 'CAJERO' => 'cajero/index.php', 'CLIENTE' => 'cliente/index.php'];
const LOGIN_POR_ROL = ['ADMIN' => 'login_admin.php', 'CAJERO' => 'login_cajero.php', 'CLIENTE' => 'login_usuario.php'];
// Hash de relleno: se verifica cuando el usuario no existe para que el tiempo de
// respuesta no revele si el usuario existe o no.
const HASH_RELLENO = '$2y$10$CjpnQ288UREKoST8zoUK3uM9W4n29TOpwY0hSBPtoc2olF4SkjW3q';

function sesion_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('BANCASESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'secure'   => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Valida usuario y contraseña para un rol. Devuelve [true,''] o [false,mensaje].
 * La contraseña se compara con password_verify() contra el hash guardado.
 */
function intentar_login(string $rol, string $usuario, string $clave): array
{
    $fila = sp_call('sp_login_obtener', 'ss', [$rol, $usuario])[0] ?? null;
    $ok = password_verify($clave, $fila['password_hash'] ?? HASH_RELLENO);
    if ($fila === null || !$ok) {
        return [false, 'Usuario o contraseña incorrectos.'];
    }
    if ((int) $fila['bloqueado'] === 1) {
        return [false, 'Su usuario está bloqueado. Comuníquese con el administrador.'];
    }
    session_regenerate_id(true); // evita fijación de sesión
    $_SESSION = [
        'uid'    => (int) $fila['id_usuario'],
        'rol'    => $fila['rol'],
        'nombre' => $fila['nombre_completo'],
        'ultimo' => time(),
        'csrf'   => bin2hex(random_bytes(32)),
    ];
    return [true, ''];
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Página 403 para quien tiene sesión pero intenta entrar a un panel que no es el suyo. */
function responder_403(): void
{
    http_response_code(403);
    render_header('Acceso no autorizado');
    echo '<section class="card narrow"><p class="arcade">Game over</p><h1>Acceso no autorizado</h1>'
       . '<p>Su usuario no tiene permiso para ver esta página.</p><p>';
    if (!empty($_SESSION['rol']) && isset(PANEL_POR_ROL[$_SESSION['rol']])) {
        echo '<a class="btn btn-primary" href="' . e(url(PANEL_POR_ROL[$_SESSION['rol']])) . '">Ir a mi panel</a>';
    }
    echo '</p></section>';
    render_footer();
    exit;
}

/**
 * Protege una página: exige sesión activa del rol indicado.
 * - Sin sesión o sesión expirada  -> redirige al login de ese rol.
 * - Usuario bloqueado/inexistente -> cierra la sesión.
 * - Sesión de otro rol            -> 403.
 * Devuelve los datos del usuario autenticado (id, rol, nombre, id_cuenta...).
 */
function require_rol(string $rol): array
{
    $login = LOGIN_POR_ROL[$rol];
    if (empty($_SESSION['uid']) || empty($_SESSION['rol'])) {
        flash_set('error', 'Debe iniciar sesión para acceder a esa página.');
        redirect($login);
    }
    if (time() - (int) ($_SESSION['ultimo'] ?? 0) > SESION_INACTIVIDAD) {
        cerrar_sesion();
        sesion_iniciar();
        flash_set('error', 'Su sesión expiró por inactividad. Inicie sesión nuevamente.');
        redirect($login);
    }
    $fila = sp_call('sp_sesion_validar', 'is', [(int) $_SESSION['uid'], (string) $_SESSION['rol']])[0] ?? null;
    if ($fila === null) {
        cerrar_sesion();
        sesion_iniciar();
        flash_set('error', 'Su sesión ya no es válida. Si su usuario fue bloqueado, comuníquese con el administrador.');
        redirect($login);
    }
    if ($_SESSION['rol'] !== $rol) {
        responder_403();
    }
    $_SESSION['ultimo'] = time();
    header('Cache-Control: no-store, no-cache, must-revalidate'); // el botón "atrás" no muestra datos tras salir
    header('Pragma: no-cache');
    return $fila;
}

// --- CSRF ----------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verifica el token CSRF de un POST; si no es válido corta la petición con 400. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        render_header('Solicitud no válida');
        echo '<section class="card narrow"><p class="arcade">Game over</p><h1>Solicitud no válida</h1>'
           . '<p>El formulario expiró o no es válido. Vuelva a la página anterior y repita la operación.</p>'
           . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Ir al inicio</a></p></section>';
        render_footer();
        exit;
    }
}
