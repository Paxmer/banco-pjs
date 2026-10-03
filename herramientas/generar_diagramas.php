<?php
/**
 * Genera los diagramas de la documentación como imágenes PNG (PHP + GD):
 *   1. diagrama_arquitectura.png
 *   2. diagrama_er.png
 *   3. casos_uso_visitante.png / casos_uso_administrador.png / casos_uso_cajero.png / casos_uso_cliente.png
 * Los nombres de tablas, columnas, páginas y procedimientos coinciden con el código real del proyecto.
 *
 * Uso: php generar_diagramas.php [carpeta_salida]
 */
if (PHP_SAPI !== 'cli') { exit('Ejecutar con: php generar_diagramas.php'); }
$salida = rtrim($argv[1] ?? __DIR__ . '/../documentacion/diagramas', '/\\');
if (!is_dir($salida)) { mkdir($salida, 0777, true); }

const FUENTE = 'C:/Windows/Fonts/segoeui.ttf';
const FUENTE_B = 'C:/Windows/Fonts/segoeuib.ttf';
const FUENTE_M = 'C:/Windows/Fonts/consola.ttf';

/** Mini librería de dibujo con suavizado (se dibuja a escala x2 y se reduce). */
final class Lienzo
{
    public $im; public int $w; public int $h; public int $k;
    public function __construct(int $w, int $h, int $k = 2)
    {
        $this->w = $w; $this->h = $h; $this->k = $k;
        $this->im = imagecreatetruecolor($w * $k, $h * $k);
        imagealphablending($this->im, true);
        imageantialias($this->im, true);
        imagefilledrectangle($this->im, 0, 0, $w * $k, $h * $k, $this->c('#ffffff'));
    }
    public function c(string $hex)
    {
        $hex = ltrim($hex, '#');
        return imagecolorallocate($this->im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }
    public function rrectFill(float $x, float $y, float $w, float $h, float $r, string $color): void
    {
        $k = $this->k; $col = $this->c($color);
        [$x, $y, $w, $h, $r] = [$x * $k, $y * $k, $w * $k, $h * $k, min($r * $k, $w / 2, $h / 2)];
        imagefilledrectangle($this->im, (int) ($x + $r), (int) $y, (int) ($x + $w - $r), (int) ($y + $h), $col);
        imagefilledrectangle($this->im, (int) $x, (int) ($y + $r), (int) ($x + $w), (int) ($y + $h - $r), $col);
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$i, $j]) {
            imagefilledellipse($this->im, (int) ($x + $r + $i * ($w - 2 * $r)), (int) ($y + $r + $j * ($h - 2 * $r)), (int) (2 * $r), (int) (2 * $r), $col);
        }
    }
    public function rect(float $x, float $y, float $w, float $h, ?string $fill, ?string $stroke = null, float $r = 8, float $sw = 2): void
    {
        if ($stroke) { $this->rrectFill($x, $y, $w, $h, $r, $stroke); }
        if ($fill) { $this->rrectFill($x + ($stroke ? $sw : 0), $y + ($stroke ? $sw : 0), $w - ($stroke ? 2 * $sw : 0), $h - ($stroke ? 2 * $sw : 0), max(0, $r - $sw), $fill); }
    }
    public function ellipse(float $cx, float $cy, float $w, float $h, string $fill, string $stroke, float $sw = 2): void
    {
        $k = $this->k;
        imagefilledellipse($this->im, (int) ($cx * $k), (int) ($cy * $k), (int) ($w * $k), (int) ($h * $k), $this->c($stroke));
        imagefilledellipse($this->im, (int) ($cx * $k), (int) ($cy * $k), (int) (($w - 2 * $sw) * $k), (int) (($h - 2 * $sw) * $k), $this->c($fill));
    }
    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#0b2a4a', float $sw = 2, bool $dash = false): void
    {
        $k = $this->k; $col = $this->c($color);
        $dx = $x2 - $x1; $dy = $y2 - $y1; $len = sqrt($dx * $dx + $dy * $dy);
        if ($len < 0.01) { return; }
        $nx = -$dy / $len * $sw / 2; $ny = $dx / $len * $sw / 2;
        $segs = $dash ? (int) ceil($len / 14) : 1;
        for ($i = 0; $i < $segs; $i++) {
            if ($dash && $i % 2) { continue; }
            $a = $i / $segs; $b = $dash ? min(1, ($i + 1) / $segs) : 1;
            $px = [$x1 + $dx * $a + $nx, $y1 + $dy * $a + $ny, $x1 + $dx * $b + $nx, $y1 + $dy * $b + $ny,
                   $x1 + $dx * $b - $nx, $y1 + $dy * $b - $ny, $x1 + $dx * $a - $nx, $y1 + $dy * $a - $ny];
            imagefilledpolygon($this->im, array_map(fn($v) => (int) round($v * $k), $px), $col);
        }
    }
    public function arrowHead(float $x, float $y, float $ang, string $color, float $size = 12): void
    {
        $k = $this->k;
        $p = [$x, $y, $x - $size * cos($ang - 0.4), $y - $size * sin($ang - 0.4), $x - $size * cos($ang + 0.4), $y - $size * sin($ang + 0.4)];
        imagefilledpolygon($this->im, array_map(fn($v) => (int) round($v * $k), $p), $this->c($color));
    }
    /** Polilínea con flecha opcional al final. */
    public function poly(array $pts, string $color = '#0b2a4a', float $sw = 2.5, bool $flecha = false, bool $dash = false): void
    {
        for ($i = 0; $i < count($pts) - 1; $i++) {
            $this->line($pts[$i][0], $pts[$i][1], $pts[$i + 1][0], $pts[$i + 1][1], $color, $sw, $dash);
        }
        if ($flecha) {
            $n = count($pts);
            $ang = atan2($pts[$n - 1][1] - $pts[$n - 2][1], $pts[$n - 1][0] - $pts[$n - 2][0]);
            $this->arrowHead($pts[$n - 1][0], $pts[$n - 1][1], $ang, $color, 13);
        }
    }
    /** Texto: $y es el borde superior del texto; $px tamaño en píxeles. */
    public function text(float $x, float $y, string $s, float $px, string $color = '#1c2733', bool $bold = false, string $align = 'l', string $font = ''): void
    {
        $k = $this->k; $f = $font ?: ($bold ? FUENTE_B : FUENTE); $pt = $px * $k * 0.75;
        $bb = imagettfbbox($pt, 0, $f, $s);
        $ancho = $bb[2] - $bb[0];
        $bbH = imagettfbbox($pt, 0, $f, 'Hg');
        $base = $y * $k - $bbH[7];
        $xx = $x * $k - ($align === 'c' ? $ancho / 2 : ($align === 'r' ? $ancho : 0));
        imagettftext($this->im, $pt, 0, (int) $xx, (int) $base, $this->c($color), $f, $s);
    }
    public function ancho(string $s, float $px, bool $bold = false, string $font = ''): float
    {
        $bb = imagettfbbox($px * $this->k * 0.75, 0, $font ?: ($bold ? FUENTE_B : FUENTE), $s);
        return ($bb[2] - $bb[0]) / $this->k;
    }
    /** Texto en varias líneas centradas dentro de un ancho dado. */
    public function parrafo(float $cx, float $y, string $s, float $px, float $maxAncho, string $color = '#1c2733', bool $bold = false, float $interlineado = 1.25): float
    {
        $lineas = []; $act = '';
        foreach (explode(' ', $s) as $pal) {
            $prueba = $act === '' ? $pal : "$act $pal";
            if ($this->ancho($prueba, $px, $bold) > $maxAncho && $act !== '') { $lineas[] = $act; $act = $pal; } else { $act = $prueba; }
        }
        $lineas[] = $act;
        foreach ($lineas as $i => $l) { $this->text($cx, $y + $i * $px * $interlineado, $l, $px, $color, $bold, 'c'); }
        return count($lineas) * $px * $interlineado;
    }
    public function badge(float $cx, float $cy, string $n, string $fill = '#0e7c86'): void
    {
        $this->ellipse($cx, $cy, 30, 30, $fill, '#ffffff', 2);
        $this->text($cx, $cy - 9, $n, 15, '#ffffff', true, 'c');
    }
    public function guardar(string $ruta): void
    {
        $fin = imagecreatetruecolor($this->w, $this->h);
        imagecopyresampled($fin, $this->im, 0, 0, 0, 0, $this->w, $this->h, $this->w * $this->k, $this->h * $this->k);
        imagepng($fin, $ruta, 6);
        echo "  generado: " . basename($ruta) . " ({$this->w}x{$this->h})\n";
    }
}

