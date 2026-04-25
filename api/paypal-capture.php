<?php
require_once __DIR__ . '/config.php';

// PayPal capture webhook（占位）
// 真实集成需校验 PayPal webhook 签名，此处先 stub
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// TODO: 校验 PayPal-Auth-Algo / PayPal-Transmission-Sig 头
// TODO: 解析 event，UPDATE orders SET status='paid' WHERE id=...

$payload = file_get_contents('php://input');
error_log('[GlamEye] PayPal capture received: ' . substr($payload, 0, 200));

sendJson(['received' => true]);
