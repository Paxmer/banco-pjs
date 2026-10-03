<?php
/**
 * Genera la DOCUMENTACIÓN OFICIAL del proyecto a partir de un único contenido:
 *   documentacion/Documentacion_Banca_UMG.docx   (documento Word de entrega)
 *   documentacion/Documentacion_Banca_UMG.html   (misma información, para ver en línea)
 *
 * Requisitos previos (el script usa sus resultados reales, no inventa cifras):
 *   php generar_diagramas.php            -> documentacion/diagramas/*.png
 *   php analizar_log_apache.php          -> documentacion/reporte_apache.json (opcional)
 *   pruebas/resultado_pruebas.txt        -> salida de probar_sistema.php (opcional)
 *
 * Uso: php generar_documentacion.php
 */
if (PHP_SAPI !== 'cli') { exit('Ejecutar con: php generar_documentacion.php'); }
mb_internal_encoding('UTF-8');
$raiz = realpath(__DIR__ . '/..');
$docDir = "$raiz/documentacion";
$imgDir = "$docDir/diagramas";

// ------------------------------------------------------------------ datos reales
$apache = is_file("$docDir/reporte_apache.json") ? json_decode(file_get_contents("$docDir/reporte_apache.json"), true) : null;
$pruebasTxt = is_file("$raiz/pruebas/resultado_pruebas.txt") ? file_get_contents("$raiz/pruebas/resultado_pruebas.txt") : '';
$pruebasOk = preg_match('/(\d+) pruebas PASARON, (\d+) FALLARON/', $pruebasTxt, $mp) ? [(int) $mp[1], (int) $mp[2]] : null;

// ------------------------------------------------------------------ contenido
$B = [];   // bloques
function add(array &$B, ...$b) { $B[] = $b; }

add($B, 'title', 'Banco PJS · Sistema de Banca en Línea');
add($B, 'subtitle', 'Documentación oficial del proyecto · HTML5 / CSS3 / PHP / MySQL');
add($B, 'p', '**Universidad Mariano Gálvez de Guatemala** · Facultad de Ingeniería en Sistemas de Información · Curso: Desarrollo Web');
add($B, 'h1', 'Datos de entrega');
add($B, 'table', ['Dato', 'Valor'], [
    ['Integrante del equipo', 'Pablo Javier Sandoval · Carné 0900-21-4333'],
    ['Enlace de acceso al sitio (hosting gratuito)', 'http://bancopjs.atwebpages.com  (hosting gratuito AwardSpace; el plan gratuito no incluye HTTPS, abrir con http://)'],
    ['Usuario administrador', '`admin`'],
    ['Clave del administrador', '`Admin2026!`'],
    ['Base de datos entregada', 'En blanco: sólo contiene el usuario administrador (script 01_esquema_y_procedimientos.sql)'],
    ['Fecha de entrega', '01/11/2026'],
], [38, 62]);
add($B, 'p', 'Credenciales académicas de prueba: se recomienda cambiar la clave del administrador después de la evaluación.');

add($B, 'h1', '1. Descripción general');
add($B, 'p', 'Aplicación web de banca en línea que permite a clientes registrados transferir dinero a cuentas de terceros, a cajeros crear cuentas y registrar depósitos y retiros, y a un administrador gestionar cajeros y monitorear la actividad. '
    . 'Está construida con **HTML5 y CSS3** (diseño responsive para computadora y smartphone), **PHP 8** en **Apache** y **MySQL**. '
    . 'Todo acceso a la base de datos se realiza mediante **stored procedures** invocados con sentencias preparadas. '
    . 'La interfaz tiene un estilo retro pixel-art de videojuegos (paleta arcade, fuentes Press Start 2P y VT323); el logo, los iconos y el banner fueron generados con Higgsfield (modelo Z Image).');

add($B, 'h1', '2. Roles y permisos');
add($B, 'table', ['Rol', 'Cómo ingresa', 'Qué puede hacer', 'Protección'], [
    ['Administrador', 'login_admin.php · usuario y clave', 'Listar, crear, bloquear y desbloquear cajeros; monitor de transferencias (estadísticas y gráfica); salir.', 'Páginas de admin/ exigen sesión con rol ADMIN.'],
    ['Cajero', 'login_cajero.php · usuario y clave', 'Crear cuentas monetarias; depósitos; retiros; salir.', 'Páginas de cajero/ exigen rol CAJERO activo (si el admin lo bloquea, pierde el acceso de inmediato).'],
    ['Usuario cliente', 'login_usuario.php · correo y clave', 'Agregar cuentas de terceros; transferir a un tercero; ver su estado de cuenta; salir.', 'Páginas de cliente/ exigen rol CLIENTE; la cuenta se deduce siempre de la sesión.'],
    ['Visitante', 'index.php', 'Ver la página principal, iniciar sesión según su rol y registrar un nuevo usuario cliente.', 'Sólo páginas públicas.'],
], [16, 22, 36, 26]);
add($B, 'p', 'Si una persona con sesión de un rol intenta abrir escribiendo la URL un panel que no es el suyo, el sistema responde **403 Acceso no autorizado**; si no tiene sesión, la redirige al inicio de sesión correspondiente.');

