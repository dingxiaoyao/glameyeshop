<?php
// 验证 promo code (P1#13)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$in = readInput();
$code     = strtoupper(trim((string)($in['code'] ?? '')));
$subtotal = floatval($in['subtotal'] ?? 0);

if ($code === '' || mb_strlen($code) > 64) {
    sendJson(['valid' => false, 'message' => 'Please enter a code']);
}
if ($subtotal <= 0) {
    sendJson(['valid' => false, 'message' => 'Add items to your cart before applying a code']);
}

$bucket = 'promo:' . rateLimitClientIp();
rateLimitGuard($bucket, 30, 3600, 'Too many promo code attempts');

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, code, type, value, min_subtotal, max_uses, used_count, expires_at, is_active
         FROM discount_codes WHERE code = :c LIMIT 1'
    );
    $stmt->execute([':c' => $code]);
    $row = $stmt->fetch();

    if (!$row || intval($row['is_active']) !== 1) {
        rateLimitFail($bucket);
        sendJson(['valid' => false, 'message' => 'This code is invalid or expired']);
    }
    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
        sendJson(['valid' => false, 'message' => 'This code has expired']);
    }
    if ($row['max_uses'] !== null && intval($row['used_count']) >= intval($row['max_uses'])) {
        sendJson(['valid' => false, 'message' => 'This code has reached its usage limit']);
    }
    if ($subtotal < floatval($row['min_subtotal'])) {
        $needed = number_format(floatval($row['min_subtotal']), 2);
        sendJson(['valid' => false, 'message' => "Minimum order \$$needed required for this code"]);
    }

    $type  = $row['type'];
    $value = floatval($row['value']);
    if ($type === 'percent') {
        $discount = round($subtotal * ($value / 100), 2);
    } else {
        $discount = min($value, $subtotal);
    }
    $discount = round($discount, 2);
    $newSubtotal = round($subtotal - $discount, 2);

    sendJson([
        'valid'        => true,
        'message'      => $type === 'percent'
            ? sprintf('%s%% off applied — you save \$%.2f', $value, $discount)
            : sprintf('\$%.2f off applied', $discount),
        'code'         => $code,
        'type'         => $type,
        'value'        => $value,
        'discount'     => $discount,
        'new_subtotal' => $newSubtotal,
    ]);
} catch (PDOException $e) {
    sendError('Validation failed', 500, $e);
}
