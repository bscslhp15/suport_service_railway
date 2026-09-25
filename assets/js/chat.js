// Chat Widget JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const chatBubble = document.getElementById('chat-bubble');
    const chatModal = document.getElementById('chat-modal');
    const chatClose = document.getElementById('chat-close');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const chatMessages = document.getElementById('chat-messages');

    if (!chatBubble || !chatModal || !chatClose || !chatInput || !chatSend || !chatMessages) {
        console.error('PASS College AI chatbot markup is incomplete.');
        return;
    }

    let isTyping = false;
    const CHAT_HISTORY_KEY = 'pass_college_chat_history_v2';
    const chatAssetBase = window.passCollegeChatbotBase || '';
    const chatLogoUrl = `${chatAssetBase}/IMG%20ASSETS/passlogo.png`;

    // Load chat history from localStorage
    function loadChatHistory() {
        try {
            const savedHistory = localStorage.getItem(CHAT_HISTORY_KEY);
            if (savedHistory) {
                const messages = JSON.parse(savedHistory);
                // Clear the default welcome message
                chatMessages.innerHTML = '';
                // Reload all saved messages
                messages.forEach(msg => {
                    displaySavedMessage(msg.text, msg.type, msg.time);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }

    // Save chat history to localStorage
    function saveChatHistory() {
        try {
            const messages = [];
            const messageElements = chatMessages.querySelectorAll('.message:not(.typing-indicator)');
            messageElements.forEach(msg => {
                const isAssistant = msg.classList.contains('assistant-message');
                const textContent = msg.querySelector('.message-text');
                const timeContent = msg.querySelector('.message-time');
                if (textContent && timeContent) {
                    messages.push({
                        type: isAssistant ? 'assistant' : 'user',
                        text: textContent.innerHTML,
                        time: timeContent.textContent
                    });
                }
            });
            localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(messages));
        } catch (error) {
            console.error('Error saving chat history:', error);
        }
    }

    // Display a saved message (from localStorage)
    function displaySavedMessage(text, type, time) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'message-avatar';

        const avatarImg = document.createElement('img');
        avatarImg.src = chatLogoUrl;
        avatarImg.alt = type === 'user' ? 'You' : 'PASS Assistant';
        avatarDiv.appendChild(avatarImg);

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        if (type === 'assistant') {
            textDiv.innerHTML = text;
        } else {
            textDiv.textContent = text;
        }

        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = time;

        contentDiv.appendChild(textDiv);
        contentDiv.appendChild(timeDiv);

        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);

        chatMessages.appendChild(messageDiv);
    }

    // Clear chat history
    const clearHistoryBtn = document.getElementById('chat-clear-history');
    if (clearHistoryBtn) {
        clearHistoryBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all chat history? This cannot be undone.')) {
                try {
                    localStorage.removeItem(CHAT_HISTORY_KEY);
                    chatMessages.innerHTML = '';
                    // Add the welcome message back
                    const welcomeDiv = document.createElement('div');
                    welcomeDiv.className = 'message assistant-message';
                    welcomeDiv.innerHTML = `
                        <div class="message-avatar">
                            <img src="${chatLogoUrl}" alt="PASS Logo">
                        </div>
                        <div class="message-content">
                            <div class="message-text">
                                Hello! I'm your PASS College AI assistant. I can help you with information about our support services including Library, Clinic, Guidance, Scholarship, SSC (Student Council), and Alumni services. Each service operates as a separate module in our system. What would you like to know about?
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    `;
                    chatMessages.appendChild(welcomeDiv);
                    alert('Chat history cleared successfully!');
                } catch (error) {
                    console.error('Error clearing chat history:', error);
                    alert('Error clearing chat history. Please try again.');
                }
            }
        });
    }

    // Toggle chat modal
    chatBubble.addEventListener('click', function() {
        chatModal.classList.toggle('show');
        if (chatModal.classList.contains('show')) {
            chatInput.focus();
        }
    });

    // Close chat modal
    chatClose.addEventListener('click', function() {
        chatModal.classList.remove('show');
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (!chatModal.contains(e.target) && !chatBubble.contains(e.target)) {
            chatModal.classList.remove('show');
        }
    });

    // Send message on button click
    chatSend.addEventListener('click', sendMessage);

    // Send message on Enter key
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize input (optional enhancement)
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message || isTyping) return;

        // Add user message
        addMessage(message, 'user');
        chatInput.value = '';
        chatInput.style.height = 'auto';

        // Show typing indicator
        showTypingIndicator();

        // Send to API
        sendToAPI(message);
    }

    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'message-avatar';

        const avatarImg = document.createElement('img');
        avatarImg.src = '../IMG ASSETS/passlogo.png';
        avatarImg.alt = type === 'user' ? 'You' : 'PASS Assistant';
        avatarDiv.appendChild(avatarImg);

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        if (type === 'assistant') {
            textDiv.innerHTML = text;
        } else {
            textDiv.textContent = text;
        }

        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        contentDiv.appendChild(textDiv);
        contentDiv.appendChild(timeDiv);

        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);

        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Save to localStorage after adding message
        saveChatHistory();
    }

    function showTypingIndicator() {
        isTyping = true;
        chatSend.disabled = true;

        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'typing-indicator';

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'message-avatar';

        const avatarImg = document.createElement('img');
        avatarImg.src = '../IMG ASSETS/passlogo.png';
        avatarImg.alt = 'PASS Assistant';
        avatarDiv.appendChild(avatarImg);

        const dotsDiv = document.createElement('div');
        dotsDiv.className = 'typing-dots';

        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('div');
            dot.className = 'typing-dot';
            dotsDiv.appendChild(dot);
        }

        typingDiv.appendChild(avatarDiv);
        typingDiv.appendChild(dotsDiv);

        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
        isTyping = false;
        chatSend.disabled = false;
    }

    function sendToAPI(message) {
        const formData = new FormData();
        formData.append('message', message);

        const apiUrl = window.passCollegeChatbotApiUrl || `${chatAssetBase}/AI%20CHAT%20BOT/AI%20CHAT%20BOT/api.php`;
        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(`Chatbot returned an invalid response (${response.status}).`);
            }
            if (!response.ok && !data.error) {
                throw new Error(`Chatbot request failed (${response.status}).`);
            }
            return data;
        })
        .then(data => {
            hideTypingIndicator();

            if (data.success) {
                addMessage(data.message, 'assistant');
            } else {
                // Show specific error message
                let errorMessage = 'Sorry, I encountered an error. Please try again later.';
                if (data.error) {
                    if (data.error.includes('Authentication required')) {
                        errorMessage = 'Please log in to use the AI assistant.';
                    } else if (data.error.includes('API returned error code: 400')) {
                        errorMessage = 'Sorry, there seems to be an issue with my configuration. Please contact support.';
                    } else if (data.error.includes('API returned error code: 403')) {
                        errorMessage = 'Sorry, I\'m temporarily unavailable. Please try again later.';
                    } else if (data.error.includes('API request failed')) {
                        errorMessage = 'Sorry, I\'m having trouble connecting to my services. Please check your internet connection.';
                    }
                }
                addMessage(errorMessage, 'assistant');
                console.error('API Error:', data.error);
            }
        })
        .catch(error => {
            hideTypingIndicator();
            addMessage('Sorry, I\'m having trouble connecting. Please check your internet connection and try again.', 'assistant');
            console.error('Fetch Error:', error);
        });
    }

    // Initialize chat state - Load history on page load
    loadChatHistory();
    chatInput.focus();
});