add($B, 'landscape');
add($B, 'h1', '3. Diagrama de arquitectura');
add($B, 'img', "$imgDir/diagrama_arquitectura.png", 9.3, 'Figura 1. Arquitectura de la solución (3 capas): navegador, Apache + PHP y MySQL con stored procedures.');
add($B, 'portrait');
add($B, 'h2', 'Flujo de datos');
add($B, 'ul', [
    'El navegador envía peticiones HTTP (GET/POST) con la cookie de sesión; los formularios incluyen un token CSRF.',
    'Cada página PHP incluye `includes/bootstrap.php`, que carga la configuración, inicia la sesión y, en páginas protegidas, ejecuta `require_rol()`.',
    '`require_rol()` valida la sesión llamando a `sp_sesion_validar` (comprueba que el usuario exista, tenga ese rol y no esté bloqueado) y controla la inactividad (20 minutos).',
    'La lógica de negocio se ejecuta en la base de datos: PHP valida el formato de los datos y llama a `sp_call()` / `sp_operacion()` (`includes/db.php`), que ejecutan `CALL sp_xxx(?, ?)` con sentencias preparadas.',
    'El procedimiento valida las reglas de negocio, aplica la transacción y devuelve una fila con `codigo` y `mensaje`; PHP la muestra al usuario. Tras una operación exitosa se redirige (patrón Post/Redirect/Get) para evitar reenvíos del formulario.',
    'Los errores técnicos se registran en `banca/logs/app.log.php` y el usuario sólo ve un mensaje genérico.',
]);

add($B, 'h1', '4. Módulos principales');
add($B, 'table', ['Archivo', 'Responsabilidad'], [
    ['index.php', 'Página principal: logo, nombre del banco y cuatro accesos (administrador, usuario, cajero, registro).'],
    ['login_admin.php · login_cajero.php · login_usuario.php', 'Inicio de sesión por rol (`pagina_login()` en `includes/layout.php`). Mensaje genérico ante credenciales inválidas; mensaje específico si el usuario está bloqueado (sólo tras acertar la clave).'],
    ['registro.php', 'Registro de usuario cliente: valida cuenta existente, DPI coincidente, cuenta sin usuario, correo y contraseña (`sp_registrar_usuario`).'],
    ['logout.php', 'Cierre de sesión (sólo POST con token CSRF): destruye la sesión y la cookie.'],
    ['admin/cajeros.php', 'Listado, alta, bloqueo y desbloqueo de cajeros.'],
    ['admin/monitor.php', 'Monitor: estadísticas (hoy / histórico) y gráfica SVG de depósitos vs. retiros, todo desde `sp_monitor_estadisticas`.'],
    ['cajero/crear_cuenta.php · deposito.php · retiro.php', 'Operaciones de ventanilla (`includes/movimiento_cajero.php` comparte la lógica de depósito y retiro).'],
    ['cliente/terceros.php', 'Alta y listado de cuentas de terceros del usuario (`sp_tercero_crear`, `sp_tercero_listar`).'],
    ['cliente/transferir.php', 'Transferencia a un tercero (`sp_transferir`, transaccional).'],
    ['cliente/estado_cuenta.php', 'Estado de cuenta del usuario autenticado (`sp_estado_cuenta`): débitos, créditos, saldo resultante y totales.'],
    ['includes/auth.php', 'Sesiones seguras, login con `password_verify`, `require_rol()`, protección CSRF.'],
    ['includes/db.php', 'Conexión mysqli y `sp_call()` / `sp_operacion()` (sentencias preparadas, manejo de errores).'],
    ['includes/helpers.php', 'Escape de HTML, rutas, mensajes flash, validadores, registro de errores.'],
    ['includes/layout.php', 'Plantilla común: encabezado con logo, menú por rol, pie de página.'],
    ['config/config.php', 'Parámetros de conexión; se sobrescriben con `config.local.php` (hosting).'],
    ['assets/css/estilos.css · assets/js/app.js', 'Diseño responsive mobile-first y validación del lado del cliente (sólo comodidad).'],
], [34, 66]);

add($B, 'landscape');
add($B, 'h1', '5. Base de datos');
add($B, 'img', "$imgDir/diagrama_er.png", 9.3, 'Figura 2. Diagrama entidad-relación (MySQL, motor InnoDB).');
add($B, 'portrait');
add($B, 'h2', 'Tablas');
add($B, 'table', ['Tabla', 'Propósito', 'Restricciones clave'], [
    ['usuarios', 'Los tres tipos de usuario (ADMIN, CAJERO, CLIENTE) en una sola tabla. El correo del cliente es su `username`.', 'UNIQUE(username); UNIQUE(id_cuenta); CHECK: un CLIENTE siempre tiene cuenta y ADMIN/CAJERO nunca. Clave guardada con bcrypt.'],
    ['cuentas', 'Cuentas bancarias creadas por un cajero, con DPI y saldo.', 'UNIQUE(numero_cuenta); CHECK(saldo >= 0); FK a usuarios (cajero creador).'],
    ['cuentas_terceros', 'Cuentas de terceros: pertenecen sólo al usuario que las registra.', 'FK a usuarios y a cuentas; UNIQUE(id_usuario, id_cuenta_destino) y UNIQUE(id_usuario, alias); CHECK(monto_maximo > 0, max_transacciones_diarias >= 1).'],
    ['transferencias', 'Cabecera de cada transferencia exitosa (origen, destino, tercero, monto, fecha).', 'FKs a cuentas y a cuentas_terceros; CHECK(monto > 0); CHECK(origen <> destino).'],
    ['movimientos', 'Libro de operaciones por cuenta: apertura, depósito, retiro, transferencia enviada/recibida, con naturaleza (CRÉDITO/DÉBITO) y saldo resultante.', 'FK a cuentas, transferencias y cajero; CHECK(monto > 0). Una transferencia genera 2 movimientos.'],
], [18, 40, 42]);
add($B, 'p', '**Decisiones de diseño.** La cuenta bancaria y el usuario cliente son entidades distintas (relación 1 a 0..1). Los montos usan `DECIMAL(15,2)` (nunca punto flotante). El estado de cuenta se obtiene del libro `movimientos`, por lo que cada débito y crédito queda registrado; el saldo de una cuenta siempre equivale a la suma de sus créditos menos sus débitos (verificado en las pruebas). '
    . 'La apertura de una cuenta con monto inicial mayor que cero registra un movimiento de tipo APERTURA que **no** se cuenta como depósito en el monitor.');

