# Banco PJS · Banca en línea retro pixel-art

Proyecto académico de **Desarrollo Web** (Universidad Mariano Gálvez de Guatemala): sistema de banca en línea con tres roles (cliente, cajero y administrador) y diseño retro pixel-art gamer.

![Banco PJS](banca/assets/img/banner.jpg)

## Qué incluye
- **Cliente:** registro, cuentas de terceros, transferencias (transaccionales) y estado de cuenta.
- **Cajero:** crear cuentas, depósitos y retiros.
- **Administrador:** gestión de cajeros y monitor de transferencias con gráfica.
- **Seguridad:** bcrypt, sesiones, CSRF, control de roles, salida HTML escapada y acceso a la base de datos solo mediante *stored procedures*.
- **Calidad:** 145 pruebas automáticas (`pruebas/probar_sistema.php`) y análisis de logs de Apache (`herramientas/`).

## Tecnologías
HTML5 · CSS3 · PHP 8 · MySQL 8 · Apache

## Estructura
```
banca/           aplicación web (lo que se sube al hosting)
base_de_datos/   scripts SQL (00 local, 01 esquema + procedimientos, 02 datos demo)
herramientas/    analizador de logs de Apache y generadores de documentación
pruebas/         suite de pruebas HTTP
docs/            vitrina estática para GitHub Pages
```

## Ejecutarlo en local (WampServer)
1. Cree la base y el usuario: `00_crear_base_y_usuario.sql`.
2. Importe `01_esquema_y_procedimientos.sql` en la base `banca_umg` (deja la BD en blanco con el usuario `admin`).
3. Opcional: `02_datos_demo_opcional.sql` para datos de prueba.
4. Sirva la carpeta `banca/` con Apache y abra `http://localhost/banca/`.

Para un hosting con PHP + MySQL, siga [documentacion/GUIA_PUBLICAR_HOSTING.md](documentacion/GUIA_PUBLICAR_HOSTING.md).

> **Nota de seguridad:** las credenciales de los scripts SQL son de demostración. Cambie la contraseña del administrador antes de usar el sistema con datos reales.

## Licencia
Proyecto académico. Todos los derechos reservados por el autor.
