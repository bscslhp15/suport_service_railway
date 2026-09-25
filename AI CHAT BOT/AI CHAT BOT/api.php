<?php
require_once __DIR__ . '/../../includes/session.php';

// Check if user is logged in without redirecting
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'message' => 'Please log in to use the AI assistant.'
    ]);
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../../includes/ai_management.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the user message
$message = trim($_POST['message'] ?? '');
if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Limit message length
if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit;
}

// Helper functions for advanced fallback system
function parseFAQData() {
    $faqData = [
        'library' => ['faqs' => [], 'followups' => []],
        'clinic' => ['faqs' => [], 'followups' => []],
        'scholarship' => ['faqs' => [], 'followups' => []],
        'guidance' => ['faqs' => [], 'followups' => []],
        'ssc' => ['faqs' => [], 'followups' => []],
        'ssaa' => ['faqs' => [], 'followups' => []],
        'general' => ['faqs' => [], 'followups' => []]
    ];

    foreach (ai_get_knowledge(false) as $entry) {
        $question = trim((string) $entry['question']);
        $answer = trim((string) $entry['content']);
        if ($entry['entry_type'] === 'knowledge') {
            continue;
        }
        if ($question === '' || $answer === '') {
            continue;
        }
        $category = strtolower(trim((string) $entry['category']));
        $module = array_key_exists($category, $faqData) ? $category : 'general';
        $faq = [
            'question' => $question,
            'answer' => $answer
        ];
        $faqData[$module]['faqs'][] = $faq;
        $faqData[$module]['followups'][] = $faq['question'];
    }

    return $faqData;
}

// Build a friendly "I don't have that in my knowledge base" reply, plus a
// short list of real sample questions the user can actually ask — pulled
// from the module's own follow-up list if we know the module, or a mix
// across all modules if we don't. This keeps the user "on track" instead of
// hitting a dead end.
function buildNotFoundResponse($module, $faqData) {
    $samples = [];

    if ($module && !empty($faqData[$module]['followups'])) {
        $pool = $faqData[$module]['followups'];
        shuffle($pool);
        $samples = array_slice($pool, 0, 4);
    } else {
        // No module detected — grab one sample from each module for variety
        foreach ($faqData as $modName => $data) {
            if (!empty($data['followups'])) {
                $samples[] = $data['followups'][array_rand($data['followups'])];
            }
        }
        shuffle($samples);
        $samples = array_slice($samples, 0, 5);
    }

    $intro = $module
        ? "Sorry, I don't have that specific detail in my knowledge base yet for the " . ucfirst($module) . " module."
        : "Sorry, I couldn't find that in my knowledge base — I can only answer questions about PASS College's Library, Clinic, Scholarship, Guidance, SSC, and SSAA services.";

    $response = $intro;
    if (!empty($samples)) {
        $response .= "\n\nHere are some things I *can* help with:\n";
        foreach ($samples as $s) {
            $response .= "- " . $s . "\n";
        }
    } else {
        $response .= " Please tell me which service you need help with (Library, Clinic, Scholarship, Guidance, SSC, or SSAA).";
    }

    return trim($response);
}

