<?php
/**
 * PRUEBAS AUTOMÁTICAS DE EXTREMO A EXTREMO (HTTP real contra Apache + MySQL).
 *
 * Uso (WampServer encendido, BD instalada con 01_esquema_y_procedimientos.sql):
 *     php probar_sistema.php [url_base] [usuario_root_mysql] [clave_root_mysql]
 *   por defecto:  http://localhost/banca/   root   (sin clave)
 *
 * - Usa el administrador de fábrica (admin / Admin2026!) y crea sus propios
 *   datos con números únicos: NO borra ni modifica datos existentes.
 * - Las verificaciones de saldos/tablas se hacen con una conexión root, para
 *   comprobar que los datos realmente quedaron en MySQL.
 * - La prueba de ROLLBACK crea un trigger temporal (sólo local) que fuerza un
 *   fallo en medio de una transferencia, y lo elimina al terminar.
 */
mb_internal_encoding('UTF-8');
$BASE = rtrim($argv[1] ?? 'http://localhost/banca/', '/') . '/';
$ROOT_USER = $argv[2] ?? 'root';
$ROOT_PASS = $argv[3] ?? '';

// ------------------------------------------------------------------ utilidades
$pasaron = 0; $fallaron = []; $seccion = '';
function seccion(string $t): void { global $seccion; $seccion = $t; echo "\n=== $t ===\n"; }
function ok(bool $c, string $nombre, string $detalle = ''): void
{
    global $pasaron, $fallaron, $seccion;
    if ($c) { $pasaron++; echo "  [PASS] $nombre\n"; }
    else { $fallaron[] = "$seccion :: $nombre"; echo "  [FAIL] $nombre" . ($detalle ? "  -> $detalle" : '') . "\n"; }
}
function contiene(string $texto, string $aguja): bool { return mb_stripos(html_entity_decode($texto, ENT_QUOTES, 'UTF-8'), $aguja) !== false; }

final class Http
{
    private string $jar;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'bk'); }
    public function req(string $metodo, string $ruta, array $datos = [], bool $seguir = true): array
    {
        $ch = curl_init($this->base . ltrim($ruta, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => $seguir,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30,
        ]);
        if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos)); }
        $resp = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $cab = substr((string) $resp, 0, $info['header_size']);
        $loc = preg_match('/^Location:\s*(\S+)/mi', $cab, $m) ? $m[1] : '';
        return ['status' => $info['http_code'], 'url' => $info['url'], 'body' => substr((string) $resp, $info['header_size']), 'headers' => $cab, 'location' => $loc];
    }
    public function get(string $ruta, bool $seguir = true): array { return $this->req('GET', $ruta, [], $seguir); }
    /** POST con token CSRF tomado de $paginaForm (por defecto la misma ruta). */
    public function post(string $ruta, array $datos, ?string $paginaForm = null, bool $seguir = true): array
    {
        if (!array_key_exists('csrf', $datos)) {
            $f = $this->get($paginaForm ?? $ruta);
            $datos['csrf'] = preg_match('/name="csrf" value="([a-f0-9]+)"/', $f['body'], $m) ? $m[1] : '';
        }
        return $this->req('POST', $ruta, $datos, $seguir);
    }
    public function mensajes(array $r): string
    {
        preg_match_all('/<div class="alert alert-\w+"[^>]*>(.*?)<\/div>/s', $r['body'], $m);
        return html_entity_decode(implode(' | ', $m[1]), ENT_QUOTES, 'UTF-8');
    }
}

function nuevo(): Http { global $BASE; return new Http($BASE); }
function unico(int $digitos): string { $s = ''; for ($i = 0; $i < $digitos; $i++) { $s .= random_int($i ? 0 : 1, 9); } return $s; }

mysqli_report(MYSQLI_REPORT_OFF);
$root = new mysqli('localhost', $ROOT_USER, $ROOT_PASS, 'banca_umg');
if ($root->connect_errno) { exit("No se pudo conectar como root a banca_umg: {$root->connect_error}\n"); }
$root->set_charset('utf8mb4');
$root->query("SET time_zone = '-06:00'");
function q(string $sql): array { global $root; $r = $root->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : []; }
function saldo(string $num): string { return q("SELECT saldo FROM cuentas WHERE numero_cuenta='$num'")[0]['saldo'] ?? 'NULL'; }
function stats(): array { global $root; $root->query("SET @x=1"); $r = $root->query("CALL sp_monitor_estadisticas('HOY')"); $f = $r->fetch_assoc(); $r->free(); while ($root->more_results()) { $root->next_result(); } return $f; }

$sfx = substr((string) time(), -6) . random_int(10, 99);
$passAdmin = 'Admin2026!';
$cajeroUser = "qa_cajero_$sfx"; $cajeroPass = 'Cajero2026!';
$ctaA = '9' . unico(7); $ctaB = '9' . unico(7); $ctaC = '9' . unico(7);
$dpiA = unico(13); $dpiB = unico(13); $dpiC = unico(13);
$mailA = "qa_a_$sfx@PJS.com.gt"; $mailB = "qa_b_$sfx@PJS.com.gt";
$passCli = 'Cliente2026!';

// ============================================================ AUTENTICACIÓN
seccion('AUTENTICACION - ADMINISTRADOR');
$adm = nuevo();
$r = $adm->post('login_admin.php', ['usuario' => 'admin', 'clave' => 'mala-clave-1']);
ok(contiene($adm->mensajes($r), 'Usuario o contraseña incorrectos'), 'login incorrecto de administrador muestra mensaje');
$r = $adm->post('login_admin.php', ['usuario' => 'admin', 'clave' => $passAdmin]);
ok(str_ends_with($r['url'], 'admin/index.php') && $r['status'] === 200, 'login correcto de administrador redirige al panel', $r['url']);
$r = $adm->get('admin/cajeros.php');
ok($r['status'] === 200 && contiene($r['body'], 'Gestión de usuarios de cajeros'), 'administrador autenticado accede a gestión de cajeros');

