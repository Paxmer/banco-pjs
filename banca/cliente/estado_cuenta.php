<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CLIENTE');
$idUsuario = (int) $usuario['id_usuario'];

// No hay parámetros de entrada: la cuenta se deduce SIEMPRE del usuario autenticado.
$cuenta = sp_call('sp_cliente_resumen', 'i', [$idUsuario])[0];
$movs = sp_call('sp_estado_cuenta', 'i', [$idUsuario]);

$totalCred = 0.0;
$totalDeb = 0.0;
foreach ($movs as $m) {
    if ($m['naturaleza'] === 'CREDITO') {
        $totalCred += (float) $m['monto'];
    } else {
        $totalDeb += (float) $m['monto'];
    }
}
$nombresTipo = [
    'APERTURA' => 'Apertura de cuenta',
    'DEPOSITO' => 'Depósito',
    'RETIRO' => 'Retiro',
    'TRANSFERENCIA_ENVIADA' => 'Transferencia enviada',
    'TRANSFERENCIA_RECIBIDA' => 'Transferencia recibida',
];

render_header('Estado de cuenta', $usuario, 'estado');
?>
<h1>Estado de cuenta</h1>

<section class="card saldo-card">
    <div>
        <span class="muted small">Cuenta</span>
        <p class="cuenta-num"><?= e($cuenta['numero_cuenta']) ?> &middot; <?= e($cuenta['nombre_cuenta']) ?></p>
    </div>
    <div class="saldo">
        <span class="muted small">Saldo actual</span>
        <p class="saldo-monto"><?= e(fmt_q($cuenta['saldo'])) ?></p>
    </div>
</section>

<section class="card">
    <h2>Operaciones realizadas</h2>
    <?php if (!$movs): ?>
        <p class="muted">Su cuenta todavía no tiene operaciones.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-responsive">
            <thead>
                <tr><th>Fecha</th><th>Operación</th><th>Detalle</th><th class="num">Débito</th><th class="num">Crédito</th><th class="num">Saldo</th></tr>
            </thead>
            <tbody>
            <?php foreach ($movs as $m): $esCred = $m['naturaleza'] === 'CREDITO'; ?>
                <tr>
                    <td data-label="Fecha"><?= e(fmt_fecha($m['fecha'])) ?></td>
                    <td data-label="Operación"><span class="badge <?= $esCred ? 'badge-ok' : 'badge-danger' ?>"><?= e($nombresTipo[$m['tipo']] ?? $m['tipo']) ?></span></td>
                    <td data-label="Detalle"><?= e($m['descripcion']) ?><?= $m['id_transferencia'] ? ' <span class="muted small">(ref. #' . (int) $m['id_transferencia'] . ')</span>' : '' ?></td>
                    <td data-label="Débito" class="num debito"><?= $esCred ? '' : e(fmt_q($m['monto'])) ?></td>
                    <td data-label="Crédito" class="num credito"><?= $esCred ? e(fmt_q($m['monto'])) : '' ?></td>
                    <td data-label="Saldo" class="num"><?= e(fmt_q($m['saldo_resultante'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3">Totales (<?= count($movs) ?> operaciones)</th>
                    <th class="num debito"><?= e(fmt_q($totalDeb)) ?></th>
                    <th class="num credito"><?= e(fmt_q($totalCred)) ?></th>
                    <th></th></tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php render_footer();
