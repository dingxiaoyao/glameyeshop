<?php
// Admin 账户设置 — 改密码 + 启用/禁用 TOTP
$pageTitle = 'My Account';
$activeNav = 'account';
require_once __DIR__ . '/../api/lib/admin-session.php';

$admin = requireAdminSession();
$flash = '';
$flashType = 'success';
$showTotpEnrol = false;
$enrolSecret = null;
$enrolUri    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf();
    $action = $_POST['action'] ?? '';
    try {
        $db = getDb();
        if ($action === 'change_password') {
            $cur = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $nc  = (string)($_POST['new_confirm'] ?? '');
            $row = $db->prepare('SELECT password_hash FROM admin_users WHERE id=:id LIMIT 1');
            $row->execute([':id' => $admin['id']]);
            $hash = $row->fetchColumn();
            if (!$hash || !adminPasswordVerify($cur, $hash)) {
                throw new Exception('Current password is incorrect.');
            }
            if ($new !== $nc) throw new Exception('New passwords do not match.');
            $err = adminPasswordStrengthError($new);
            if ($err) throw new Exception($err);
            $db->prepare('UPDATE admin_users SET password_hash=:h, password_changed_at=NOW() WHERE id=:id')
               ->execute([':h' => adminPasswordHash($new), ':id' => $admin['id']]);
            $flash = '✓ Password updated. You stay signed in.';
        }
        elseif ($action === 'totp_init') {
            $secret = totpGenerateSecret();
            $_SESSION['admin_totp_enrol'] = $secret;
            $showTotpEnrol = true;
            $enrolSecret = $secret;
            $enrolUri    = totpUri($secret, $admin['email']);
        }
        elseif ($action === 'totp_confirm') {
            $secret = $_SESSION['admin_totp_enrol'] ?? '';
            $code   = $_POST['totp'] ?? '';
            if (!$secret || !totpVerify($secret, $code)) {
                throw new Exception('Invalid code — check your authenticator and try again.');
            }
            $db->prepare('UPDATE admin_users SET totp_secret=:s, totp_enabled=1 WHERE id=:id')
               ->execute([':s' => $secret, ':id' => $admin['id']]);
            unset($_SESSION['admin_totp_enrol']);
            $flash = '✓ Two-factor authentication enabled.';
            $admin['totp_enabled'] = 1;
        }
        elseif ($action === 'totp_disable') {
            $cur = (string)($_POST['current_password'] ?? '');
            $row = $db->prepare('SELECT password_hash FROM admin_users WHERE id=:id LIMIT 1');
            $row->execute([':id' => $admin['id']]);
            $hash = $row->fetchColumn();
            if (!$hash || !adminPasswordVerify($cur, $hash)) {
                throw new Exception('Password incorrect — cannot disable 2FA.');
            }
            $db->prepare('UPDATE admin_users SET totp_secret=NULL, totp_enabled=0 WHERE id=:id')
               ->execute([':id' => $admin['id']]);
            $admin['totp_enabled'] = 0;
            $flash = '✓ Two-factor authentication disabled.';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

// 用主 layout
require __DIR__ . '/_layout.php';
$csrf = adminCsrfToken();
?>
<h1 style="margin-top:0">👤 My Account</h1>
<p class="muted">Signed in as <strong style="color:var(--gold)"><?= htmlspecialchars($admin['email']) ?></strong></p>

<?php if ($flash): ?>
  <div class="admin-card" style="border-left:3px solid <?= $flashType==='error'?'var(--error)':'var(--gold)' ?>;margin-bottom:1.5rem">
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">🔑 Change password</h3>
  <form method="post" style="display:grid;gap:.75rem;max-width:420px;margin-top:1rem" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="action" value="change_password" />
    <label>
      <span class="muted small">Current password</span>
      <input type="password" name="current_password" required autocomplete="current-password" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small">New password (12+ chars · mixed case · digit · symbol)</span>
      <input type="password" name="new_password" required minlength="12" autocomplete="new-password" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small">Confirm new password</span>
      <input type="password" name="new_confirm" required minlength="12" autocomplete="new-password" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <button type="submit" class="button button-primary" style="justify-self:start">Update password</button>
  </form>
</div>

<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">🔐 Two-factor authentication (TOTP)</h3>

  <?php if ((int)($admin['totp_enabled'] ?? 0) === 1 && !$showTotpEnrol): ?>
    <p style="color:var(--success);margin:.5rem 0">✓ 2FA is currently <strong>enabled</strong>.</p>
    <p class="muted small" style="margin-bottom:1rem">You'll be asked for a 6-digit code on every sign-in.</p>
    <form method="post" style="display:grid;gap:.75rem;max-width:380px" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="action" value="totp_disable" />
      <label>
        <span class="muted small">Confirm with your password to disable 2FA</span>
        <input type="password" name="current_password" required autocomplete="current-password" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
      </label>
      <button type="submit" class="button button-outline" style="justify-self:start;color:var(--error);border-color:var(--error)">Disable 2FA</button>
    </form>

  <?php elseif ($showTotpEnrol): ?>
    <p>1️⃣ Scan this QR code with Google Authenticator / Authy / 1Password:</p>
    <div style="margin:1rem 0;padding:1rem;background:#fff;display:inline-block;border-radius:8px">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=<?= urlencode($enrolUri) ?>" alt="TOTP QR" width="240" height="240" />
    </div>
    <p class="muted small">Or enter this secret manually:<br>
      <code style="font-size:1.05rem;letter-spacing:.15em;color:var(--gold);user-select:all"><?= htmlspecialchars($enrolSecret) ?></code>
    </p>
    <p style="margin-top:1rem">2️⃣ Enter the 6-digit code from your app to confirm:</p>
    <form method="post" style="display:flex;gap:.5rem;align-items:center;max-width:380px;margin-top:.5rem">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="action" value="totp_confirm" />
      <input type="text" name="totp" required inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" autofocus
             style="font-family:monospace;letter-spacing:.4em;font-size:1.2rem;text-align:center;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px;width:180px" />
      <button type="submit" class="button button-primary">Enable 2FA</button>
    </form>
  <?php else: ?>
    <p class="muted">Add an extra layer of security — you'll need a code from your authenticator app on every sign-in.</p>
    <form method="post" style="margin-top:1rem">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="action" value="totp_init" />
      <button type="submit" class="button button-primary">Set up 2FA</button>
    </form>
  <?php endif; ?>
</div>

