<?php
/**
 * ANALIZADOR DE LOGS DE APACHE (administración, mantenimiento y soporte del servidor web)
 *
 * Lee el access.log (formato "common" o "combined") y el error.log reales de Apache y genera:
 *   - un resumen por consola
 *   - un reporte HTML autocontenido (se puede subir a un hosting para verlo en línea)
 *
 * Responde: ¿cuántas solicitudes fueron OK?, ¿cuáles son las páginas más visitadas?,
 * ¿qué páginas dan error?, ¿qué códigos de estado hay?, ¿cuándo hay más tráfico?, etc.
 *
 * Uso:
 *   php analizar_log_apache.php [access.log] [opciones]
 *     --error-log=RUTA   error.log de Apache (por defecto: apache_error.log junto al access.log)
 *     --prefijo=/banca   sólo cuenta las URLs que empiezan con ese prefijo (por defecto /banca)
 *     --todo             analiza todas las URLs del servidor (ignora --prefijo)
 *     --salida=ARCHIVO   reporte HTML de salida (por defecto ../documentacion/reporte_apache.html)
 *
 * Por defecto lee C:\wamp64\logs\access.log (WampServer).
 */
if (PHP_SAPI !== 'cli') { exit('Ejecutar desde la línea de comandos: php analizar_log_apache.php'); }
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------- argumentos
$ruta = 'C:\\wamp64\\logs\\access.log';
$opt = ['error-log' => null, 'prefijo' => '/banca', 'salida' => __DIR__ . '/../documentacion/reporte_apache.html', 'todo' => false];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--todo') { $opt['todo'] = true; }
    elseif (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m) && array_key_exists($m[1], $opt)) { $opt[$m[1]] = $m[2]; }
    elseif ($a[0] !== '-') { $ruta = $a; }
}
if (!is_readable($ruta)) { fwrite(STDERR, "No se puede leer el log: $ruta\n"); exit(1); }
$errorLog = $opt['error-log'] ?? dirname($ruta) . DIRECTORY_SEPARATOR . 'apache_error.log';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
const EXT_ESTATICAS = '/\.(css|js|svg|png|jpe?g|gif|ico|woff2?|ttf|map|webp)$/i';

// ---------------------------------------------------------------- lectura del access.log
$total = 0; $ignoradas = 0; $malformadas = 0; $bytes = 0;
$estados = []; $metodos = []; $ips = []; $porHora = []; $porDia = [];
$paginas = []; $estaticos = []; $errores = []; $primera = null; $ultima = null;
$reg = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(?:(\S+) (\S+) [^"]*|[^"]*)" (\d{3}) (\d+|-)/';

$fh = fopen($ruta, 'r');
while (($linea = fgets($fh)) !== false) {
    $linea = rtrim($linea);
    if ($linea === '' || $linea[0] === '-') { continue; }          // línea vacía o aviso de WampServer
    if (!preg_match($reg, $linea, $m) || $m[3] === '') { $malformadas++; continue; }
    [, $ip, $fechaTxt, $metodo, $uri, $estado, $b] = $m;
    $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
    if (!$opt['todo'] && strncmp($path, $opt['prefijo'], strlen($opt['prefijo'])) !== 0) { $ignoradas++; continue; }

    $dt = DateTime::createFromFormat('d/M/Y:H:i:s O', $fechaTxt);
    $total++; $bytes += ($b === '-') ? 0 : (int) $b;
    $estados[$estado] = ($estados[$estado] ?? 0) + 1;
    $metodos[$metodo] = ($metodos[$metodo] ?? 0) + 1;
    $ips[$ip] = ($ips[$ip] ?? 0) + 1;
    if ($dt) {
        $porHora[$dt->format('H')] = ($porHora[$dt->format('H')] ?? 0) + 1;
        $porDia[$dt->format('Y-m-d')] = ($porDia[$dt->format('Y-m-d')] ?? 0) + 1;
        $primera = $primera === null || $dt < $primera ? $dt : $primera;
        $ultima = $ultima === null || $dt > $ultima ? $dt : $ultima;
    }
    if (preg_match(EXT_ESTATICAS, $path)) { $estaticos[$path] = ($estaticos[$path] ?? 0) + 1; }
    else { $paginas[$path] = ($paginas[$path] ?? 0) + 1; }
    if ((int) $estado >= 400) { $k = "$estado $path"; $errores[$k] = ($errores[$k] ?? 0) + 1; }
}
fclose($fh);
ksort($estados); ksort($porHora); ksort($porDia); arsort($paginas); arsort($estaticos); arsort($errores); arsort($ips); arsort($metodos);

