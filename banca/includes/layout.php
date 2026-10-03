<?php
/**
 * Plantilla común (encabezado, navegación por rol y pie de página) · estilo retro pixel-art.
 */

function menu_por_rol(?string $rol): array
{
    switch ($rol) {
        case 'ADMIN':
            return [
                'panel'   => ['admin/index.php', 'Panel'],
                'cajeros' => ['admin/cajeros.php', 'Cajeros'],
                'monitor' => ['admin/monitor.php', 'Monitor de transferencias'],
            ];
        case 'CAJERO':
            return [
                'panel'    => ['cajero/index.php', 'Panel'],
                'cuenta'   => ['cajero/crear_cuenta.php', 'Crear cuenta'],
                'deposito' => ['cajero/deposito.php', 'Depósito'],
                'retiro'   => ['cajero/retiro.php', 'Retiro'],
            ];
        case 'CLIENTE':
            return [
                'panel'      => ['cliente/index.php', 'Panel'],
                'terceros'   => ['cliente/terceros.php', 'Cuentas de terceros'],
                'transferir' => ['cliente/transferir.php', 'Transferir'],
                'estado'     => ['cliente/estado_cuenta.php', 'Estado de cuenta'],
            ];
        default:
            return ['inicio' => ['index.php', 'Inicio']];
    }
}

/** Icono pixel-art (assets/img/iconos/NOMBRE.png) para los accesos de los paneles. */
function icono(string $nombre, int $tam = 64): string
{
    return '<img class="tile-icon" src="' . e(url('assets/img/iconos/' . $nombre . '.png')) . '" alt="" width="' . $tam . '" height="' . $tam . '">';
}

/**
 * @param string     $titulo  título de la página
 * @param array|null $usuario usuario autenticado (resultado de require_rol) o null si es pública
 * @param string     $activa  clave del menú que se resalta
 */
function render_header(string $titulo, ?array $usuario = null, string $activa = ''): void
{
    $rol = $usuario['rol'] ?? null;
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Press+Start+2P&amp;family=VT323&amp;display=swap">
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="topbar">
    <div class="wrap topbar-in">
        <a class="brand" href="<?= e(url($rol ? PANEL_POR_ROL[$rol] : 'index.php')) ?>">
            <img src="<?= e(url('assets/img/logo_pjs.png')) ?>" alt="Logo de <?= e(APP_NOMBRE) ?>" width="52" height="52">
            <span class="brand-text"><?= e(APP_NOMBRE) ?><small>Banca en Línea &middot; Player 1 ready</small></span>
        </a>
        <input type="checkbox" id="menu-toggle" class="menu-toggle" aria-hidden="true">
        <label for="menu-toggle" class="burger" aria-label="Abrir o cerrar el menú"><span></span></label>
        <nav class="nav" aria-label="Navegación principal">
            <?php foreach (menu_por_rol($rol) as $clave => [$ruta, $texto]): ?>
                <a href="<?= e(url($ruta)) ?>"<?= $clave === $activa ? ' class="activo" aria-current="page"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
            <?php if ($rol): ?>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="nav-salir">
                    <?= csrf_campo() ?>
                    <button type="submit" class="nav-boton">Salir</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($usuario): ?>
<div class="userbar"><div class="wrap">&#9654; Jugador: <strong><?= e($usuario['nombre_completo']) ?></strong>
    <span class="badge"><?= e(['ADMIN' => 'Administrador', 'CAJERO' => 'Cajero', 'CLIENTE' => 'Cliente'][$rol]) ?></span></div></div>
<?php endif; ?>
<main class="wrap" id="contenido" tabindex="-1">
<?= flash_render() ?>
<?php
}

/** @param bool $insertCoin muestra "Insert coin to continue" en el pie (la portada lo muestra bajo "Press start") */
function render_footer(bool $insertCoin = true): void
{
    ?>
</main>
<footer class="footer">
    <div class="wrap">&copy; <?= date('Y') ?> <?= e(APP_NOMBRE) ?> &middot; Proyecto académico de Desarrollo Web &middot; Universidad Mariano Gálvez de Guatemala
        <?php if ($insertCoin): ?><span class="coin blink">Insert coin to continue &#9654;</span><?php endif; ?>
        <button type="button" id="btn-efectos" class="efectos" aria-pressed="true">Efectos retro: SÍ</button></div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Cuadro de formulario reutilizable: etiqueta + campo + ayuda opcional. */
function campo(string $id, string $etiqueta, array $attrs = [], string $ayuda = '', string $valor = ''): string
{
    $atributos = '';
    foreach ($attrs as $k => $v) {
        $atributos .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
    }
    $tipo = $attrs['type'] ?? 'text';
    $valorAttr = ($tipo === 'password') ? '' : ' value="' . e($valor) . '"';
    $html = '<div class="field"><label for="' . e($id) . '">' . e($etiqueta) . '</label>'
          . '<input id="' . e($id) . '" name="' . e($id) . '"' . $atributos . $valorAttr . '>';
    if ($ayuda !== '') {
        $html .= '<small class="hint">' . e($ayuda) . '</small>';
    }
    return $html . '</div>';
}

/** Página de inicio de sesión compartida por los tres roles. */
function pagina_login(string $rol, string $titulo, string $etiquetaUsuario, string $descripcion): void
{
    // Si ya hay una sesión válida de este rol, se envía directo a su panel.
    if (!empty($_SESSION['uid']) && ($_SESSION['rol'] ?? '') === $rol
        && sp_call('sp_sesion_validar', 'is', [(int) $_SESSION['uid'], $rol])) {
        redirect(PANEL_POR_ROL[$rol]);
    }

    $error = '';
    $usuario = '';
    if (es_post()) {
        csrf_verificar();
        $usuario = post('usuario');
        $clave = $_POST['clave'] ?? '';
        if ($usuario === '' || !is_string($clave) || $clave === '') {
            $error = 'Ingrese su usuario y su contraseña.';
        } elseif (strlen($usuario) > 100 || strlen($clave) > 200) {
            $error = 'Usuario o contraseña incorrectos.';
        } else {
            [$ok, $error] = intentar_login($rol, $usuario, $clave);
            if ($ok) {
                redirect(PANEL_POR_ROL[$rol]);
            }
        }
    }

    render_header($titulo);
    ?>
<section class="card narrow">
    <p class="arcade blink">&#9654; Press start</p>
    <h1><?= e($titulo) ?></h1>
    <p class="muted"><?= e($descripcion) ?></p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form" data-loading novalidate>
        <?= csrf_campo() ?>
        <?= campo('usuario', $etiquetaUsuario, [
            'type' => $rol === 'CLIENTE' ? 'email' : 'text', 'required' => true, 'maxlength' => '100',
            'autocomplete' => 'username', 'autofocus' => true,
        ], '', $usuario) ?>
        <?= campo('clave', 'Contraseña', [
            'type' => 'password', 'required' => true, 'maxlength' => '200', 'autocomplete' => 'current-password',
        ]) ?>
        <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
    </form>
    <p class="center"><a href="<?= e(url('index.php')) ?>">&larr; Volver a la página principal</a></p>
</section>
<?php
    render_footer();
}