seccion('GESTION DE CAJEROS (ADMIN)');
$st0 = stats();
$r = $adm->post('admin/cajeros.php', ['accion' => 'crear', 'nombre' => 'QA Cajero ' . $sfx, 'usuario' => $cajeroUser, 'clave' => $cajeroPass, 'confirmar' => 'otra-clave-9']);
ok(contiene($adm->mensajes($r), 'confirmación de clave no coincide'), 'crear cajero: confirmación distinta se rechaza');
$r = $adm->post('admin/cajeros.php', ['accion' => 'crear', 'nombre' => 'QA Cajero ' . $sfx, 'usuario' => 'a b', 'clave' => $cajeroPass, 'confirmar' => $cajeroPass]);
ok(contiene($adm->mensajes($r), 'El usuario debe tener'), 'crear cajero: usuario con formato inválido se rechaza');
$r = $adm->post('admin/cajeros.php', ['accion' => 'crear', 'nombre' => 'QA Cajero ' . $sfx, 'usuario' => $cajeroUser, 'clave' => '1234', 'confirmar' => '1234']);
ok(contiene($adm->mensajes($r), 'entre 8 y 72'), 'crear cajero: clave débil se rechaza');
$r = $adm->post('admin/cajeros.php', ['accion' => 'crear', 'nombre' => 'QA Cajero ' . $sfx, 'usuario' => $cajeroUser, 'clave' => $cajeroPass, 'confirmar' => $cajeroPass]);
ok(contiene($adm->mensajes($r), 'Cajero creado correctamente'), 'crear cajero correcto');
$r = $adm->post('admin/cajeros.php', ['accion' => 'crear', 'nombre' => 'Otro Nombre', 'usuario' => $cajeroUser, 'clave' => $cajeroPass, 'confirmar' => $cajeroPass]);
ok(contiene($adm->mensajes($r), 'ya existe'), 'crear cajero: usuario duplicado se rechaza');
$fila = q("SELECT id_usuario, password_hash, rol FROM usuarios WHERE username='$cajeroUser'")[0] ?? [];
ok(($fila['rol'] ?? '') === 'CAJERO' && str_starts_with($fila['password_hash'] ?? '', '$2y$') && !str_contains($fila['password_hash'], $cajeroPass),
   'la clave del cajero se guardó con hash bcrypt (no en texto plano)');
$idCajero = (int) ($fila['id_usuario'] ?? 0);

seccion('AUTENTICACION - CAJERO');
$caj = nuevo();
$r = $caj->post('login_cajero.php', ['usuario' => $cajeroUser, 'clave' => 'mala-clave-1']);
ok(contiene($caj->mensajes($r), 'Usuario o contraseña incorrectos'), 'login incorrecto de cajero muestra mensaje');
$r = $caj->post('login_cajero.php', ['usuario' => 'admin', 'clave' => $passAdmin]);
ok(contiene($caj->mensajes($r), 'Usuario o contraseña incorrectos'), 'el administrador NO puede entrar por el login de cajero');
$r = $caj->post('login_cajero.php', ['usuario' => $cajeroUser, 'clave' => $cajeroPass]);
ok(str_ends_with($r['url'], 'cajero/index.php') && $r['status'] === 200, 'login correcto de cajero redirige al panel', $r['url']);

seccion('AUTORIZACION - ACCESO DIRECTO POR URL');
$anon = nuevo();
foreach (['admin/index.php', 'admin/cajeros.php', 'admin/monitor.php'] as $p) {
    $r = $anon->get($p, false);
    ok($r['status'] === 302 && str_contains($r['location'], 'login_admin.php'), "sin sesión: $p redirige al login de administrador");
}
foreach (['cajero/index.php', 'cajero/crear_cuenta.php', 'cajero/deposito.php', 'cajero/retiro.php'] as $p) {
    $r = $anon->get($p, false);
    ok($r['status'] === 302 && str_contains($r['location'], 'login_cajero.php'), "sin sesión: $p redirige al login de cajero");
}
foreach (['cliente/index.php', 'cliente/terceros.php', 'cliente/transferir.php', 'cliente/estado_cuenta.php'] as $p) {
    $r = $anon->get($p, false);
    ok($r['status'] === 302 && str_contains($r['location'], 'login_usuario.php'), "sin sesión: $p redirige al login de usuario");
}
foreach (['admin/index.php', 'admin/cajeros.php', 'admin/monitor.php'] as $p) {
    $r = $caj->get($p);
    ok($r['status'] === 403, "un CAJERO no puede abrir $p escribiendo la URL (403)", (string) $r['status']);
}