// =============================================================== 1. ARQUITECTURA
function diagrama_arquitectura(string $ruta): void
{
    $L = new Lienzo(1760, 1010);
    $azul = '#0b2a4a'; $teal = '#0e7c86'; $gris = '#5d6b7a';
    $L->text(880, 14, 'Arquitectura de la solución: Sistema de Banca en Línea (3 capas)', 26, $azul, true, 'c');

    // --- Capa 1: cliente
    $L->rect(30, 90, 300, 520, '#f3f7fb', $azul, 14);
    $L->text(180, 104, 'CAPA 1 · CLIENTE', 17, $teal, true, 'c');
    $L->text(180, 130, 'Navegador web', 22, $azul, true, 'c');
    $L->text(180, 160, '(computadora y smartphone)', 15, $gris, false, 'c');
    foreach ([['HTML5', 'Estructura y formularios'], ['CSS3 responsive', 'estilos.css · mobile-first'], ['JavaScript (app.js)', 'Validación cliente, filtros de'], ] as $i => [$t, $d]) {
        $y = 205 + $i * 112;
        $L->rect(55, $y, 250, 92, '#ffffff', '#9fb3c8', 10, 2);
        $L->text(180, $y + 12, $t, 19, $azul, true, 'c');
        $L->text(180, $y + 42, $d, 14, $gris, false, 'c');
        if ($i === 2) { $L->text(180, $y + 60, 'campos y estado de carga', 14, $gris, false, 'c'); }
    }
    $L->parrafo(180, 548, 'La validación del navegador es sólo comodidad: todo se vuelve a validar en PHP y en MySQL.', 13, 240, $gris);

    // --- Capa 2: servidor
    $L->rect(430, 90, 820, 760, '#f3f7fb', $azul, 14);
    $L->text(840, 104, 'CAPA 2 · SERVIDOR WEB: Apache + PHP 8 (WampServer / hosting gratuito)', 17, $teal, true, 'c');
    $L->rect(455, 140, 770, 118, '#ffffff', '#9fb3c8', 10);
    $L->text(840, 150, 'Páginas públicas (carpeta banca/)', 17, $azul, true, 'c');
    $L->text(840, 182, 'index.php · login_admin.php · login_cajero.php · login_usuario.php', 15, '#1c2733', false, 'c', FUENTE_M);
    $L->text(840, 210, 'registro.php · logout.php', 15, '#1c2733', false, 'c', FUENTE_M);

    $paneles = [
        ['Panel administrador', 'admin/', ['index.php', 'cajeros.php', 'monitor.php']],
        ['Panel cajero', 'cajero/', ['index.php', 'crear_cuenta.php', 'deposito.php', 'retiro.php']],
        ['Panel cliente', 'cliente/', ['index.php', 'terceros.php', 'transferir.php', 'estado_cuenta.php']],
    ];
    foreach ($paneles as $i => [$t, $dir, $pags]) {
        $x = 455 + $i * 260;
        $L->rect($x, 285, 245, 200, '#ffffff', '#9fb3c8', 10);
        $L->text($x + 122, 295, $t, 17, $azul, true, 'c');
        $L->text($x + 122, 320, $dir, 15, $teal, true, 'c', FUENTE_M);
        foreach ($pags as $j => $p) { $L->text($x + 122, 352 + $j * 26, $p, 15, '#1c2733', false, 'c', FUENTE_M); }
    }
    $L->rect(455, 515, 770, 260, '#e8f1fb', $teal, 10, 2.5);
    $L->text(840, 525, 'Capa de servicios: includes/  y  config/', 17, $azul, true, 'c');
    $servicios = [['bootstrap.php', 'arranque común'], ['auth.php', 'sesiones · roles · CSRF'], ['helpers.php', 'validación · escape HTML · log'],
        ['db.php', 'sp_call(): CALL sp_x(?) preparado'], ['layout.php', 'plantilla y menú por rol'], ['config.php', 'conexión BD editable']];
    foreach ($servicios as $i => [$n, $d]) {
        $x = 475 + ($i % 3) * 250; $y = 560 + intdiv($i, 3) * 100;
        $L->rect($x, $y, 235, 82, '#ffffff', '#9fb3c8', 8, 1.5);
        $L->text($x + 117, $y + 10, $n, 16, $azul, true, 'c', FUENTE_M);
        $L->parrafo($x + 117, $y + 40, $d, 13, 215, $gris);
    }
    $L->text(840, 757, 'Autorización por página: require_rol(ADMIN | CAJERO | CLIENTE)', 14, $azul, true, 'c');

    // --- Capa 3: datos
    $L->rect(1350, 90, 380, 760, '#f3f7fb', $azul, 14);
    $L->text(1540, 104, 'CAPA 3 · BASE DE DATOS', 17, $teal, true, 'c');
    $L->text(1540, 130, 'MySQL · banca_umg (InnoDB)', 21, $azul, true, 'c');
    $L->rect(1375, 170, 330, 330, '#ffffff', $teal, 10, 2.5);
    $L->text(1540, 180, 'Stored procedures (15)', 18, $azul, true, 'c');
    $grupos = ['Autenticación: sp_login_obtener,', 'sp_sesion_validar', 'Registro: sp_registrar_usuario', 'Admin: sp_cajero_listar/crear/', 'cambiar_estado, sp_monitor_estadisticas', 'Cajero: sp_cuenta_crear,', 'sp_deposito, sp_retiro', 'Cliente: sp_cliente_resumen,', 'sp_tercero_crear/listar, sp_transferir,', 'sp_estado_cuenta'];
    foreach ($grupos as $i => $g) { $L->text(1540, 218 + $i * 26, $g, 14, '#1c2733', false, 'c'); }
    $L->rect(1375, 525, 330, 190, '#ffffff', '#9fb3c8', 10);
    $L->text(1540, 535, 'Tablas (5)', 18, $azul, true, 'c');
    foreach (['usuarios', 'cuentas', 'cuentas_terceros', 'transferencias', 'movimientos'] as $i => $t) { $L->text(1540, 568 + $i * 26, $t, 15, '#1c2733', false, 'c', FUENTE_M); }
    $L->parrafo(1540, 735, 'START TRANSACTION · COMMIT · ROLLBACK dentro de los procedimientos', 14, 300, $teal, true);
    $L->parrafo(1540, 790, 'Usuario de la app: banca_app (sólo permiso EXECUTE)', 13, 300, $gris);

    // --- flechas
    $L->poly([[330, 330], [425, 330]], $azul, 3.5, true); $L->poly([[425, 400], [335, 400]], $azul, 3.5, true);
    $L->parrafo(378, 262, 'HTTP / HTTPS', 14, 90, $azul, true);
    $L->parrafo(378, 425, 'GET · POST + cookie de sesión + token CSRF', 12, 92, $gris);
    $L->poly([[1250, 340], [1345, 340]], $teal, 3.5, true); $L->poly([[1345, 420], [1255, 420]], $teal, 3.5, true);
    $L->parrafo(1298, 262, 'mysqli', 14, 90, $teal, true);
    $L->parrafo(1298, 440, 'CALL sp_xxx(?, ?) con sentencias preparadas', 12, 92, $gris);

    // --- Administración del servidor (logs)
    $L->rect(430, 880, 820, 110, '#fff8e8', '#e0a526', 12, 2);
    $L->text(840, 890, 'Administración de Apache (mantenimiento y soporte)', 16, $azul, true, 'c');
    $L->text(560, 930, 'access.log / apache_error.log', 14, '#1c2733', true, 'c', FUENTE_M);
    $L->poly([[690, 940], [770, 940]], '#b07d12', 3, true);
    $L->text(910, 920, 'analizar_log_apache.php', 14, '#1c2733', true, 'c', FUENTE_M);
    $L->text(910, 942, '(herramienta PHP CLI)', 13, $gris, false, 'c');
    $L->poly([[1030, 940], [1075, 940]], '#b07d12', 3, true);
    $L->text(1155, 920, 'reporte_apache.html', 14, '#1c2733', true, 'c', FUENTE_M);
    $L->text(1155, 942, 'OK · más visitadas · errores', 12, $gris, false, 'c');
    $L->text(30, 650, 'Errores técnicos de la aplicación', 14, $gris, true);
    $L->text(30, 673, 'se registran en banca/logs/app.log;', 13, $gris);
    $L->text(30, 692, 'el usuario sólo ve un mensaje genérico.', 13, $gris);
    $L->guardar($ruta);
}

