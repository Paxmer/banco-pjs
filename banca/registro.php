<?php
require __DIR__ . '/includes/bootstrap.php';

$errores = [];
$v = ['cuenta' => '', 'correo' => '', 'dpi' => ''];

if (es_post()) {
    csrf_verificar();
    $v['cuenta'] = post('cuenta');
    $v['correo'] = strtolower(post('correo'));
    $v['dpi'] = post('dpi');
    $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
    $confirmar = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

    if (!es_numero_cuenta($v['cuenta'])) {
        $errores[] = 'El número de cuenta debe contener de 4 a 20 dígitos.';
    }
    if (!es_correo($v['correo'])) {
        $errores[] = 'Ingrese un correo electrónico válido.';
    }
    if (!es_dpi($v['dpi'])) {
        $errores[] = 'El DPI debe tener exactamente 13 dígitos.';
    }
    if (($msg = error_clave($clave)) !== null) {
        $errores[] = $msg;
    }
    if ($clave !== $confirmar) {
        $errores[] = 'La confirmación de contraseña no coincide.';
    }

    if (!$errores) {
        $hash = password_hash($clave, PASSWORD_BCRYPT);
        $r = sp_operacion('sp_registrar_usuario', 'ssss', [$v['cuenta'], $v['correo'], $v['dpi'], $hash]);
        if ($r['codigo'] === 0) {
            flash_set('success', $r['mensaje']);
            redirect('login_usuario.php');
        }
        $errores[] = $r['mensaje'];
    }
}

render_header('Registro de nuevo usuario', null);
?>
<section class="card narrow">
    <h1>Registro de nuevo usuario</h1>
    <p class="muted">Para registrarse, su cuenta bancaria debe haber sido creada previamente por un cajero en el banco.
        Su correo electrónico será su nombre de usuario.</p>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" data-loading>
        <?= csrf_campo() ?>
        <?= campo('cuenta', 'No. de cuenta bancaria', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{4,20}',
            'maxlength' => '20', 'data-solo-digitos' => true, 'title' => 'De 4 a 20 dígitos',
        ], 'Sólo números (de 4 a 20 dígitos).', $v['cuenta']) ?>
        <?= campo('correo', 'Correo electrónico', [
            'type' => 'email', 'required' => true, 'maxlength' => '100', 'autocomplete' => 'email',
        ], 'Será su nombre de usuario.', $v['correo']) ?>
        <?= campo('dpi', 'DPI', [
            'type' => 'text', 'required' => true, 'inputmode' => 'numeric', 'pattern' => '[0-9]{13}',
            'maxlength' => '13', 'data-solo-digitos' => true, 'title' => '13 dígitos',
        ], 'Los 13 dígitos, sin espacios ni guiones. Debe coincidir con el registrado en su cuenta.', $v['dpi']) ?>
        <?= campo('clave', 'Contraseña', [
            'type' => 'password', 'required' => true, 'minlength' => '8', 'maxlength' => '72',
            'autocomplete' => 'new-password', 'data-clave' => true,
        ], 'Mínimo 8 caracteres, con al menos una letra y un número.') ?>
        <?= campo('confirmar', 'Confirmación de contraseña', [
            'type' => 'password', 'required' => true, 'minlength' => '8', 'maxlength' => '72',
            'autocomplete' => 'new-password', 'data-confirmar' => 'clave',
        ]) ?>
        <button type="submit" class="btn btn-primary btn-block">Registrarme</button>
    </form>
    <p class="center"><a href="<?= e(url('login_usuario.php')) ?>">¿Ya tiene usuario? Inicie sesión</a></p>
</section>
<?php render_footer();