// ============================================================ CUENTAS
seccion('CAJERO - CREAR CUENTA');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Cliente A $sfx", 'numero' => $ctaA, 'dpi' => $dpiA, 'monto' => '1000.00']);
ok(contiene($caj->mensajes($r), 'creada correctamente') && saldo($ctaA) === '1000.00', 'creación correcta (saldo inicial 1000.00 en MySQL)', $caj->mensajes($r));
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Cliente A2 $sfx", 'numero' => $ctaA, 'dpi' => unico(13), 'monto' => '10']);
ok(contiene($caj->mensajes($r), 'Ya existe una cuenta con ese número'), 'cuenta duplicada se rechaza');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => 'QA', 'numero' => '9' . unico(7), 'dpi' => '123', 'monto' => '10']);
ok(contiene($caj->mensajes($r), 'El DPI debe tener exactamente 13 dígitos'), 'DPI inválido se rechaza');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Neg $sfx", 'numero' => '9' . unico(7), 'dpi' => unico(13), 'monto' => '-50']);
ok(contiene($caj->mensajes($r), 'monto inicial no es válido'), 'monto inicial negativo se rechaza');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Letras $sfx", 'numero' => 'ABC123', 'dpi' => unico(13), 'monto' => 'abc']);
ok(contiene($caj->mensajes($r), 'número de cuenta debe contener') && contiene($caj->mensajes($r), 'monto inicial no es válido'), 'número de cuenta y monto con letras se rechazan');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Cliente B $sfx", 'numero' => $ctaB, 'dpi' => $dpiB, 'monto' => '500']);
ok(saldo($ctaB) === '500.00', 'segunda cuenta creada (B = 500.00)');
$r = $caj->post('cajero/crear_cuenta.php', ['nombre' => "QA Cliente C $sfx", 'numero' => $ctaC, 'dpi' => $dpiC, 'monto' => '0']);
ok(saldo($ctaC) === '0.00', 'cuenta con monto inicial 0 se acepta (C = 0.00)');

seccion('CAJERO - DEPOSITOS');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaA, 'monto' => '250.50']);
ok(contiene($caj->mensajes($r), 'Depósito de Q 250.50') && saldo($ctaA) === '1250.50', 'depósito válido actualiza saldo (1250.50)', $caj->mensajes($r));
$r = $caj->post('cajero/deposito.php', ['numero' => '99999999', 'monto' => '10']);
ok(contiene($caj->mensajes($r), 'La cuenta no existe'), 'depósito a cuenta inexistente se rechaza');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaA, 'monto' => 'abc']);
ok(contiene($caj->mensajes($r), 'monto no es válido'), 'depósito con monto no numérico se rechaza');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaA, 'monto' => '0']);
ok(contiene($caj->mensajes($r), 'mayor que cero'), 'depósito de monto cero se rechaza');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaA, 'monto' => '-10']);
ok(contiene($caj->mensajes($r), 'monto no es válido'), 'depósito de monto negativo se rechaza');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaA, 'monto' => '10.999']);
ok(contiene($caj->mensajes($r), 'monto no es válido'), 'depósito con 3 decimales se rechaza');
ok(saldo($ctaA) === '1250.50', 'los intentos inválidos no modificaron el saldo');

seccion('CAJERO - RETIROS');
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaA, 'monto' => '50.50']);
ok(contiene($caj->mensajes($r), 'Retiro de Q 50.50') && saldo($ctaA) === '1200.00', 'retiro válido actualiza saldo (1200.00)', $caj->mensajes($r));
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaB, 'monto' => '500.01']);
ok(contiene($caj->mensajes($r), 'Saldo insuficiente') && saldo($ctaB) === '500.00', 'retiro con saldo insuficiente se rechaza y no cambia el saldo');
$r = $caj->post('cajero/retiro.php', ['numero' => '88888888', 'monto' => '5']);
ok(contiene($caj->mensajes($r), 'La cuenta no existe'), 'retiro de cuenta inexistente se rechaza');
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaB, 'monto' => '-1']);
ok(contiene($caj->mensajes($r), 'monto no es válido'), 'retiro con monto negativo se rechaza');
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaB, 'monto' => '0']);
ok(contiene($caj->mensajes($r), 'mayor que cero'), 'retiro de monto cero se rechaza');
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaB, 'monto' => '500.00']);
ok(saldo($ctaB) === '0.00', 'retirar exactamente todo el saldo es válido (B = 0.00) y nunca queda negativo');
$r = $caj->post('cajero/deposito.php', ['numero' => $ctaB, 'monto' => '500.00']);
ok(saldo($ctaB) === '500.00', 'B repuesto a 500.00');

