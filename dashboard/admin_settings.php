<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../AI CHAT BOT/AI CHAT BOT/config.php';
require_once __DIR__ . '/../includes/ai_management.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}
ensure_user_archive_schema();
ensure_ai_management_schema(SYSTEM_PROMPT, SERVICE_CONTEXT);
$aiMessage = '';
$aiMessageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_action'])) {
    try {
        if ($_POST['ai_action'] === 'save_persona') {
            $persona = ai_clean_editable_persona(trim((string)($_POST['persona_prompt'] ?? '')));
            if ($persona === '') {
                throw new RuntimeException('Please enter the AI persona instructions.');
            }
            ai_save_setting('persona_prompt', $persona);
            ai_save_setting('agent_name', trim((string)($_POST['agent_name'] ?? 'PASS AI Assistant')));
            ai_save_setting('agent_role', trim((string)($_POST['agent_role'] ?? 'Student Support Assistant')));
            ai_save_setting('default_language', trim((string)($_POST['default_language'] ?? 'English')));
            ai_save_setting('tone_of_voice', trim((string)($_POST['tone_of_voice'] ?? 'Professional and friendly')));
            ai_save_setting('conversation_style', trim((string)($_POST['conversation_style'] ?? 'Helpful and concise')));
            ai_save_setting('response_length', trim((string)($_POST['response_length'] ?? 'Short')));
            ai_save_setting('chat_guidelines', trim((string)($_POST['chat_guidelines'] ?? 'Use verified information and ask for clarification when needed.')));
            $aiMessage = 'AI Persona saved successfully.';
        } elseif ($_POST['ai_action'] === 'save_knowledge') {
            $id = (int)($_POST['knowledge_id'] ?? 0);
            $title = trim((string)($_POST['knowledge_title'] ?? ''));
            $category = trim((string)($_POST['knowledge_category'] ?? 'General'));
            $content = trim((string)($_POST['knowledge_content'] ?? ''));
            if ($title === '' || $content === '') {
                throw new RuntimeException('Knowledge title and content are required.');
            }
            if ($id > 0) {
                $stmt = get_db()->prepare('UPDATE ai_knowledge SET title = ?, category = ?, content = ? WHERE id = ?');
                $stmt->execute([$title, $category, $content, $id]);
            } else {
                $stmt = get_db()->prepare('INSERT INTO ai_knowledge (entry_type, title, category, content) VALUES (?, ?, ?, ?)');
                $stmt->execute(['knowledge', $title, $category, $content]);
            }
            $aiMessage = 'Knowledge base entry saved successfully.';
        } elseif ($_POST['ai_action'] === 'save_faq') {
            $id = (int)($_POST['faq_id'] ?? 0);
            $question = trim((string)($_POST['faq_question'] ?? ''));
            $answer = trim((string)($_POST['faq_answer'] ?? ''));
            $category = trim((string)($_POST['faq_category'] ?? 'General'));
            if ($question === '' || $answer === '') {
                throw new RuntimeException('Question and answer are required.');
            }
            if ($id > 0) {
                $stmt = get_db()->prepare('UPDATE ai_knowledge SET question = ?, category = ?, content = ? WHERE id = ? AND entry_type = ?');
                $stmt->execute([$question, $category, $answer, $id, 'faq']);
            } else {
                $stmt = get_db()->prepare('INSERT INTO ai_knowledge (entry_type, title, category, question, content) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute(['faq', 'Trained Question', $category, $question, $answer]);
            }
            $aiMessage = 'Training question saved successfully.';
        } elseif ($_POST['ai_action'] === 'delete_knowledge') {
            $id = (int)($_POST['knowledge_id'] ?? 0);
            if ($id > 0) {
                get_db()->prepare('DELETE FROM ai_knowledge WHERE id = ?')->execute([$id]);
            }
            $aiMessage = 'AI knowledge entry deleted.';
        } elseif ($_POST['ai_action'] === 'toggle_knowledge') {
            $id = (int)($_POST['knowledge_id'] ?? 0);
            get_db()->prepare('UPDATE ai_knowledge SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
            $aiMessage = 'AI knowledge entry status updated.';
        }
    } catch (Throwable $e) {
        $aiMessage = $e->getMessage();
        $aiMessageType = 'error';
    }
}
$aiKnowledgeEntries = ai_get_knowledge();
$aiPersonaPrompt = ai_get_editable_persona(SYSTEM_PROMPT);
$aiKnowledgeCategories = [];
$aiFaqCategories = [];
foreach ($aiKnowledgeEntries as $entry) {
    $category = trim((string)($entry['category'] ?? 'General')) ?: 'General';
    if ($entry['entry_type'] === 'knowledge') {
        $aiKnowledgeCategories[$category] = true;
    } elseif ($entry['entry_type'] === 'faq') {
        $aiFaqCategories[$category] = true;
    }
}
$aiKnowledgeCategories = array_keys($aiKnowledgeCategories);
$aiFaqCategories = array_keys($aiFaqCategories);
$aiAgentName = ai_get_setting('agent_name', 'PASS AI Assistant');
$aiAgentRole = ai_get_setting('agent_role', 'Student Support Assistant');
$aiDefaultLanguage = ai_get_setting('default_language', 'English');
$aiTone = ai_get_setting('tone_of_voice', 'Professional and friendly');
$aiConversationStyle = ai_get_setting('conversation_style', 'Helpful and concise');
$aiResponseLength = ai_get_setting('response_length', 'Short');
$aiChatGuidelines = ai_get_setting('chat_guidelines', 'Use verified information and ask for clarification when needed.');
$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Admin | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        /* Smooth scrolling for anchor links */
        html {
            scroll-behavior: smooth;
        }

        /* Tabs styling */
        .settings-tabs,
        .footer-subtabs,
        .about-subtabs {
            touch-action: pan-x;
        }

        .settings-tabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            width: 100%;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
        }

        .tab-button {
            flex: 1 1 0;
            min-width: 140px;
            padding: 12px 20px;
            border: 2px solid transparent;
            background: white;
            color: #374151;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 14px;
            white-space: nowrap;
            scroll-snap-align: start;
        }

        .settings-tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-button {
            flex: 1 1 0;
            min-width: 140px;
            padding: 12px 20px;
            border: 2px solid transparent;
            background: white;
            color: #374151;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 14px;
            white-space: nowrap;
            scroll-snap-align: start;
        }

        .tab-button:hover {
            background: #f3f4f6;
            color: #800000;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #800000, #a84a38);
            color: white;
            border-color: #800000;
        }

        .tab-content {
            display: flex;
            flex-direction: column;
        }

        .tab-panel {
            display: none;
            padding: 24px;
            background: white;
            border-radius: 12px;
        }

        .tab-panel.active {
            display: block;
            animation: fadeIn 0.2s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-panel h2 {
            margin: 0 0 12px 0;
            font-size: 1.4rem;
            color: #800000;
        }

        .tab-panel > p {
            margin: 0 0 24px 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        #archived-alumni-table,
        #archived-user-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }

        #archived-alumni-table th,
        #archived-alumni-table td,
        #archived-user-table th,
        #archived-user-table td {
            padding: 12px 10px;
            border: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            word-break: normal;
            overflow-wrap: normal;
            white-space: normal;
        }

        #archived-alumni-table th,
        #archived-user-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 700;
        }

        #archived-alumni-table th:nth-child(1), #archived-alumni-table td:nth-child(1),
        #archived-user-table th:nth-child(1), #archived-user-table td:nth-child(1) { width: 18%; }
        #archived-alumni-table th:nth-child(2), #archived-alumni-table td:nth-child(2),
        #archived-user-table th:nth-child(2), #archived-user-table td:nth-child(2) { width: 16%; }
        #archived-alumni-table th:nth-child(3), #archived-alumni-table td:nth-child(3),
        #archived-user-table th:nth-child(3), #archived-user-table td:nth-child(3) { width: 19%; }
        #archived-alumni-table th:nth-child(4), #archived-alumni-table td:nth-child(4),
        #archived-user-table th:nth-child(4), #archived-user-table td:nth-child(4) { width: 22%; }
        #archived-alumni-table th:nth-child(5), #archived-alumni-table td:nth-child(5),
        #archived-user-table th:nth-child(5), #archived-user-table td:nth-child(5) { width: 15%; }
        #archived-alumni-table th:nth-child(6), #archived-alumni-table td:nth-child(6),
        #archived-user-table th:nth-child(6), #archived-user-table td:nth-child(6) { width: 10%; }

        #archived-alumni-table .secondary-button.small,
        #archived-user-table .secondary-button.small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 86px;
            min-height: 34px;
            padding: 7px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #374151;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            box-shadow: none;
        }

        #archived-alumni-table .secondary-button.small:hover,
        #archived-user-table .secondary-button.small:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        @media (max-width: 768px) {
            .archive-filter-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .archive-filter-grid .form-group select {
                min-height: 40px;
                font-size: 0.9rem;
            }

            #archived-alumni-table,
            #archived-user-table,
            #archived-alumni-table thead,
            #archived-user-table thead,
            #archived-alumni-table tbody,
            #archived-user-table tbody,
            #archived-alumni-table th,
            #archived-user-table th,
            #archived-alumni-table tr,
            #archived-user-table tr {
                display: block;
                width: 100%;
            }

            #archived-alumni-table thead,
            #archived-user-table thead {
                display: none;
            }

            #archived-alumni-table tbody,
            #archived-user-table tbody {
                display: block;
                width: 100%;
            }

            #archived-alumni-table tbody tr,
            #archived-user-table tbody tr {
                display: grid;
                gap: 12px;
                width: 100%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                padding: 16px;
                margin-bottom: 14px;
                box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
            }

            #archived-alumni-table td,
            #archived-user-table td {
                display: grid;
                width: 100%;
                padding: 8px 0;
                border: none;
                font-size: 14px;
                white-space: normal;
                line-height: 1.5;
                word-break: normal;
                overflow-wrap: normal;
            }

            #archived-alumni-table td + td,
            #archived-user-table td + td {
                padding-top: 12px;
            }

            #archived-alumni-table td::before,
            #archived-user-table td::before {
                content: attr(data-label);
                display: block;
                color: #475569;
                font-weight: 700;
                margin-bottom: 6px;
                font-size: 12px;
                letter-spacing: 0.01em;
            }

            #archived-alumni-table td:first-child,
            #archived-user-table td:first-child {
                padding-top: 0;
            }

            #archived-alumni-table td:last-child,
            #archived-user-table td:last-child {
                padding-bottom: 0;
            }
        }

        @media (max-width: 420px) {
            .tab-panel {
                padding: 16px 14px;
            }

            .card {
                padding: 14px 12px;
            }

            .archive-filter-grid .form-group label {
                font-size: 0.78rem;
            }

            .archive-filter-grid .form-group select {
                padding: 9px 10px;
                font-size: 0.85rem;
            }
        }

        /* Card styling */
        .card {
            background: #fafbfc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            color: #374151;
        }

        .archive-filter-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .archive-filter-grid .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
            margin: 0;
        }

        .archive-filter-grid .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: #4b5563;
            letter-spacing: 0.02em;
        }

        .archive-filter-grid .form-group select {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            color: #1f2937;
            font-size: 0.92rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .archive-filter-grid .form-group select:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.12);
        }

        /* Form rows */
        .form-row {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }

        .form-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-row input,
        .form-row textarea,
        .form-row select {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-row input:focus,
        .form-row textarea:focus,
        .form-row select:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        .form-row input[type="color"] {
            width: 60px;
            height: 42px;
            padding: 4px;
            cursor: pointer;
        }

        .form-row textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Poppins', sans-serif;
        }

        .form-row.checkbox-row {
            flex-direction: row;
            align-items: center;
        }

        .form-row.checkbox-row label {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .form-row.checkbox-row input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin: 0;
        }

        /* Preview grid for carousel images */
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .preview-grid img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        /* Image preview */
        .image-preview {
            padding: 20px;
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            text-align: center;
            color: #6b7280;
            min-height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 6px;
        }

        /* Repeatable sections */
        .repeatable-section {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 16px;
        }

        .repeatable-item {
            padding: 16px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .repeatable-item .form-row {
            margin-bottom: 0;
        }

        .remove-item {
            padding: 10px 16px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            align-self: flex-start;
        }

        .remove-item:hover {
            background: #fecaca;
            border-color: #f87171;
        }

        /* Buttons */
        .add-item,
        .save-button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .add-item {
            background: #e0f2fe;
            color: #0c4a6e;
            border: 2px solid #0ea5e9;
            align-self: flex-start;
        }

        .add-item:hover {
            background: #0ea5e9;
            color: white;
        }

        .save-button {
            background: linear-gradient(135deg, #800000, #a84a38);
            color: white;
            border: none;
        }

        .save-button:hover {
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        /* About sub-tabbed sections */
        .about-subtabs,
        .footer-subtabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            margin: 14px 0 18px;
            padding: 6px;
            background: #f8f7f2;
            border: 1px solid #ece7da;
            border-radius: 12px;
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .about-subtabs::-webkit-scrollbar,
        .footer-subtabs::-webkit-scrollbar {
            display: none;
        }

        .about-subtab-button,
        .footer-subtab-button {
            border: none;
            background: transparent;
            color: #6b7280;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .about-subtab-button:hover {
            background: #f3efe6;
            color: #800000;
        }

        .about-subtab-button.active {
            background: #800000;
            color: #fff;
            box-shadow: 0 2px 8px rgba(120, 40, 40, 0.18);
        }

        .about-subtab-panel {
            display: none;
            animation: fadeIn 0.2s ease;
        }

        .about-subtab-panel.active {
            display: block;
        }

        .main-scroll {
            width: 100%;
            max-width: 100%;
        }

        .content-panel {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            width: 100%;
            max-width: none;
            min-width: 0;
        }

        .settings-tabs-panel {
            width: 100%;
            max-width: none;
        }

        /* Dashboard intro styling */
        .dashboard-intro {
            padding: 0;
            margin-bottom: 24px;
        }

        .case-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border: 0;
            border-radius: 0 0 24px 24px;
        }

        .case-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }

        .case-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }

        .case-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .case-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .case-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }

        .case-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }

        .case-header__art::before {
            content: '';
            position: absolute;
            right: 13%;
            bottom: 2%;
            width: 82%;
            height: 30%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%);
            border-radius: 50%;
        }

        .case-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 4%;
            width: 76%;
            height: 42%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }

        .case-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-size: 28px;
            font-style: normal;
            font-weight: 900;
        }

        .case-header__art .art-chat {
            top: 57%;
            left: 2%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 15px;
        }

        .case-header__art .art-chat::after {
            content: '';
            position: absolute;
            right: 12px;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 2px solid rgba(215, 165, 141, .7);
            border-bottom: 2px solid rgba(215, 165, 141, .7);
            background: #f8f1e7;
            transform: rotate(45deg);
        }

        .case-header__art .art-file {
            top: 17%;
            left: 39%;
            padding: 16px 19px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42);
            font-size: 35px;
        }

        .case-header__art .art-card {
            top: 35%;
            left: 47%;
            padding: 13px 16px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 8px;
            background: rgba(248, 241, 231, .7);
            font-size: 28px;
        }

        .case-header__art .art-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 10%;
            left: 42%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }

        .case-header__art .art-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }

        .case-header__art .art-dots {
            right: 25%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        @media (max-width: 768px) {
            .case-page-header {
                min-height: 245px;
                padding: 32px 24px 110px;
            }

            .case-page-header > div:first-child {
                width: 100%;
            }

            .case-page-header h1 {
                font-size: 36px;
            }

            .case-header__art {
                width: 55%;
                opacity: .35;
            }
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .settings-tabs {
                gap: 8px;
                padding: 10px;
            }

            .about-subtabs,
            .footer-subtabs {
                max-width: 100%;
                width: 100%;
            }

            .tab-button {
                flex: 1 1 0;
                min-width: 110px;
                text-align: center;
            }

            .tab-content,
            .tab-panel,
            .about-subtab-panels,
            .about-subtab-panel,
            .footer-subtab-panels,
            .footer-subtab-panel,
            .about-subtab-panel .card,
            .footer-subtab-panel .card {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .tab-panel {
                padding: 16px;
            }

            .preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }

            .repeatable-item {
                padding: 12px;
            }

            .form-row input,
            .form-row textarea,
            .form-row select {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 16px;
            }
        }

        @media (max-width: 720px) {
            .about-subtabs {
                width: 100%;
                padding: 6px;
                margin: 10px 0 14px;
            }

            .about-subtab-button {
                padding: 8px 10px;
                font-size: 0.9rem;
            }

            .content-panel,
            .tab-content,
            .tab-panel,
            .about-subtab-panels,
            .about-subtab-panel,
            .footer-subtab-panels,
            .footer-subtab-panel,
            .about-subtab-panel .card,
            .footer-subtab-panel .card,
            .about-subtab-panel .form-row,
            .footer-subtab-panel .form-row {
                width: 100% !important;
                max-width: none !important;
                box-sizing: border-box;
            }

            .about-subtab-panels,
            .footer-subtab-panels {
                padding: 0 !important;
                margin: 0 !important;
            }

            .about-subtab-panel,
            .footer-subtab-panel {
                margin: 0 auto !important;
                padding: 0 !important;
            }

            .about-subtab-panel .card {
                padding: 16px;
                margin: 0 0 14px 0;
                width: 100% !important;
                max-width: none !important;
                box-sizing: border-box;
                border-radius: 12px;
                background: #fcfcfd;
                border: 1px solid #e5e7eb;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }

            .about-subtab-panel .card h3 {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            .about-subtab-panel .form-row {
                margin-bottom: 12px;
            }

            .about-subtab-panel .form-row label {
                font-size: 0.9rem;
            }

            .about-subtab-panel .form-row input,
            .about-subtab-panel .form-row textarea,
            .about-subtab-panel .form-row select {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
            }

            .about-subtab-panel .form-row textarea {
                min-height: 110px;
                border-radius: 10px;
                padding: 12px;
            }

            .about-subtab-panel .save-button {
                width: 100%;
                justify-content: center;
                max-width: 100%;
                border-radius: 10px;
            }
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }
        }

        @media (max-width: 420px) {
            .about-subtabs {
                gap: 6px;
            }

            .about-subtab-button {
                padding: 7px 8px;
                font-size: 0.85rem;
            }

            .about-subtab-panel .card {
                padding: 12px;
            }

            .about-subtab-panel .form-row textarea {
                min-height: 100px;
            }
        }

        .ai-entry-list { display: grid; gap: 12px; margin-top: 18px; }
        .ai-entry { border: 1px solid #e5e7eb; border-left: 4px solid #2563eb; border-radius: 8px; padding: 14px; background: #f8fafc; }
        .ai-entry h4 { margin: 0 0 6px; color: #1f2937; }
        .ai-entry p { white-space: pre-wrap; margin: 6px 0 12px; color: #4b5563; max-height: 180px; overflow: auto; }
        .ai-entry-meta { color: #64748b; font-size: .85rem; margin-bottom: 8px; }
        .ai-entry-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .ai-entry-actions button { border: 0; border-radius: 6px; padding: 7px 10px; cursor: pointer; font-weight: 600; }
        .ai-category-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; padding-bottom: 2px; }
        .ai-category-filter { border: 1px solid #cbd5e1; border-radius: 999px; padding: 7px 12px; background: #ffffff; color: #475569; cursor: pointer; font-weight: 600; font-size: .85rem; }
        .ai-category-filter:hover { border-color: #2563eb; color: #1d4ed8; }
        .ai-category-filter.active { border-color: #2563eb; background: #2563eb; color: #ffffff; }
        .ai-edit { background: #dbeafe; color: #1d4ed8; }
        .ai-toggle { background: #fef3c7; color: #92400e; }
        .ai-delete { background: #fee2e2; color: #b91c1c; }
        .ai-status { padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; background: #ecfdf5; color: #065f46; }
        .ai-status.error { background: #fef2f2; color: #b91c1c; }
        .ai-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .ai-form-grid .full { grid-column: 1 / -1; }
        @media (max-width: 700px) { .ai-form-grid { grid-template-columns: 1fr; } .ai-form-grid .full { grid-column: auto; } }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; inset: 0; background: rgba(255, 255, 255, 0.92); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0; text-align: center;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="admin_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="admin_assignments.php" data-tooltip="Head Assignments">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Head Assignments</span>
                </a>
                <a href="admin_users.php" data-tooltip="Users">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Users</span>
                </a>
                <a href="admin_analytics.php" data-tooltip="Analytics">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-text">Analytics</span>
                </a>
                <a href="admin_calendar_manager.php" data-tooltip="School Calendar">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span class="nav-text">School Calendar</span>
                </a>
                <a href="admin_settings.php" class="active" data-tooltip="Settings">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
                <a href="feedback_and_ratings.php" data-tooltip="Feedback">
                    <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                    <span class="nav-text">Feedback</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $ssaaOpen ? 'true' : 'false' ?>" data-tooltip="SSAA Management">
                        <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="nav-text">SSAA Management</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $ssaaOpen ? ' open' : '' ?>" aria-hidden="<?= $ssaaOpen ? 'false' : 'true' ?>">
                        <a href="student_promotion.php" data-tooltip="Student Promotion">
                            <span class="nav-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            <span class="nav-text">Student Promotion</span>
                        </a>
                        <a href="alumni_management.php" data-tooltip="Alumni Management">
                            <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                            <span class="nav-text">Alumni Management</span>
                        </a>
                        <a href="reports_analytics.php" data-tooltip="Reports & Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Reports & Analytics</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Admin Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Admin</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <!-- Profile button removed per request -->
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest system alerts</span>
                        </div>
                        <div class="menu-empty">No new notifications.</div>
                        <a class="menu-link menu-footer-link" href="admin_analytics.php">View system analytics</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="admin_assignments.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">Manage admin assignments</span>
                        </div>
                        <a href="admin_users.php" class="menu-action">View users</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="admin_settings.php">System settings</a>
                            <span class="menu-subtext">Configure system policies and access.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">System Controls</p>
                            <h1>Admin Settings</h1>
                            <p class="dashboard-subtitle">Manage login page branding, the about page, and footer content from a single tabbed interface.</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-file-lines art-file"></i>
                            <i class="fa-solid fa-user-tie art-card"></i>
                            <i class="fa-solid fa-clipboard-check art-chat"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>
                    <section class="settings-tabs-panel">
                        <div class="settings-tabs" role="tablist" aria-label="Admin settings tabs">
                            <button type="button" class="tab-button active" data-tab="login-settings" role="tab" aria-selected="true">Login Page</button>
                            <button type="button" class="tab-button" data-tab="about-settings" role="tab" aria-selected="false">About Page</button>
                            <button type="button" class="tab-button" data-tab="footer-content" role="tab" aria-selected="false">Footer Content</button>
                            <button type="button" class="tab-button" data-tab="alumni-archive" role="tab" aria-selected="false">Alumni Archive</button>
                            <button type="button" class="tab-button" data-tab="user-archive" role="tab" aria-selected="false">User Archive</button>
                            <button type="button" class="tab-button" data-tab="ai-management" role="tab" aria-selected="false"><i class="fa-solid fa-robot"></i> AI Management</button>
                        </div>
                        <div class="tab-content">
                            <div id="login-settings" class="tab-panel active" role="tabpanel">
                                <h2>Student / Teacher Login Page</h2>
                                <p>Configure carousel images, background color, and login logo used on <strong>student_login.php</strong> and <strong>teacher_login.php</strong>.</p>
                                <div class="card">
                                    <h3>Carousel Images</h3>
                                    <p>Add multiple carousel images for the student and teacher login pages.</p>
                                    <div class="form-row">
                                        <label for="carouselLoginType">Target login page</label>
                                        <select id="carouselLoginType">
                                            <option value="student">Student Login</option>
                                            <option value="teacher">Teacher Login</option>
                                            <option value="both">Both Student and Teacher Login</option>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label>Selected target</label>
                                        <div id="carouselPreviewTarget" style="font-size:0.95rem;color:#111827;padding:10px 12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
                                            Student Login
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <label for="carouselImages">Select images</label>
                                        <input id="carouselImages" type="file" accept="image/*" multiple>
                                    </div>
                                    <button type="button" class="add-item" id="uploadCarouselBtn">Upload selected images</button>
                                    <div class="preview-grid" id="carouselPreview"></div>
                                    <div class="preview-grid" id="currentCarouselImages"></div>
                                </div>
                                <div class="card">
                                    <h3>Login Logo</h3>
                                    <div class="form-row">
                                        <label for="loginLogo">Choose logo</label>
                                        <input id="loginLogo" type="file" accept="image/*">
                                    </div>
                                    <div class="image-preview" id="logoPreview">Logo preview will appear here.</div>
                                </div>
                                <button type="button" class="save-button" id="saveLoginSettingsBtn">Save Login Settings</button>
                                <div id="logoSaveStatus" style="margin-top:12px;font-size:0.95rem;color:#065f46;"></div>
                            </div>
                            <div id="about-settings" class="tab-panel" role="tabpanel" hidden>
                                <h2>About Page</h2>
                                <p>Manage the sections that appear on the About page.</p>
                                <div class="about-subtabs" role="tablist" aria-label="About page sections">
                                    <button type="button" class="about-subtab-button active" data-abouttab="about-mission-vision" role="tab" aria-selected="true">Mission &amp; Vision</button>
                                    <button type="button" class="about-subtab-button" data-abouttab="about-programs" role="tab" aria-selected="false">Program Gallery</button>
                                    <button type="button" class="about-subtab-button" data-abouttab="about-people" role="tab" aria-selected="false">People / Employees</button>
                                </div>
                                <div class="about-subtab-panels">
                                    <div id="about-mission-vision" class="about-subtab-panel active" role="tabpanel">
                                        <div class="card">
                                            <h3>Mission &amp; Vision</h3>
                                            <div class="form-row">
                                                <label for="aboutVision">Vision</label>
                                                <textarea id="aboutVision" rows="4" placeholder="Enter the institution vision..."></textarea>
                                            </div>
                                            <div class="form-row">
                                                <label for="aboutMission">Mission</label>
                                                <textarea id="aboutMission" rows="4" placeholder="Enter the institution mission..."></textarea>
                                            </div>
                                            <div class="form-row">
                                                <label for="aboutCoreValues">Core values (one per line)</label>
                                                <textarea id="aboutCoreValues" rows="5" placeholder="Integrity&#10;Excellence&#10;Service"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="about-programs" class="about-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>Program Gallery</h3>
                                            <div class="repeatable-section" id="aboutPrograms">
                                                <div class="repeatable-item">
                                                    <div class="form-row">
                                                        <label>Program title</label>
                                                        <input type="text" class="program-title">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Description</label>
                                                        <textarea rows="2" class="program-description"></textarea>
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Accent color</label>
                                                        <input type="color" class="program-color" value="#800000">
                                                    </div>
                                                    <button type="button" class="remove-item">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="add-item" data-target="aboutPrograms">Add program</button>
                                        </div>
                                    </div>
                                    <div id="about-people" class="about-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>People / Employees</h3>
                                            <div class="repeatable-section" id="aboutPeople">
                                                <div class="repeatable-item">
                                                    <div class="form-row">
                                                        <label>Profile image</label>
                                                        <input type="file" accept="image/*" class="person-image-input">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Name</label>
                                                        <input type="text" class="person-name">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Category</label>
                                                        <select class="person-category-select">
                                                            <option value="">Select category</option>
                                                            <option value="Administration">Administration</option>
                                                            <option value="Support Services">Support Services</option>
                                                            <option value="Student Engagement">Student Engagement</option>
                                                            <option value="Operations">Operations</option>
                                                            <option value="custom">Custom</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-row custom-person-category" style="display:none;">
                                                        <label>Custom category</label>
                                                        <input type="text" class="person-category-custom">
                                                    </div>
                                                    <input type="hidden" class="person-current-image" value="">
                                                    <button type="button" class="remove-item">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="add-item" data-target="aboutPeople">Add person</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="save-button" id="saveAboutPageBtn">Save About Page</button>
                                <div id="aboutSaveStatus" style="margin-top:12px;font-size:0.95rem;color:#065f46;"></div>
                                <script>
                                    window.aboutSettingsData = <?= json_encode([
                                        'vision' => trim((string)get_library_setting('about_vision', 'To become a leading Higher Educational Institution committed to a holistic and transformative community through learning dedicated to academic excellence, leadership, and values formation.')),
                                        'mission' => trim((string)get_library_setting('about_mission', 'Empower every student with accessible learning resources, strong guidance, and a safe environment that encourages growth, innovation, and global competitiveness.')),
                                        'coreValues' => json_decode(get_library_setting('about_core_values', '[]'), true) ?: [],
                                        'programs' => json_decode(get_library_setting('about_programs', '[]'), true) ?: [],
                                        'people' => json_decode(get_library_setting('about_people', '[]'), true) ?: [],
                                    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                                </script>
                            </div>
                            <div id="footer-content" class="tab-panel" role="tabpanel" hidden>
                                <h2>Footer Content</h2>
                                <p>Manage footer contacts, locations, and social links from one place.</p>
                                <div class="footer-subtabs" role="tablist" aria-label="Footer content sections">
                                    <button type="button" class="footer-subtab-button active" data-footertab="footer-contacts" role="tab" aria-selected="true">Contacts</button>
                                    <button type="button" class="footer-subtab-button" data-footertab="footer-locations" role="tab" aria-selected="false">Locations</button>
                                    <button type="button" class="footer-subtab-button" data-footertab="footer-social" role="tab" aria-selected="false">Social Media</button>
                                </div>
                                <div class="footer-subtab-panels">
                                    <div id="footer-contacts" class="footer-subtab-panel active" role="tabpanel">
                                        <div class="card">
                                            <h3>Contacts</h3>
                                            <div class="repeatable-section" id="contactItems">
                                                <div class="repeatable-item">
                                                    <div class="form-row">
                                                        <label>Contact label</label>
                                                        <input type="text" placeholder="e.g. Main Office">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Contact value</label>
                                                        <input type="text" placeholder="e.g. +63 912 345 6789 or info@supportsystem.local">
                                                    </div>
                                                    <button type="button" class="remove-item">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="add-item" data-target="contactItems">Add contact</button>
                                        </div>
                                    </div>
                                    <div id="footer-locations" class="footer-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>Locations</h3>
                                            <div class="repeatable-section" id="locationItems">
                                                <div class="repeatable-item">
                                                    <div class="form-row">
                                                        <label>Location name</label>
                                                        <input type="text" placeholder="e.g. Main Campus">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Location link</label>
                                                        <input type="url" placeholder="https://maps.google.com/...">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Location address</label>
                                                        <input type="text" placeholder="e.g. 123 Main St, City">
                                                    </div>
                                                    <button type="button" class="remove-item">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="add-item" data-target="locationItems">Add location</button>
                                        </div>
                                    </div>
                                    <div id="footer-social" class="footer-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>Social Media</h3>
                                            <div class="repeatable-section" id="socialItems">
                                                <div class="repeatable-item">
                                                    <div class="form-row">
                                                        <label>Platform</label>
                                                        <select>
                                                            <option value="">-- choose --</option>
                                                            <option value="facebook">Facebook</option>
                                                            <option value="facebook-messenger">Facebook Messenger</option>
                                                            <option value="tiktok">TikTok</option>
                                                            <option value="x">X</option>
                                                            <option value="youtube">YouTube</option>
                                                            <option value="instagram">Instagram</option>
                                                            <option value="threads">Threads</option>
                                                            <option value="whatsapp">WhatsApp</option>
                                                            <option value="telegram">Telegram</option>
                                                            <option value="discord">Discord</option>
                                                            <option value="reddit">Reddit</option>
                                                            <option value="pinterest">Pinterest</option>
                                                            <option value="quora">Quora</option>
                                                            <option value="others">Others</option>
                                                        </select>
                                                        <input type="text" class="custom-platform" placeholder="Custom platform name" style="display:none;margin-top:8px;">
                                                    </div>
                                                    <div class="form-row">
                                                        <label>Link</label>
                                                        <input type="url" placeholder="https://facebook.com...">
                                                    </div>
                                                    <button type="button" class="remove-item">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="add-item" data-target="socialItems">Add social link</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="save-button" id="saveFooterContentBtn">Save Footer Content</button>
                                <script>
                                    // Provide initial footer data from server
                                    window.footerData = <?= json_encode([
                                        'contacts' => json_decode(get_library_setting('footer_contacts', '[]'), true),
                                        'locations' => json_decode(get_library_setting('footer_locations', '[]'), true),
                                        'social' => json_decode(get_library_setting('footer_social', '[]'), true),
                                    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                                </script>
                            </div>
                            <div id="alumni-archive" class="tab-panel" role="tabpanel" hidden>
                                <h2>Alumni Archive</h2>
                                <p>Review archived alumni records and restore them back to the active alumni list when needed.</p>
                                <div class="card">
                                    <h3>Archived Alumni</h3>
                                    <div class="archive-filter-grid">
                                        <div class="form-group">
                                            <label for="archive-filter-course">Course</label>
                                            <select id="archive-filter-course">
                                                <option value="">All Courses</option>
                                                <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                                <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="archive-filter-year">Year Graduated</label>
                                            <select id="archive-filter-year">
                                                <option value="">All Years</option>
                                                <?php for ($year = date('Y'); $year >= 2000; $year--): ?>
                                                    <option value="<?= $year ?>"><?= $year ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="alumni-table-wrapper">
                                        <table class="alumni-table" id="archived-alumni-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Course</th>
                                                    <th>Year Graduated</th>
                                                    <th>Email</th>
                                                    <th>Archived On</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="archived-alumni-list">
                                                <tr><td colspan="6">Loading archived alumni...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div id="user-archive" class="tab-panel" role="tabpanel" hidden>
                                <h2>User Archive</h2>
                                <p>Review archived student and teacher records and restore them back to the active user list when needed.</p>
                                <div class="card">
                                    <h3>Archived Users</h3>
                                    <div class="archive-filter-grid">
                                        <div class="form-group">
                                            <label for="user-archive-filter-course">Course / Department</label>
                                            <select id="user-archive-filter-course">
                                                <option value="">All Courses</option>
                                                <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                                <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="user-archive-filter-year">Year</label>
                                            <select id="user-archive-filter-year">
                                                <option value="">All Years</option>
                                                <option value="1">1st Year</option>
                                                <option value="2">2nd Year</option>
                                                <option value="3">3rd Year</option>
                                                <option value="4">4th Year</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="alumni-table-wrapper">
                                        <table class="alumni-table" id="archived-user-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Course / Department</th>
                                                    <th>Email</th>
                                                    <th>Archived On</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="archived-user-list">
                                                <tr><td colspan="6">Loading archived users...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div id="ai-management" class="tab-panel" role="tabpanel" hidden>
                                <h2>AI Management</h2>
                                <p>Configure how the support assistant talks and manage the information it uses to answer questions.</p>
                                <?php if ($aiMessage !== ''): ?>
                                    <div class="ai-status<?= $aiMessageType === 'error' ? ' error' : '' ?>"><?= htmlspecialchars($aiMessage, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <div class="about-subtabs" role="tablist" aria-label="AI management sections">
                                    <button type="button" class="about-subtab-button active" data-abouttab="ai-persona" role="tab" aria-selected="true">AI Persona</button>
                                    <button type="button" class="about-subtab-button" data-abouttab="ai-knowledge" role="tab" aria-selected="false">Knowledge Base</button>
                                    <button type="button" class="about-subtab-button" data-abouttab="ai-teach" role="tab" aria-selected="false">Teach Your Agent</button>
                                </div>
                                <div class="about-subtab-panels">
                                    <div id="ai-persona" class="about-subtab-panel active" role="tabpanel">
                                        <div class="card">
                                            <h3>AI Persona</h3>
                                            <form method="post">
                                                <input type="hidden" name="ai_action" value="save_persona">
                                                <div class="ai-form-grid">
                                                    <div class="form-row"><label for="agent_name">Agent name</label><input id="agent_name" name="agent_name" value="<?= htmlspecialchars($aiAgentName, ENT_QUOTES, 'UTF-8') ?>" required></div>
                                                    <div class="form-row"><label for="agent_role">Agent role</label><input id="agent_role" name="agent_role" value="<?= htmlspecialchars($aiAgentRole, ENT_QUOTES, 'UTF-8') ?>" required></div>
                                                    <div class="form-row"><label for="default_language">Default language</label><select id="default_language" name="default_language"><option <?= $aiDefaultLanguage === 'English' ? 'selected' : '' ?>>English</option><option <?= $aiDefaultLanguage === 'Filipino' ? 'selected' : '' ?>>Filipino</option><option <?= $aiDefaultLanguage === 'English and Filipino' ? 'selected' : '' ?>>English and Filipino</option></select></div>
                                                    <div class="form-row"><label for="tone_of_voice">Tone of voice</label><input id="tone_of_voice" name="tone_of_voice" value="<?= htmlspecialchars($aiTone, ENT_QUOTES, 'UTF-8') ?>"></div>
                                                    <div class="form-row"><label for="conversation_style">Conversation style</label><input id="conversation_style" name="conversation_style" value="<?= htmlspecialchars($aiConversationStyle, ENT_QUOTES, 'UTF-8') ?>"></div>
                                                    <div class="form-row"><label for="response_length">Chat response length</label><select id="response_length" name="response_length"><option <?= $aiResponseLength === 'Short' ? 'selected' : '' ?>>Short</option><option <?= $aiResponseLength === 'Balanced' ? 'selected' : '' ?>>Balanced</option><option <?= $aiResponseLength === 'Detailed' ? 'selected' : '' ?>>Detailed</option></select></div>
                                                    <div class="form-row full"><label for="chat_guidelines">Chat guidelines</label><textarea id="chat_guidelines" name="chat_guidelines" rows="4"><?= htmlspecialchars($aiChatGuidelines, ENT_QUOTES, 'UTF-8') ?></textarea></div>
                                                    <div class="form-row full"><label for="persona_prompt">How the agent talks and acts</label><textarea id="persona_prompt" name="persona_prompt" rows="12" required><?= htmlspecialchars($aiPersonaPrompt, ENT_QUOTES, 'UTF-8') ?></textarea></div>
                                                </div>
                                                <button type="submit" class="save-button">Save AI Persona</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div id="ai-knowledge" class="about-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>Knowledge Base</h3>
                                            <p>Add only verified information here. The chatbot will use active entries from this list.</p>
                                            <form method="post">
                                                <input type="hidden" name="ai_action" value="save_knowledge">
                                                <input type="hidden" name="knowledge_id" id="knowledge_id" value="">
                                                <div class="form-row"><label for="knowledge_title">Title</label><input id="knowledge_title" name="knowledge_title" placeholder="e.g. Library policies and procedures" required></div>
                                                <div class="form-row"><label for="knowledge_category">Category</label><input id="knowledge_category" name="knowledge_category" placeholder="e.g. Library" value="General" required></div>
                                                <div class="form-row"><label for="knowledge_content">Knowledge content</label><textarea id="knowledge_content" name="knowledge_content" rows="16" placeholder="Enter verified information the AI may use..." required></textarea></div>
                                                <button type="submit" class="save-button">Save Knowledge Entry</button>
                                                <button type="button" class="add-item" id="clearKnowledgeForm">New Entry</button>
                                            </form>
                                            <div class="ai-category-filters" data-filter-target="knowledge" role="tablist" aria-label="Knowledge Base categories">
                                                <button type="button" class="ai-category-filter active" data-category-filter="all" role="tab" aria-selected="true">All</button>
                                                <?php foreach ($aiKnowledgeCategories as $category): ?>
                                                    <button type="button" class="ai-category-filter" data-category-filter="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" role="tab" aria-selected="false"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ai-entry-list">
                                                <?php foreach ($aiKnowledgeEntries as $entry): ?>
                                                    <?php if ($entry['entry_type'] !== 'knowledge') continue; ?>
                                                    <article class="ai-entry" data-entry-type="knowledge" data-entry-category="<?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <h4><?= htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                        <div class="ai-entry-meta"><?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?> · <?= $entry['is_active'] ? 'Active' : 'Disabled' ?></div>
                                                        <p><?= htmlspecialchars($entry['content'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <div class="ai-entry-actions">
                                                            <button type="button" class="ai-edit" data-ai-edit='<?= htmlspecialchars(json_encode($entry), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                                            <form method="post"><input type="hidden" name="ai_action" value="toggle_knowledge"><input type="hidden" name="knowledge_id" value="<?= (int)$entry['id'] ?>"><button class="ai-toggle" type="submit"><?= $entry['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                                                            <form method="post" onsubmit="return confirm('Delete this AI knowledge entry?');"><input type="hidden" name="ai_action" value="delete_knowledge"><input type="hidden" name="knowledge_id" value="<?= (int)$entry['id'] ?>"><button class="ai-delete" type="submit">Delete</button></form>
                                                        </div>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="ai-teach" class="about-subtab-panel" role="tabpanel" hidden>
                                        <div class="card">
                                            <h3>Teach Your Agent</h3>
                                            <p>Add a verified question-and-answer pair for common conversations.</p>
                                            <form method="post">
                                                <input type="hidden" name="ai_action" value="save_faq">
                                                <input type="hidden" name="faq_id" id="faq_id" value="">
                                                <div class="form-row"><label for="faq_category">Category</label><input id="faq_category" name="faq_category" value="General" required></div>
                                                <div class="form-row"><label for="faq_question">User question</label><input id="faq_question" name="faq_question" placeholder="e.g. How do I reserve a book?" required></div>
                                                <div class="form-row"><label for="faq_answer">Correct answer</label><textarea id="faq_answer" name="faq_answer" rows="8" placeholder="Write the verified answer..." required></textarea></div>
                                                <button type="submit" class="save-button">Save Training</button>
                                                <button type="button" class="add-item" id="clearFaqForm">New Training</button>
                                            </form>
                                            <div class="ai-category-filters" data-filter-target="faq" role="tablist" aria-label="Teach Your Agent categories">
                                                <button type="button" class="ai-category-filter active" data-category-filter="all" role="tab" aria-selected="true">All</button>
                                                <?php foreach ($aiFaqCategories as $category): ?>
                                                    <button type="button" class="ai-category-filter" data-category-filter="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" role="tab" aria-selected="false"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ai-entry-list">
                                                <?php foreach ($aiKnowledgeEntries as $entry): ?>
                                                    <?php if ($entry['entry_type'] !== 'faq') continue; ?>
                                                    <article class="ai-entry" data-entry-type="faq" data-entry-category="<?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <h4><?= htmlspecialchars($entry['question'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                        <div class="ai-entry-meta"><?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?> · <?= $entry['is_active'] ? 'Active' : 'Disabled' ?></div>
                                                        <p><?= htmlspecialchars($entry['content'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <div class="ai-entry-actions"><button type="button" class="ai-edit" data-faq-edit='<?= htmlspecialchars(json_encode($entry), ENT_QUOTES, 'UTF-8') ?>'>Edit</button><form method="post"><input type="hidden" name="ai_action" value="toggle_knowledge"><input type="hidden" name="knowledge_id" value="<?= (int)$entry['id'] ?>"><button class="ai-toggle" type="submit"><?= $entry['is_active'] ? 'Disable' : 'Enable' ?></button></form><form method="post" onsubmit="return confirm('Delete this training entry?');"><input type="hidden" name="ai_action" value="delete_knowledge"><input type="hidden" name="knowledge_id" value="<?= (int)$entry['id'] ?>"><button class="ai-delete" type="submit">Delete</button></form></div>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const pageOverlay = document.getElementById('pageOverlay');
        const sideNav = document.querySelector('.side-nav');
        if (!mobileToggle || !pageOverlay || !sideNav) return;
        function openMobileNav(){ sideNav.classList.add('mobile-open'); pageOverlay.classList.add('active'); }
        function closeMobileNav(){ sideNav.classList.remove('mobile-open'); pageOverlay.classList.remove('active'); }
        mobileToggle.addEventListener('click', function(){ sideNav.classList.contains('mobile-open') ? closeMobileNav() : openMobileNav(); });
        pageOverlay.addEventListener('click', closeMobileNav);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMobileNav(); });
    });
    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>
        // Expandable navigation functionality
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
        const navToggle = document.querySelector('.nav-toggle');
        const submenu = document.querySelector('.submenu');
        const toggleArrow = document.querySelector('.toggle-arrow');

        if (navToggle && submenu && toggleArrow) {
            const initialExpanded = navToggle.getAttribute('aria-expanded') === 'true';
            if (initialExpanded) {
                submenu.classList.add('open');
                submenu.style.display = 'grid';
                toggleArrow.style.transform = 'rotate(180deg)';
            } else {
                submenu.classList.remove('open');
                submenu.style.display = 'none';
                toggleArrow.style.transform = 'rotate(0deg)';
            }

            navToggle.addEventListener('click', function() {
                const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';
                navToggle.setAttribute('aria-expanded', !isExpanded);
                submenu.setAttribute('aria-hidden', isExpanded);
                if (isExpanded) {
                    submenu.classList.remove('open');
                    submenu.style.display = 'none';
                    toggleArrow.style.transform = 'rotate(0deg)';
                } else {
                    submenu.classList.add('open');
                    submenu.style.display = 'grid';
                    toggleArrow.style.transform = 'rotate(180deg)';
                }
            });
        }

        const sidebarToggle = document.getElementById('sidebarToggle');
        const sideNav = document.querySelector('.side-nav');
        if (sidebarToggle && sideNav) {
            sidebarToggle.addEventListener('click', function() {
                sideNav.classList.toggle('collapsed');
            });
        }

        const tabs = document.querySelectorAll('.tab-button');
        const panels = document.querySelectorAll('.tab-panel');
        const addButtons = document.querySelectorAll('.add-item');
        const tabList = document.querySelector('.settings-tabs');
        let touchStartX = 0;
        let touchEndX = 0;

        function shouldUseSwipe() {
            return window.innerWidth <= 768;
        }

        function activateTab(targetTab) {
            tabs.forEach(btn => {
                const isActive = btn.dataset.tab === targetTab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive.toString());
            });
            panels.forEach(panel => {
                const isActive = panel.id === targetTab;
                panel.classList.toggle('active', isActive);
                panel.hidden = !isActive;
            });
            const activeTab = document.querySelector(`.tab-button[data-tab="${targetTab}"]`);
            if (activeTab && tabList) {
                activeTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }

        tabs.forEach(button => {
            button.addEventListener('click', function() {
                activateTab(button.dataset.tab);
            });
        });

        if (tabList) {
            tabList.addEventListener('touchstart', function(event) {
                if (!shouldUseSwipe()) return;
                touchStartX = event.touches[0].clientX;
            }, { passive: true });

            tabList.addEventListener('touchend', function(event) {
                if (!shouldUseSwipe()) return;
                touchEndX = event.changedTouches[0].clientX;
                const delta = touchEndX - touchStartX;
                if (Math.abs(delta) < 50) {
                    return;
                }
                const activeTab = document.querySelector('.tab-button.active');
                if (!activeTab) {
                    return;
                }
                const activeIndex = Array.from(tabs).indexOf(activeTab);
                const nextIndex = delta < 0 ? Math.min(activeIndex + 1, tabs.length - 1) : Math.max(activeIndex - 1, 0);
                activateTab(tabs[nextIndex].dataset.tab);
            }, { passive: true });
        }

        const footerSubTabs = document.querySelectorAll('.footer-subtab-button');
        const footerSubPanels = document.querySelectorAll('.footer-subtab-panel');
        const aboutSubTabs = document.querySelectorAll('#about-settings .about-subtab-button');
        const aboutSubPanels = document.querySelectorAll('#about-settings .about-subtab-panel');
        const aiSubTabs = document.querySelectorAll('#ai-management .about-subtab-button');
        const aiSubPanels = document.querySelectorAll('#ai-management .about-subtab-panel');
        let footerTouchStartX = 0;
        let footerTouchEndX = 0;
        let aboutTouchStartX = 0;
        let aboutTouchEndX = 0;

        function activateFooterSubTab(targetTab) {
            footerSubTabs.forEach(btn => {
                const isActive = btn.dataset.footertab === targetTab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive.toString());
            });
            footerSubPanels.forEach(panel => {
                const isActive = panel.id === targetTab;
                panel.classList.toggle('active', isActive);
                panel.hidden = !isActive;
            });
        }

        function activateAboutSubTab(targetTab) {
            aboutSubTabs.forEach(btn => {
                const isActive = btn.dataset.abouttab === targetTab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive.toString());
            });
            aboutSubPanels.forEach(panel => {
                const isActive = panel.id === targetTab;
                panel.classList.toggle('active', isActive);
                panel.hidden = !isActive;
            });
        }

        function activateAiSubTab(targetTab) {
            aiSubTabs.forEach(btn => {
                const isActive = btn.dataset.abouttab === targetTab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive.toString());
            });
            aiSubPanels.forEach(panel => {
                const isActive = panel.id === targetTab;
                panel.classList.toggle('active', isActive);
                panel.hidden = !isActive;
            });
        }

        footerSubTabs.forEach(button => {
            button.addEventListener('click', function() {
                activateFooterSubTab(button.dataset.footertab);
            });
        });

        aboutSubTabs.forEach(button => {
            button.addEventListener('click', function() {
                activateAboutSubTab(button.dataset.abouttab);
            });
        });

        aiSubTabs.forEach(button => {
            button.addEventListener('click', function() {
                activateAiSubTab(button.dataset.abouttab);
            });
        });

        const footerSubTabList = document.querySelector('.footer-subtabs');
        const aboutSubTabList = document.querySelector('#about-settings .about-subtabs');
        const aiSubTabList = document.querySelector('#ai-management .about-subtabs');

        if (footerSubTabList) {
            footerSubTabList.addEventListener('touchstart', function(event) {
                if (!shouldUseSwipe()) return;
                footerTouchStartX = event.touches[0].clientX;
            }, { passive: true });

            footerSubTabList.addEventListener('touchend', function(event) {
                if (!shouldUseSwipe()) return;
                footerTouchEndX = event.changedTouches[0].clientX;
                const delta = footerTouchEndX - footerTouchStartX;
                if (Math.abs(delta) < 50) return;
                const activeTab = document.querySelector('.footer-subtab-button.active');
                if (!activeTab) return;
                const tabsArray = Array.from(footerSubTabs);
                const activeIndex = tabsArray.indexOf(activeTab);
                const nextIndex = delta < 0 ? Math.min(activeIndex + 1, tabsArray.length - 1) : Math.max(activeIndex - 1, 0);
                activateFooterSubTab(tabsArray[nextIndex].dataset.footertab);
            }, { passive: true });
        }

        if (aboutSubTabList) {
            aboutSubTabList.addEventListener('touchstart', function(event) {
                if (!shouldUseSwipe()) return;
                aboutTouchStartX = event.touches[0].clientX;
            }, { passive: true });

            aboutSubTabList.addEventListener('touchend', function(event) {
                if (!shouldUseSwipe()) return;
                aboutTouchEndX = event.changedTouches[0].clientX;
                const delta = aboutTouchEndX - aboutTouchStartX;
                if (Math.abs(delta) < 50) return;
                const activeTab = document.querySelector('.about-subtab-button.active');
                if (!activeTab) return;
                const tabsArray = Array.from(aboutSubTabs);
                const activeIndex = tabsArray.indexOf(activeTab);
                const nextIndex = delta < 0 ? Math.min(activeIndex + 1, tabsArray.length - 1) : Math.max(activeIndex - 1, 0);
                activateAboutSubTab(tabsArray[nextIndex].dataset.abouttab);
            }, { passive: true });
        }

        function bindSocialHandlersTo(item) {
            const select = item.querySelector('select');
            const custom = item.querySelector('.custom-platform');
            if (select) {
                select.addEventListener('change', function(){
                    if (this.value === 'others') {
                        if (custom) custom.style.display = '';
                    } else {
                        if (custom) { custom.style.display = 'none'; custom.value = ''; }
                    }
                });
            }
        }

        function bindAboutCategoryHandlers(item) {
            const select = item.querySelector('.person-category-select');
            const customRow = item.querySelector('.custom-person-category');
            const customInput = item.querySelector('.person-category-custom');
            if (!select || !customRow || !customInput) return;
            const updateVisibility = () => {
                const isCustom = select.value === 'custom';
                customRow.style.display = isCustom ? '' : 'none';
                if (!isCustom) customInput.value = '';
            };
            select.addEventListener('change', updateVisibility);
            updateVisibility();
        }

        addButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = button.dataset.target;
                const target = document.getElementById(targetId);
                if (!target) return;
                const template = target.querySelector('.repeatable-item');
                if (!template) return;
                const clone = template.cloneNode(true);
                clone.querySelectorAll('input, select').forEach(input => { if (input.tagName.toLowerCase() === 'select') input.value = ''; else input.value = ''; });
                clone.querySelectorAll('.remove-item').forEach(removeButton => {
                    removeButton.addEventListener('click', () => {
                        if (target.children.length > 1) clone.remove();
                    });
                });
                // bind social select if present
                bindSocialHandlersTo(clone);
                bindAboutCategoryHandlers(clone);
                target.appendChild(clone);
            });
        });

        // Populate footer-content repeatable sections from server-provided window.footerData
        (function populateFooterContent(){
            try {
                const data = window.footerData || {};
                function populateSection(sectionId, items, templateClear=true){
                    const container = document.getElementById(sectionId);
                    if (!container) return;
                    // keep one template element to clone if exists
                    const existingTemplate = container.querySelector('.repeatable-item');
                    const templateHtml = existingTemplate ? existingTemplate.outerHTML : '<div class="repeatable-item"><div class="form-row"><label>Field</label><input type="text"></div><button type="button" class="remove-item">Remove</button></div>';
                    container.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) {
                        container.insertAdjacentHTML('beforeend', templateHtml);
                        // bind social handlers if needed
                        const last = container.querySelector('.repeatable-item:last-child');
                        if (last) bindSocialHandlersTo(last);
                        return;
                    }
                    items.forEach(it => {
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = templateHtml;
                        const tpl = wrapper.firstElementChild;
                        if (!tpl) return;
                        if (sectionId === 'contactItems'){
                            const inputs = tpl.querySelectorAll('input');
                            if (inputs[0]) inputs[0].value = it.label ?? it[0] ?? '';
                            if (inputs[1]) inputs[1].value = it.value ?? it[1] ?? '';
                        }
                        if (sectionId === 'locationItems'){
                            const inputs = tpl.querySelectorAll('input');
                            if (inputs[0]) inputs[0].value = it.name ?? it[0] ?? '';
                            if (inputs[1]) inputs[1].value = it.link ?? it[1] ?? '';
                            if (inputs[2]) inputs[2].value = it.address ?? it[2] ?? '';
                        }
                            if (sectionId === 'socialItems'){
                                // select is first, custom input may be present, then link input
                                const select = tpl.querySelector('select');
                                const custom = tpl.querySelector('.custom-platform');
                                const linkInput = tpl.querySelector('input[type="url"]');
                                if (select) {
                                    const platformValue = (it.platform ?? it[0] ?? '').toString().toLowerCase();
                                    const knownOptions = Array.from(select.options).map(o=>o.value.toString().toLowerCase());
                                    if (knownOptions.includes(platformValue) && platformValue !== 'others') {
                                        select.value = platformValue;
                                        if (custom) custom.style.display = 'none';
                                    } else if (platformValue && platformValue !== '') {
                                        select.value = 'others';
                                        if (custom) { custom.style.display = ''; custom.value = it.platform ?? it[0] ?? ''; }
                                    } else {
                                        select.value = '';
                                        if (custom) custom.style.display = 'none';
                                    }
                                    // bind change
                                    select.addEventListener('change', function(){
                                        if (this.value === 'others') {
                                            if (custom) custom.style.display = '';
                                        } else {
                                            if (custom) { custom.style.display = 'none'; custom.value = ''; }
                                        }
                                    });
                                }
                                if (linkInput) linkInput.value = it.link ?? it[1] ?? '';
                            }
                        tpl.querySelectorAll('.remove-item').forEach(btn=>btn.addEventListener('click', ()=>{ if (container.children.length>1) btn.closest('.repeatable-item').remove(); }));
                        container.appendChild(tpl);
                    });
                }
                populateSection('contactItems', data.contacts || []);
                populateSection('locationItems', data.locations || []);
                populateSection('socialItems', data.social || []);
            } catch(e){ console.error('populateFooterContent', e); }
        })();

        (function populateAboutSettings(){
            try {
                const data = window.aboutSettingsData || {};
                const visionInput = document.getElementById('aboutVision');
                const missionInput = document.getElementById('aboutMission');
                const valuesArea = document.getElementById('aboutCoreValues');
                const programsContainer = document.getElementById('aboutPrograms');
                const peopleContainer = document.getElementById('aboutPeople');

                if (visionInput) visionInput.value = data.vision || '';
                if (missionInput) missionInput.value = data.mission || '';
                if (valuesArea) {
                    const values = Array.isArray(data.coreValues) ? data.coreValues : [];
                    const flattened = values.map(item => {
                        if (typeof item === 'string') return item;
                        if (item && typeof item === 'object') return item.value || item.label || item.title || '';
                        return '';
                    }).filter(Boolean);
                    valuesArea.value = flattened.join('\n');
                }

                if (programsContainer) {
                    const template = programsContainer.querySelector('.repeatable-item');
                    if (template) {
                        const templateHtml = template.outerHTML;
                        programsContainer.innerHTML = '';
                        const items = Array.isArray(data.programs) ? data.programs : [];
                        if (!items.length) {
                            programsContainer.insertAdjacentHTML('beforeend', templateHtml);
                            const last = programsContainer.querySelector('.repeatable-item:last-child');
                            if (last) last.querySelector('.program-color').value = '#800000';
                        } else {
                            items.forEach(program => {
                                const wrapper = document.createElement('div');
                                wrapper.innerHTML = templateHtml;
                                const item = wrapper.firstElementChild;
                                if (!item) return;
                                item.querySelector('.program-title').value = program.title || '';
                                item.querySelector('.program-description').value = program.description || '';
                                item.querySelector('.program-color').value = program.color || '#800000';
                                programsContainer.appendChild(item);
                            });
                        }
                    }
                }

                if (peopleContainer) {
                    const template = peopleContainer.querySelector('.repeatable-item');
                    if (template) {
                        const templateHtml = template.outerHTML;
                        peopleContainer.innerHTML = '';
                        const items = Array.isArray(data.people) ? data.people : [];
                        if (!items.length) {
                            peopleContainer.insertAdjacentHTML('beforeend', templateHtml);
                            const last = peopleContainer.querySelector('.repeatable-item:last-child');
                            if (last) bindAboutCategoryHandlers(last);
                        } else {
                            items.forEach(person => {
                                const wrapper = document.createElement('div');
                                wrapper.innerHTML = templateHtml;
                                const item = wrapper.firstElementChild;
                                if (!item) return;
                                item.querySelector('.person-name').value = person.name || '';
                                const select = item.querySelector('.person-category-select');
                                const input = item.querySelector('.person-category-custom');
                                const currentImage = item.querySelector('.person-current-image');
                                if (select) {
                                    const category = person.category || '';
                                    const known = ['Administration', 'Support Services', 'Student Engagement', 'Operations'];
                                    if (known.includes(category)) {
                                        select.value = category;
                                    } else {
                                        select.value = 'custom';
                                        if (input) input.value = category;
                                    }
                                }
                                if (currentImage) currentImage.value = person.image || '';
                                bindAboutCategoryHandlers(item);
                                peopleContainer.appendChild(item);
                            });
                        }
                    }
                }
            } catch (e) {
                console.error('populateAboutSettings', e);
            }
        })();

        const saveAboutBtn = document.getElementById('saveAboutPageBtn');
        const aboutSaveStatus = document.getElementById('aboutSaveStatus');
        if (saveAboutBtn) {
            saveAboutBtn.addEventListener('click', function(){
                const getAboutValues = () => {
                    const valuesArea = document.getElementById('aboutCoreValues');
                    const raw = valuesArea ? valuesArea.value : '';
                    return raw.split(/\r?\n/).map(v => v.trim()).filter(Boolean);
                };

                const programs = Array.from(document.querySelectorAll('#aboutPrograms .repeatable-item')).map(item => ({
                    title: item.querySelector('.program-title')?.value?.trim() || '',
                    description: item.querySelector('.program-description')?.value?.trim() || '',
                    color: item.querySelector('.program-color')?.value?.trim() || ''
                })).filter(item => item.title || item.description || item.color);

                const people = Array.from(document.querySelectorAll('#aboutPeople .repeatable-item')).map((item, index) => {
                    const name = item.querySelector('.person-name')?.value?.trim() || '';
                    const select = item.querySelector('.person-category-select');
                    const customInput = item.querySelector('.person-category-custom');
                    const currentImage = item.querySelector('.person-current-image');
                    const category = select && select.value === 'custom' ? (customInput?.value?.trim() || '') : (select?.value?.trim() || '');
                    return {
                        name,
                        category,
                        image: currentImage?.value?.trim() || ''
                    };
                }).filter(item => item.name || item.category || item.image);

                const formData = new FormData();
                formData.append('vision', document.getElementById('aboutVision')?.value?.trim() || '');
                formData.append('mission', document.getElementById('aboutMission')?.value?.trim() || '');
                formData.append('coreValues', JSON.stringify(getAboutValues()));
                formData.append('programs', JSON.stringify(programs));
                formData.append('people', JSON.stringify(people));

                document.querySelectorAll('#aboutPeople .repeatable-item').forEach((item, index) => {
                    const fileInput = item.querySelector('.person-image-input');
                    const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : new File([''], '');
                    formData.append('people_images[]', file);
                });

                if (aboutSaveStatus) aboutSaveStatus.textContent = 'Saving...';
                fetch('../includes/api_about_settings.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        if (aboutSaveStatus) aboutSaveStatus.textContent = 'About page settings saved.';
                        alert('About page settings saved.');
                    } else {
                        if (aboutSaveStatus) aboutSaveStatus.textContent = res && res.message ? res.message : 'Could not save changes.';
                        alert(res && res.message ? res.message : 'Could not save about page settings.');
                    }
                }).catch(err => {
                    console.error(err);
                    if (aboutSaveStatus) aboutSaveStatus.textContent = 'Error saving about page settings.';
                    alert('Error saving about page settings.');
                });
            });
        }

        if (aiSubTabList) {
            aiSubTabList.addEventListener('touchstart', function(event) {
                if (!shouldUseSwipe()) return;
                aboutTouchStartX = event.touches[0].clientX;
            }, { passive: true });

            aiSubTabList.addEventListener('touchend', function(event) {
                if (!shouldUseSwipe()) return;
                aboutTouchEndX = event.changedTouches[0].clientX;
                const delta = aboutTouchEndX - aboutTouchStartX;
                if (Math.abs(delta) < 50) return;
                const activeTab = document.querySelector('#ai-management .about-subtab-button.active');
                if (!activeTab) return;
                const tabsArray = Array.from(aiSubTabs);
                const activeIndex = tabsArray.indexOf(activeTab);
                const nextIndex = delta < 0 ? Math.min(activeIndex + 1, tabsArray.length - 1) : Math.max(activeIndex - 1, 0);
                activateAiSubTab(tabsArray[nextIndex].dataset.abouttab);
            }, { passive: true });
        }

        // Save footer content
        const saveFooterBtn = document.getElementById('saveFooterContentBtn');
        if (saveFooterBtn) {
            saveFooterBtn.addEventListener('click', function(){
                const collect = (containerId, mapFn) => {
                    const container = document.getElementById(containerId);
                    if (!container) return [];
                    return Array.from(container.querySelectorAll('.repeatable-item')).map(item=>{
                        const inputs = item.querySelectorAll('input, select');
                        return mapFn(inputs);
                    }).filter(i=>i && (Array.isArray(i)? i.some(Boolean): Object.keys(i).some(k=>Boolean(i[k]))));
                };

            const contacts = collect('contactItems', inputs=>({ label: inputs[0]?.value?.trim()||'', value: inputs[1]?.value?.trim()||'' }));
            const locations = collect('locationItems', inputs=>({ name: inputs[0]?.value?.trim()||'', link: inputs[1]?.value?.trim()||'', address: inputs[2]?.value?.trim()||'' }));
                const social = collect('socialItems', inputs=>{
                    // inputs is a NodeList of select and inputs; find select, custom, and url
                    let sel = null, custom = null, url = null;
                    inputs.forEach(el=>{
                        if (el.tagName && el.tagName.toLowerCase() === 'select') sel = el;
                        else if (el.classList && el.classList.contains('custom-platform')) custom = el;
                        else if (el.type === 'url') url = el;
                    });
                    let platform = '';
                    if (sel) {
                        platform = sel.value === 'others' ? (custom ? custom.value.trim() : '') : sel.value;
                    }
                    return { platform: platform || '', link: url ? url.value.trim() : '' };
                });

                fetch('../includes/api_footer_content.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contacts, locations, social })
                }).then(r=>r.json()).then(res=>{
                    if (res && res.success) {
                        alert('Footer content saved.');
                    } else {
                        alert('Could not save footer content.');
                    }
                }).catch(err=>{ console.error(err); alert('Error saving footer content'); });
            });
        }

        function loadArchivedAlumni() {
            const course = document.getElementById('archive-filter-course') ? document.getElementById('archive-filter-course').value : '';
            const year = document.getElementById('archive-filter-year') ? document.getElementById('archive-filter-year').value : '';
            const params = new URLSearchParams();
            params.append('action', 'get_archived_alumni');
            params.append('course', course);
            params.append('year', year);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('archived-alumni-list');
                if (!listBody) return;
                if (!Array.isArray(data) || data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="6">No archived alumni records found.</td></tr>';
                    return;
                }

                listBody.innerHTML = data.map(alumni => `
                    <tr>
                        <td data-label="Name">${alumni.full_name || 'N/A'}</td>
                        <td data-label="Course">${alumni.course || 'N/A'}</td>
                        <td data-label="Year Graduated">${alumni.graduation_year || 'N/A'}</td>
                        <td data-label="Email">${alumni.email || 'N/A'}</td>
                        <td data-label="Archived On">${alumni.archived_at ? new Date(alumni.archived_at).toLocaleString() : 'N/A'}</td>
                        <td data-label="Action">
                            <button type="button" class="secondary-button small" onclick="restoreArchivedAlumni(${alumni.id})">Restore</button>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading archived alumni:', error);
            });
        }

        function loadArchivedUsers() {
            const course = document.getElementById('user-archive-filter-course') ? document.getElementById('user-archive-filter-course').value : '';
            const year = document.getElementById('user-archive-filter-year') ? document.getElementById('user-archive-filter-year').value : '';
            const params = new URLSearchParams();
            params.append('action', 'get_archived_users');
            params.append('course', course);
            params.append('year', year);

            fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('archived-user-list');
                if (!listBody) return;
                if (!Array.isArray(data) || data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="6">No archived user records found.</td></tr>';
                    return;
                }

                listBody.innerHTML = data.map(user => {
                    const courseText = user.course_year || 'Not assigned';
                    const roleText = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User';
                    return `
                        <tr>
                            <td data-label="Name">${user.full_name || 'N/A'}</td>
                            <td data-label="Role">${roleText}</td>
                            <td data-label="Course / Department">${courseText}</td>
                            <td data-label="Email">${user.email || 'N/A'}</td>
                            <td data-label="Archived On">${user.archived_at ? new Date(user.archived_at).toLocaleString() : 'N/A'}</td>
                            <td data-label="Action">
                                <button type="button" class="secondary-button small" onclick="restoreArchivedUser(${user.id})">Restore</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            })
            .catch(error => {
                console.error('Error loading archived users:', error);
            });
        }

        function restoreArchivedAlumni(alumniId) {
            if (!alumniId) return;
            const params = new URLSearchParams();
            params.append('action', 'restore_archived_alumni');
            params.append('alumni_id', alumniId);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    alert(data.message || 'Alumni restored.');
                    loadArchivedAlumni();
                    if (typeof applyAlumniInfoFilters === 'function') {
                        applyAlumniInfoFilters();
                    }
                } else {
                    alert(data && data.message ? data.message : 'Unable to restore alumni record.');
                }
            })
            .catch(error => {
                console.error('Error restoring archived alumni:', error);
                alert('Error restoring archived alumni.');
            });
        }

        function restoreArchivedUser(userId) {
            if (!userId) return;
            const params = new URLSearchParams();
            params.append('action', 'restore_archived_user');
            params.append('user_id', userId);

            fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    alert(data.message || 'User restored.');
                    loadArchivedUsers();
                } else {
                    alert(data && data.message ? data.message : 'Unable to restore user.');
                }
            })
            .catch(error => {
                console.error('Error restoring archived user:', error);
                alert('Error restoring archived user.');
            });
        }

        const archiveFilterCourse = document.getElementById('archive-filter-course');
        const archiveFilterYear = document.getElementById('archive-filter-year');
        if (archiveFilterCourse) {
            archiveFilterCourse.addEventListener('change', loadArchivedAlumni);
        }
        if (archiveFilterYear) {
            archiveFilterYear.addEventListener('change', loadArchivedAlumni);
        }
        const userArchiveFilterCourse = document.getElementById('user-archive-filter-course');
        const userArchiveFilterYear = document.getElementById('user-archive-filter-year');
        if (userArchiveFilterCourse) {
            userArchiveFilterCourse.addEventListener('change', loadArchivedUsers);
        }
        if (userArchiveFilterYear) {
            userArchiveFilterYear.addEventListener('change', loadArchivedUsers);
        }
        if (document.querySelector('.tab-button[data-tab="alumni-archive"]')) {
            document.querySelector('.tab-button[data-tab="alumni-archive"]').addEventListener('click', function() {
                loadArchivedAlumni();
            });
        }
        if (document.querySelector('.tab-button[data-tab="user-archive"]')) {
            document.querySelector('.tab-button[data-tab="user-archive"]').addEventListener('click', function() {
                loadArchivedUsers();
            });
        }
        document.querySelectorAll('.repeatable-section').forEach(section => {
            section.addEventListener('click', function(event) {
                const removeButton = event.target.closest('.remove-item');
                if (!removeButton) return;
                const item = removeButton.closest('.repeatable-item');
                if (!item) return;
                if (section.children.length > 1) item.remove();
            });
        });

        const carouselInput = document.getElementById('carouselImages');
        const carouselTypeSelect = document.getElementById('carouselLoginType');
        const carouselPreviewTarget = document.getElementById('carouselPreviewTarget');
        const previewArea = document.getElementById('carouselPreview');
        const currentImagesArea = document.getElementById('currentCarouselImages');
        const uploadCarouselBtn = document.getElementById('uploadCarouselBtn');
        const logoInput = document.getElementById('loginLogo');
        const logoPreview = document.getElementById('logoPreview');
        const saveLoginSettingsBtn = document.getElementById('saveLoginSettingsBtn');
        const logoSaveStatus = document.getElementById('logoSaveStatus');

        function getLoginTypeLabel(loginType) {
            switch (loginType) {
                case 'student': return 'Student Login';
                case 'teacher': return 'Teacher Login';
                case 'both': return 'Both Student and Teacher Login';
                default: return 'Unknown Login Target';
            }
        }

        function updateImagePreview(file, container) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const image = document.createElement('img');
                image.src = e.target.result;
                image.alt = file.name;
                container.appendChild(image);
            };
            reader.readAsDataURL(file);
        }

        function renderCurrentImages(images) {
            if (!currentImagesArea) return;
            currentImagesArea.innerHTML = '';
            if (!images.length) {
                currentImagesArea.innerHTML = '<div style="color:#6b7280; padding: 16px; background:#f8fafc; border-radius:8px;">No carousel images uploaded yet for this login page.</div>';
                return;
            }
            images.forEach(image => {
                const wrapper = document.createElement('div');
                wrapper.className = 'repeatable-item';
                wrapper.style.padding = '12px';
                wrapper.style.display = 'flex';
                wrapper.style.flexDirection = 'column';
                wrapper.style.gap = '10px';
                const src = image.image_path.startsWith('/') || image.image_path.startsWith('..') ? image.image_path : '../' + image.image_path;
                const label = getLoginTypeLabel(image.login_type);
                wrapper.innerHTML = `
                    <img src="${src}" alt="Carousel image" style="width:100%;height:110px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <span style="font-size:0.85rem;color:#1f2937;background:#eef2ff;padding:4px 10px;border-radius:999px;border:1px solid #c7d2fe;">${label}</span>
                        <span style="font-size:0.9rem;color:#374151;">Image ID ${image.id}</span>
                        <button type="button" class="remove-item" data-image-id="${image.id}" style="margin-left:auto;background:#f87171;color:#ffffff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;">Delete</button>
                    </div>
                `;
                currentImagesArea.appendChild(wrapper);
            });
        }

        async function fetchCarouselImages(loginType) {
            try {
                const response = await fetch(`../includes/api_carousel_images.php?action=get_images&login_type=${loginType}`);
                const result = await response.json();
                if (result.success) {
                    renderCurrentImages(result.data);
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function uploadCarouselImages() {
            if (!carouselInput || !carouselTypeSelect) return;
            const files = Array.from(carouselInput.files);
            const loginType = carouselTypeSelect.value;
            if (!files.length) {
                alert('Please select image files to upload.');
                return;
            }
            for (const file of files) {
                const formData = new FormData();
                formData.append('action', 'upload_image');
                formData.append('login_type', loginType);
                formData.append('image', file);
                try {
                    const response = await fetch('../includes/api_carousel_images.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!result.success) {
                        alert(result.error || 'Failed to upload image.');
                        return;
                    }
                } catch (error) {
                    console.error(error);
                    alert('Upload failed.');
                    return;
                }
            }
            carouselInput.value = '';
            previewArea.innerHTML = '';
            fetchCarouselImages(loginType);
        }

        async function deleteCarouselImage(imageId, loginType) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_image');
                formData.append('image_id', imageId);
                const response = await fetch('../includes/api_carousel_images.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    fetchCarouselImages(loginType);
                } else {
                    alert(result.error || 'Could not delete image.');
                }
            } catch (error) {
                console.error(error);
                alert('Delete failed.');
            }
        }

        if (carouselInput && previewArea) {
            carouselInput.addEventListener('change', function() {
                previewArea.innerHTML = '';
                Array.from(carouselInput.files).slice(0, 10).forEach(file => updateImagePreview(file, previewArea));
            });
        }

        if (carouselTypeSelect) {
            carouselTypeSelect.addEventListener('change', function() {
                carouselPreviewTarget.textContent = getLoginTypeLabel(carouselTypeSelect.value);
                fetchCarouselImages(carouselTypeSelect.value);
            });
            carouselPreviewTarget.textContent = getLoginTypeLabel(carouselTypeSelect.value);
        }

        if (uploadCarouselBtn) {
            uploadCarouselBtn.addEventListener('click', function() {
                uploadCarouselImages();
            });
        }

        currentImagesArea?.addEventListener('click', function(event) {
            const target = event.target;
            if (target.matches('.remove-item')) {
                const imageId = target.getAttribute('data-image-id');
                if (imageId) {
                    deleteCarouselImage(imageId, carouselTypeSelect.value);
                }
            }
        });

        if (carouselTypeSelect) {
            fetchCarouselImages(carouselTypeSelect.value);
        }

        async function loadCurrentLoginLogo() {
            if (!logoPreview) return;
            try {
                const response = await fetch('../includes/api_login_logo.php?action=get_logo');
                const result = await response.json();
                if (result.success && result.data?.path) {
                    logoPreview.innerHTML = '<img src="' + result.data.path + '" alt="Current login logo">';
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function uploadLoginLogo() {
            if (!logoInput || !logoPreview || !logoSaveStatus) return;
            const file = logoInput.files[0];
            if (!file) {
                alert('Please choose a logo file to upload.');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'upload_logo');
            formData.append('logo', file);
            logoSaveStatus.textContent = 'Uploading logo...';
            try {
                const response = await fetch('../includes/api_login_logo.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Unable to upload logo');
                }
                logoPreview.innerHTML = '<img src="' + result.data.path + '" alt="Current login logo">';
                logoInput.value = '';
                logoSaveStatus.textContent = 'Login logo saved successfully.';
                setTimeout(() => { if (logoSaveStatus) logoSaveStatus.textContent = ''; }, 4000);
            } catch (error) {
                console.error(error);
                logoSaveStatus.textContent = 'Logo upload failed.';
                alert(error.message || 'Logo upload failed.');
            }
        }

        if (logoInput && logoPreview) {
            logoInput.addEventListener('change', function() {
                const file = logoInput.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Logo preview">';
                };
                reader.readAsDataURL(file);
            });
        }

        if (saveLoginSettingsBtn) {
            saveLoginSettingsBtn.addEventListener('click', function() {
                uploadLoginLogo();
            });
        }

        loadCurrentLoginLogo();

        document.querySelectorAll('.ai-category-filters').forEach(function(filterGroup) {
            filterGroup.addEventListener('click', function(event) {
                const button = event.target.closest('[data-category-filter]');
                if (!button) return;

                const selectedCategory = button.dataset.categoryFilter;
                const targetType = filterGroup.dataset.filterTarget;
                filterGroup.querySelectorAll('[data-category-filter]').forEach(function(filterButton) {
                    const isActive = filterButton === button;
                    filterButton.classList.toggle('active', isActive);
                    filterButton.setAttribute('aria-selected', isActive.toString());
                });

                document.querySelectorAll('.ai-entry[data-entry-type="' + targetType + '"]').forEach(function(entry) {
                    const matches = selectedCategory === 'all' || entry.dataset.entryCategory === selectedCategory;
                    entry.hidden = !matches;
                });
            });
        });

        document.querySelectorAll('[data-ai-edit]').forEach(button => {
            button.addEventListener('click', function() {
                const entry = JSON.parse(this.dataset.aiEdit);
                document.getElementById('knowledge_id').value = entry.id || '';
                document.getElementById('knowledge_title').value = entry.title || '';
                document.getElementById('knowledge_category').value = entry.category || 'General';
                document.getElementById('knowledge_content').value = entry.content || '';
                document.getElementById('knowledge_content').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        document.querySelectorAll('[data-faq-edit]').forEach(button => {
            button.addEventListener('click', function() {
                const entry = JSON.parse(this.dataset.faqEdit);
                document.getElementById('faq_id').value = entry.id || '';
                document.getElementById('faq_category').value = entry.category || 'General';
                document.getElementById('faq_question').value = entry.question || '';
                document.getElementById('faq_answer').value = entry.content || '';
                document.getElementById('faq_question').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        document.getElementById('clearKnowledgeForm')?.addEventListener('click', function() {
            document.getElementById('knowledge_id').value = '';
            document.getElementById('knowledge_title').value = '';
            document.getElementById('knowledge_category').value = 'General';
            document.getElementById('knowledge_content').value = '';
        });

        document.getElementById('clearFaqForm')?.addEventListener('click', function() {
            document.getElementById('faq_id').value = '';
            document.getElementById('faq_category').value = 'General';
            document.getElementById('faq_question').value = '';
            document.getElementById('faq_answer').value = '';
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            if (!swipeRefreshSpinner || window.innerWidth > 768) {
                return;
            }

            let touchStartY = 0;
            let isPulling = false;

            document.addEventListener('touchstart', function (event) {
                if (window.scrollY > 0 || event.touches.length !== 1) {
                    return;
                }
                touchStartY = event.touches[0].clientY;
                isPulling = true;
            }, { passive: true });

            document.addEventListener('touchmove', function (event) {
                if (!isPulling) {
                    return;
                }

                const deltaY = event.touches[0].clientY - touchStartY;
                if (deltaY > 90) {
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    isPulling = false;
                }
            }, { passive: true });

            document.addEventListener('touchend', function () {
                isPulling = false;
            }, { passive: true });
        });
    </script>
</body>
</html>
