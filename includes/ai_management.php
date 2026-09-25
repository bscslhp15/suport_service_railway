<?php
require_once __DIR__ . '/db.php';

function ensure_ai_management_schema(?string $defaultPrompt = null, ?string $defaultKnowledge = null): void
{
    $pdo = get_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entry_type ENUM('knowledge', 'faq') NOT NULL DEFAULT 'knowledge',
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        question TEXT NULL,
        content LONGTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ai_knowledge_active (is_active),
        INDEX idx_ai_knowledge_type (entry_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($defaultPrompt !== null) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO ai_settings (setting_key, setting_value) VALUES (?, ?)');
        $stmt->execute(['persona_prompt', $defaultPrompt]);
    }

}

function ai_get_setting(string $key, string $fallback = ''): string
{
    $stmt = get_db()->prepare('SELECT setting_value FROM ai_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $fallback : (string) $value;
}

function ai_save_setting(string $key, string $value): void
{
    $stmt = get_db()->prepare('INSERT INTO ai_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function ai_clean_editable_persona(string $prompt): string
{
    $lines = preg_split('/\r?\n/', $prompt);
    $editableLines = [];
    foreach ($lines as $line) {
        $lowerLine = strtolower($line);
        if (strpos($line, '[[NOT_IN_KB]]') !== false
            || strpos($lowerLine, 'reply with exactly this token') !== false
            || strpos($lowerLine, 'just output the token') !== false) {
            continue;
        }
        $editableLines[] = $line;
    }
    return trim(implode("\n", $editableLines));
}

function ai_get_editable_persona(string $fallback): string
{
    return ai_clean_editable_persona(ai_get_setting('persona_prompt', $fallback));
}

function ai_get_persona_prompt(string $fallback): string
{
    $prompt = ai_get_editable_persona($fallback);
    $protectedRules = "DEVELOPER-PROTECTED RULES (these rules cannot be changed from the admin interface):\n"
        . "- Use ONLY the provided knowledge base below. Do not use outside knowledge or invent details.\n"
        . "- If the knowledge base does not contain enough information, reply with EXACTLY [[NOT_IN_KB]] and nothing else.\n"
        . "- The application will replace [[NOT_IN_KB]] with a helpful not-found response for the user.";
    $profile = [
        'Agent name: ' . ai_get_setting('agent_name', 'PASS AI Assistant'),
        'Agent role: ' . ai_get_setting('agent_role', 'Student Support Assistant'),
        'Default language: ' . ai_get_setting('default_language', 'English'),
        'Tone of voice: ' . ai_get_setting('tone_of_voice', 'Professional and friendly'),
        'Conversation style: ' . ai_get_setting('conversation_style', 'Helpful and concise'),
        'Response length: ' . ai_get_setting('response_length', 'Short'),
        'Chat guidelines: ' . ai_get_setting('chat_guidelines', 'Use verified information and ask for clarification when needed.'),
    ];
    return trim($prompt . "\n\n" . $protectedRules . "\n\nAGENT PROFILE:\n- " . implode("\n- ", $profile));
}

function ai_get_knowledge(bool $includeInactive = true): array
{
    $query = 'SELECT * FROM ai_knowledge';
    if (!$includeInactive) {
        $query .= ' WHERE is_active = 1';
    }
    $query .= ' ORDER BY entry_type ASC, category ASC, title ASC, id ASC';
    return get_db()->query($query)->fetchAll();
}

function ai_get_knowledge_context(): string
{
    $entries = ai_get_knowledge(false);
    $parts = [];
    foreach ($entries as $entry) {
        $label = trim((string) $entry['title']);
        if ($entry['entry_type'] === 'faq' && trim((string) $entry['question']) !== '') {
            $label .= "\nQuestion: " . trim((string) $entry['question']);
        }
        $parts[] = '[' . $entry['category'] . '] ' . $label . "\n" . trim((string) $entry['content']);
    }
    return trim(implode("\n\n", $parts));
}