$clase = ['2' => 0, '3' => 0, '4' => 0, '5' => 0, 'otros' => 0];
foreach ($estados as $e => $n) { $c = ((string) $e)[0]; isset($clase[$c]) ? $clase[$c] += $n : $clase['otros'] += $n; }
$pct = fn(int $n) => $total ? number_format($n * 100 / $total, 1) . ' %' : '0 %';

// ---------------------------------------------------------------- error.log de Apache
$errTotal = 0; $errTipos = []; $errMensajes = [];
if (is_readable($errorLog)) {
    foreach (file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (!preg_match('/^\[[^\]]+\] \[([^\]]+)\] (?:\[pid[^\]]*\] )?(?:\[client [^\]]+\] )?(.*)$/', $l, $m)) { continue; }
        if (!$opt['todo'] && stripos($l, $opt['prefijo']) === false && stripos($l, str_replace('/', '\\', $opt['prefijo'])) === false) { continue; }
        $errTotal++;
        $errTipos[$m[1]] = ($errTipos[$m[1]] ?? 0) + 1;
        $msg = preg_replace('/\b[A-Z]:[\/\\\\]\S*/', '<ruta>', $m[2]);
        $msg = preg_replace('/\d{2,}/', 'N', mb_substr($msg, 0, 110));
        $errMensajes[$msg] = ($errMensajes[$msg] ?? 0) + 1;
    }
    arsort($errTipos); arsort($errMensajes);
}

// ---------------------------------------------------------------- consola
$periodo = $primera ? $primera->format('d/m/Y H:i') . '  a  ' . $ultima->format('d/m/Y H:i') : 'sin datos';
echo "ANALISIS DE LOG DE APACHE\n";
echo "Archivo      : $ruta\n";
echo "Filtro       : " . ($opt['todo'] ? 'todas las URLs' : "URLs que empiezan con {$opt['prefijo']}") . " ($ignoradas líneas fuera del filtro, $malformadas conexiones vacías o ilegibles, p. ej. 408)\n";
echo "Periodo      : $periodo\n";
echo "Solicitudes  : $total   (datos transferidos: " . number_format($bytes / 1024, 1) . " KB)\n";
echo "OK (2xx)     : {$clase['2']} (" . $pct($clase['2']) . ")\n";
echo "Redirec. 3xx : {$clase['3']} (" . $pct($clase['3']) . ")\n";
echo "Error cli 4xx: {$clase['4']} (" . $pct($clase['4']) . ")\n";
echo "Error srv 5xx: {$clase['5']} (" . $pct($clase['5']) . ")\n";
echo "Páginas más visitadas:\n";
foreach (array_slice($paginas, 0, 10, true) as $p => $n) { printf("  %5d  %s\n", $n, $p); }
echo "Páginas/recursos con error:\n";
foreach (array_slice($errores, 0, 10, true) as $p => $n) { printf("  %5d  %s\n", $n, $p); }