function findMatchingFAQ($message, $faqData) {
    $normalize = function ($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $normalized = [];
        foreach ($words as $word) {
            $word = preg_replace('/(ies)$/', 'y', $word);
            $word = preg_replace('/(ing|ed|s)$/', '', $word);
            $normalized[] = $word;
        }
        return $normalized;
    };

    $concepts = [
        'borrow' => ['borrow', 'borrowing', 'loan', 'loans', 'take'],
        'reserve' => ['reserve', 'reservation', 'hold'],
        'book' => ['book', 'copy', 'copies', 'title', 'material'],
        'report' => ['report', 'analytic', 'statistic', 'history'],
        'catalog' => ['catalog', 'search', 'find', 'look'],
        'availability' => ['available', 'availability', 'stock', 'check'],
        'cancel' => ['cancel', 'cancellation', 'remove'],
        'renew' => ['renew', 'extend', 'extension'],
        'qr' => ['qr', 'scan', 'code'],
        'clinic' => ['clinic', 'health', 'medical', 'nurse', 'doctor'],
        'wellness' => ['wellness', 'symptom', 'sleep', 'water', 'mood'],
        'visit' => ['visit', 'appointment', 'consultation', 'checkup'],
        'guidance' => ['guidance', 'counseling', 'counselor', 'advising'],
        'case' => ['case', 'incident', 'concern', 'report'],
        'certificate' => ['certificate', 'good', 'moral'],
        'scholarship' => ['scholarship', 'tdp', 'tes', 'grant', 'financial'],
        'document' => ['document', 'paper', 'requirement', 'checklist', 'submit'],
        'ssc' => ['ssc', 'council', 'election', 'candidate', 'campaign'],
        'event' => ['event', 'activity', 'activities', 'program', 'volunteer'],
        'announcement' => ['announcement', 'update', 'news'],
        'ssaa' => ['ssaa', 'alumni', 'graduate', 'graduation', 'career'],
        'employment' => ['employment', 'employed', 'job', 'work', 'employer'],
        'progression' => ['progression', 'progress', 'milestone', 'status'],
    ];
    $stopWords = ['how', 'what', 'where', 'when', 'why', 'can', 'do', 'does', 'the', 'a', 'an', 'i', 'to', 'my', 'is', 'are', 'from', 'for', 'please'];
    $messageWords = array_values(array_diff($normalize($message), $stopWords));
    $messageConcepts = [];
    foreach ($concepts as $concept => $aliases) {
        if (array_intersect($aliases, $messageWords)) {
            $messageConcepts[$concept] = true;
        }
    }

    $bestMatch = null;
    $bestScore = 0;
    foreach ($faqData as $module => $data) {
        foreach ($data['faqs'] as $faq) {
            $questionWords = array_values(array_diff($normalize($faq['question']), $stopWords));
            $questionConcepts = [];
            foreach ($concepts as $concept => $aliases) {
                if (array_intersect($aliases, $questionWords)) {
                    $questionConcepts[$concept] = true;
                }
            }

            $sharedConcepts = count(array_intersect_key($messageConcepts, $questionConcepts));
            $sharedWords = count(array_intersect($messageWords, $questionWords));
            $score = ($sharedConcepts * 3) + ($sharedWords * 0.5);
            if ($sharedConcepts > 0 && count(array_diff_key($messageConcepts, $questionConcepts)) === 0) {
                $score += 1;
            }

            if ($score > $bestScore && ($sharedConcepts >= 2 || $sharedWords >= 2)) {
                $bestScore = $score;
                $bestMatch = [
                    'module' => $module,
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'followups' => $data['followups'] ?? []
                ];
            }
        }
    }

    return $bestMatch;
}

function detectModule($message) {
    $modules = [
        'library' => ['library', 'book', 'borrow', 'reserve', 'catalog', 'qr'],
        'clinic' => ['clinic', 'health', 'medical', 'nurse', 'doctor', 'symptom'],
        'scholarship' => ['scholarship', 'tdp', 'tes', 'financial aid', 'grant'],
        'guidance' => ['guidance', 'counseling', 'counselor', 'advising', 'incident'],
        'ssc' => ['ssc', 'student council', 'election', 'candidate', 'activity'],
        'ssaa' => ['alumni', 'ssaa', 'graduate', 'career', 'employment']
    ];

    foreach ($modules as $module => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return $module;
            }
        }
    }
    return null;
}

