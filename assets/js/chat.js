(function () {
    const toggle = document.getElementById('santiago-chat-toggle');
    const caja = document.getElementById('santiago-chat-box');
    const mensajes = document.getElementById('santiago-chat-mensajes');
    const entrada = document.getElementById('santiago-chat-input');
    const enviar = document.getElementById('santiago-chat-enviar');
    let enviando = false;

    if (!toggle || !caja) {
        return;
    }

    function agregar(texto, esUsuario) {
        const linea = document.createElement('div');
        linea.className = 'chat-linea ' + (esUsuario ? 'usuario' : 'bot');
        const burbuja = document.createElement('span');
        burbuja.className = 'chat-burbuja';
        burbuja.textContent = texto;
        linea.appendChild(burbuja);
        mensajes.appendChild(linea);
        mensajes.scrollTop = mensajes.scrollHeight;
        return burbuja;
    }

    toggle.addEventListener('click', function () {
        const abrir = caja.hidden;
        caja.hidden = !abrir;
        toggle.setAttribute('aria-expanded', String(abrir));
        if (abrir) {
            entrada.focus();
        }
    });

    async function enviarMensaje() {
        const texto = entrada.value.trim();
        if (!texto || enviando) {
            return;
        }
        enviando = true;
        agregar(texto, true);
        entrada.value = '';
        const respuesta = agregar('Escribiendo...', false);

        const selectLocal = document.querySelector('select[name="local"]');
        const selectVisitante = document.querySelector('select[name="visitante"]');
        const liga = new URLSearchParams(window.location.search).get('liga') || '';

        try {
            const res = await fetch('core/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mensaje: texto,
                    local: selectLocal ? selectLocal.value : '',
                    visitante: selectVisitante ? selectVisitante.value : '',
                    liga: liga
                })
            });
            const datos = await res.json();
            respuesta.textContent = datos.respuesta || datos.error || 'Sin respuesta.';
        } catch (e) {
            respuesta.textContent = 'Error de conexión con la IA.';
        } finally {
            enviando = false;
            mensajes.scrollTop = mensajes.scrollHeight;
        }
    }

    enviar.addEventListener('click', enviarMensaje);
    entrada.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            enviarMensaje();
        }
    });
})();