// ---------------------------------------------------------------- reporte HTML
function tabla(array $filas, array $cabeceras, int $limite = 15): string
{
    $h = '<table><thead><tr>';
    foreach ($cabeceras as $c) { $h .= '<th>' . h($c) . '</th>'; }
    $h .= '</tr></thead><tbody>';
    $i = 0;
    foreach ($filas as $fila) {
        if (++$i > $limite) { break; }
        $h .= '<tr>';
        foreach ($fila as $k => $v) { $h .= '<td' . (is_int($v) || is_float($v) ? ' class="n"' : '') . '>' . h((string) $v) . '</td>'; }
        $h .= '</tr>';
    }
    if (!$filas) { $h .= '<tr><td colspan="' . count($cabeceras) . '"><em>Sin datos</em></td></tr>'; }
    return $h . '</tbody></table>';
}
function barras(array $datos): string
{
    $max = max($datos ?: [1]);
    $h = '<div class="barras">';
    foreach ($datos as $k => $v) {
        $w = $max ? round($v * 100 / $max) : 0;
        $h .= '<div class="fila"><span class="et">' . h((string) $k) . '</span><span class="bar"><i style="width:' . $w . '%"></i></span><span class="v">' . $v . '</span></div>';
    }
    return $h . '</div>';
}
$nombresEstado = ['200' => 'OK', '201' => 'Creado', '204' => 'Sin contenido', '206' => 'Contenido parcial', '301' => 'Movido permanentemente', '302' => 'Redirección (Found)',
    '304' => 'No modificado (caché)', '400' => 'Solicitud incorrecta', '401' => 'No autenticado', '403' => 'Prohibido', '404' => 'No encontrado', '405' => 'Método no permitido',
    '408' => 'Tiempo agotado', '500' => 'Error interno del servidor', '503' => 'Servicio no disponible'];
$filasEstado = []; foreach ($estados as $e => $n) { $filasEstado[] = [(string) $e, $nombresEstado[$e] ?? '-', $n, $pct($n)]; }
$filasPag = []; foreach ($paginas as $p => $n) { $filasPag[] = [$p, $n]; }
$filasEst = []; foreach ($estaticos as $p => $n) { $filasEst[] = [$p, $n]; }
$filasErr = []; foreach ($errores as $k => $n) { [$e, $p] = explode(' ', $k, 2); $filasErr[] = [$e, $nombresEstado[$e] ?? '-', $p, $n]; }
$filasIp = []; foreach ($ips as $ip => $n) { $filasIp[] = [$ip, $n]; }
$filasMet = []; foreach ($metodos as $mm => $n) { $filasMet[] = [$mm, $n]; }
$filasEL = []; foreach ($errMensajes as $mm => $n) { $filasEL[] = [$mm, $n]; }
$filasELT = []; foreach ($errTipos as $mm => $n) { $filasELT[] = [$mm, $n]; }

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
  . '<title>Reporte de logs de Apache | Banco PJS</title><style>
