/* Validación del lado del cliente (comodidad para el usuario).
   Toda validación crítica se repite en PHP y en los stored procedures. */
(function () {
    'use strict';

    // Campos que sólo aceptan dígitos (cuenta, DPI)
    document.querySelectorAll('[data-solo-digitos]').forEach(function (el) {
        el.addEventListener('input', function () {
            el.value = el.value.replace(/\D+/g, '');
        });
    });

    // Montos: sólo dígitos y un punto decimal, máximo 2 decimales
    document.querySelectorAll('[data-monto]').forEach(function (el) {
        el.addEventListener('input', function () {
            var v = el.value.replace(',', '.').replace(/[^0-9.]/g, '');
            var partes = v.split('.');
            if (partes.length > 2) { v = partes[0] + '.' + partes.slice(1).join(''); partes = v.split('.'); }
            if (partes[1] !== undefined) { v = partes[0] + '.' + partes[1].slice(0, 2); }
            el.value = v;
        });
    });

    // Contraseña con letra y número; confirmación igual a la contraseña
    var clave = document.querySelector('[data-clave]');
    if (clave) {
        clave.addEventListener('input', function () {
            var ok = /[A-Za-z]/.test(clave.value) && /\d/.test(clave.value);
            clave.setCustomValidity(ok || clave.value === '' ? '' : 'Debe incluir al menos una letra y un número.');
        });
    }
    document.querySelectorAll('[data-confirmar]').forEach(function (conf) {
        var origen = document.getElementById(conf.getAttribute('data-confirmar'));
        function revisar() {
            conf.setCustomValidity(origen && conf.value !== origen.value ? 'Las contraseñas no coinciden.' : '');
        }
        conf.addEventListener('input', revisar);
        if (origen) { origen.addEventListener('input', revisar); }
    });

    // Contraseñas: botón "Ver" para mostrar u ocultar lo escrito (evita errores al teclear en el celular)
    document.querySelectorAll('input[type=password]').forEach(function (inp) {
        var caja = document.createElement('div');
        caja.className = 'input-clave';
        inp.parentNode.insertBefore(caja, inp);
        caja.appendChild(inp);
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'ver-clave'; b.textContent = 'VER';
        b.setAttribute('aria-label', 'Mostrar u ocultar la contraseña');
        b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', function () {
            var visible = inp.type === 'text';
            inp.type = visible ? 'password' : 'text';
            b.textContent = visible ? 'VER' : 'OCULTAR';
            b.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });
        caja.appendChild(b);
    });

    // Confirmación antes de mover dinero: <form data-resumen="Título">
    document.querySelectorAll('form[data-resumen]').forEach(function (form) {
        if (typeof HTMLDialogElement === 'undefined') { return; } // navegador antiguo: se envía directo
        form.addEventListener('submit', function (ev) {
            if (form.dataset.ok === '1') { return; }
            if (!form.checkValidity()) { return; }
            ev.preventDefault();
            ev.stopImmediatePropagation();
            var dlg = document.createElement('dialog');
            dlg.className = 'confirmar';
            dlg.setAttribute('aria-labelledby', 'dlg-titulo');
            var caja = document.createElement('div'); caja.className = 'confirmar-caja';
            var h = document.createElement('h2'); h.id = 'dlg-titulo'; h.textContent = form.getAttribute('data-resumen');
            var p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Revise los datos antes de continuar. Esta operación no se puede deshacer.';
            var ul = document.createElement('ul'); ul.className = 'confirmar-lista';
            form.querySelectorAll('.field').forEach(function (campo) {
                var lab = campo.querySelector('label'), ctl = campo.querySelector('input, select');
                if (!lab || !ctl) { return; }
                var valor = ctl.tagName === 'SELECT' ? ctl.options[ctl.selectedIndex].text : ctl.value;
                if (ctl.hasAttribute('data-monto')) { valor = 'Q ' + valor; }
                var li = document.createElement('li');
                var a = document.createElement('span'); a.textContent = lab.textContent;
                var c = document.createElement('strong'); c.textContent = valor;
                li.appendChild(a); li.appendChild(c); ul.appendChild(li);
            });
            var acc = document.createElement('div'); acc.className = 'confirmar-acciones';
            var no = document.createElement('button'); no.type = 'button'; no.className = 'btn btn-secondary'; no.textContent = 'Cancelar';
            var si = document.createElement('button'); si.type = 'button'; si.className = 'btn btn-primary'; si.textContent = 'Confirmar';
            acc.appendChild(no); acc.appendChild(si);
            caja.appendChild(h); caja.appendChild(p); caja.appendChild(ul); caja.appendChild(acc);
            dlg.appendChild(caja); document.body.appendChild(dlg);
            function cerrar() { dlg.close(); dlg.remove(); }
            no.addEventListener('click', cerrar);
            dlg.addEventListener('cancel', function () { dlg.remove(); });
            si.addEventListener('click', function () {
                form.dataset.ok = '1'; dlg.close(); dlg.remove();
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            });
            dlg.showModal();
            no.focus();
        }, true);
    });

    // Efectos retro (líneas CRT y parpadeo): se pueden apagar y se recuerda la elección
    var btnEf = document.getElementById('btn-efectos');
    function aplicarEfectos(apagados) {
        document.body.classList.toggle('sin-efectos', apagados);
        if (btnEf) { btnEf.textContent = apagados ? 'Efectos retro: NO' : 'Efectos retro: SÍ'; btnEf.setAttribute('aria-pressed', apagados ? 'false' : 'true'); }
    }
    var guardado = false;
    try { guardado = window.localStorage.getItem('pjs_sin_efectos') === '1'; } catch (e) { guardado = false; }
    aplicarEfectos(guardado);
    if (btnEf) {
        btnEf.addEventListener('click', function () {
            var ahora = !document.body.classList.contains('sin-efectos');
            aplicarEfectos(ahora);
            try { window.localStorage.setItem('pjs_sin_efectos', ahora ? '1' : '0'); } catch (e) { /* sin almacenamiento: sólo vale en esta página */ }
        });
    }

    // Estado de carga: evita doble envío de operaciones bancarias
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) { return; }
            var boton = form.querySelector('button[type=submit]');
            if (boton) {
                boton.setAttribute('data-texto', boton.textContent);
                boton.textContent = 'Procesando…';
                // Se deshabilita en el siguiente ciclo para que el navegador alcance a enviar el formulario
                setTimeout(function () { boton.disabled = true; boton.classList.add('cargando'); }, 0);
            }
        });
    });
    // Si el usuario vuelve con "atrás", se restaura el botón
    window.addEventListener('pageshow', function (ev) {
        if (!ev.persisted) { return; }
        document.querySelectorAll('form[data-resumen]').forEach(function (f) { delete f.dataset.ok; });
        document.querySelectorAll('form[data-loading] button[type=submit]').forEach(function (b) {
            b.disabled = false; b.classList.remove('cargando');
            if (b.getAttribute('data-texto')) { b.textContent = b.getAttribute('data-texto'); }
        });
    });
})();
