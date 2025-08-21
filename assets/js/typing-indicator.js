// typing-indicator.js
let typingTimeout;

function sendTyping() {
    if (messagingSystem.websocket) {
        messagingSystem.websocket.send(JSON.stringify({
            type: 'typing',
            conversation_id: messagingSystem.currentConversationId
        }));
    }
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(sendTypingStop, 3000);
}

function sendTypingStop() {
    if (messagingSystem.websocket) {
        messagingSystem.websocket.send(JSON.stringify({
            type: 'typing_stop',
            conversation_id: messagingSystem.currentConversationId
        }));
    }
}

// Ajoute à message-input event: input.addEventListener('input', sendTyping);