body{font-family:"Segoe UI",system-ui,Arial,sans-serif;margin:0;background:#f2f5f9;color:#1c2733}
header{background:#0b2a4a;color:#fff;padding:20px 16px}header h1{margin:0;font-size:1.4rem}header p{margin:4px 0 0;color:#b9c9dc;font-size:.9rem}
main{max-width:1000px;margin:0 auto;padding:16px}section{background:#fff;border:1px solid #d6dde6;border-radius:12px;padding:16px;margin:16px 0}
h2{margin:0 0 10px;font-size:1.1rem;color:#0b2a4a}.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.kpi{background:#f6f9fc;border:1px solid #d6dde6;border-radius:10px;padding:12px}.kpi b{display:block;font-size:1.7rem;color:#0a5f67}
.kpi.ok b{color:#1d8348}.kpi.err b{color:#b3261e}.kpi small{color:#5d6b7a}
table{width:100%;border-collapse:collapse;font-size:.9rem}th,td{padding:7px 8px;border-bottom:1px solid #e3e8ee;text-align:left;word-break:break-all}
th{background:#eef2f7;font-size:.8rem;text-transform:uppercase}td.n{text-align:right}.tw{overflow-x:auto}
.barras .fila{display:grid;grid-template-columns:48px 1fr 48px;gap:8px;align-items:center;margin:3px 0;font-size:.85rem}
.bar{background:#e3eaf2;border-radius:4px;height:14px;display:block}.bar i{display:block;height:14px;background:#0e7c86;border-radius:4px}.v{text-align:right}
footer{text-align:center;color:#5d6b7a;font-size:.8rem;padding:16px}</style></head><body>'
  . '<header><h1>Reporte de análisis de logs de Apache</h1><p>Sistema de Banca en Línea &middot; generado el ' . gmdate('d/m/Y H:i') . ' UTC con herramientas/analizar_log_apache.php</p></header><main>'
  . '<section><h2>Resumen</h2><p>Archivo analizado: <code>' . h(basename($ruta)) . '</code> &middot; Filtro: '
  . ($opt['todo'] ? 'todas las URLs' : 'URLs que empiezan con <code>' . h($opt['prefijo']) . '</code>') . ' &middot; Periodo: ' . h($periodo) . '</p><div class="kpis">'
  . '<div class="kpi"><b>' . $total . '</b><small>Solicitudes totales</small></div>'
  . '<div class="kpi ok"><b>' . $clase['2'] . '</b><small>OK (2xx) &middot; ' . $pct($clase['2']) . '</small></div>'
  . '<div class="kpi"><b>' . $clase['3'] . '</b><small>Redirecciones (3xx) &middot; ' . $pct($clase['3']) . '</small></div>'
  . '<div class="kpi err"><b>' . $clase['4'] . '</b><small>Errores de cliente (4xx) &middot; ' . $pct($clase['4']) . '</small></div>'
  . '<div class="kpi err"><b>' . $clase['5'] . '</b><small>Errores de servidor (5xx) &middot; ' . $pct($clase['5']) . '</small></div>'
  . '<div class="kpi"><b>' . number_format($bytes / 1024, 1) . ' KB</b><small>Datos transferidos</small></div></div></section>'
  . '<section><h2>Solicitudes por código de estado</h2><div class="tw">' . tabla($filasEstado, ['Código', 'Significado', 'Solicitudes', '%'], 50) . '</div></section>'
  . '<section><h2>Páginas más visitadas</h2><div class="tw">' . tabla($filasPag, ['Página (URL)', 'Solicitudes'], 15) . '</div></section>'
  . '<section><h2>Páginas que dan error (4xx / 5xx)</h2><div class="tw">' . tabla($filasErr, ['Código', 'Significado', 'URL', 'Veces'], 20)
  . '</div><p><small>Los 302 son redirecciones normales (por ejemplo, tras iniciar sesión). Los 403 indican acceso bloqueado a archivos internos o a un panel de otro rol; los 400, formularios sin token CSRF válido.</small></p></section>'
  . '<section><h2>Recursos estáticos más solicitados</h2><div class="tw">' . tabla($filasEst, ['Recurso', 'Solicitudes'], 10) . '</div></section>'
  . '<section><h2>Tráfico por hora del día</h2>' . barras($porHora) . '</section>'
  . '<section><h2>Tráfico por día</h2>' . barras($porDia) . '</section>'
  . '<section><h2>Métodos HTTP</h2><div class="tw">' . tabla($filasMet, ['Método', 'Solicitudes'], 10) . '</div></section>'
  . '<section><h2>Direcciones IP de origen</h2><div class="tw">' . tabla($filasIp, ['IP', 'Solicitudes'], 10) . '</div></section>'
  . '<section><h2>error.log de Apache (' . $errTotal . ' registros' . ($opt['todo'] ? '' : ' relacionados con la aplicación') . ')</h2><div class="tw">'
  . tabla($filasELT, ['Módulo:nivel', 'Veces'], 10) . '</div><div class="tw" style="margin-top:10px">' . tabla($filasEL, ['Mensaje (rutas y números normalizados)', 'Veces'], 10) . '</div></section>'
  . '</main><footer>Banco PJS &middot; Proyecto de Desarrollo Web &middot; Universidad Mariano Gálvez de Guatemala</footer></body></html>';

$salida = $opt['salida'];
if (!is_dir(dirname($salida))) { mkdir(dirname($salida), 0777, true); }
file_put_contents($salida, $html);
// Cifras en JSON: las usa generar_documentacion.php para citar datos reales en el documento.
file_put_contents(dirname($salida) . '/reporte_apache.json', json_encode([
    'generado' => date('c'), 'archivo' => basename($ruta), 'periodo' => $periodo, 'total' => $total,
    'ok_2xx' => $clase['2'], 'redir_3xx' => $clase['3'], 'error_4xx' => $clase['4'], 'error_5xx' => $clase['5'],
    'estados' => $estados, 'paginas_top' => array_slice($paginas, 0, 8, true), 'errores_top' => array_slice($errores, 0, 8, true),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nReporte HTML generado: " . realpath($salida) . "\n";
