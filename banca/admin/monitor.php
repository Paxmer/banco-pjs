<?php
require __DIR__ . '/../includes/bootstrap.php';
$usuario = require_rol('ADMIN');

$periodo = ($_GET['periodo'] ?? 'hoy') === 'total' ? 'TOTAL' : 'HOY';
$s = sp_call('sp_monitor_estadisticas', 's', [$periodo])[0];

$montoDep = (float) $s['monto_depositos'];
$montoRet = (float) $s['monto_retiros'];
$maximo = max($montoDep, $montoRet, 0.01);

// Gráfica de barras en SVG (sin librerías externas): datos 100 % de la BD.
$altoMax = 220;
$altoDep = (int) round($altoMax * $montoDep / $maximo);
$altoRet = (int) round($altoMax * $montoRet / $maximo);
$base = 260;

render_header('Monitor de transferencias', $usuario, 'monitor');
?>
<h1>Monitor de transferencias</h1>

<div class="tabs" role="tablist" aria-label="Periodo">
    <a href="?periodo=hoy" class="tab<?= $periodo === 'HOY' ? ' activo' : '' ?>">Hoy (<?= e(date('d/m/Y')) ?>)</a>
    <a href="?periodo=total" class="tab<?= $periodo === 'TOTAL' ? ' activo' : '' ?>">Histórico total</a>
</div>

<section class="card">
    <h2>Resumen <?= $periodo === 'HOY' ? 'del día' : 'histórico' ?></h2>
    <div class="stats">
        <div class="stat"><span class="stat-num"><?= (int) $s['cuentas_creadas'] ?></span><span class="stat-lbl">Cuentas creadas<?= $periodo === 'HOY' ? ' en el día' : '' ?></span></div>
        <div class="stat"><span class="stat-num"><?= (int) $s['usuarios_cliente'] ?></span><span class="stat-lbl">Usuarios cliente registrados<?= $periodo === 'HOY' ? ' en el día' : '' ?></span></div>
        <div class="stat"><span class="stat-num"><?= (int) $s['transacciones'] ?></span><span class="stat-lbl">Transacciones<small>(depósitos + retiros + transferencias)</small></span></div>
        <div class="stat"><span class="stat-num"><?= (int) $s['depositos'] ?></span><span class="stat-lbl">Depósitos</span></div>
        <div class="stat"><span class="stat-num"><?= (int) $s['retiros'] ?></span><span class="stat-lbl">Retiros</span></div>
        <div class="stat"><span class="stat-num"><?= (int) $s['transferencias'] ?></span><span class="stat-lbl">Transferencias</span></div>
    </div>
    <p class="muted small">Los usuarios de cajero y administrador no se cuentan como usuarios cliente.</p>
</section>

<section class="card">
    <h2>Depósitos vs. retiros (monto en quetzales)</h2>
    <figure class="chart">
        <svg viewBox="0 0 520 320" role="img" aria-labelledby="graf-t graf-d">
            <title id="graf-t">Monto total en depósitos contra monto total en retiros</title>
            <desc id="graf-d">Depósitos: <?= e(fmt_q($montoDep)) ?>. Retiros: <?= e(fmt_q($montoRet)) ?>.</desc>
            <line x1="40" y1="<?= $base ?>" x2="500" y2="<?= $base ?>" class="chart-eje"/>
            <line x1="40" y1="20" x2="40" y2="<?= $base ?>" class="chart-eje"/>
            <rect x="110" y="<?= $base - $altoDep ?>" width="120" height="<?= $altoDep ?>" class="barra-dep"/>
            <rect x="310" y="<?= $base - $altoRet ?>" width="120" height="<?= $altoRet ?>" class="barra-ret"/>
            <text x="170" y="<?= $base - $altoDep - 8 ?>" text-anchor="middle" class="chart-val"><?= e(fmt_q($montoDep)) ?></text>
            <text x="370" y="<?= $base - $altoRet - 8 ?>" text-anchor="middle" class="chart-val"><?= e(fmt_q($montoRet)) ?></text>
            <text x="170" y="285" text-anchor="middle" class="chart-lbl">Depósitos</text>
            <text x="370" y="285" text-anchor="middle" class="chart-lbl">Retiros</text>
        </svg>
        <figcaption class="muted small">Periodo: <?= $periodo === 'HOY' ? 'hoy' : 'histórico total' ?>. La escala se ajusta al valor mayor.</figcaption>
    </figure>
    <table class="table">
        <thead><tr><th>Concepto</th><th class="num">Cantidad</th><th class="num">Monto total</th></tr></thead>
        <tbody>
            <tr><td>Depósitos</td><td class="num"><?= (int) $s['depositos'] ?></td><td class="num"><?= e(fmt_q($montoDep)) ?></td></tr>
            <tr><td>Retiros</td><td class="num"><?= (int) $s['retiros'] ?></td><td class="num"><?= e(fmt_q($montoRet)) ?></td></tr>
        </tbody>
    </table>
</section>
<?php render_footer();
