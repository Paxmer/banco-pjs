# Banco PJS · Retro pixel-art online banking

Academic **Web Development** project (Universidad Mariano Gálvez, Guatemala): an online banking system with three roles (customer, cashier and administrator) and a retro pixel-art gaming look.

![Banco PJS](banca/assets/img/banner.jpg)

**Live demo:** <http://bancopjs.atwebpages.com> (free hosting; open it with `http://`, not HTTPS). The interface is in Spanish.

## Features
- **Customer:** registration, third-party accounts, transactional transfers and account statement.
- **Cashier:** create accounts, deposits and withdrawals.
- **Administrator:** cashier management and a transfer monitor with charts.
- **Security:** bcrypt password hashing, sessions, CSRF protection, role-based access control, escaped HTML output, and database access only through *stored procedures*.
- **Quality:** 145 automated tests (`pruebas/probar_sistema.php`) and an Apache log analyzer (`herramientas/`).

## Tech stack
HTML5 · CSS3 · PHP 8 · MySQL 8 · Apache

## Project structure
```
banca/           web application (what gets uploaded to the host)
base_de_datos/   SQL scripts (00 local setup, 01 schema + procedures, 02 demo data)
herramientas/    Apache log analyzer and documentation generators
pruebas/         HTTP test suite
docs/            static showcase site for GitHub Pages
```

## Run it locally (WampServer)
1. Create the database and user: `00_crear_base_y_usuario.sql`.
2. Import `01_esquema_y_procedimientos.sql` into the `banca_umg` database (leaves a blank DB with the `admin` user).
3. Optional: `02_datos_demo_opcional.sql` for sample data.
4. Serve the `banca/` folder with Apache and open `http://localhost/banca/`.

Deployed on AwardSpace (free, no credit card). For any PHP + MySQL host, follow [documentacion/GUIA_PUBLICAR_HOSTING.md](documentacion/GUIA_PUBLICAR_HOSTING.md) (in Spanish).

> **Security note:** the credentials in the SQL scripts are demo values. Change the administrator password before using the system with real data.

## License
Academic project. All rights reserved by the author.
