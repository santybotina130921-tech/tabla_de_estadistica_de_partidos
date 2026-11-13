<div class="chat-widget">
    <button type="button" id="santiago-chat-toggle" class="chat-toggle" aria-label="Abrir el chat de IA" aria-expanded="false">💬</button>

    <div id="santiago-chat-box" class="chat-caja" hidden>
        <div class="chat-cabecera">🤖 SANTIAGO IA</div>
        <div id="santiago-chat-mensajes" class="chat-mensajes" aria-live="polite"></div>
        <div class="chat-entrada">
            <input id="santiago-chat-input" class="chat-input" type="text" placeholder="Pregúntame algo..." maxlength="500" aria-label="Escribe tu pregunta">
            <button type="button" id="santiago-chat-enviar" class="chat-enviar" aria-label="Enviar">➤</button>
        </div>
    </div>
</div>
<script src="assets/js/chat.js?v=<?= filemtime(__DIR__ . '/../assets/js/chat.js') ?>" defer></script>