function getModuleGeneralResponse($module, $faqData) {
    $responses = [
          'library' => "I'd be happy to help you with library services! PASS College has a comprehensive library system where you can reserve and borrow books, access digital resources, check your borrowing history, and make library visit reservations. You can access the <a href='/dashboard/library_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Library module</a> from your dashboard.",
          'clinic' => "PASS College Clinic provides comprehensive health services including daily health tracking, medical consultations, clinic visit records, and health announcements. Access the <a href='/dashboard/clinic_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Clinic module</a> from your dashboard.",
          'scholarship' => "For scholarship information, PASS College offers scholarship announcements, application tracking, and document checklist management. Check the <a href='/dashboard/scholarship/scholarship_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Scholarship module</a> on your dashboard. Note that scholarship applications involve physical document verification.",
          'guidance' => "For guidance and counseling services, PASS College offers personal counseling appointments, academic advising, career guidance, and incident reporting. You can access the <a href='/dashboard/guidance_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Guidance module</a> from your dashboard.",
          'ssc' => "The Supreme Student Council (SSC) handles student activities and events, leadership programs, student initiatives, and election management. Visit the <a href='/dashboard/ssc/' style='color: #2563eb; text-decoration: underline;'>SSC module</a> on your dashboard.",
          'ssaa' => "The Student Support & Alumni Affairs (SSAA) provides alumni network connections, career development support, graduate employment tracking, and long-term student lifecycle support. Access the <a href='/dashboard/alumni.php' style='color: #2563eb; text-decoration: underline;'>Alumni module</a> from your dashboard."
    ];

    $response = $responses[$module] ?? "I'm here to help with PASS College services. Could you please specify what you'd like to know about?";
    return $response;
}

// Helper functions for system data queries
function detectSystemQuery($message) {
    $lowerMessage = strtolower($message);

    // Clearance status queries
    if (strpos($lowerMessage, 'clearance') !== false &&
        (strpos($lowerMessage, 'status') !== false ||
         strpos($lowerMessage, 'blocked') !== false ||
         strpos($lowerMessage, 'why') !== false ||
         strpos($lowerMessage, 'cant') !== false ||
         strpos($lowerMessage, "can't") !== false)) {
        return 'clearance_status';
    }

    // Fines queries
    if (strpos($lowerMessage, 'fine') !== false ||
        strpos($lowerMessage, 'fines') !== false ||
        strpos($lowerMessage, 'pay') !== false ||
        strpos($lowerMessage, 'payment') !== false) {
        return 'fines';
    }

    // Reservation limit queries
    if ((strpos($lowerMessage, 'reserve') !== false ||
         strpos($lowerMessage, 'reservation') !== false ||
         strpos($lowerMessage, 'book') !== false) &&
        (strpos($lowerMessage, 'cant') !== false ||
         strpos($lowerMessage, "can't") !== false ||
         strpos($lowerMessage, 'limit') !== false ||
         strpos($lowerMessage, 'maximum') !== false)) {
        return 'reservation_limit';
    }

    // E-resource access queries
    if ((strpos($lowerMessage, 'e-resource') !== false ||
         strpos($lowerMessage, 'e resource') !== false ||
         strpos($lowerMessage, 'module') !== false) &&
        (strpos($lowerMessage, 'cant') !== false ||
         strpos($lowerMessage, "can't") !== false ||
         strpos($lowerMessage, 'see') !== false ||
         strpos($lowerMessage, 'access') !== false ||
         strpos($lowerMessage, 'available') !== false)) {
        return 'eresource_access';
    }

    return null;
}

