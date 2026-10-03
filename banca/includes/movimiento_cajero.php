<?php
/**
 * Pantalla común de depósito y retiro (misma estructura, distinto procedimiento).
 *
 * @param string $tipo 'deposito' | 'retiro'
 */
function pagina_movimiento_cajero(string $tipo): void
{
    $esDeposito = $tipo === 'deposito';
    $usuario = require_rol('CAJERO');
    $sp = $esDeposito ? 'sp_deposito' : 'sp_retiro';

    $errores = [];
    $v = ['numero' => '', 'monto' => ''];

    if (es_post()) {
        csrf_verificar();
        $v['numero'] = post('numero');
        $v['monto'] = post('monto');

        if (!es_numero_cuenta($v['numero'])) {
            $errores[] = 'El número de cuenta debe contener de 4 a 20 dígitos.';
        }
        $monto = normalizar_monto($v['monto']);
        if ($monto === null) {
            $errores[] = 'El monto no es válido (use números, hasta 2 decimales, sin comas ni signos).';
        } elseif ((float) $monto <= 0) {
            $errores[] = 'El monto debe ser mayor que cero.';
        }

        if (!$errores) {
            $r = sp_operacion($sp, 'iss', [(int) $usuario['id_usuario'], $v['numero'], $monto]);
            if ($r['codigo'] === 0) {
                flash_set('success', ($esDeposito ? 'Depósito' : 'Retiro') . ' de ' . fmt_q($monto) . ' realizado en la cuenta '
                    . $r['numero_cuenta'] . ' (' . $r['nombre_cuenta'] . '). Nuevo saldo: ' . fmt_q($r['saldo_actual']) . '.');
                redirect('cajero/' . $tipo . '.php');
            }
            $errores[] = $r['mensaje'];
        }
    }

    $titulo = $esDeposito ? 'Depósito monetario' : 'Retiro monetario';
    render_header($titulo, $usuario, $tipo);
    ?>
<section class="card narrow-form">
    <h1><?= e($titulo) ?></h1>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" data-loading data-resumen="<?= $esDeposito ? 'Confirmar depósito' : 'Confirmar retiro' ?>">
        <?= csrf_campo() ?>
        <?= campo('numero', 'No. de cuenta', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{4,20}',
            'maxlength' => '20', 'data-solo-digitos' => true, 'title' => 'De 4 a 20 dígitos',
        ], '', $v['numero']) ?>
        <?= campo('monto', $esDeposito ? 'Cantidad a depositar (Q)' : 'Cantidad a retirar (Q)', [
            'type' => 'text', 'required' => true, 'inputmode' => 'decimal', 'pattern' => '\d{1,9}(\.\d{1,2})?',
            'title' => 'Número mayor que cero con hasta 2 decimales, por ejemplo 250.00', 'data-monto' => true,
        ], 'Use punto decimal, por ejemplo 250.00.', $v['monto']) ?>
        <button type="submit" class="btn btn-primary"><?= $esDeposito ? 'Depositar' : 'Retirar' ?></button>
    </form>
</section>
<?php
    render_footer();
}
