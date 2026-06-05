<?php
// ============================================================
// 邮件模板 — 简洁 inline HTML(品牌一致 · 大多邮件客户端兼容)
// ============================================================

class EmailTemplates
{
    /** 通用包装:header (logo) + content + footer (法务 + 退订链接) */
    private static function wrap(string $contentHtml, string $unsubscribeUrl = ''): string
    {
        $baseUrl = 'https://glameyeshop.com';
        try {
            $cfg = Mailer::loadConfig();
            $baseUrl = rtrim($cfg['site_base_url'] ?? 'https://glameyeshop.com', '/');
        } catch (Throwable $e) {}

        $unsubHtml = $unsubscribeUrl
            ? '<p style="margin:0;padding-top:8px;">You are receiving this because you have a GlamEye account or recent order.<br><a href="' . htmlspecialchars($unsubscribeUrl) . '" style="color:#9a8062;">Unsubscribe</a> from marketing emails.</p>'
            : '<p style="margin:0;padding-top:8px;">You are receiving this because you have a GlamEye account or recent order.</p>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GlamEye</title></head>
<body style="margin:0;padding:0;background:#f6f4ee;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;color:#2a2a2a;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f4ee;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5dfd2;">
      <tr><td style="padding:32px 40px 16px;text-align:center;">
        <a href="' . $baseUrl . '" style="text-decoration:none;color:#1a1a1a;font-family:Georgia,serif;font-size:24px;letter-spacing:2px;font-weight:600;">GlamEye</a>
      </td></tr>
      <tr><td style="padding:8px 40px 40px;font-size:15px;line-height:1.7;color:#2a2a2a;">' . $contentHtml . '</td></tr>
      <tr><td style="padding:24px 40px;border-top:1px solid #ede7d8;background:#fafaf6;font-size:12px;line-height:1.6;color:#7a7468;text-align:center;">
        ' . $unsubHtml . '
        <p style="margin:8px 0 0;">
          <a href="' . $baseUrl . '/terms.html" style="color:#7a7468;">Terms</a> ·
          <a href="' . $baseUrl . '/privacy.html" style="color:#7a7468;">Privacy</a> ·
          <a href="' . $baseUrl . '/refund.html" style="color:#7a7468;">Refunds</a> ·
          <a href="' . $baseUrl . '/shipping.html" style="color:#7a7468;">Shipping</a>
        </p>
        <p style="margin:8px 0 0;">© 2026 GlamEye · Ealdcrest Capital LLC · New Jersey 07901, USA</p>
      </td></tr>
    </table>
  </td></tr>
</table></body></html>';
    }

    /** 订单确认邮件 */
    public static function orderConfirmation(array $order, array $items, string $lookupToken): array
    {
        $cfg = Mailer::loadConfig();
        $base = rtrim($cfg['site_base_url'] ?? 'https://glameyeshop.com', '/');
        $statusUrl = $base . '/order-success.html?order_id=' . intval($order['id']) . '&lt=' . urlencode($lookupToken);

        $itemRows = '';
        foreach ($items as $it) {
            $itemRows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #ede7d8;">'
                . htmlspecialchars($it['product_name']) . ' &times; ' . intval($it['quantity'])
                . '</td><td style="padding:8px 0;border-bottom:1px solid #ede7d8;text-align:right;">$'
                . number_format(floatval($it['line_total']), 2) . '</td></tr>';
        }

        $subtotal = number_format(floatval($order['subtotal'] ?? 0), 2);
        $shipping = floatval($order['shipping'] ?? 0);
        $tax      = floatval($order['tax'] ?? 0);
        $total    = number_format(floatval($order['amount'] ?? 0), 2);
        $name     = htmlspecialchars($order['customer_name'] ?? 'there');
        $addr     = htmlspecialchars(($order['customer_name'] ?? '') . ' · ' . ($order['address_line'] ?? '') . ', ' . ($order['city'] ?? '') . ', ' . ($order['state'] ?? '') . ' ' . ($order['postal_code'] ?? ''));

        $shipLine = ($shipping > 0) ? '$' . number_format($shipping, 2) : 'FREE';
        $taxLine  = ($tax > 0) ? '<tr><td style="padding:4px 0;">Tax</td><td style="padding:4px 0;text-align:right;">$' . number_format($tax, 2) . '</td></tr>' : '';

        $content = '<h1 style="font-family:Georgia,serif;font-size:22px;font-weight:500;color:#1a1a1a;margin:0 0 16px;">Thanks, ' . $name . ' — order received! ✨</h1>
<p>Your order <strong>#' . intval($order['id']) . '</strong> is in our system. We will process and ship within <strong>1–2 business days</strong>, and you will get a tracking email when it leaves our warehouse.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0 8px;border-top:2px solid #1a1a1a;padding-top:12px;">'
        . $itemRows
        . '<tr><td style="padding:8px 0;">Subtotal</td><td style="padding:8px 0;text-align:right;">$' . $subtotal . '</td></tr>'
        . '<tr><td style="padding:4px 0;">Shipping</td><td style="padding:4px 0;text-align:right;">' . $shipLine . '</td></tr>'
        . $taxLine
        . '<tr><td style="padding:12px 0;border-top:1px solid #ede7d8;font-weight:600;font-size:17px;">Total</td><td style="padding:12px 0;border-top:1px solid #ede7d8;font-weight:600;font-size:17px;text-align:right;">$' . $total . ' USD</td></tr>
</table>
<p style="text-align:center;margin:32px 0 16px;">
  <a href="' . $statusUrl . '" style="display:inline-block;background:#1a1a1a;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:500;font-size:14px;letter-spacing:1px;">View Order Status &rarr;</a>
</p>
<p style="font-size:13px;color:#7a7468;margin:24px 0 0;">Shipping to: ' . $addr . '</p>
<p style="font-size:13px;color:#7a7468;margin:6px 0 0;">Questions? Reply to this email or contact <a href="mailto:support@glameyeshop.com" style="color:#9a8062;">support@glameyeshop.com</a></p>';

        return [
            'subject' => 'Order #' . intval($order['id']) . ' confirmed — GlamEye',
            'html'    => self::wrap($content),
        ];
    }