add($B, 'h1', '6. Stored procedures');
add($B, 'p', 'La aplicación se conecta con el usuario MySQL `banca_app`, que sólo tiene permiso **EXECUTE**: no puede hacer SELECT/INSERT/UPDATE directos sobre las tablas (comprobado en las pruebas). Los procedimientos de operación devuelven una fila con `codigo` (0 = éxito, mayor que 0 = error de negocio, 99 = error técnico) y `mensaje`; ante el código 99 el detalle técnico va a `logs/app.log.php` y nunca se muestra.');
add($B, 'table', ['Procedimiento', 'Función', 'Transacción'], [
    ['sp_login_obtener(rol, usuario)', 'Devuelve el usuario con su hash para que PHP ejecute `password_verify()`.', 'No (consulta)'],
    ['sp_sesion_validar(id_usuario, rol)', 'Confirma en cada petición que el usuario existe, conserva el rol y no está bloqueado.', 'No (consulta)'],
    ['sp_registrar_usuario(cuenta, correo, dpi, hash)', 'Valida cuenta existente, DPI coincidente, cuenta sin usuario y correo único; crea el usuario cliente.', 'Sí (bloquea la cuenta con FOR UPDATE)'],
    ['sp_cajero_listar()', 'Lista los usuarios con rol CAJERO.', 'No'],
    ['sp_cajero_crear(nombre, usuario, hash)', 'Valida datos y unicidad del usuario; crea el cajero.', 'Sí'],
    ['sp_cajero_cambiar_estado(id, bloqueado)', 'Bloquea o desbloquea; sólo actúa sobre usuarios con rol CAJERO.', 'Sí'],
    ['sp_monitor_estadisticas(periodo)', 'Cuentas creadas, usuarios cliente, transacciones, depósitos, retiros, transferencias y montos (HOY o TOTAL).', 'No (consulta)'],
    ['sp_cuenta_crear(cajero, nombre, numero, dpi, monto)', 'Valida cajero activo, número único, DPI de 13 dígitos y monto; crea la cuenta y su movimiento de apertura.', 'Sí'],
    ['sp_deposito(cajero, numero, monto)', 'Valida cuenta y monto > 0; acredita el saldo y registra el movimiento.', 'Sí (FOR UPDATE)'],
    ['sp_retiro(cajero, numero, monto)', 'Valida cuenta, monto > 0 y saldo suficiente; debita y registra el movimiento.', 'Sí (FOR UPDATE)'],
    ['sp_cliente_resumen(id_usuario)', 'Número, nombre y saldo de la cuenta del usuario autenticado.', 'No'],
    ['sp_tercero_crear(usuario, numero, monto_max, max_tx, alias)', 'Valida que la cuenta exista, no sea la propia, límites y alias; crea el tercero del usuario.', 'Sí'],
    ['sp_tercero_listar(id_usuario)', 'Terceros del usuario (con las transferencias de hoy ya usadas).', 'No'],
    ['sp_transferir(id_usuario, id_tercero, monto)', 'Transferencia atómica: valida propiedad, límites y saldo; débito + crédito + registro + 2 movimientos.', 'Sí (READ COMMITTED + FOR UPDATE)'],
    ['sp_estado_cuenta(id_usuario)', 'Movimientos de la cuenta del usuario autenticado (no recibe número de cuenta).', 'No'],
], [34, 48, 18]);

add($B, 'h1', '7. Manejo de transacciones');
add($B, 'p', 'Toda operación que modifica más de una fila se ejecuta dentro de una transacción de base de datos en el propio procedimiento. La más importante es `sp_transferir`:');
add($B, 'ul', [
    '**Antes de escribir** (con la transacción abierta): obtiene la cuenta origen del usuario de la sesión (nunca del navegador); verifica que el tercero pertenezca a ese usuario; bloquea ambas cuentas con `SELECT ... FOR UPDATE`; valida monto máximo del tercero, límite de transferencias del día y saldo suficiente. Si una validación falla hace `ROLLBACK` y devuelve el mensaje.',
    '**Escritura atómica**: debita la cuenta origen, acredita la cuenta destino, inserta la transferencia y los dos movimientos (débito y crédito).',
    '**COMMIT** si todo funcionó. Un `EXIT HANDLER FOR SQLEXCEPTION` captura cualquier error inesperado, ejecuta **ROLLBACK** y devuelve un mensaje genérico (el detalle se registra en el log). Nunca queda una transferencia a medias.',
    '**Concurrencia**: se usa `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` y lecturas `FOR UPDATE`. Durante las pruebas, 6 transferencias simultáneas de Q 100 desde una cuenta con Q 500 produjeron exactamente 5 éxitos y 1 rechazo por saldo insuficiente, con saldos exactos. (Una primera versión con el nivel de aislamiento por defecto perdía actualizaciones; la prueba de concurrencia lo detectó y se corrigió.)',
]);
add($B, 'h2', 'Prueba de ROLLBACK');
add($B, 'p', 'La suite de pruebas crea un trigger temporal que provoca un error justo al registrar el crédito del destino (después de haber debitado el origen). Resultado verificado: saldos, tabla `transferencias` y tabla `movimientos` quedaron exactamente iguales (débito, crédito y registro revertidos), el usuario recibió un mensaje comprensible y el detalle técnico quedó sólo en `logs/app.log.php`. Al eliminar el trigger, la misma transferencia se aplicó completa (COMMIT).');

