<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $stmt = $db->query(
        'SELECT id, company, contact, email, phone, message, status, created_at
         FROM wholesale_leads ORDER BY created_at DESC LIMIT 200'
    );
    sendJson(['leads' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