// =============================================================== 2. ER
function diagrama_er(string $ruta): void
{
    $L = new Lienzo(1920, 1020);
    $azul = '#0b2a4a'; $teal = '#0e7c86'; $gris = '#5d6b7a';
    $tablas = [
        'usuarios' => [40, 70, 480, [['PK', 'id_usuario', 'INT UNSIGNED'], ['', 'rol', "ENUM('ADMIN','CAJERO','CLIENTE')"], ['', 'nombre_completo', 'VARCHAR(100)'],
            ['UQ', 'username', 'VARCHAR(100)  (correo del cliente)'], ['', 'password_hash', 'VARCHAR(255)  (bcrypt)'], ['', 'bloqueado', 'TINYINT(1)'],
            ['FK', 'id_cuenta (UQ)', 'INT UNSIGNED NULL → cuentas'], ['', 'fecha_creacion', 'DATETIME']]],
        'cuentas' => [760, 70, 480, [['PK', 'id_cuenta', 'INT UNSIGNED'], ['UQ', 'numero_cuenta', 'VARCHAR(20)'], ['', 'nombre_cuenta', 'VARCHAR(100)'], ['', 'dpi', 'CHAR(13)'],
            ['', 'saldo', 'DECIMAL(15,2)  CHECK (saldo >= 0)'], ['FK', 'id_cajero', 'INT UNSIGNED → usuarios'], ['', 'fecha_creacion', 'DATETIME']]],
        'movimientos' => [1400, 70, 480, [['PK', 'id_movimiento', 'BIGINT UNSIGNED'], ['FK', 'id_cuenta', 'INT UNSIGNED → cuentas'],
            ['', 'tipo', 'ENUM(APERTURA, DEPOSITO, RETIRO,'], ['', '', '   TRANSFERENCIA_ENVIADA/RECIBIDA)'], ['', 'naturaleza', "ENUM('CREDITO','DEBITO')"], ['', 'monto', 'DECIMAL(15,2)  CHECK (> 0)'],
            ['', 'saldo_resultante', 'DECIMAL(15,2)'], ['FK', 'id_transferencia', 'INT UNSIGNED NULL → transferencias'], ['FK', 'id_cajero', 'INT UNSIGNED NULL → usuarios'],
            ['', 'descripcion', 'VARCHAR(255)'], ['', 'fecha', 'DATETIME']]],
        'cuentas_terceros' => [40, 560, 480, [['PK', 'id_tercero', 'INT UNSIGNED'], ['FK', 'id_usuario', 'INT UNSIGNED → usuarios'], ['FK', 'id_cuenta_destino', 'INT UNSIGNED → cuentas'],
            ['', 'alias', 'VARCHAR(50)'], ['', 'monto_maximo', 'DECIMAL(15,2)  CHECK (> 0)'], ['', 'max_transacciones_diarias', 'SMALLINT UNSIGNED  (>= 1)'], ['', 'fecha_creacion', 'DATETIME']]],
        'transferencias' => [760, 560, 480, [['PK', 'id_transferencia', 'INT UNSIGNED'], ['FK', 'id_cuenta_origen', 'INT UNSIGNED → cuentas'], ['FK', 'id_cuenta_destino', 'INT UNSIGNED → cuentas'],
            ['FK', 'id_tercero', 'INT UNSIGNED → cuentas_terceros'], ['', 'monto', 'DECIMAL(15,2)  CHECK (> 0)'], ['', 'fecha', 'DATETIME']]],
    ];
    $geo = [];
    foreach ($tablas as $nombre => [$x, $y, $w, $cols]) {
        $h = 44 + count($cols) * 30 + 12; $geo[$nombre] = [$x, $y, $w, $h];
        $L->rect($x, $y, $w, $h, '#ffffff', $azul, 10, 2.5);
        $L->rect($x, $y, $w, 44, $azul, $azul, 10, 0);
        $L->rect($x, $y + 30, $w, 14, $azul, null, 0);
        $L->text($x + $w / 2, $y + 9, $nombre, 21, '#ffffff', true, 'c', FUENTE_B);
        foreach ($cols as $i => [$k, $n, $t]) {
            $cy = $y + 44 + $i * 30 + 6;
            if ($k) {
                $color = ['PK' => '#b07d12', 'FK' => $teal, 'UQ' => '#6a3fa0'][$k];
                $L->rect($x + 10, $cy + 1, 34, 22, $color, null, 5);
                $L->text($x + 27, $cy + 3, $k, 13, '#ffffff', true, 'c');
            }
            $L->text($x + 54, $cy + 2, $n, 16, $azul, $k === 'PK', 'l', $k === 'PK' ? FUENTE_B : FUENTE);
            $L->text($x + 54 + $L->ancho($n, 16, $k === 'PK') + 10, $cy + 4, $t, 13, $gris);
        }
    }
    $rel = function (array $pts, string $n, string $hijo, string $padre) use ($L, $teal) {
        $L->poly($pts, $teal, 3);
        $a = $pts[0]; $b = $pts[1]; $z = $pts[count($pts) - 1]; $y = $pts[count($pts) - 2];
        $off = function ($p, $q) { $dx = $q[0] - $p[0]; $dy = $q[1] - $p[1]; $l = max(1, hypot($dx, $dy)); return [$p[0] + $dx / $l * 18 + (-$dy / $l) * 14, $p[1] + $dy / $l * 18 + ($dx / $l) * 14]; };
        [$tx, $ty] = $off($a, $b); $L->text($tx, $ty - 9, $hijo, 15, '#b3261e', true, 'c');
        [$tx, $ty] = $off($z, $y); $L->text($tx, $ty - 9, $padre, 15, '#b3261e', true, 'c');
        // insignia en el punto medio del tramo más largo
        $mejor = 0; $largo = -1;
        for ($i = 0; $i < count($pts) - 1; $i++) { $l = hypot($pts[$i + 1][0] - $pts[$i][0], $pts[$i + 1][1] - $pts[$i][1]); if ($l > $largo) { $largo = $l; $mejor = $i; } }
        $L->badge(($pts[$mejor][0] + $pts[$mejor + 1][0]) / 2, ($pts[$mejor][1] + $pts[$mejor + 1][1]) / 2, $n);
    };
    // ① usuarios.id_cuenta → cuentas
    $rel([[520, 110], [760, 110]], '1', '0..1', '1');
    // ② cuentas.id_cajero → usuarios
    $rel([[760, 200], [520, 200]], '2', 'N', '1');
    // ③ cuentas_terceros.id_usuario → usuarios
    $rel([[280, 560], [280, $geo['usuarios'][1] + $geo['usuarios'][3]]], '3', 'N', '1');
    // ④ cuentas_terceros.id_cuenta_destino → cuentas
    $rel([[520, 640], [640, 640], [640, 290], [760, 290]], '4', 'N', '1');
    // ⑤ transferencias.id_tercero → cuentas_terceros
    $rel([[760, 720], [520, 720]], '5', 'N', '1');
    // ⑥ ⑦ transferencias origen / destino → cuentas
    $rel([[880, 560], [880, $geo['cuentas'][1] + $geo['cuentas'][3]]], '6', 'N', '1');
    $rel([[1120, 560], [1120, $geo['cuentas'][1] + $geo['cuentas'][3]]], '7', 'N', '1');
    // ⑧ movimientos.id_cuenta → cuentas
    $rel([[1400, 150], [1240, 150]], '8', 'N', '1');
    // ⑨ movimientos.id_transferencia → transferencias
    $by = $geo['movimientos'][1] + $geo['movimientos'][3];
    $rel([[1640, $by], [1640, 680], [1240, 680]], '9', 'N', '1');
    // ⑩ movimientos.id_cajero → usuarios
    $rel([[1520, 70], [1520, 32], [150, 32], [150, 70]], '10', 'N', '1');

    // leyenda
    $L->text(40, 870, 'Relaciones (1 = uno · N = muchos · 0..1 = cero o uno). PK = clave primaria · FK = clave foránea · UQ = valor único', 15, $azul, true);
    $leyenda = [
        '1  Una cuenta tiene como máximo un usuario cliente (usuarios.id_cuenta es UNIQUE).',
        '2  Toda cuenta fue creada por un usuario con rol CAJERO (cuentas.id_cajero).',
        '3  Una cuenta de tercero pertenece a un solo usuario (cuentas_terceros.id_usuario): no se comparte.',
        '4  Cada tercero apunta a una cuenta bancaria existente. UNIQUE(id_usuario, id_cuenta_destino) y UNIQUE(id_usuario, alias).',
        '5  Cada transferencia se hizo a través de un tercero del usuario (límites de monto y de transferencias diarias).',
        '6  Cuenta de origen (la del usuario autenticado).        7  Cuenta de destino.',
        '8  Cada movimiento afecta a una cuenta (libro de operaciones: débitos y créditos con saldo resultante).',
        '9  Una transferencia genera exactamente 2 movimientos (débito en origen y crédito en destino).',
        '10 Los movimientos de ventanilla (apertura, depósito, retiro) registran el cajero que atendió.',
    ];
    foreach ($leyenda as $i => $l) {
        $col = intdiv($i, 5); $fila = $i % 5;
        $L->text(40 + $col * 960, 900 + $fila * 24, $l, 14, '#1c2733');
    }
    $L->guardar($ruta);
}

