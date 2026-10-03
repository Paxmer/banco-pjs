<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('CAJERO');

$errores = [];
$v = ['nombre' => '', 'numero' => '', 'dpi' => '', 'monto' => ''];

if (es_post()) {
    csrf_verificar();
    $v['nombre'] = post('nombre');
    $v['numero'] = post('numero');
    $v['dpi'] = post('dpi');
    $v['monto'] = post('monto');

    if (mb_strlen($v['nombre']) < 3 || mb_strlen($v['nombre']) > 100) {
        $errores[] = 'El nombre de la cuenta debe tener entre 3 y 100 caracteres.';
    }
    if (!es_numero_cuenta($v['numero'])) {
        $errores[] = 'El número de cuenta debe contener de 4 a 20 dígitos.';
    }
    if (!es_dpi($v['dpi'])) {
        $errores[] = 'El DPI debe tener exactamente 13 dígitos.';
    }
    $monto = normalizar_monto($v['monto']);
    if ($monto === null) {
        $errores[] = 'El monto inicial no es válido (use números, hasta 2 decimales, sin comas ni signos).';
    }

    if (!$errores) {
        $r = sp_operacion('sp_cuenta_crear', 'issss', [(int) $usuario['id_usuario'], $v['nombre'], $v['numero'], $v['dpi'], $monto]);
        if ($r['codigo'] === 0) {
            flash_set('success', 'Cuenta ' . $r['numero_cuenta'] . ' creada correctamente con saldo inicial de ' . fmt_q($r['saldo']) . '.');
            redirect('cajero/crear_cuenta.php');
        }
        $errores[] = $r['mensaje'];
    }
}

render_header('Crear cuenta monetaria', $usuario, 'cuenta');
?>
<section class="card narrow-form">
    <h1>Crear cuenta monetaria</h1>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" data-loading>
        <?= csrf_campo() ?>
        <?= campo('nombre', 'Nombre de la cuenta', ['type' => 'text', 'required' => true, 'minlength' => '3', 'maxlength' => '100'], 'Nombre del titular o de la cuenta.', $v['nombre']) ?>
        <?= campo('numero', 'No. de cuenta', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{4,20}',
            'maxlength' => '20', 'data-solo-digitos' => true, 'title' => 'De 4 a 20 dígitos',
        ], 'Sólo números (de 4 a 20 dígitos). Debe ser único.', $v['numero']) ?>
        <?= campo('dpi', 'Identificación (DPI)', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{13}',
            'maxlength' => '13', 'data-solo-digitos' => true, 'title' => '13 dígitos',
        ], '13 dígitos, sin espacios ni guiones.', $v['dpi']) ?>
        <?= campo('monto', 'Monto inicial (Q)', [
            'type' => 'text', 'required' => true, 'inputmode' => 'decimal', 'pattern' => '\d{1,9}(\.\d{1,2})?',
            'title' => 'Número con hasta 2 decimales, por ejemplo 500.00', 'data-monto' => true,
        ], 'Use punto decimal, por ejemplo 500.00. Puede ser 0.', $v['monto']) ?>
        <button type="submit" class="btn btn-primary">Crear cuenta</button>
    </form>
</section>
<?php render_footer();