// ============================================================ REGISTRO
seccion('REGISTRO DE USUARIO CLIENTE');
$reg = nuevo();
$r = $reg->post('registro.php', ['cuenta' => '77777777', 'correo' => $mailA, 'dpi' => $dpiA, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'cuenta bancaria no existe'), 'cuenta inexistente se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => $mailA, 'dpi' => unico(13), 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'DPI no coincide'), 'DPI incorrecto se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => 'no-es-correo', 'dpi' => $dpiA, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'correo electrónico válido'), 'correo inválido se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => $mailA, 'dpi' => $dpiA, 'clave' => $passCli, 'confirmar' => 'Distinta2026']);
ok(contiene($reg->mensajes($r), 'confirmación de contraseña no coincide'), 'contraseñas diferentes se rechazan');
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => $mailA, 'dpi' => $dpiA, 'clave' => 'corta1', 'confirmar' => 'corta1']);
ok(contiene($reg->mensajes($r), 'entre 8 y 72'), 'contraseña corta se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => $mailA, 'dpi' => $dpiA, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(str_ends_with($r['url'], 'login_usuario.php') && contiene($reg->mensajes($r), 'Usuario registrado correctamente'), 'registro correcto (cuenta A)', $reg->mensajes($r));
$r = $reg->post('registro.php', ['cuenta' => $ctaA, 'correo' => "otro_$mailA", 'dpi' => $dpiA, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'ya tiene un usuario registrado'), 'cuenta ya asociada a un usuario se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaB, 'correo' => $mailA, 'dpi' => $dpiB, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'ya existe un usuario registrado con ese correo'), 'correo ya utilizado se rechaza');
$r = $reg->post('registro.php', ['cuenta' => $ctaB, 'correo' => $mailB, 'dpi' => $dpiB, 'clave' => $passCli, 'confirmar' => $passCli]);
ok(contiene($reg->mensajes($r), 'Usuario registrado correctamente'), 'registro correcto (cuenta B)');
$hashCli = q("SELECT password_hash FROM usuarios WHERE username='$mailA'")[0]['password_hash'] ?? '';
ok(str_starts_with($hashCli, '$2y$') && !str_contains($hashCli, $passCli), 'la contraseña del cliente se guardó con hash bcrypt');

seccion('AUTENTICACION - CLIENTE');
$a = nuevo();
$r = $a->post('login_usuario.php', ['usuario' => $mailA, 'clave' => 'incorrecta1']);
ok(contiene($a->mensajes($r), 'Usuario o contraseña incorrectos'), 'login incorrecto de cliente muestra mensaje');
$r = $a->post('login_usuario.php', ['usuario' => 'admin', 'clave' => $passAdmin]);
ok(contiene($a->mensajes($r), 'Usuario o contraseña incorrectos'), 'el administrador NO puede entrar por el login de cliente');
$r = $a->post('login_usuario.php', ['usuario' => $mailA, 'clave' => $passCli]);
ok(str_ends_with($r['url'], 'cliente/index.php') && $r['status'] === 200, 'login correcto de cliente redirige al panel', $r['url']);
foreach (['admin/index.php', 'admin/cajeros.php', 'admin/monitor.php', 'cajero/index.php', 'cajero/deposito.php', 'cajero/crear_cuenta.php'] as $p) {
    ok($a->get($p)['status'] === 403, "un CLIENTE no puede abrir $p escribiendo la URL (403)");
}
$b = nuevo();
$b->post('login_usuario.php', ['usuario' => $mailB, 'clave' => $passCli]);

// ============================================================ TERCEROS
seccion('CLIENTE - CUENTAS DE TERCEROS');
$ta = 'cliente/terceros.php';
$r = $a->post($ta, ['cuenta' => $ctaB, 'monto_max' => '300.00', 'max_tx' => '2', 'alias' => 'Cuenta de B']);
ok(contiene($a->mensajes($r), 'agregada correctamente'), 'tercero válido se agrega');
$r = $a->post($ta, ['cuenta' => '66666666', 'monto_max' => '100', 'max_tx' => '1', 'alias' => 'Fantasma']);
ok(contiene($a->mensajes($r), 'La cuenta bancaria no existe'), 'tercero con cuenta inexistente se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaA, 'monto_max' => '100', 'max_tx' => '1', 'alias' => 'Yo mismo']);
ok(contiene($a->mensajes($r), 'propia cuenta'), 'la cuenta propia como tercero se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '0', 'max_tx' => '1', 'alias' => 'Cero']);
ok(contiene($a->mensajes($r), 'monto máximo debe ser'), 'monto máximo 0 se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '-5', 'max_tx' => '1', 'alias' => 'Neg']);
ok(contiene($a->mensajes($r), 'monto máximo debe ser'), 'monto máximo negativo se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '100', 'max_tx' => '0', 'alias' => 'Sin tx']);
ok(contiene($a->mensajes($r), 'transacciones diarias'), 'máximo de transacciones 0 se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '100', 'max_tx' => 'abc', 'alias' => 'Letras']);
ok(contiene($a->mensajes($r), 'transacciones diarias'), 'máximo de transacciones no numérico se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '100', 'max_tx' => '1', 'alias' => 'X']);
ok(contiene($a->mensajes($r), 'alias debe tener'), 'alias demasiado corto se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaB, 'monto_max' => '100', 'max_tx' => '1', 'alias' => 'Repetida']);
ok(contiene($a->mensajes($r), 'Ya registró esa cuenta'), 'tercero duplicado para el mismo usuario se rechaza');
$r = $a->post($ta, ['cuenta' => $ctaC, 'monto_max' => '1000', 'max_tx' => '5', 'alias' => '<script>alert(1)</script>']);
ok(contiene($a->mensajes($r), 'agregada correctamente'), 'tercero con alias HTML se acepta (se escapa al mostrar)');
$lista = $a->get($ta);
ok(!str_contains($lista['body'], '<script>alert(1)</script>') && str_contains($lista['body'], '&lt;script&gt;'), 'XSS: el alias se escapa en la salida HTML');
ok(!str_contains($lista['body'], $mailB), 'el listado no revela datos del otro usuario');
$r = $b->post($ta, ['cuenta' => $ctaA, 'monto_max' => '50', 'max_tx' => '1', 'alias' => 'Cuenta de A']);
ok(contiene($b->mensajes($r), 'agregada correctamente'), 'el usuario B agrega a A como su tercero');
$bLista = $b->get($ta)['body'];
ok(!str_contains($bLista, 'Cuenta de B') && !str_contains($bLista, 'alert(1)'), 'los terceros de A NO aparecen para B');
$idTA_B = (int) q("SELECT t.id_tercero FROM cuentas_terceros t JOIN usuarios u ON u.id_usuario=t.id_usuario WHERE u.username='$mailA' AND t.alias='Cuenta de B'")[0]['id_tercero'];
$idTA_C = (int) q("SELECT t.id_tercero FROM cuentas_terceros t JOIN usuarios u ON u.id_usuario=t.id_usuario WHERE u.username='$mailA' AND t.alias LIKE '<script>%'")[0]['id_tercero'];
$idTB_A = (int) q("SELECT t.id_tercero FROM cuentas_terceros t JOIN usuarios u ON u.id_usuario=t.id_usuario WHERE u.username='$mailB' AND t.alias='Cuenta de A'")[0]['id_tercero'];
$selA = $a->get('cliente/transferir.php')['body'];
ok(str_contains($selA, 'value="' . $idTA_B . '"') && !str_contains($selA, 'value="' . $idTB_A . '"'), 'el formulario de transferencia lista SÓLO los terceros del usuario autenticado');

// ============================================================ TRANSFERENCIAS
seccion('CLIENTE - TRANSFERENCIAS');
$tr = 'cliente/transferir.php';
$sA0 = saldo($ctaA); $sB0 = saldo($ctaB);
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '100.00']);
ok(contiene($a->mensajes($r), 'realizada correctamente') && saldo($ctaA) === '1100.00' && saldo($ctaB) === '600.00', "transferencia válida: A $sA0 -> 1100.00 y B $sB0 -> 600.00 en MySQL", $a->mensajes($r));
$rowT = q("SELECT COUNT(*) c FROM transferencias WHERE id_cuenta_origen=(SELECT id_cuenta FROM cuentas WHERE numero_cuenta='$ctaA') AND id_cuenta_destino=(SELECT id_cuenta FROM cuentas WHERE numero_cuenta='$ctaB')")[0]['c'];
$rowM = q("SELECT COUNT(*) c FROM movimientos WHERE id_transferencia IS NOT NULL AND id_cuenta IN ((SELECT id_cuenta FROM cuentas WHERE numero_cuenta='$ctaA'),(SELECT id_cuenta FROM cuentas WHERE numero_cuenta='$ctaB'))")[0]['c'];
ok((int) $rowT === 1 && (int) $rowM === 2, 'la transferencia quedó registrada (1 transferencia + 2 movimientos: débito y crédito)');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '300.01']);
ok(contiene($a->mensajes($r), 'supera el máximo permitido') && saldo($ctaA) === '1100.00', 'monto superior al máximo del tercero se rechaza');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '0']);
ok(contiene($a->mensajes($r), 'mayor que cero'), 'monto cero se rechaza');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '-5']);
ok(contiene($a->mensajes($r), 'monto no es válido'), 'monto negativo se rechaza');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => 'mucho']);
ok(contiene($a->mensajes($r), 'monto no es válido'), 'monto no numérico se rechaza');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '100.00']);
ok(contiene($a->mensajes($r), 'realizada correctamente') && saldo($ctaA) === '1000.00', 'segunda transferencia del día permitida (2 de 2)');
$r = $a->post($tr, ['tercero' => (string) $idTA_B, 'monto' => '1.00']);
ok(contiene($a->mensajes($r), 'límite de 2 transferencia') && saldo($ctaA) === '1000.00' && saldo($ctaB) === '700.00', 'límite diario alcanzado: la 3.ª transferencia se rechaza sin tocar saldos');
$r = $a->post($tr, ['tercero' => (string) $idTB_A, 'monto' => '10']);
ok(contiene($a->mensajes($r), 'no existe o no le pertenece') && saldo($ctaA) === '1000.00', 'IDOR: usar el id de un tercero de OTRO usuario se rechaza');
$r = $a->post($tr, ['tercero' => '99999999', 'monto' => '10']);
ok(contiene($a->mensajes($r), 'no existe o no le pertenece'), 'tercero inexistente se rechaza');
$r = $a->post($tr, ['tercero' => '1 OR 1=1', 'monto' => '10']);
ok(contiene($a->mensajes($r), 'Seleccione una cuenta'), 'inyección SQL en el id del tercero no tiene efecto');
// Se intenta "forzar" la cuenta origen con parámetros extra: el servidor debe ignorarlos y usar la cuenta de la sesión (A).
$r = $a->post($tr, ['tercero' => (string) $idTA_C, 'monto' => '5.00', 'cuenta_origen' => $ctaB, 'id_cuenta' => '1', 'origen' => $ctaB]);
ok(contiene($a->mensajes($r), 'realizada correctamente') && saldo($ctaA) === '995.00' && saldo($ctaC) === '5.00' && saldo($ctaB) === '700.00',
   'cuenta origen manipulada en el POST: se ignora; el débito sale de la cuenta de la sesión (A=995.00, C=5.00, B sin cambio)');