function getSystemDataResponse($queryType, $userId) {
    require_once __DIR__ . '/../../includes/functions.php';

    switch ($queryType) {
        case 'clearance_status':
            $clearanceStatus = can_student_get_clearance($userId);
            if ($clearanceStatus['can_clear']) {
                return "Your clearance status is **Eligible** ✓. You have no outstanding fines or overdue books. You can request your clearance from the Library module at: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Request clearance</a>";
                            return "Your clearance status is **Eligible** ✓. You have no outstanding fines or overdue books. You can request your clearance from the Library module at: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Request clearance</a>";
            } else {
                $response = "Your clearance status is **Blocked** ✗. " . $clearanceStatus['reason'] . ".\n\n";
                if ($clearanceStatus['unpaid_fines'] > 0) {
                    $response .= "- **Unpaid fines**: ₱" . number_format($clearanceStatus['unpaid_fines'], 2) . "\n";
                }
                if ($clearanceStatus['overdue_count'] > 0) {
                    $response .= "- **Overdue books**: " . $clearanceStatus['overdue_count'] . " item(s)\n";
                }
                $response .= "\nPlease settle these issues before requesting clearance. Visit: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Clearance page</a>";
                                $response .= "\nPlease settle these issues before requesting clearance. Visit: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Clearance page</a>";
                return $response;
            }

        case 'fines':
            $fines = array_values(array_filter(
                get_student_fines($userId),
                static fn(array $fine): bool => (int)($fine['paid'] ?? 0) === 0
            ));
            if (empty($fines)) {
                return "You have **no unpaid fines** ✓. Your library account is in good standing!";
            } else {
                $totalFines = array_sum(array_column($fines, 'amount'));
                $response = "You have **₱" . number_format($totalFines, 2) . "** in unpaid fines:\n\n";
                foreach ($fines as $fine) {
                    $response .= "- **" . $fine['description'] . "**: ₱" . number_format($fine['amount'], 2) . "\n";
                }
                $response .= "\nPlease pay these fines at the library to clear your account. Visit: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Clearance page</a>";
                                $response .= "\nPlease pay these fines at the library to clear your account. Visit: <a href='/dashboard/clearance.php' style='color: #2563eb; text-decoration: underline;'>Clearance page</a>";
                return $response;
            }

        case 'reservation_limit':
            $activeReservations = get_user_reservations($userId);
            $activeCount = count(array_filter($activeReservations, fn($r) => $r['status'] === 'Active'));

            // Assuming a limit of 3 active reservations per user
            $maxReservations = 3;

            if ($activeCount >= $maxReservations) {
                return "You currently have **" . $activeCount . "** active reservations, which is the maximum allowed (" . $maxReservations . "). You cannot make new reservations until some are fulfilled or expire. Check your reservations at: <a href='/dashboard/library_dashboard.php' style='color: #2563eb; text-decoration: underline;'>My Library Reservations</a>";
                            return "You currently have **" . $activeCount . "** active reservations, which is the maximum allowed (" . $maxReservations . "). You cannot make new reservations until some are fulfilled or expire. Check your reservations at: <a href='/dashboard/library_dashboard.php' style='color: #2563eb; text-decoration: underline;'>My Library Reservations</a>";
            } else {
                return "You have **" . $activeCount . "** active reservations out of a maximum of " . $maxReservations . ". You can still make new reservations. Visit the Library module at: <a href='/dashboard/library_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Library Dashboard</a>";
                            return "You have **" . $activeCount . "** active reservations out of a maximum of " . $maxReservations . ". You can still make new reservations. Visit the Library module at: <a href='/dashboard/library_dashboard.php' style='color: #2563eb; text-decoration: underline;'>Library Dashboard</a>";
            }

        case 'eresource_access':
            $user = find_user_by_id($userId);
            if (!$user) {
                return "Unable to verify your account. Please contact support.";
            }

            $userCourse = get_user_course_category($userId);
            if (!$userCourse) {
                return "Your course information is not available. E-resources are filtered by course. Please update your profile or contact the library staff.";
            }

            $resources = get_library_resources_for_user($userId, true);
            if (empty($resources)) {
                return "No e-resources are currently available for your course (**" . $userCourse . "**). Resources are restricted to your specific course materials. Contact the library staff if you believe this is an error.";
            } else {
                return "You have access to **" . count($resources) . "** e-resources for your course (**" . $userCourse . "**). If you're not seeing certain modules, they may be restricted to other courses or not yet uploaded. Visit the E-Resources section at: <a href='/dashboard/library_e-resources.php' style='color: #2563eb; text-decoration: underline;'>E-Resources</a>";
                            return "You have access to **" . count($resources) . "** e-resources for your course (**" . $userCourse . "**). If you're not seeing certain modules, they may be restricted to other courses or not yet uploaded. Visit the E-Resources section at: <a href='/dashboard/library_e-resources.php' style='color: #2563eb; text-decoration: underline;'>E-Resources</a>";
            }

        default:
            return null;
    }
}

