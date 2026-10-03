<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CLIENTE');
$idUsuario = (int) $usuario['id_usuario'];

$errores = [];
$v = ['tercero' => '', 'monto' => ''];

if (es_post()) {
    csrf_verificar();
    $v['tercero'] = post('tercero');
    $v['monto'] = post('monto');

    $idTercero = filter_var($v['tercero'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idTercero === false) {
        $errores[] = 'Seleccione una cuenta de tercero.';
    }
    $monto = normalizar_monto($v['monto']);
    if ($monto === null) {
        $errores[] = 'El monto no es válido (use números, hasta 2 decimales, sin comas ni signos).';
    } elseif ((float) $monto <= 0) {
        $errores[] = 'El monto debe ser mayor que cero.';
    }

    if (!$errores) {
        // La cuenta origen NO se envía desde el navegador: el procedimiento la obtiene del usuario de la sesión,
        // y verifica que el tercero pertenezca a ese mismo usuario.
        $r = sp_operacion('sp_transferir', 'iis', [$idUsuario, $idTercero, $monto]);
        if ($r['codigo'] === 0) {
            flash_set('success', 'Transferencia #' . $r['id_transferencia'] . ' de ' . fmt_q($monto) . ' a "' . $r['alias']
                . '" (cuenta ' . $r['numero_cuenta_destino'] . ') realizada correctamente. Su nuevo saldo es ' . fmt_q($r['saldo_actual']) . '.');
            redirect('cliente/transferir.php');
        }
        $errores[] = $r['mensaje'];
    }
}

$terceros = sp_call('sp_tercero_listar', 'i', [$idUsuario]);
$cuenta = sp_call('sp_cliente_resumen', 'i', [$idUsuario])[0];

render_header('Transferencia a tercero', $usuario, 'transferir');
?>
<section class="card narrow-form">
    <h1>Transferencia a cuenta de tercero</h1>
    <p class="muted">Cuenta de origen: <strong><?= e($cuenta['numero_cuenta']) ?></strong> &middot; Saldo disponible: <strong><?= e(fmt_q($cuenta['saldo'])) ?></strong></p>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

    <?php if (!$terceros): ?>
        <div class="alert alert-info">Todavía no tiene cuentas de terceros. <a href="<?= e(url('cliente/terceros.php')) ?>">Agregue una</a> para poder transferir.</div>
    <?php else: ?>
    <form method="post" class="form" data-loading data-resumen="Confirmar transferencia">
        <?= csrf_campo() ?>
        <div class="field">
            <label for="tercero">Cuenta de tercero</label>
            <select id="tercero" name="tercero" required>
                <option value="">— Seleccione una cuenta —</option>
                <?php foreach ($terceros as $t): ?>
                    <option value="<?= (int) $t['id_tercero'] ?>"<?= (string) $t['id_tercero'] === $v['tercero'] ? ' selected' : '' ?>>
                        <?= e($t['alias']) ?> &mdash; <?= e($t['numero_cuenta']) ?> (<?= e($t['nombre_cuenta']) ?>)
                        &middot; máx. <?= e(fmt_q($t['monto_maximo'])) ?> &middot; hoy <?= (int) $t['usadas_hoy'] ?>/<?= (int) $t['max_transacciones_diarias'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?= campo('monto', 'Monto a transferir (Q)', [
            'type' => 'text', 'required' => true, 'inputmode' => 'decimal', 'pattern' => '\d{1,9}(\.\d{1,2})?',
            'title' => 'Número mayor que cero con hasta 2 decimales, por ejemplo 100.00', 'data-monto' => true,
        ], 'Use punto decimal, por ejemplo 100.00.', $v['monto']) ?>
        <button type="submit" class="btn btn-primary">Transferir</button>
    </form>
    <?php endif; ?>
</section>
<?php render_footer();