// saldo insuficiente: A se deja con 5.00 y se intenta transferir 500 a C (límite del tercero: 1000)
$r = $caj->post('cajero/retiro.php', ['numero' => $ctaA, 'monto' => '990.00']);
$sA = saldo($ctaA);
$r = $a->post($tr, ['tercero' => (string) $idTA_C, 'monto' => '500.00']);
ok(contiene($a->mensajes($r), 'Saldo insuficiente') && saldo($ctaA) === $sA && saldo($ctaC) === '5.00', "saldo insuficiente se rechaza (A=$sA, C sigue en 5.00)");
$r = $a->req('POST', $tr, ['tercero' => (string) $idTA_C, 'monto' => '1.00'], false);
ok($r['status'] === 400, 'POST sin token CSRF se rechaza (400)', (string) $r['status']);
$r = $a->req('POST', $tr, ['tercero' => (string) $idTA_C, 'monto' => '1.00', 'csrf' => 'falso'], false);
ok($r['status'] === 400, 'POST con token CSRF falso se rechaza (400)');

// ============================================================ ESTADO DE CUENTA
seccion('ESTADO DE CUENTA');
$ec = $a->get('cliente/estado_cuenta.php');
ok($ec['status'] === 200 && contiene($ec['body'], 'Apertura de cuenta') && contiene($ec['body'], 'Depósito en ventanilla') && contiene($ec['body'], 'Retiro en ventanilla')
   && contiene($ec['body'], 'Transferencia a Cuenta de B'), 'el estado de cuenta muestra apertura, depósito, retiro y transferencias propias');