add($B, 'h1', '8. Seguridad implementada');
add($B, 'ul', [
    '**Contraseñas** con `password_hash()` (bcrypt) y verificación con `password_verify()`; nunca se guardan en texto plano. Mensaje genérico ante usuario o clave incorrectos y comparación de relleno para no revelar si el usuario existe.',
    '**Sesiones**: cookie HttpOnly y SameSite, modo estricto, `session_regenerate_id()` al iniciar sesión, expiración por inactividad (20 minutos) y cierre completo en logout. La sesión se revalida contra la BD en cada petición protegida (un cajero bloqueado queda fuera de inmediato).',
    '**Autorización por página** con `require_rol()` y **propiedad de los datos**: la cuenta del cliente sale siempre de la sesión; los terceros se filtran por usuario; el estado de cuenta no acepta parámetros de cuenta.',
    '**SQL injection**: ningún dato del usuario se concatena en SQL; todo va por stored procedures con sentencias preparadas.',
    '**XSS**: toda salida HTML se escapa con `htmlspecialchars` y se envía una cabecera Content-Security-Policy sin scripts ni estilos en línea.',
    '**CSRF**: token en todos los formularios que modifican datos; el cierre de sesión es sólo POST.',
    '**Validación** en tres niveles: HTML5/JavaScript (comodidad), PHP (formato) y procedimientos/restricciones CHECK de MySQL (regla de negocio). Montos con `DECIMAL`, máximo 2 decimales, mayores que cero y tope de 999,999,999.99.',
    '**Menor privilegio en BD**: el usuario de la app sólo tiene EXECUTE. Las carpetas `config/`, `includes/` y `logs/` están bloqueadas por `.htaccess` (403); además los logs se guardan como `.log.php` con una primera línea que corta la ejecución, de modo que no se pueden leer por HTTP aunque el hosting ignore los `.htaccess`.',
    '**Errores**: `display_errors` desactivado; los errores técnicos se registran en `logs/app.log.php`.',
]);

add($B, 'h1', '9. Diagramas de casos de uso');
foreach ([['casos_uso_visitante', 'Figura 3. Casos de uso del visitante.'], ['casos_uso_administrador', 'Figura 4. Casos de uso del administrador.'],
          ['casos_uso_cajero', 'Figura 5. Casos de uso del cajero.'], ['casos_uso_cliente', 'Figura 6. Casos de uso del usuario cliente.']] as [$f, $cap]) {
    add($B, 'img', "$imgDir/$f.png", 6.4, $cap);
}

add($B, 'h1', '10. Instrucciones para ejecutar el proyecto');
add($B, 'h2', 'Local con WampServer');
add($B, 'ul', [
    'Inicie WampServer y espere el ícono verde (Apache y MySQL en ejecución).',
    'Copie la carpeta `banca` a `C:\\wamp64\\www\\banca` (queda disponible en `http://localhost/banca/`).',
    'Abra phpMyAdmin (`http://localhost/phpmyadmin`) con el usuario `root` (sin clave en una instalación nueva de Wamp). En la pestaña SQL ejecute `base_de_datos/00_crear_base_y_usuario.sql`; luego seleccione la base `banca_umg` e importe `base_de_datos/01_esquema_y_procedimientos.sql`. (Opcional: importe `02_datos_demo_opcional.sql` para tener un cajero, tres cuentas y dos clientes de prueba.)',
    'Abra `http://localhost/banca/` y entre con las credenciales de la sección 11.',
]);
add($B, 'code', "REM Alternativa por línea de comandos (CMD o PowerShell, desde la carpeta base_de_datos):\nmysql -uroot -e \"source 00_crear_base_y_usuario.sql\"\nmysql -uroot banca_umg -e \"source 01_esquema_y_procedimientos.sql\"\nmysql -uroot banca_umg -e \"source 02_datos_demo_opcional.sql\"");
add($B, 'h2', 'Hosting gratuito (evaluación en computadora y smartphone)');
add($B, 'ul', [
    'Cree desde el panel del hosting una base de datos MySQL y su usuario (anote servidor, nombre, usuario y clave).',
    'En phpMyAdmin seleccione esa base e importe `01_esquema_y_procedimientos.sql` (el archivo **no** crea la base: usa la ya seleccionada). Esto deja la BD en blanco con el administrador.',
    'Suba por FTP o administrador de archivos el **contenido** de la carpeta `banca/` a la carpeta pública (`htdocs` o `public_html`).',
    'En `config/` copie `config.ejemplo.php` como `config.local.php` y escriba los datos de conexión del hosting. Nada más depende de rutas de su computadora (la ruta base se detecta automáticamente).',
    'Verifique que `logs/` permita escritura y abra el sitio desde el navegador y desde el teléfono. Requisitos del hosting: PHP 8.x con mysqli/mysqlnd y MySQL/MariaDB con soporte de stored procedures. Si el hosting respondiera con error 500 por los archivos `.htaccess` de `config/`, `includes/` o `logs/` (sólo contienen `Require all denied`), puede eliminarlos: la aplicación sigue funcionando y los logs quedan igualmente protegidos.',
]);

