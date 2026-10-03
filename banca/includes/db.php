<?php
/**
 * Acceso a MySQL. PHP NO ejecuta SQL directo: todo pasa por stored procedures
 * invocados con sentencias preparadas (CALL sp_xxx(?, ?, ...)).
 */

function db(): mysqli
{
    static $conexion = null;
    if ($conexion instanceof mysqli) {
        return $conexion;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
        $conexion->set_charset('utf8mb4');
        // El mismo huso horario que PHP: "hoy" significa lo mismo en ambos lados.
        $conexion->query("SET time_zone = '" . DB_TIMEZONE . "'");
    } catch (mysqli_sql_exception $ex) {
        log_error('No se pudo conectar a la BD: ' . $ex->getMessage());
        throw new RuntimeException('Sin conexión a la base de datos');
    }
    return $conexion;
}

/**
 * Ejecuta un stored procedure y devuelve las filas de su primer resultado.
 *
 * @param string $sp      nombre del procedimiento (sólo constantes del código)
 * @param string $tipos   tipos de bind_param: i, d, s (p. ej. "iss")
 * @param array  $params  valores en el mismo orden
 * @return array<int,array<string,mixed>>
 */
function sp_call(string $sp, string $tipos = '', array $params = []): array
{
    if (!preg_match('/^sp_[a-z_]+$/', $sp)) {
        throw new InvalidArgumentException('Nombre de procedimiento inválido');
    }
    $marcas = implode(',', array_fill(0, count($params), '?'));
    try {
        $stmt = db()->prepare("CALL {$sp}({$marcas})");
        if ($params) {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();
        $filas = [];
        $resultado = $stmt->get_result();
        if ($resultado instanceof mysqli_result) {
            $filas = $resultado->fetch_all(MYSQLI_ASSOC);
            $resultado->free();
        }
        // Un CALL deja resultados pendientes (estado OK): hay que consumirlos.
        while ($stmt->more_results()) {
            $stmt->next_result();
            if (($extra = $stmt->get_result()) instanceof mysqli_result) {
                $extra->free();
            }
        }
        $stmt->close();
        return $filas;
    } catch (mysqli_sql_exception $ex) {
        log_error("Error ejecutando {$sp}: " . $ex->getMessage());
        throw new RuntimeException('Error de base de datos');
    }
}

/**
 * Ejecuta un procedimiento de operación (devuelve una fila con codigo/mensaje).
 * Si el procedimiento reporta un error técnico (codigo 99) se registra el
 * detalle en el log y al usuario sólo se le muestra el mensaje genérico.
 *
 * @return array{codigo:int,mensaje:string}&array<string,mixed>
 */
function sp_operacion(string $sp, string $tipos = '', array $params = []): array
{
    $fila = sp_call($sp, $tipos, $params)[0] ?? null;
    if ($fila === null) {
        log_error("El procedimiento {$sp} no devolvió resultado");
        return ['codigo' => 99, 'mensaje' => 'Ocurrió un error inesperado. Intente de nuevo.'];
    }
    $fila['codigo'] = (int) $fila['codigo'];
    if ($fila['codigo'] === 99) {
        log_error("{$sp} falló: " . ($fila['detalle'] ?? 'sin detalle'));
        unset($fila['detalle']);
    }
    return $fila;
}