ok(substr_count($ec['body'], 'Transferencia a Cuenta de B') === 2, 'muestra exactamente las 2 transferencias enviadas a B');
$ecB = $b->get('cliente/estado_cuenta.php');
ok(contiene($ecB['body'], 'Transferencia recibida de la cuenta ' . $ctaA), 'B ve los créditos que recibió de A');
ok(!contiene($ecB['body'], 'Transferencia a Cuenta de B') && !str_contains($ecB['body'], $mailA), 'B NO ve las operaciones enviadas por A ni sus datos');
$ecManip = $a->get('cliente/estado_cuenta.php?id_cuenta=' . $ctaB . '&cuenta=' . $ctaB . '&id=1&numero=' . $ctaB);
ok($ecManip['status'] === 200 && $ecManip['body'] === $ec['body'],
   'IDOR: agregar parámetros (id_cuenta, cuenta, numero...) a la URL no cambia el estado de cuenta mostrado: siempre es el del usuario autenticado');
$saldoPantalla = preg_match('/Saldo actual<\/span>\s*<p class="saldo-monto">Q ([\d,\.]+)/', $ec['body'], $m) ? str_replace(',', '', $m[1]) : '';
ok($saldoPantalla === saldo($ctaA), 'el saldo mostrado coincide con el saldo en MySQL', "$saldoPantalla vs " . saldo($ctaA));

// ============================================================ ADMIN: MONITOR Y BLOQUEO
seccion('ADMINISTRADOR - MONITOR DE TRANSFERENCIAS');
$st1 = stats();
$dep = (int) $st1['depositos'] - (int) $st0['depositos']; $ret = (int) $st1['retiros'] - (int) $st0['retiros'];
ok((int) $st1['cuentas_creadas'] - (int) $st0['cuentas_creadas'] === 3, 'monitor: +3 cuentas creadas en el día');
ok((int) $st1['usuarios_cliente'] - (int) $st0['usuarios_cliente'] === 2, 'monitor: +2 usuarios cliente (el cajero creado NO cuenta)');
ok($dep === 2 && $ret === 3, "monitor: depósitos +2 y retiros +3 (obtenido +$dep / +$ret)");
ok((int) $st1['transferencias'] - (int) $st0['transferencias'] === 3, 'monitor: +3 transferencias');
ok((int) $st1['transacciones'] - (int) $st0['transacciones'] === 2 + 3 + 3, 'monitor: transacciones = depósitos + retiros + transferencias (+8)');
$real = q("SELECT SUM(CASE WHEN tipo='DEPOSITO' THEN monto END) d, SUM(CASE WHEN tipo='RETIRO' THEN monto END) r FROM movimientos WHERE fecha >= CURDATE()")[0];
ok(abs((float) $st1['monto_depositos'] - (float) $real['d']) < 0.001 && abs((float) $st1['monto_retiros'] - (float) $real['r']) < 0.001, 'monitor: montos de depósitos/retiros coinciden con la suma real de movimientos');
$mon = $adm->get('admin/monitor.php');
preg_match_all('/<span class="stat-num">(\d+)<\/span>/', $mon['body'], $mm);
ok($mon['status'] === 200 && $mm[1] === [(string) $st1['cuentas_creadas'], (string) $st1['usuarios_cliente'], (string) $st1['transacciones'], (string) $st1['depositos'], (string) $st1['retiros'], (string) $st1['transferencias']],
   'la página del monitor muestra las cifras de la BD', implode(',', $mm[1]));
ok(str_contains($mon['body'], '<svg') && str_contains($mon['body'], 'barra-dep') && str_contains($mon['body'], 'barra-ret'), 'la página del monitor incluye la gráfica de depósitos vs retiros');
$tot = $adm->get('admin/monitor.php?periodo=total');
ok($tot['status'] === 200 && str_contains($tot['body'], 'Histórico'), 'el monitor histórico responde');

seccion('ADMINISTRADOR - BLOQUEO / DESBLOQUEO DE CAJERO');
$r = $adm->post('admin/cajeros.php', ['accion' => 'estado', 'id_usuario' => (string) $idCajero, 'bloquear' => '1'], 'admin/cajeros.php');
ok(contiene($adm->mensajes($r), 'bloqueado correctamente') && (int) q("SELECT bloqueado b FROM usuarios WHERE id_usuario=$idCajero")[0]['b'] === 1, 'cajero bloqueado (verificado en MySQL)');
$r = $caj->get('cajero/deposito.php', false);
ok($r['status'] === 302 && str_contains($r['location'], 'login_cajero.php'), 'un cajero con sesión abierta queda fuera en cuanto se le bloquea');
$c2 = nuevo();
$r = $c2->post('login_cajero.php', ['usuario' => $cajeroUser, 'clave' => $cajeroPass]);
ok(contiene($c2->mensajes($r), 'bloqueado'), 'un cajero bloqueado no puede iniciar sesión');
$r = $adm->post('admin/cajeros.php', ['accion' => 'estado', 'id_usuario' => (string) $idCajero, 'bloquear' => '0'], 'admin/cajeros.php');
ok(contiene($adm->mensajes($r), 'desbloqueado correctamente') && (int) q("SELECT bloqueado b FROM usuarios WHERE id_usuario=$idCajero")[0]['b'] === 0, 'cajero desbloqueado (verificado en MySQL)');
$r = $c2->post('login_cajero.php', ['usuario' => $cajeroUser, 'clave' => $cajeroPass]);
ok(str_ends_with($r['url'], 'cajero/index.php'), 'tras desbloquear, el cajero puede iniciar sesión');
$idCliA = (int) q("SELECT id_usuario FROM usuarios WHERE username='$mailA'")[0]['id_usuario'];
$r = $adm->post('admin/cajeros.php', ['accion' => 'estado', 'id_usuario' => (string) $idCliA, 'bloquear' => '1'], 'admin/cajeros.php');
ok(contiene($adm->mensajes($r), 'cajero no existe') && (int) q("SELECT bloqueado b FROM usuarios WHERE id_usuario=$idCliA")[0]['b'] === 0, 'el bloqueo sólo afecta a cajeros (un id de cliente se ignora)');

