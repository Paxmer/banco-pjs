<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CLIENTE');
$idUsuario = (int) $usuario['id_usuario'];

$errores = [];
$v = ['cuenta' => '', 'monto_max' => '', 'max_tx' => '', 'alias' => ''];

if (es_post()) {
    csrf_verificar();
    $v['cuenta'] = post('cuenta');
    $v['monto_max'] = post('monto_max');
    $v['max_tx'] = post('max_tx');
    $v['alias'] = post('alias');

    if (!es_numero_cuenta($v['cuenta'])) {
        $errores[] = 'El número de cuenta debe contener de 4 a 20 dígitos.';
    }
    $montoMax = normalizar_monto($v['monto_max']);
    if ($montoMax === null || (float) $montoMax <= 0) {
        $errores[] = 'El monto máximo debe ser un número mayor que cero (hasta 2 decimales).';
    }
    $maxTx = filter_var($v['max_tx'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($maxTx === false) {
        $errores[] = 'La cantidad máxima de transacciones diarias debe ser un entero entre 1 y 1000.';
    }
    if (mb_strlen($v['alias']) < 2 || mb_strlen($v['alias']) > 50) {
        $errores[] = 'El alias debe tener entre 2 y 50 caracteres.';
    }

    if (!$errores) {
        $r = sp_operacion('sp_tercero_crear', 'issis', [$idUsuario, $v['cuenta'], $montoMax, $maxTx, $v['alias']]);
        if ($r['codigo'] === 0) {
            flash_set('success', $r['mensaje']);
            redirect('cliente/terceros.php');
        }
        $errores[] = $r['mensaje'];
    }
}

$terceros = sp_call('sp_tercero_listar', 'i', [$idUsuario]);

render_header('Cuentas de terceros', $usuario, 'terceros');
?>
<h1>Cuentas de terceros</h1>

<section class="card narrow-form">
    <h2>Agregar cuenta de tercero</h2>
    <p class="muted">Una cuenta de tercero es una cuenta bancaria ajena a usted a la que desea transferir dinero. Sólo usted la verá.</p>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" data-loading>
        <?= csrf_campo() ?>
        <?= campo('cuenta', 'No. de cuenta', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{4,20}',
            'maxlength' => '20', 'data-solo-digitos' => true, 'title' => 'De 4 a 20 dígitos',
        ], 'La cuenta debe existir en el banco.', $v['cuenta']) ?>
        <?= campo('monto_max', 'Monto máximo por transferencia (Q)', [
            'type' => 'text', 'required' => true, 'inputmode' => 'decimal', 'pattern' => '\d{1,9}(\.\d{1,2})?',
            'title' => 'Número mayor que cero con hasta 2 decimales', 'data-monto' => true,
        ], 'Lo máximo que podrá enviar a esta cuenta en una sola operación.', $v['monto_max']) ?>
        <?= campo('max_tx', 'Cantidad máxima de transacciones diarias', [
            'type' => 'number', 'required' => true, 'min' => '1', 'max' => '1000', 'step' => '1', 'inputmode' => 'numeric',
        ], 'Cuántas transferencias por día podrá hacer a esta cuenta (1 a 1000).', $v['max_tx']) ?>
        <?= campo('alias', 'Alias', ['type' => 'text', 'required' => true, 'minlength' => '2', 'maxlength' => '50'], 'Un nombre para reconocer la cuenta (por ejemplo, "Mamá").', $v['alias']) ?>
        <button type="submit" class="btn btn-primary">Agregar cuenta</button>
    </form>
</section>

<section class="card">
    <h2>Mis cuentas de terceros</h2>
    <?php if (!$terceros): ?>
        <p class="muted">Aún no ha agregado cuentas de terceros.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-responsive">
            <thead><tr><th>Alias</th><th>No. de cuenta</th><th>Titular</th><th class="num">Monto máximo</th><th class="num">Transferencias hoy</th></tr></thead>
            <tbody>
            <?php foreach ($terceros as $t): ?>
                <tr>
                    <td data-label="Alias"><?= e($t['alias']) ?></td>
                    <td data-label="No. de cuenta"><?= e($t['numero_cuenta']) ?></td>
                    <td data-label="Titular"><?= e($t['nombre_cuenta']) ?></td>
                    <td data-label="Monto máximo" class="num"><?= e(fmt_q($t['monto_maximo'])) ?></td>
                    <td data-label="Transferencias hoy" class="num"><?= (int) $t['usadas_hoy'] ?> de <?= (int) $t['max_transacciones_diarias'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php render_footer();
