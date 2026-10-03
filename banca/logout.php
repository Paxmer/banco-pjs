<?php
require __DIR__ . '/includes/bootstrap.php';

// Sólo se cierra la sesión con POST + token CSRF (un enlace externo no puede forzar la salida).
if (!es_post()) {
    redirect('index.php');
}
csrf_verificar();
cerrar_sesion();
sesion_iniciar();
flash_set('success', 'Sesión cerrada correctamente.');
redirect('index.php');
