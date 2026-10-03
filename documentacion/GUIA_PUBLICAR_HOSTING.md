# Guía rápida: publicar Banco PJS en un hosting gratuito (PHP + MySQL)

Tiempo estimado: 20 a 30 minutos. Ejemplo con InfinityFree (sirve cualquier hosting con PHP 8.x y MySQL/MariaDB).
GitHub Pages **no sirve**: sólo aloja páginas estáticas y este proyecto necesita PHP y MySQL.

## 1. Crear la cuenta y el sitio
1. Regístrese en https://www.infinityfree.com y cree una cuenta de hosting (elija el subdominio gratuito, por ejemplo `bancopjs.infinityfreeapp.com`).
2. Entre al panel de control (Client Area → Control Panel).

## 2. Crear la base de datos
1. En el panel abra **MySQL Databases**, escriba un nombre (por ejemplo `banca`) y pulse **Create Database**.
2. Anote los cuatro datos que muestra el panel: **host** (algo como `sql123.infinityfree.com`), **nombre de la base** (con prefijo, como `if0_12345678_banca`), **usuario** (`if0_12345678`) y **contraseña** (la de su cuenta de hosting).

## 3. Importar el script
1. Pulse **phpMyAdmin** junto a su base y seleccione la base en el panel izquierdo.
2. Pestaña **Importar** → elija `base_de_datos/01_esquema_y_procedimientos.sql` → **Continuar**.
3. Debe aparecer éxito y, en la base, 5 tablas y los procedimientos `sp_*`. (Este script deja la BD en blanco con el usuario `admin`.)
4. **No** importe `00_...` (es sólo para uso local) ni `02_...` (datos de prueba, no se entrega).

## 4. Subir la aplicación
1. Descomprima `banca_para_hosting.zip`.
2. En el panel abra **Online File Manager** (o use FileZilla) y entre a la carpeta `htdocs`.
3. Suba **el contenido** de la carpeta `banca` dentro de `htdocs` (que `index.php` quede directamente en `htdocs`).
4. Dentro de `htdocs/config`, copie `config.ejemplo.php` como `config.local.php` y escriba los cuatro datos del paso 2:
   ```php
   define('DB_HOST', 'sql123.infinityfree.com');
   define('DB_PORT', 3306);
   define('DB_NAME', 'if0_12345678_banca');
   define('DB_USER', 'if0_12345678');
   define('DB_PASS', 'su_contraseña');
   ```

## 5. Probar
1. Abra su dirección (por ejemplo `https://bancopjs.infinityfreeapp.com`) en la computadora **y en el celular**.
2. Inicie sesión como administrador: usuario `admin`, contraseña `Admin2026!`.
3. Cree un cajero, entre como cajero, cree una cuenta, haga un depósito, registre un cliente y pruebe una transferencia.

## 6. Si algo falla
- **Error 500**: borre los archivos `.htaccess` de `config/`, `includes/` y `logs/` (sólo contienen `Require all denied`; los logs siguen protegidos por su primera línea PHP).
- **No conecta a la base**: revise host, nombre (con prefijo), usuario y contraseña en `config.local.php`.
- **El primer acceso tarda**: los hostings gratuitos duermen el sitio; recargue una vez.
- Las fuentes vienen de Google Fonts: sin internet el sitio funciona igual con fuentes de respaldo.

## 7. Terminar la entrega
1. Escriba la URL en el Word (tabla «Datos de entrega», línea marcada `[PENDIENTE]`), o en `herramientas/generar_documentacion.php` y regenere.
2. Abra el Word una vez para confirmar que se ve bien.
3. Suba a UMG-Virtual: código fuente + `01_esquema_y_procedimientos.sql` + el Word con integrante, enlace y credenciales del administrador. **Fecha límite: 01/11/2026.**
