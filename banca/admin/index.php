<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('ADMIN');

render_header('Panel de administrador', $usuario, 'panel');
?>
<h1>Panel de administrador</h1>
<p class="muted">Seleccione una opción.</p>
<section class="grid grid-3">
    <a class="tile" href="<?= e(url('admin/cajeros.php')) ?>">
        <span class="tag">NPCs</span>
        <?= icono('cajeros') ?>
        <h2>Gestión de usuarios de cajeros</h2>
        <p>Listar, crear, bloquear y desbloquear cajeros.</p>
    </a>
    <a class="tile" href="<?= e(url('admin/monitor.php')) ?>">
        <span class="tag">Ranking</span>
        <?= icono('monitor') ?>
        <h2>Monitor de transferencias</h2>
        <p>Estadísticas del día y gráfica de depósitos vs. retiros.</p>
    </a>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="tile tile-form">
        <?= csrf_campo() ?>
        <button type="submit" class="tile-boton">
            <span class="tag">Game over</span>
            <?= icono('salir') ?>
            <h2>Salir</h2>
            <p>Cerrar sesión y salir del sistema.</p>
        </button>
    </form>
</section>
<?php render_footer();
