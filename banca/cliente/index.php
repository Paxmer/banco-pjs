<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CLIENTE');
$cuenta = sp_call('sp_cliente_resumen', 'i', [(int) $usuario['id_usuario']])[0];

render_header('Panel de usuario', $usuario, 'panel');
?>
<h1>Panel de usuario</h1>

<section class="card saldo-card">
    <div>
        <span class="muted small">Su cuenta</span>
        <p class="cuenta-num"><?= e($cuenta['numero_cuenta']) ?> &middot; <?= e($cuenta['nombre_cuenta']) ?></p>
    </div>
    <div class="saldo">
        <span class="muted small">Saldo disponible</span>
        <p class="saldo-monto"><?= e(fmt_q($cuenta['saldo'])) ?></p>
    </div>
</section>

<section class="grid grid-3">
    <a class="tile" href="<?= e(url('cliente/terceros.php')) ?>">
        <span class="tag">Co-op</span>
        <?= icono('terceros') ?>
        <h2>Agregar cuentas de terceros</h2>
        <p>Registre las cuentas a las que desea transferir.</p>
    </a>
    <a class="tile" href="<?= e(url('cliente/transferir.php')) ?>">
        <span class="tag">Warp</span>
        <?= icono('transferir') ?>
        <h2>Transferencia a cuenta de tercero</h2>
        <p>Envíe dinero a una de sus cuentas de terceros.</p>
    </a>
    <a class="tile" href="<?= e(url('cliente/estado_cuenta.php')) ?>">
        <span class="tag">Quest log</span>
        <?= icono('estado') ?>
        <h2>Estado de cuenta</h2>
        <p>Todos sus débitos y créditos.</p>
    </a>
</section>
<?php render_footer();