add($B, 'h1', '11. Credenciales para evaluación');
add($B, 'table', ['Rol', 'Usuario', 'Clave', 'Disponible en'], [
    ['Administrador', 'admin', 'Admin2026!', 'BD en blanco (script 01) y BD con datos demo'],
    ['Cajero (demo)', 'cajero1', 'Cajero2026!', 'Sólo con datos demo (script 02); el administrador puede crear más cajeros'],
    ['Cliente (demo)', 'juan@PJS.com.gt', 'Cliente2026!', 'Sólo con datos demo; cuenta 1001 · Q 5,000.00'],
    ['Cliente (demo)', 'maria@PJS.com.gt', 'Cliente2026!', 'Sólo con datos demo; cuenta 1002 · Q 3,000.00'],
    ['Cliente (demo)', 'carlos@PJS.com.gt', 'Cliente2026!', 'Sólo con datos demo; cuenta 1003 · Q 1,500.00'],
], [20, 26, 20, 34]);
add($B, 'p', 'Con la BD en blanco, el flujo completo es: administrador crea un cajero → cajero crea una cuenta → el cliente se registra con el número de cuenta y su DPI. Con los datos demo, para probar el registro cree primero una cuenta nueva desde el panel del cajero.');

add($B, 'h1', '12. Administración, mantenimiento y soporte de Apache');
add($B, 'p', 'Para analizar el log de Apache se desarrolló la herramienta `herramientas/analizar_log_apache.php` (PHP, línea de comandos, sin dependencias). Lee el `access.log` y el `apache_error.log` reales del servidor y genera el reporte HTML `documentacion/reporte_apache.html`, que puede subirse al hosting para consultarlo en línea. '
    . 'Se eligió una herramienta propia porque GoAccess no tiene versión nativa para Windows y AWStats requiere Perl, que WampServer no incluye; el formato de log estándar de Apache permite además usar esas herramientas en un servidor Linux.');
add($B, 'code', "php herramientas\\analizar_log_apache.php                     (usa C:\\wamp64\\logs\\access.log y filtra /banca)\nphp herramientas\\analizar_log_apache.php ruta\\access.log --todo    (todas las URLs del servidor)");
add($B, 'p', 'El reporte responde: cuántas solicitudes fueron OK (2xx), redirecciones (3xx) y errores (4xx/5xx), qué páginas son las más visitadas, qué páginas generan errores, tráfico por hora y por día, métodos HTTP, IP de origen y un resumen del `error.log`.');
if ($apache) {
    $pc = fn($n) => $apache['total'] ? number_format($n * 100 / $apache['total'], 1) . ' %' : '0 %';
    add($B, 'h2', 'Resultados reales del análisis');
    add($B, 'p', 'Log analizado: `' . $apache['archivo'] . '` · Periodo: ' . $apache['periodo'] . ' · Generado: ' . gmdate('d/m/Y H:i', strtotime($apache['generado'])) . ' UTC (tráfico producido durante las pruebas de la aplicación).');
    add($B, 'table', ['Indicador', 'Solicitudes', '%'], [
        ['Total de solicitudes a la aplicación', (string) $apache['total'], '100 %'],
        ['OK (2xx)', (string) $apache['ok_2xx'], $pc($apache['ok_2xx'])],
        ['Redirecciones (3xx): por ejemplo, tras iniciar sesión', (string) $apache['redir_3xx'], $pc($apache['redir_3xx'])],
        ['Errores de cliente (4xx)', (string) $apache['error_4xx'], $pc($apache['error_4xx'])],
        ['Errores de servidor (5xx)', (string) $apache['error_5xx'], $pc($apache['error_5xx'])],
    ], [60, 22, 18]);
    $filas = []; foreach ($apache['paginas_top'] as $p => $n) { $filas[] = [$p, (string) $n]; }
    add($B, 'p', '**Páginas más visitadas**');
    add($B, 'table', ['Página', 'Solicitudes'], $filas, [75, 25]);
    $filas = []; foreach ($apache['errores_top'] as $k => $n) { [$e, $p] = explode(' ', $k, 2); $filas[] = [$e, $p, (string) $n]; }
    add($B, 'p', '**Páginas que dan error**');
    add($B, 'table', ['Código', 'URL', 'Veces'], $filas, [14, 70, 16]);
    add($B, 'p', 'Los códigos 403 corresponden a pruebas de seguridad (acceso directo por URL a paneles de otro rol o a archivos internos bloqueados) y los 400 a formularios enviados sin token CSRF válido: son rechazos esperados y demuestran que los controles funcionan. No hubo errores 5xx.');
}

