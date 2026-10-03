<?php
require __DIR__ . '/includes/bootstrap.php';

render_header('Inicio', null, 'inicio');
?>
<section class="portada" aria-label="Página principal del banco">
    <div class="portada-lado">
        <a class="tile" href="<?= e(url('login_usuario.php')) ?>">
            <span class="tag">Jugador 1</span>
            <?= icono('jugador') ?>
            <h2>Inicio de sesión de usuario</h2>
            <p>Cuentas de terceros, transferencias y estado de cuenta.</p>
        </a>
        <a class="tile" href="<?= e(url('registro.php')) ?>">
            <span class="tag">Nueva partida</span>
            <?= icono('registro') ?>
            <h2>Registro de nuevo usuario</h2>
            <p>Cree su usuario con su cuenta bancaria ya existente.</p>
        </a>
    </div>

    <div class="hero">
        <img class="banner" src="<?= e(url('assets/img/banner.jpg')) ?>" alt="Ciudad pixel-art de noche con el edificio del Banco PJS y monedas flotantes" width="1280" height="720">
        <h1>Bienvenido a <?= e(APP_NOMBRE) ?></h1>
        <p>Banca en línea segura con alma de arcade: consulte su cuenta, administre sus terceros y transfiera monedas desde cualquier dispositivo.</p>
        <p class="press-start blink">&#9654; Press start &mdash; elija su jugador</p>
        <p class="coin blink">Insert coin to continue &#9654;</p>
    </div>

    <div class="portada-lado">
        <a class="tile" href="<?= e(url('login_cajero.php')) ?>">
            <span class="tag">Tienda</span>
            <?= icono('cajero') ?>
            <h2>Inicio de sesión de cajero</h2>
            <p>Crear cuentas, depósitos y retiros.</p>
        </a>
        <a class="tile" href="<?= e(url('login_admin.php')) ?>">
            <span class="tag">Jefe final</span>
            <?= icono('admin') ?>
            <h2>Inicio de sesión de administrador</h2>
            <p>Gestión de cajeros y monitor de transferencias.</p>
        </a>
    </div>
</section>
<?php render_footer(false);
