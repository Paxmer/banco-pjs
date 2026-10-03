<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CAJERO');

render_header('Panel de cajero', $usuario, 'panel');
?>
<h1>Panel de cajero</h1>
<p class="muted">Seleccione la operación que desea realizar.</p>
<section class="grid grid-3">
    <a class="tile" href="<?= e(url('cajero/crear_cuenta.php')) ?>">
        <span class="tag">Save file</span>
        <?= icono('crear_cuenta') ?>
        <h2>Crear cuenta monetaria</h2>
        <p>Abrir una nueva cuenta con su monto inicial.</p>
    </a>
    <a class="tile" href="<?= e(url('cajero/deposito.php')) ?>">
        <span class="tag">+ Monedas</span>
        <?= icono('deposito') ?>
        <h2>Depósito monetario</h2>
        <p>Acreditar dinero a una cuenta.</p>
    </a>
    <a class="tile" href="<?= e(url('cajero/retiro.php')) ?>">
        <span class="tag">- Monedas</span>
        <?= icono('retiro') ?>
        <h2>Retiro monetario</h2>
        <p>Debitar dinero de una cuenta.</p>
    </a>
</section>
<?php render_footer();