seccion('CIERRE DE SESION');
$r = $adm->get('logout.php', false);
ok($r['status'] === 302 && $adm->get('admin/index.php')['status'] === 200, 'GET a logout.php NO cierra la sesión (evita cierre forzado)');
$r = $adm->post('logout.php', [], 'admin/index.php');
ok(contiene($adm->mensajes($r), 'Sesión cerrada correctamente'), 'logout (POST + CSRF) cierra la sesión del administrador');
$r = $adm->get('admin/index.php', false);
ok($r['status'] === 302 && str_contains($r['location'], 'login_admin.php'), 'tras salir, el panel de administrador ya no es accesible');
foreach (['a' => $a, 'b' => $b, 'caj' => $caj] as $k => $cli) {
    $pg = ['a' => 'cliente/index.php', 'b' => 'cliente/index.php', 'caj' => 'cajero/index.php'][$k];
    $cli->post('logout.php', [], $pg);
    ok($cli->get($pg, false)['status'] === 302, "logout de $k: el panel deja de ser accesible");
}

seccion('ERRORES Y SEGURIDAD DE SALIDA');
ok(str_contains(nuevo()->get('index.php')['headers'], 'Content-Security-Policy'), 'se envían cabeceras de seguridad (CSP)');
foreach (['config/config.php', 'includes/db.php', 'logs/app.log.php', 'includes/.htaccess'] as $p) {
    ok(nuevo()->get($p, false)['status'] === 403, "archivo interno no accesible por HTTP: $p");
}
$err = nuevo()->get('login_admin.php');
ok(!contiene($err['body'], 'mysqli') && !contiene($err['body'], 'Fatal error') && !contiene($err['body'], 'Stack trace'), 'no se filtran detalles técnicos en pantalla');

// ============================================================ BASE DE DATOS
seccion('BASE DE DATOS - ROLLBACK / COMMIT / INTEGRIDAD');
$dbapp = new mysqli('localhost', 'banca_app', 'BancaApp#2026', 'banca_umg');
ok(!$dbapp->connect_errno, 'el usuario de la aplicación (banca_app) puede conectarse');
$rr = @$dbapp->query('SELECT * FROM usuarios');
ok($rr === false, 'banca_app NO puede hacer SELECT directo sobre las tablas (sólo EXECUTE de procedimientos)', $dbapp->error);
$rr = @$dbapp->query("UPDATE cuentas SET saldo = 999999 WHERE numero_cuenta='$ctaA'");
ok($rr === false && saldo($ctaA) === $sA, 'banca_app NO puede modificar saldos con UPDATE directo');

// ---- ROLLBACK: se fuerza un error con un trigger temporal justo al registrar el crédito del destino
$aU = nuevo(); $aU->post('login_usuario.php', ['usuario' => $mailB, 'clave' => $passCli]);
$caj3 = nuevo(); $caj3->post('login_cajero.php', ['usuario' => $cajeroUser, 'clave' => $cajeroPass]);
$caj3->post('cajero/deposito.php', ['numero' => $ctaB, 'monto' => '1000.00']);
$idTB_C = null;
$aU->post('cliente/terceros.php', ['cuenta' => $ctaC, 'monto_max' => '500', 'max_tx' => '10', 'alias' => 'Hacia C']);
$idTB_C = (int) q("SELECT t.id_tercero FROM cuentas_terceros t JOIN usuarios u ON u.id_usuario=t.id_usuario WHERE u.username='$mailB' AND t.alias='Hacia C'")[0]['id_tercero'];
$antes = ['B' => saldo($ctaB), 'C' => saldo($ctaC),
          't' => q('SELECT COUNT(*) c FROM transferencias')[0]['c'], 'm' => q('SELECT COUNT(*) c FROM movimientos')[0]['c']];
