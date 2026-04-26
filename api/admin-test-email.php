<?php
// Admin: 用当前 SMTP/mail 配置,给 admin_email 发一封测试邮件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/mailer.php';
requireAdminAuth();

$cfg = Mailer::loadConfig();
$to = $cfg['admin_email'] ?: '';
if (!$to) sendJson(['ok' => false, 'error' => 'admin_email is empty — set it first.'], 422);

$html = '<!DOCTYPE html><html><body style="font-family:Inter,Arial,sans-serif;padding:24px;max-width:560px;margin:0 auto;color:#222">
  <h2 style="color:#a8843a">✓ Email is working</h2>
  <p>If you can read this, your GlamEye support email pipeline is configured correctly.</p>
  <p>Mode: <strong>' . htmlspecialchars($cfg['smtp_host'] ? 'SMTP via ' . $cfg['smtp_host'] : 'mail() function') . '</strong></p>
  <p>From: <code>' . htmlspecialchars($cfg['email_from_address']) . '</code></p>
  <p style="color:#888;font-size:.85em;margin-top:30px">Sent from GlamEye admin · ' . date('c') . '</p>
</body></html>';

$res = Mailer::send($to, 'Admin', '[GlamEye] Test email — pipeline OK', $html);
sendJson([
    'ok' => $res['ok'],
    'mode' => $res['mode'],
    'error' => $res['error'] ?? null,
    'sent_to' => $to,
]);
