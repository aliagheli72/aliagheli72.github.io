<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

$config = require __DIR__ . '/config.php';
$formType = $GLOBALS['NTF_FORM_TYPE'] ?? 'contact';
$required = $GLOBALS['NTF_REQUIRED_FIELDS'] ?? ['fullName', 'email'];

if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Honeypot hit: return success to avoid helping bots tune retries.
    respond(true, 'Thank you.');
}

$fields = [
    'fullName' => clean('fullName', 160),
    'contactName' => clean('contactName', 160),
    'email' => clean('email', 220),
    'organization' => clean('organization', 220),
    'interest' => clean('interest', 160),
    'topic' => clean('topic', 220),
    'titleRole' => clean('titleRole', 220),
    'professionalBio' => clean('professionalBio', 5000),
    'message' => clean('message', 5000),
];

foreach ($required as $field) {
    if (($fields[$field] ?? '') === '') {
        respond(false, fieldLabel($field) . ' is required.', 422);
    }
}

if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, 'A valid email is required.', 422);
}

$secret = trim((string)($config['turnstile_secret'] ?? ''));
if ($secret !== '' && !verifyTurnstile($secret, (string)($_POST['cf-turnstile-response'] ?? ''))) {
    respond(false, 'Verification failed. Please try again.', 422);
}

$fullName = $fields['fullName'] !== '' ? $fields['fullName'] : $fields['contactName'];
$payload = [
    'formType' => $formType,
    'fullName' => $fullName,
    'contactName' => $fields['contactName'],
    'email' => $fields['email'],
    'organization' => $fields['organization'],
    'interest' => $fields['interest'],
    'topic' => $fields['topic'],
    'titleRole' => $fields['titleRole'],
    'professionalBio' => $fields['professionalBio'],
    'message' => $fields['message'],
];

try {
    $pdo = db($config['db']);
    $stmt = $pdo->prepare(
        'INSERT INTO ntf_form_submissions
        (form_type, full_name, email, organization, interest, topic, title_role, professional_bio, message, payload, ip_address, user_agent)
        VALUES
        (:form_type, :full_name, :email, :organization, :interest, :topic, :title_role, :professional_bio, :message, :payload, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':form_type' => $formType,
        ':full_name' => nullable($fullName),
        ':email' => $fields['email'],
        ':organization' => nullable($fields['organization']),
        ':interest' => nullable($fields['interest']),
        ':topic' => nullable($fields['topic']),
        ':title_role' => nullable($fields['titleRole']),
        ':professional_bio' => nullable($fields['professionalBio']),
        ':message' => nullable($fields['message']),
        ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    if (!empty($config['mail']['enabled'])) {
        sendNotification($config['mail'], $formType, $payload);
    }

    respond(true, 'Thank you. Your submission was received.');
} catch (Throwable $e) {
    error_log('[NTF form] ' . $e->getMessage());
    respond(false, 'Something went wrong. Please try again later.', 500);
}

function clean(string $key, int $max): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function nullable(string $value): ?string
{
    return $value === '' ? null : $value;
}

function db(array $db): PDO
{
    $charset = $db['charset'] ?? 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $charset);
    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function verifyTurnstile(string $secret, string $token): bool
{
    if ($token === '') return false;
    $post = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $post,
            'timeout' => 8,
        ],
    ]);
    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($raw === false) return false;
    $json = json_decode($raw, true);
    return (bool)($json['success'] ?? false);
}

function sendNotification(array $mail, string $formType, array $payload): void
{
    $to = (string)($mail['to'] ?? '');
    $from = (string)($mail['from'] ?? '');
    if ($to === '' || $from === '') return;

    $subject = 'NeuroTech Frontiers: new ' . ucfirst($formType) . ' submission';
    $lines = [];
    foreach ($payload as $key => $value) {
        if ($value === '' || $value === null) continue;
        $lines[] = humanize($key) . ': ' . $value;
    }
    $body = implode("\n", $lines) . "\n\nSubmitted from neurotech-frontiers.com";
    $fromName = trim((string)($mail['from_name'] ?? 'NeuroTech Frontiers Website'));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . encodeHeader($fromName) . ' <' . $from . '>',
        'Reply-To: ' . $payload['email'],
    ];
    @mail($to, $subject, $body, implode("\r\n", $headers));
}

function humanize(string $key): string
{
    $map = [
        'formType' => 'Form type',
        'fullName' => 'Full name',
        'contactName' => 'Contact name',
        'titleRole' => 'Title / Role',
        'professionalBio' => 'Professional bio',
    ];
    return $map[$key] ?? ucfirst((string)preg_replace('/(?<!^)[A-Z]/', ' $0', $key));
}

function encodeHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function fieldLabel(string $field): string
{
    $labels = [
        'fullName' => 'Full name',
        'contactName' => 'Contact name',
        'email' => 'Email',
        'message' => 'Message',
    ];
    return $labels[$field] ?? $field;
}

function respond(bool $ok, string $message = '', int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