// =============================================================== 3. CASOS DE USO
function actor(Lienzo $L, float $x, float $y, string $nombre): void
{
    $a = '#0b2a4a';
    $L->ellipse($x, $y, 34, 34, '#ffffff', $a, 3);
    $L->line($x, $y + 17, $x, $y + 70, $a, 3); $L->line($x - 28, $y + 38, $x + 28, $y + 38, $a, 3);
    $L->line($x, $y + 70, $x - 24, $y + 108, $a, 3); $L->line($x, $y + 70, $x + 24, $y + 108, $a, 3);
    $L->text($x, $y + 118, $nombre, 18, $a, true, 'c');
}
function caso_uso(Lienzo $L, float $cx, float $cy, string $txt, bool $incluido = false): array
{
    $w = $incluido ? 330 : 380; $h = 62;
    $L->ellipse($cx, $cy, $w, $h, $incluido ? '#fff8e8' : '#e8f1fb', $incluido ? '#b07d12' : '#0e7c86', 2.5);
    $lineas = $L->ancho($txt, 15, false) > $w - 70 ? 2 : 1;
    if ($lineas === 1) { $L->text($cx, $cy - 10, $txt, 15, '#1c2733', false, 'c'); }
    else { $L->parrafo($cx, $cy - 19, $txt, 15, $w - 70, '#1c2733', false, 1.25); }
    return [$cx - $w / 2, $cx + $w / 2, $cy];
}
function diagrama_casos(string $ruta, string $titulo, string $rolActor, array $principales, array $incluidos, array $includes): void
{
    $n = max(count($principales), count($incluidos) * 1.4);
    $alto = (int) (150 + max(count($principales) * 92, count($incluidos) * 120) + 60);
    $L = new Lienzo(1300, max(520, $alto));
    $L->text(650, 12, $titulo, 24, '#0b2a4a', true, 'c');
    $L->rect(210, 60, 1060, $L->h - 80, '#fbfcfe', '#9fb3c8', 14, 2);
    $L->text(740, 72, 'Sistema de Banca en Línea', 16, '#5d6b7a', true, 'c');
    $pos = [];
    foreach ($principales as $i => $t) { $pos[$t] = caso_uso($L, 430, 150 + $i * 92, $t); }
    $step = count($incluidos) > 1 ? ($L->h - 260) / (count($incluidos) - 1) : 0;
    $total = count($incluidos);
    foreach ($incluidos as $i => $t) {
        $y = $total > 1 ? 150 + $i * min(120, ($L->h - 260) / ($total - 1)) : 170;
        $pos[$t] = caso_uso($L, 1035, $y, $t, true);
    }
    $ay = 150 + (count($principales) - 1) * 46 - 40;
    actor($L, 105, $ay, $rolActor);
    foreach ($principales as $t) { $L->poly([[130, $ay + 45], [$pos[$t][0] + 4, $pos[$t][2]]], '#0b2a4a', 2); }
    foreach ($includes as [$de, $a]) {
        if (!isset($pos[$de], $pos[$a])) { continue; }
        $L->poly([[$pos[$de][1], $pos[$de][2]], [$pos[$a][0], $pos[$a][2]]], '#b07d12', 2, true, true);
        $L->text(($pos[$de][1] + $pos[$a][0]) / 2, ($pos[$de][2] + $pos[$a][2]) / 2 - 18, '«include»', 12, '#b07d12', true, 'c');
    }
    $L->guardar($ruta);
}