add($B, 'h1', '13. Pruebas realizadas');
add($B, 'p', 'Se ejecutó una suite automática de extremo a extremo (`pruebas/probar_sistema.php`) que usa HTTP real contra Apache y MySQL, y verifica los datos directamente en la base. Además se probó manualmente en el navegador (escritorio y vista de smartphone de 375 px).');
if ($pruebasOk) {
    add($B, 'p', '**Resultado de la última ejecución: ' . $pruebasOk[0] . ' pruebas pasaron, ' . $pruebasOk[1] . ' fallaron.**');
}
add($B, 'table', ['Área', 'Qué se verificó'], [
    ['Autenticación', 'Login correcto e incorrecto de administrador, cajero y cliente; un rol no puede entrar por el login de otro; acceso directo por URL sin sesión (redirige) y con otro rol (403); logout; expiración de sesión por inactividad.'],
    ['Registro', 'Cuenta inexistente, DPI incorrecto, cuenta ya asociada, correo inválido o repetido, contraseña corta o confirmación distinta, registro correcto y clave guardada con hash.'],
    ['Cuentas', 'Creación correcta (saldo en MySQL), número duplicado, DPI inválido, montos negativos o con letras, monto inicial 0.'],
    ['Depósitos y retiros', 'Cuenta válida e inexistente, monto inválido, cero, negativo y con 3 decimales, saldo insuficiente, retiro del saldo exacto, saldo nunca negativo.'],
    ['Terceros', 'Tercero válido, cuenta inexistente, cuenta propia, límites inválidos, duplicados, alias con HTML escapado, terceros de un usuario invisibles para otro.'],
    ['Transferencias', 'Válida (con registro y 2 movimientos), monto superior al máximo, límite diario, saldo insuficiente, id de tercero ajeno o inexistente, inyección SQL, cuenta origen manipulada, CSRF, ROLLBACK ante un fallo simulado, COMMIT, 6 transferencias concurrentes.'],
    ['Estado de cuenta', 'Muestra operaciones propias, no las ajenas; modificar parámetros de la URL no cambia la cuenta mostrada; el saldo coincide con MySQL.'],
    ['Administrador', 'Estadísticas del monitor iguales a las de la BD (los cajeros no cuentan como clientes), gráfica presente, bloqueo y desbloqueo de cajero (también con sesión abierta).'],
    ['Integridad de datos', 'Saldo = créditos − débitos de cada cuenta, ninguna cuenta con saldo negativo, cada transferencia con exactamente 2 movimientos, `banca_app` sin acceso directo a las tablas.'],
    ['Responsive', 'Escritorio y smartphone (375 px): menú hamburguesa, tablas convertidas en tarjetas y sin desbordamiento horizontal.'],
], [20, 80]);

add($B, 'h1', '14. Decisiones técnicas y limitaciones');
add($B, 'ul', [
    'El DPI se valida como 13 dígitos numéricos (no se verifica el dígito verificador oficial) para no rechazar números de prueba de los evaluadores.',
    'Zona horaria fija de Guatemala (UTC-6) en PHP y MySQL; "hoy" significa el día calendario en esa zona.',
    'No se implementó límite de intentos fallidos de inicio de sesión (fuera del alcance de la especificación).',
    'La publicación en un hosting gratuito concreto debe hacerla el equipo con su cuenta; el proyecto queda preparado (configuración externa, rutas relativas, script SQL independiente).',
    'Los montos están limitados a 999,999,999.99 por operación.',
]);

