# Guía rápida: publicar Banco PJS en un hosting gratuito (PHP + MySQL)

Hosting usado en este proyecto: **AwardSpace** (plan gratuito, sin tarjeta, PHP 8.4 y MySQL 8 con stored procedures).
Sitio publicado: **http://bancopjs.atwebpages.com** (el plan gratuito no incluye HTTPS; abra la dirección con `http://`).
GitHub Pages **no sirve** para la aplicación: sólo aloja páginas estáticas y este proyecto necesita PHP y MySQL.
Evite hostings que usen MyISAM o no permitan procedimientos almacenados (por ejemplo InfinityFree): el proyecto exige `CALL sp_*` e InnoDB.

## 1. Crear la cuenta y el subdominio
1. Regístrese en https://www.awardspace.com (plan *Free*, no pide tarjeta).
2. En el panel, **Domain Manager → Subdomains** y cree un subdominio gratuito de `atwebpages.com` (por ejemplo `bancopjs`).

## 2. Crear la base de datos
1. **Hosting Tools → MySQL Databases** → *Create Database*; escriba una contraseña (¡escríbala usted mismo!).
2. Anote: **host** (como `fdb1029.awardspace.net`), **nombre** (con prefijo, como `4794308_banca`), **usuario** (igual al nombre) y **contraseña**.

## 3. Importar el script
1. Abra **phpMyAdmin** y seleccione su base.
2. Pestaña **Importar** → `base_de_datos/01_esquema_y_procedimientos.sql` → **Continuar**. Si se reimporta y marca error por claves foráneas, desactive la casilla «Enable foreign key checks».
3. Deben aparecer 5 tablas y 16 procedimientos `sp_*`. El script deja la BD en blanco con el usuario `admin`.
4. **No** importe `00_...` (sólo local) ni `02_...` (datos de prueba).

## 4. Subir la aplicación
El plan gratuito **no permite subir .zip** ni carpetas completas: suba los archivos carpeta por carpeta.
1. **Hosting Tools → File Manager**, entre a `www/<su subdominio>`.
2. Cree las carpetas `admin`, `cajero`, `cliente`, `config`, `includes`, `logs`, `assets` (dentro: `css`, `js`, `img` y `img/iconos`).
3. Con **Upload**, suba los archivos de cada carpeta de `banca/` a su carpeta correspondiente (`index.php` queda en la raíz del subdominio). No suba los `.htaccess`.
4. En `config/` suba también `config.local.php` (copia de `config.ejemplo.php`) con los datos del paso 2:
   ```php
   define('DB_HOST', 'fdb1029.awardspace.net');
   define('DB_PORT', 3306);
   define('DB_NAME', '4794308_banca');
   define('DB_USER', '4794308_banca');
   define('DB_PASS', 'su_contraseña');
   ```
   `config.local.php` contiene su contraseña: **nunca** lo suba a GitHub.

## 5. Probar
1. Abra `http://su-subdominio.atwebpages.com` en la computadora **y en el celular**.
2. Inicie sesión como administrador: usuario `admin`, contraseña `Admin2026!`.
3. Cree un cajero, entre como cajero, cree una cuenta, haga un depósito, registre un cliente y pruebe una transferencia.

## 6. Si algo falla
- **«Illegal mix of collations»** al iniciar sesión: use la versión actual de `01_esquema_y_procedimientos.sql` (las tablas heredan la collation de la BD) y reimpórtela.
- **No conecta a la base**: revise host, nombre (con prefijo), usuario y contraseña en `config.local.php`.
- **El navegador avisa «conexión no privada»**: el plan gratuito no tiene HTTPS; escriba `http://` al inicio.
- Las fuentes vienen de Google Fonts: sin internet el sitio funciona igual con fuentes de respaldo.

## 7. Terminar la entrega
1. La URL ya está en el Word (tabla «Datos de entrega»). Ábralo una vez para confirmar que se ve bien.
2. Cambie la contraseña del administrador (`Admin2026!`) y la de la base de datos después de la calificación.
3. Suba a UMG-Virtual: código fuente + `01_esquema_y_procedimientos.sql` + el Word con integrante, enlace y credenciales del administrador. **Fecha límite: 01/11/2026.**
