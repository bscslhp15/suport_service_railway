<?php
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBase = preg_replace('#/(?:dashboard|auth)$#', '', $scriptDirectory) ?: '';
$appBase = rtrim($appBase, '/');
$logoUrl = $appBase . '/IMG%20ASSETS/passlogo.png';
$chatCssUrl = $appBase . '/assets/css/chat.css';
$chatJsUrl = $appBase . '/assets/js/chat.js?v=20260906-2';
$chatApiUrl = $appBase . '/AI%20CHAT%20BOT/AI%20CHAT%20BOT/api.php';
?>
<!-- PASS College AI Chat Widget -->
<link rel="stylesheet" href="<?= htmlspecialchars($chatCssUrl, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div id="chat-widget">
    <div id="chat-bubble" class="chat-bubble">
        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="PASS Logo" class="chat-logo">
        <div class="chat-tooltip">Need help? Chat with our AI assistant!</div>
    </div>

    <div id="chat-modal" class="chat-modal">
        <div class="chat-header">
            <div class="chat-header-info">
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="PASS Logo" class="chat-header-logo">
                <div>
                    <div class="chat-header-title">PASS College AI Assistant</div>
                    <div class="chat-header-subtitle">Your support services guide</div>
                </div>
            </div>
            <div class="chat-header-actions">
                <button id="chat-clear-history" class="chat-history-btn" title="Clear chat history">
                    <i class="fas fa-trash-alt"></i>
                </button>
                <button id="chat-close" class="chat-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div id="chat-messages" class="chat-messages">
            <div class="message assistant-message">
                <div class="message-avatar">
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="PASS Logo">
                </div>
                <div class="message-content">
                    <div class="message-text">
                        Hello! I'm your PASS College AI assistant. I can help you with information about our support services including Library, Clinic, Guidance, Scholarship, SSC (Student Council), and Alumni services. Each service operates as a separate module in our system. What would you like to know about?
                    </div>
                    <div class="message-time">Just now</div>
                </div>
            </div>
        </div>

        <div class="chat-input-container">
            <div class="chat-input-wrapper">
                <input type="text" id="chat-input" placeholder="Type your message..." maxlength="1000">
                <button id="chat-send" class="chat-send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="chat-input-footer">
                <small>Make your question more specific for a better answer.<br>If something goes wrong, please don't blame the AI chatbot; blame the developer.</small>
            </div>
        </div>
    </div>
</div>

<script>
    window.passCollegeChatbotBase = <?= json_encode($appBase, JSON_UNESCAPED_SLASHES) ?>;
    window.passCollegeChatbotApiUrl = <?= json_encode($chatApiUrl, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($chatJsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