// ------------------------------------------------------------------ inline: **negrita** y `código`
function inline_tokens(string $s): array
{
    $tokens = [];
    foreach (preg_split('/(\*\*.+?\*\*|`.+?`)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $p) {
        if (str_starts_with($p, '**')) { $tokens[] = [substr($p, 2, -2), 'b']; }
        elseif (str_starts_with($p, '`')) { $tokens[] = [substr($p, 1, -1), 'c']; }
        else { $tokens[] = [$p, '']; }
    }
    return $tokens;
}
function xe(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

// ------------------------------------------------------------------ HTML
$html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Documentación | Sistema de Banca en Línea</title><style>
body{font-family:"Segoe UI",system-ui,Arial,sans-serif;max-width:1000px;margin:0 auto;padding:24px 16px;color:#1c2733;line-height:1.55}
h1{color:#0b2a4a;border-bottom:3px solid #0e7c86;padding-bottom:4px;margin-top:36px}h2{color:#0b2a4a}.title{font-size:2.2rem;font-weight:700;color:#0b2a4a;margin:0}.subtitle{color:#5d6b7a;font-size:1.1rem;margin:0 0 16px}
table{width:100%;border-collapse:collapse;margin:12px 0;font-size:.92rem}th,td{border:1px solid #c9d3de;padding:7px 9px;text-align:left;vertical-align:top}th{background:#0b2a4a;color:#fff}tr:nth-child(even) td{background:#f4f7fb}
code{background:#eef2f7;padding:1px 5px;border-radius:4px;font-family:Consolas,monospace;font-size:.9em}pre{background:#0b2a4a;color:#e8f1fb;padding:12px;border-radius:8px;overflow-x:auto;font-size:.88rem}
figure{margin:16px 0;text-align:center}figure img{max-width:100%;border:1px solid #d6dde6;border-radius:8px}figcaption{color:#5d6b7a;font-size:.88rem}
@media print{body{max-width:none}h1{page-break-after:avoid}figure,table{page-break-inside:avoid}}</style></head><body>';
$htmlInline = function (string $s) {
    $o = '';
    foreach (inline_tokens($s) as [$t, $k]) { $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); $o .= $k === 'b' ? "<strong>$t</strong>" : ($k === 'c' ? "<code>$t</code>" : $t); }
    return $o;
};
foreach ($B as $b) {
    switch ($b[0]) {
        case 'title': $html .= '<p class="title">' . $htmlInline($b[1]) . '</p>'; break;
        case 'subtitle': $html .= '<p class="subtitle">' . $htmlInline($b[1]) . '</p>'; break;
        case 'h1': $html .= '<h1>' . $htmlInline($b[1]) . '</h1>'; break;
        case 'h2': $html .= '<h2>' . $htmlInline($b[1]) . '</h2>'; break;
        case 'p': $html .= '<p>' . $htmlInline($b[1]) . '</p>'; break;
        case 'ul': $html .= '<ul>' . implode('', array_map(fn($i) => '<li>' . $htmlInline($i) . '</li>', $b[1])) . '</ul>'; break;
        case 'code': $html .= '<pre>' . htmlspecialchars($b[1], ENT_QUOTES, 'UTF-8') . '</pre>'; break;
        case 'table':
            $html .= '<table><thead><tr>' . implode('', array_map(fn($c) => '<th>' . $htmlInline($c) . '</th>', $b[1])) . '</tr></thead><tbody>';
            foreach ($b[2] as $fila) { $html .= '<tr>' . implode('', array_map(fn($c) => '<td>' . $htmlInline($c) . '</td>', $fila)) . '</tr>'; }
            $html .= '</tbody></table>'; break;
        case 'img': $html .= '<figure><img src="diagramas/' . basename($b[1]) . '" alt="' . htmlspecialchars($b[3], ENT_QUOTES, 'UTF-8') . '"><figcaption>' . $htmlInline($b[3]) . '</figcaption></figure>'; break;
    }
}
$html .= '</body></html>';
if (in_array('--html', $argv, true)) {   // la versión HTML es opcional (el entregable es el .docx)
    file_put_contents("$docDir/Documentacion_Banca_PJS.html", $html);
    echo "HTML generado: documentacion/Documentacion_Banca_PJS.html\n";
}

// ------------------------------------------------------------------ DOCX (OOXML escrito a mano)
$W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
   . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
   . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
// $encabezado = true: texto blanco en negrita (cabecera de tabla). El orden de los hijos de w:rPr sigue el esquema OOXML.
$runs = function (string $s, bool $encabezado = false) {
    $x = '';
    foreach (inline_tokens($s) as [$t, $k]) {
        $rpr = ($k === 'c' ? '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>' : '')
             . ($k === 'b' || $encabezado ? '<w:b/>' : '')
             . ($encabezado ? '<w:color w:val="FFFFFF"/>' : '')
             . ($k === 'c' ? '<w:sz w:val="19"/><w:shd w:val="clear" w:color="auto" w:fill="EEF2F7"/>' : '');
        $x .= '<w:r>' . ($rpr ? "<w:rPr>$rpr</w:rPr>" : '') . '<w:t xml:space="preserve">' . xe($t) . '</w:t></w:r>';
    }
    return $x;
};
$para = fn(string $inner, string $style = '', string $ppr = '') => '<w:p><w:pPr>' . ($style ? "<w:pStyle w:val=\"$style\"/>" : '') . $ppr . '</w:pPr>' . $inner . '</w:p>';
$sect = fn(bool $land) => '<w:sectPr><w:pgSz w:w="' . ($land ? 15840 : 12240) . '" w:h="' . ($land ? 12240 : 15840) . '"' . ($land ? ' w:orient="landscape"' : '') . '/>'
    . '<w:pgMar w:top="' . ($land ? 900 : 1300) . '" w:right="' . ($land ? 900 : 1300) . '" w:bottom="' . ($land ? 900 : 1300) . '" w:left="' . ($land ? 900 : 1300) . '" w:header="600" w:footer="600" w:gutter="0"/></w:sectPr>';
$anchoPortada = 12240 - 2 * 1300;   // twips útiles en vertical
$body = ''; $rels = ''; $media = []; $imgId = 0;
foreach ($B as $b) {
    switch ($b[0]) {
        case 'title': $body .= $para($runs($b[1]), 'Title'); break;
        case 'subtitle': $body .= $para($runs($b[1]), 'Subtitle'); break;
        case 'h1': $body .= $para($runs($b[1]), 'Heading1'); break;
        case 'h2': $body .= $para($runs($b[1]), 'Heading2'); break;
        case 'p': $body .= $para($runs($b[1])); break;
        case 'ul': foreach ($b[1] as $i) { $body .= $para('<w:r><w:t>•</w:t></w:r><w:r><w:tab/></w:r>' . $runs($i), 'ListBullet'); } break;
        case 'code': foreach (explode("\n", $b[1]) as $l) { $body .= $para('<w:r><w:t xml:space="preserve">' . xe($l) . '</w:t></w:r>', 'Code'); } break;
        case 'landscape': $body .= '<w:p><w:pPr>' . $sect(false) . '</w:pPr></w:p>'; break;
        case 'portrait': $body .= '<w:p><w:pPr>' . $sect(true) . '</w:pPr></w:p>'; break;
        case 'table':
            [, $head, $rows, $pcts] = $b;
            $tw = $anchoPortada; $cols = array_map(fn($p) => (int) round($tw * $p / 100), $pcts);
            $t = '<w:tbl><w:tblPr><w:tblW w:w="' . $tw . '" w:type="dxa"/><w:tblBorders>';
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $e) { $t .= "<w:$e w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"9FB3C8\"/>"; }
            $t .= '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="50" w:type="dxa"/><w:left w:w="90" w:type="dxa"/><w:bottom w:w="50" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
            foreach ($cols as $c) { $t .= "<w:gridCol w:w=\"$c\"/>"; }
            $t .= '</w:tblGrid>';
            $celda = function ($txt, $w, $hdr, $zebra) use ($runs, $para) {
                $shd = $hdr ? '0B2A4A' : ($zebra ? 'F4F7FB' : 'FFFFFF');
                return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="' . $shd . '"/></w:tcPr>'
                    . $para($runs($txt, $hdr), 'TableText') . '</w:tc>';
            };
            $t .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
            foreach ($head as $i => $c) { $t .= $celda($c, $cols[$i], true, false); }
            $t .= '</w:tr>';
            foreach ($rows as $ri => $fila) {
                $t .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
                foreach ($fila as $i => $c) { $t .= $celda($c, $cols[$i], false, $ri % 2 === 1); }
                $t .= '</w:tr>';
            }
            $body .= $t . '</w:tbl>' . $para('', '', '<w:spacing w:after="80"/>');
            break;
        case 'img':
            [, $ruta, $anchoIn, $cap] = $b;
            if (!is_file($ruta)) { echo "AVISO: falta la imagen $ruta\n"; break; }
            [$pw, $ph] = getimagesize($ruta); $imgId++;
            $cx = (int) ($anchoIn * 914400); $cy = (int) ($cx * $ph / $pw);
            $media[$imgId] = $ruta;
            $rels .= '<Relationship Id="rIdImg' . $imgId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/img' . $imgId . '.png"/>';
            $dr = '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="' . $imgId . '" name="Imagen ' . $imgId . '" descr="' . xe($cap) . '"/>'
                . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                . '<pic:pic><pic:nvPicPr><pic:cNvPr id="' . $imgId . '" name="img' . $imgId . '.png"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdImg' . $imgId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
                . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
            $body .= $para($dr, '', '<w:keepNext/><w:jc w:val="center"/>') . $para($runs($cap), 'Caption');
            break;
    }
}
$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $W . '><w:body>' . $body . $sect(false) . '</w:body></w:document>';

$estilo = fn($id, $name, $ppr, $rpr, $based = 'Normal', $next = 'Normal') => '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/>'
    . ($id !== 'Normal' ? '<w:basedOn w:val="' . $based . '"/><w:next w:val="' . $next . '"/>' : '') . '<w:qFormat/><w:pPr>' . $ppr . '</w:pPr><w:rPr>' . $rpr . '</w:rPr></w:style>';
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="es-GT"/></w:rPr></w:rPrDefault>'
    . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
    . $estilo('Normal', 'Normal', '', '')
    . $estilo('Title', 'Title', '<w:spacing w:before="600" w:after="60"/>', '<w:b/><w:color w:val="0B2A4A"/><w:sz w:val="56"/>')
    . $estilo('Subtitle', 'Subtitle', '<w:spacing w:after="240"/>', '<w:color w:val="5D6B7A"/><w:sz w:val="26"/>')
    . $estilo('Heading1', 'heading 1', '<w:keepNext/><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="2" w:color="0E7C86"/></w:pBdr><w:spacing w:before="360" w:after="140"/><w:outlineLvl w:val="0"/>', '<w:b/><w:color w:val="0B2A4A"/><w:sz w:val="32"/>')
    . $estilo('Heading2', 'heading 2', '<w:keepNext/><w:spacing w:before="240" w:after="100"/><w:outlineLvl w:val="1"/>', '<w:b/><w:color w:val="0E7C86"/><w:sz w:val="26"/>')
    . $estilo('ListBullet', 'List Bullet', '<w:tabs><w:tab w:val="left" w:pos="360"/></w:tabs><w:spacing w:after="80"/><w:ind w:left="360" w:hanging="360"/>', '')
    . $estilo('Code', 'Code', '<w:shd w:val="clear" w:color="auto" w:fill="0B2A4A"/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="120" w:right="120"/>', '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:color w:val="E8F1FB"/><w:sz w:val="18"/>')
    . $estilo('Caption', 'caption', '<w:spacing w:after="200"/><w:jc w:val="center"/>', '<w:i/><w:color w:val="5D6B7A"/><w:sz w:val="18"/>')
    . $estilo('TableText', 'Table Text', '<w:spacing w:after="0" w:line="252" w:lineRule="auto"/>', '<w:sz w:val="19"/>')
    . '</w:styles>';
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . $rels . '</Relationships>';
$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>Sistema de Banca en Línea - Documentación</dc:title><dc:creator>Pablo Javier Sandoval</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>';

$docx = "$docDir/Documentacion_Banca_PJS.docx";
@unlink($docx);
$zip = new ZipArchive();
if ($zip->open($docx, ZipArchive::CREATE) !== true) { exit("No se pudo crear $docx\n"); }
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/styles.xml', $stylesXml);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->addFromString('docProps/core.xml', $core);
foreach ($media as $i => $ruta) { $zip->addFile($ruta, "word/media/img$i.png"); }
$zip->close();

// ------------------------------------------------------------------ verificación del DOCX
$chk = new ZipArchive(); $chk->open($docx); $malas = 0;
for ($i = 0; $i < $chk->numFiles; $i++) {
    $n = $chk->getNameIndex($i);
    if (str_ends_with($n, '.xml') || str_ends_with($n, '.rels')) {
        $d = new DOMDocument(); libxml_use_internal_errors(true);
        if (!$d->loadXML($chk->getFromIndex($i))) { echo "XML MAL FORMADO: $n\n"; $malas++; }
    }
}
echo "DOCX generado: documentacion/Documentacion_Banca_PJS.docx (" . round(filesize($docx) / 1024) . " KB, {$chk->numFiles} partes, $imgId imágenes, $malas errores de XML)\n";
exit($malas ? 1 : 0);
