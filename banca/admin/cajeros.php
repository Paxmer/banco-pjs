<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('ADMIN');

$errores = [];
$v = ['nombre' => '', 'usuario' => ''];

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'estado') {
        // Bloquear / desbloquear: el id viaja en el formulario pero el SP sólo actúa sobre rol CAJERO.
        $id = filter_var(post('id_usuario'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $bloquear = post('bloquear') === '1' ? 1 : 0;
        if ($id === false) {
            flash_set('error', 'Cajero no válido.');
        } else {
            $r = sp_operacion('sp_cajero_cambiar_estado', 'ii', [$id, $bloquear]);
            flash_set($r['codigo'] === 0 ? 'success' : 'error', $r['mensaje']);
        }
        redirect('admin/cajeros.php');
    }

    if ($accion === 'crear') {
        $v['nombre'] = post('nombre');
        $v['usuario'] = post('usuario');
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
        $confirmar = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

        if (mb_strlen($v['nombre']) < 3 || mb_strlen($v['nombre']) > 100) {
            $errores[] = 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $v['usuario'])) {
            $errores[] = 'El usuario debe tener de 3 a 30 caracteres (letras, números, punto, guion o guion bajo).';
        }
        if (($msg = error_clave($clave)) !== null) {
            $errores[] = str_replace('contraseña', 'clave', $msg);
        }
        if ($clave !== $confirmar) {
            $errores[] = 'La confirmación de clave no coincide.';
        }
        if (!$errores) {
            $r = sp_operacion('sp_cajero_crear', 'sss', [$v['nombre'], $v['usuario'], password_hash($clave, PASSWORD_BCRYPT)]);
            if ($r['codigo'] === 0) {
                flash_set('success', $r['mensaje']);
                redirect('admin/cajeros.php');
            }
            $errores[] = $r['mensaje'];
        }
    }
}

$cajeros = sp_call('sp_cajero_listar');

render_header('Gestión de cajeros', $usuario, 'cajeros');
?>
<h1>Gestión de usuarios de cajeros</h1>

<section class="card">
    <h2>Listado de cajeros</h2>
    <?php if (!$cajeros): ?>
        <p class="muted">Todavía no hay cajeros registrados. Cree el primero con el formulario de abajo.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-responsive">
            <thead><tr><th>Nombre completo</th><th>Usuario</th><th>Creado</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($cajeros as $c): $bloq = (int) $c['bloqueado'] === 1; ?>
                <tr>
                    <td data-label="Nombre"><?= e($c['nombre_completo']) ?></td>
                    <td data-label="Usuario"><?= e($c['username']) ?></td>
                    <td data-label="Creado"><?= e(fmt_fecha($c['fecha_creacion'])) ?></td>
                    <td data-label="Estado"><span class="badge <?= $bloq ? 'badge-danger' : 'badge-ok' ?>"><?= $bloq ? 'Bloqueado' : 'Activo' ?></span></td>
                    <td data-label="Acción">
                        <form method="post" data-loading>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id_usuario" value="<?= (int) $c['id_usuario'] ?>">
                            <input type="hidden" name="bloquear" value="<?= $bloq ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= $bloq ? 'btn-secondary' : 'btn-danger' ?>"
                                    aria-label="<?= $bloq ? 'Desbloquear' : 'Bloquear' ?> a <?= e($c['username']) ?>">
                                <?= $bloq ? 'Desbloquear' : 'Bloquear' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="card narrow-form">
    <h2>Agregar nuevo cajero</h2>
    <?php foreach ($errores as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" data-loading>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="crear">
        <?= campo('nombre', 'Nombre completo', ['type' => 'text', 'required' => true, 'minlength' => '3', 'maxlength' => '100'], '', $v['nombre']) ?>
        <?= campo('usuario', 'Usuario', [
            'type' => 'text', 'required' => true, 'minlength' => '3', 'maxlength' => '30',
            'pattern' => '[A-Za-z0-9_.\-]{3,30}', 'autocomplete' => 'off',
            'title' => 'Letras, números, punto, guion o guion bajo (3 a 30 caracteres)',
        ], 'Letras, números, punto, guion o guion bajo.', $v['usuario']) ?>
        <?= campo('clave', 'Clave', [
            'type' => 'password', 'required' => true, 'minlength' => '8', 'maxlength' => '72',
            'autocomplete' => 'new-password',
        ], 'Mínimo 8 caracteres, con al menos una letra y un número.') ?>
        <?= campo('confirmar', 'Confirmación de clave', [
            'type' => 'password', 'required' => true, 'minlength' => '8', 'maxlength' => '72',
            'autocomplete' => 'new-password', 'data-confirmar' => 'clave',
        ]) ?>
        <button type="submit" class="btn btn-primary">Crear cajero</button>
    </form>
</section>
<?php render_footer();
