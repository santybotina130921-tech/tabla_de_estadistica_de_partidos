<div id="santiago-chat-toggle" style="position:fixed; bottom:20px; right:20px; z-index:2000; background:var(--green,#00ff00); color:#000; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:26px; cursor:pointer; box-shadow:0 0 25px rgba(0,255,0,0.4);">💬</div>

<div id="santiago-chat-box" style="display:none; position:fixed; bottom:95px; right:20px; z-index:2000; width:320px; max-width:90vw; height:420px; background:#0b0e11; border:2px solid var(--green,#00ff00); border-radius:20px; flex-direction:column; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.6);">
    <div style="background:#161a1e; padding:14px; font-weight:900; color:var(--green,#00ff00); border-bottom:1px solid #222;">
        🤖 SANTIAGO IA
    </div>
    <div id="santiago-chat-mensajes" style="flex:1; overflow-y:auto; padding:12px; font-size:14px; color:#eee;"></div>
    <div style="display:flex; border-top:1px solid #222;">
        <input id="santiago-chat-input" type="text" placeholder="Pregúntame algo..."
               style="flex:1; background:#000; color:#fff; border:none; padding:12px; font-size:13px;">
        <button id="santiago-chat-enviar" style="background:var(--green,#00ff00); color:#000; border:none; padding:0 16px; font-weight:900; cursor:pointer;">➤</button>
    </div>
</div>

<script>
(function () {
    const toggle   = document.getElementById('santiago-chat-toggle');
    const box      = document.getElementById('santiago-chat-box');
    const mensajes = document.getElementById('santiago-chat-mensajes');
    const input    = document.getElementById('santiago-chat-input');
    const enviar   = document.getElementById('santiago-chat-enviar');

    toggle.addEventListener('click', () => {
        box.style.display = box.style.display === 'none' ? 'flex' : 'none';
    });

    function agregarMensaje(texto, esUsuario) {
        const linea = document.createElement('div');
        linea.style.margin = '8px 0';
        linea.style.textAlign = esUsuario ? 'right' : 'left';
        linea.innerHTML = `<span style="display:inline-block; padding:8px 12px; border-radius:12px; background:${esUsuario ? '#00ff0022' : '#1c2127'}; max-width:85%;">${texto}</span>`;
        mensajes.appendChild(linea);
        mensajes.scrollTop = mensajes.scrollHeight;
    }

    async function enviarMensaje() {
        const texto = input.value.trim();
        if (!texto) return;
        agregarMensaje(texto, true);
        input.value = '';

        // Toma los equipos seleccionados en el predictor, si existen, para dar contexto real
        const selectLocal = document.querySelector('select[name="local"]');
        const selectVisitante = document.querySelector('select[name="visitante"]');

        try {
            const res = await fetch('core/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mensaje: texto,
                    local: selectLocal ? selectLocal.value : '',
                    visitante: selectVisitante ? selectVisitante.value : ''
                })
            });
            const data = await res.json();
            agregarMensaje(data.respuesta || data.error || 'Sin respuesta.', false);
        } catch (e) {
            agregarMensaje('Error de conexión con la IA.', false);
        }
    }

    enviar.addEventListener('click', enviarMensaje);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') enviarMensaje(); });
})();
</script>