$root->query('DROP TRIGGER IF EXISTS trg_prueba_fallo');
$root->query("CREATE TRIGGER trg_prueba_fallo BEFORE INSERT ON movimientos FOR EACH ROW
              BEGIN IF NEW.tipo = 'TRANSFERENCIA_RECIBIDA' AND NEW.monto = 13.13 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo simulado para prueba de rollback'; END IF; END");
$r = $aU->post('cliente/transferir.php', ['tercero' => (string) $idTB_C, 'monto' => '13.13']);
$despues = ['B' => saldo($ctaB), 'C' => saldo($ctaC),
            't' => q('SELECT COUNT(*) c FROM transferencias')[0]['c'], 'm' => q('SELECT COUNT(*) c FROM movimientos')[0]['c']];
ok(contiene($aU->mensajes($r), 'falló y no se aplicó ningún cambio'), 'fallo a mitad de la transferencia: el usuario recibe un mensaje comprensible', $aU->mensajes($r));
ok($antes === $despues, 'ROLLBACK: saldos, transferencias y movimientos quedaron EXACTAMENTE igual (débito, crédito y registro revertidos)', json_encode([$antes, $despues]));
ok(!contiene($r['body'], 'Fallo simulado') && !contiene($r['body'], 'SQLSTATE'), 'el detalle técnico del fallo no se muestra al usuario');
$logApp = @file_get_contents(__DIR__ . '/../banca/logs/app.log.php') ?: '';
ok(str_contains($logApp, 'Fallo simulado para prueba de rollback'), 'el detalle técnico sí quedó registrado en logs/app.log');
$root->query('DROP TRIGGER IF EXISTS trg_prueba_fallo');
$r = $aU->post('cliente/transferir.php', ['tercero' => (string) $idTB_C, 'monto' => '13.13']);
ok(contiene($aU->mensajes($r), 'realizada correctamente') && saldo($ctaC) === '18.13' && (int) q('SELECT COUNT(*) c FROM transferencias')[0]['c'] === (int) $antes['t'] + 1
   && (int) q('SELECT COUNT(*) c FROM movimientos')[0]['c'] === (int) $antes['m'] + 2, 'COMMIT: sin el fallo, la misma transferencia se aplica completa (débito + crédito + 3 registros)');

// ---- Invariantes de integridad
$bad = q("SELECT c.numero_cuenta FROM cuentas c
          JOIN (SELECT id_cuenta, SUBSTRING_INDEX(GROUP_CONCAT(saldo_resultante ORDER BY id_movimiento DESC), ',', 1) ult FROM movimientos GROUP BY id_cuenta) m
            ON m.id_cuenta = c.id_cuenta WHERE c.saldo <> m.ult");
ok(!$bad, 'integridad: el saldo de cada cuenta coincide con el saldo_resultante de su último movimiento', json_encode($bad));
$bad = q("SELECT c.numero_cuenta FROM cuentas c LEFT JOIN movimientos m ON m.id_cuenta=c.id_cuenta
          GROUP BY c.id_cuenta HAVING c.saldo <> COALESCE(SUM(CASE m.naturaleza WHEN 'CREDITO' THEN m.monto WHEN 'DEBITO' THEN -m.monto END),0)");
ok(!$bad, 'integridad: saldo = suma de créditos - débitos del libro de movimientos (todas las cuentas)', json_encode($bad));
ok((int) q('SELECT COUNT(*) c FROM cuentas WHERE saldo < 0')[0]['c'] === 0, 'integridad: no existe ninguna cuenta con saldo negativo');
$huerf = q('SELECT COUNT(*) c FROM transferencias t WHERE (SELECT COUNT(*) FROM movimientos m WHERE m.id_transferencia=t.id_transferencia) <> 2');
ok((int) $huerf[0]['c'] === 0, 'integridad: toda transferencia tiene exactamente 2 movimientos (débito y crédito)');
$neg = $root->query("INSERT INTO cuentas (numero_cuenta,nombre_cuenta,dpi,saldo,id_cajero) VALUES ('1','x','1234567890123',-5,$idCajero)");
ok($neg === false, 'restricción CHECK en BD: no se puede insertar un saldo negativo', $root->error);

// ---- Concurrencia: 6 procesos transfieren a la vez desde una cuenta con saldo para sólo 5
seccion('CONCURRENCIA - 6 TRANSFERENCIAS SIMULTANEAS CON SALDO PARA 5');
$conc = nuevo(); $conc->post('login_usuario.php', ['usuario' => $mailB, 'clave' => $passCli]);
$sBantes = (float) saldo($ctaB);
// se deja a B con exactamente 500.00 (para 5 de 100)
$exceso = $sBantes - 500.00;
if ($exceso > 0) { $caj3->post('cajero/retiro.php', ['numero' => $ctaB, 'monto' => number_format($exceso, 2, '.', '')]); }
$idTB_A2 = $idTB_C;   // tercero "Hacia C" de B (máx. 500 por operación, 10 diarias; ya usó 1)
$sB500 = saldo($ctaB); $sA500 = saldo($ctaC);
$mh = curl_multi_init(); $hs = []; $jars = [];
for ($i = 0; $i < 6; $i++) {
    $cl = nuevo(); $cl->post('login_usuario.php', ['usuario' => $mailB, 'clave' => $passCli]);
    $pg = $cl->get('cliente/transferir.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $pg['body'], $mt);
    $jarProp = (new ReflectionClass($cl))->getProperty('jar'); $jarProp->setAccessible(true); $jar = $jarProp->getValue($cl);
    $ch = curl_init($BASE . 'cliente/transferir.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_POSTFIELDS => http_build_query(['csrf' => $mt[1], 'tercero' => (string) $idTB_A2, 'monto' => '100.00'])]);
    curl_multi_add_handle($mh, $ch); $hs[] = $ch;
}
do { curl_multi_exec($mh, $act); curl_multi_select($mh, 0.2); } while ($act > 0);
$exitos = 0; $rechazos = 0;
foreach ($hs as $ch) {
    $cuerpo = curl_multi_getcontent($ch);
    if (contiene($cuerpo, 'realizada correctamente')) { $exitos++; } elseif (contiene($cuerpo, 'Saldo insuficiente')) { $rechazos++; }
}
$bFin = saldo($ctaB);
ok($exitos === 5 && $rechazos === 1, "exactamente 5 transferencias exitosas y 1 rechazada por saldo (éxitos=$exitos, rechazos=$rechazos)");
ok($bFin === '0.00' && (float) saldo($ctaC) === (float) $sA500 + 500.00, "saldos exactos tras la concurrencia: B=$bFin, C=" . saldo($ctaC) . " (esperado " . number_format((float) $sA500 + 500, 2, '.', '') . ')');
ok((int) q('SELECT COUNT(*) c FROM cuentas WHERE saldo < 0')[0]['c'] === 0, 'ninguna cuenta quedó con saldo negativo tras la concurrencia');

// ============================================================ RESUMEN
echo "\n==============================================\n";
echo "RESULTADO: $pasaron pruebas PASARON, " . count($fallaron) . " FALLARON\n";
foreach ($fallaron as $f) { echo "  - $f\n"; }
exit(count($fallaron) ? 1 : 0);