echo "Generando diagramas en $salida\n";
diagrama_arquitectura("$salida/diagrama_arquitectura.png");
diagrama_er("$salida/diagrama_er.png");
diagrama_casos("$salida/casos_uso_visitante.png", 'Casos de uso: Visitante (página principal)', 'Visitante',
    ['Iniciar sesión de administrador', 'Iniciar sesión de cajero', 'Iniciar sesión de usuario', 'Registrar nuevo usuario'],
    ['Verificar contraseña (password_verify)', 'Validar que la cuenta exista', 'Validar que el DPI coincida', 'Validar que la cuenta no tenga usuario'],
    [['Iniciar sesión de administrador', 'Verificar contraseña (password_verify)'], ['Iniciar sesión de cajero', 'Verificar contraseña (password_verify)'], ['Iniciar sesión de usuario', 'Verificar contraseña (password_verify)'],
     ['Registrar nuevo usuario', 'Validar que la cuenta exista'], ['Registrar nuevo usuario', 'Validar que el DPI coincida'], ['Registrar nuevo usuario', 'Validar que la cuenta no tenga usuario']]);
diagrama_casos("$salida/casos_uso_administrador.png", 'Casos de uso: Administrador', 'Administrador',
    ['Iniciar sesión', 'Listar cajeros', 'Crear cajero', 'Bloquear / desbloquear cajero', 'Monitor de transferencias', 'Cerrar sesión'],
    ['Guardar clave con hash (bcrypt)', 'Ver estadísticas del día', 'Ver gráfica depósitos vs. retiros'],
    [['Crear cajero', 'Guardar clave con hash (bcrypt)'], ['Monitor de transferencias', 'Ver estadísticas del día'], ['Monitor de transferencias', 'Ver gráfica depósitos vs. retiros']]);
