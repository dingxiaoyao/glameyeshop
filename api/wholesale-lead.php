<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$company = trim($_POST['company'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$company || mb_strlen($company) > 200) {
    sendJson(['error' => 'Invalid company'], 422);
}
if (!$contact || mb_strlen($contact) > 100) {
    sendJson(['error' => 'Invalid contact'], 422);
}
if (!$email) {
    sendJson(['error' => 'Invalid email'], 422);
}

try {
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO wholesale_leads (company, contact, email, created_at)
         VALUES (:company, :contact, :email, NOW())'
    );
    $stmt->execute([
        ':company' => $company,
        ':contact' => $contact,
        ':email' => $email,
    ]);
    sendJson(['success' => true]);
} catch (PDOException $exception) {
    sendError('Failed to submit lead', 500, $exception);
}