// Prepare the Gemini API request using structured system + user content
$AI_SERVICE_CONTEXT = ai_get_knowledge_context();
$systemMessage = ai_get_persona_prompt(SYSTEM_PROMPT);
$userMessage = "Knowledge base (this is the ONLY information you are allowed to use):\n\n"
    . $AI_SERVICE_CONTEXT
    . "\n\nUser Query: " . $message
    . "\n\nRemember: if the knowledge base above does not answer this, reply with exactly " . NOT_IN_KB_MARKER . " and nothing else.";

$data = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => $systemMessage
                ]
            ]
        ],
        [
            'parts' => [
                [
                    'text' => $userMessage
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.4,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024,
    ]
];

// Runs the rule-based knowledge-base lookup (module detection, phrase
// overrides, FAQ keyword matching). Used both as the primary answer path
// when Gemini is disabled/unavailable, and as the source of real sample
// questions whenever nothing — AI or rules — can answer the query.
function getLocalFallbackResponse($message, $message_lower) {
    if (preg_match('/\b(who are you|what are you|your name|tell me about yourself)\b/i', $message_lower)) {
        return "I'm the PASS College AI Assistant. I help students and teachers with the Library, Clinic, Scholarship, Guidance, SSC, and SSAA support services using the information available in this system.";
    }

    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)[!,. ]*$/i', trim($message_lower))) {
        return "Hello! I'm the PASS College AI Assistant. What support service do you need help with?";
    }

    $faqData = parseFAQData();

    $matchedFAQ = findMatchingFAQ($message_lower, $faqData);
    if ($matchedFAQ) {
        return $matchedFAQ['answer'];
    }

    // Do not guess from a module name alone. Offer verified questions instead.
    $module = detectModule($message_lower);
    return buildNotFoundResponse($module, $faqData);
}

// Keep answers grounded in the verified local knowledge base. This prevents
// the external model from inventing an answer when the FAQ has no match.
$forceLocalOnly = true;

$message_lower = strtolower($message);

// First, check for system-specific queries that need live user/account data —
// these can never be answered by the KB text alone (clearance, fines, etc.)
$systemQuery = detectSystemQuery($message);
if ($systemQuery) {
    $userId = $_SESSION['user_id'] ?? null;
    $assistantMessage = $userId
        ? getSystemDataResponse($systemQuery, $userId)
        : "Please log in to access your personal account information.";

    echo json_encode(['success' => true, 'message' => $assistantMessage]);
    exit;
}

// Use the complete local FAQ answer for known questions so a partial AI
// response cannot replace a verified step-by-step answer.
$faqData = parseFAQData();
$matchedFAQ = findMatchingFAQ($message_lower, $faqData);
if ($matchedFAQ) {
    echo json_encode([
        'success' => true,
        'message' => $matchedFAQ['answer']
    ]);
    exit;
}

$assistantMessage = null;

if (!$forceLocalOnly && defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'PUT_YOUR_NEW_KEY_HERE') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Gemini API Curl Error: ' . $error);
    } elseif ($httpCode !== 200) {
        error_log('Gemini API HTTP Error: ' . $httpCode . ' Response: ' . $response);
    } else {
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text !== null) {
            $text = trim($text);
            if (strpos($text, NOT_IN_KB_MARKER) !== false) {
                // AI itself says this isn't covered by the knowledge base —
                // hand off to the local matcher just to pick good sample
                // questions (module detection), then build the honest reply.
                $faqData = parseFAQData();
                $module = detectModule($message_lower);
                $assistantMessage = buildNotFoundResponse($module, $faqData);
            } else {
                $assistantMessage = $text;
            }
        } else {
            error_log('Gemini API Invalid Response: ' . $response);
        }
    }
}

// If the AI path wasn't used, failed, or returned nothing usable — fall back
// to the rule-based knowledge-base matcher so the user always gets an answer.
if ($assistantMessage === null) {
    $assistantMessage = getLocalFallbackResponse($message, $message_lower);
}

echo json_encode([
    'success' => true,
    'message' => $assistantMessage
]);
exit;
?>
