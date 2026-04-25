<?php
require_once __DIR__ . '/config.php';

// ============================================================
// 邮件发送（占位）
// 真实集成建议：
//   方案 A: PHPMailer + SMTP (composer require phpmailer/phpmailer)
//   方案 B: 调 SendGrid/Resend HTTP API (curl)
//   方案 C: Linux 自带 mail() 函数（需配置 sendmail/postfix）
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();
$to      = filter_var($input['to'] ?? '', FILTER_VALIDATE_EMAIL);
$subject = trim((string)($input['subject'] ?? ''));
$body    = (string)($input['body'] ?? '');

if (!$to || !$subject || !$body) sendJson(['error' => 'to/subject/body required'], 422);

// TODO: 接入真实邮件服务
// $sent = mail($to, $subject, $body, "From: noreply@glameyeshop.com\r\nContent-Type: text/html; charset=utf-8");

error_log("[GlamEye] Email queued to=$to subject=$subject");
sendJson(['success' => true, 'queued' => true, 'note' => 'Email service not yet configured']);