diagrama_casos("$salida/casos_uso_cajero.png", 'Casos de uso: Cajero', 'Cajero',
    ['Iniciar sesión', 'Crear cuenta monetaria', 'Depósito monetario', 'Retiro monetario', 'Cerrar sesión'],
    ['Validar número de cuenta único y DPI', 'Validar monto mayor que cero', 'Validar saldo suficiente', 'Registrar movimiento y actualizar saldo'],
    [['Crear cuenta monetaria', 'Validar número de cuenta único y DPI'], ['Depósito monetario', 'Validar monto mayor que cero'], ['Retiro monetario', 'Validar monto mayor que cero'],
     ['Retiro monetario', 'Validar saldo suficiente'], ['Crear cuenta monetaria', 'Registrar movimiento y actualizar saldo'], ['Depósito monetario', 'Registrar movimiento y actualizar saldo'], ['Retiro monetario', 'Registrar movimiento y actualizar saldo']]);
diagrama_casos("$salida/casos_uso_cliente.png", 'Casos de uso: Usuario cliente', 'Usuario cliente',
    ['Iniciar sesión', 'Agregar cuenta de tercero', 'Transferir a cuenta de tercero', 'Consultar estado de cuenta', 'Cerrar sesión'],
    ['Validar que la cuenta exista y no sea propia', 'Validar propiedad del tercero', 'Validar monto máximo y límite diario', 'Validar saldo suficiente', 'Ejecutar transacción (COMMIT / ROLLBACK)'],
    [['Agregar cuenta de tercero', 'Validar que la cuenta exista y no sea propia'], ['Transferir a cuenta de tercero', 'Validar propiedad del tercero'], ['Transferir a cuenta de tercero', 'Validar monto máximo y límite diario'],
     ['Transferir a cuenta de tercero', 'Validar saldo suficiente'], ['Transferir a cuenta de tercero', 'Ejecutar transacción (COMMIT / ROLLBACK)']]);
echo "Listo.\n";