    /** 密码重置请求邮件 — 1 小时失效 */
    public static function passwordReset(string $email, string $firstName, string $resetUrl): array
    {
        $name = htmlspecialchars($firstName ?: 'there');
        $emailEsc = htmlspecialchars($email);
        $content = '<h1 style="font-family:Georgia,serif;font-size:22px;font-weight:500;color:#1a1a1a;margin:0 0 16px;">Reset your password</h1>
<p>Hi ' . $name . ' — we received a request to reset the password for <strong>' . $emailEsc . '</strong>.</p>
<p style="text-align:center;margin:32px 0;">
  <a href="' . $resetUrl . '" style="display:inline-block;background:#1a1a1a;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:500;font-size:14px;letter-spacing:1px;">Reset password &rarr;</a>
</p>
<p style="font-size:13px;color:#7a7468;">This link expires in <strong>1 hour</strong>. If you did not request a reset, you can safely ignore this email — your password will not change.</p>
<p style="font-size:13px;color:#7a7468;margin-top:16px;">Trouble clicking? Copy this link into your browser:<br><code style="background:#fafaf6;padding:4px 8px;border-radius:4px;font-size:11px;word-break:break-all;">' . $resetUrl . '</code></p>';
        return [
            'subject' => 'Reset your GlamEye password',
            'html'    => self::wrap($content),
        ];
    }

    /** 邮箱验证邮件 — 48 小时失效 */
    public static function emailVerification(string $email, string $firstName, string $verifyUrl): array
    {
        $name = htmlspecialchars($firstName ?: 'there');
        $content = '<h1 style="font-family:Georgia,serif;font-size:22px;font-weight:500;color:#1a1a1a;margin:0 0 16px;">Welcome to GlamEye, ' . $name . '!</h1>
<p>Thanks for joining. To finish setting up your account and unlock features like order history and reviews, please verify your email address.</p>
<p style="text-align:center;margin:32px 0;">
  <a href="' . $verifyUrl . '" style="display:inline-block;background:#b8924e;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:500;font-size:14px;letter-spacing:1px;">Verify email &rarr;</a>
</p>
<p style="font-size:13px;color:#7a7468;">This link expires in 48 hours. If you didn\'t create a GlamEye account, you can ignore this email.</p>';
        return [
            'subject' => 'Verify your email — GlamEye',
            'html'    => self::wrap($content),
        ];
    }

    /** 退订确认邮件 */
    public static function unsubscribeConfirmation(string $email): array
    {
        $cfg = Mailer::loadConfig();
        $base = rtrim($cfg['site_base_url'] ?? 'https://glameyeshop.com', '/');
        $resubUrl = $base . '/#newsletter';
        $emailEsc = htmlspecialchars($email);

        $content = '<h1 style="font-family:Georgia,serif;font-size:22px;font-weight:500;color:#1a1a1a;margin:0 0 16px;">You are unsubscribed.</h1>
<p>We will not send marketing emails to <strong>' . $emailEsc . '</strong> anymore. You will still receive transactional emails about active orders (order confirmation, shipping notifications, etc.) as required to fulfill your purchase.</p>
<p>Changed your mind? You can re-subscribe any time on our <a href="' . $resubUrl . '" style="color:#9a8062;">homepage</a>.</p>';

        return [
            'subject' => 'You have been unsubscribed — GlamEye',
            'html'    => self::wrap($content),
        ];
    }
}
