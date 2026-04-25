<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();
$company = trim((string)($input['company'] ?? ''));
$contact = trim((string)($input['contact'] ?? $input['contact_name'] ?? ''));
$email   = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone   = trim((string)($input['phone'] ?? ''));
$message = trim((string)($input['message'] ?? ''));

if (!$company || mb_strlen($company) > 200) sendJson(['error' => 'Invalid company'], 422);
if (!$contact || mb_strlen($contact) > 100) sendJson(['error' => 'Invalid contact'], 422);
if (!$email)                                sendJson(['error' => 'Invalid email'], 422);
if (mb_strlen($phone) > 64)                 sendJson(['error' => 'Invalid phone'], 422);
if (mb_strlen($message) > 2000)             sendJson(['error' => 'Message too long'], 422);

try {
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO wholesale_leads (company, contact, email, phone, message, status, created_at)
         VALUES (:company, :contact, :email, :phone, :message, :status, NOW())'
    );
    $stmt->execute([
        ':company' => $company, ':contact' => $contact, ':email' => $email,
        ':phone'   => $phone,   ':message' => $message, ':status' => 'new',
    ]);
    sendJson(['success' => true]);
} catch (PDOException $e) {
    sendError('Failed to submit lead', 500, $e);
}
