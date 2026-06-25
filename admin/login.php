<?php
// ============================================================
// Admin 登录页(独立,不复用前台 user session)
// 若 admin_users 表空 → 首次设置流程
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/lib/admin-session.php';
require_once __DIR__ . '/../api/lib/rate-limit.php';

// 已登录直接跳后台
$current = currentAdmin();
if ($current) {
    header('Location: index.php', true, 302);
    exit;
}

$count = adminUserCount();
$isSetup = ($count === 0);                       // 第一次访问 — 引导设置
$redirectBack = $_GET['redirect'] ?? 'index.php';
$err = '';
$notice = '';

// 防 open redirect — 只允许本站相对路径
if (!preg_match('#^/[^/]#', $redirectBack)) {
    $redirectBack = '/admin/index.php';
}

$ip = rateLimitClientIp();

// ----------------- POST 处理 -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 通用 IP 限频
    $ipBucket = "admin-login-ip:$ip";
    if (rateLimitFailCount($ipBucket, ADMIN_IP_FAILS_WIN) >= ADMIN_IP_FAILS_MAX) {
        $err = '⚠ Too many failed attempts from your IP. Try again in 15 minutes.';
        adminLogAttempt($_POST['email'] ?? null, false, 'ip_locked');
    } elseif ($isSetup) {
        // ----- 首次设置流程 -----
        $email = trim($_POST['email'] ?? '');
        $pwd   = (string)($_POST['password'] ?? '');
        $pwd2  = (string)($_POST['password_confirm'] ?? '');
        $name  = trim($_POST['display_name'] ?? 'Admin');

        if ($pwd !== $pwd2) {
            $err = 'Passwords do not match.';
        } else {
            $r = adminCreate($email, $pwd, $name);
            if (!$r['ok']) {
                $err = $r['error'];
            } else {
                $admin = adminUserByEmail($email);
                adminLogin($admin);
                adminLogAttempt($email, true, 'first_setup');
                header('Location: ' . $redirectBack, true, 302);
                exit;
            }
        }
    } else {
        // ----- 普通登录流程 -----
        $email = trim($_POST['email'] ?? '');
        $pwd   = (string)($_POST['password'] ?? '');
        $totp  = trim((string)($_POST['totp'] ?? ''));

        // 按 email 限频(防针对单一账号撞库)
        $emailKey = strtolower($email);
        $emailBucket = "admin-login-email:$emailKey";
        if (rateLimitFailCount($emailBucket, 900) >= ADMIN_LOCKOUT_FAILS) {
            $err = '⚠ This account is temporarily locked due to repeated failed sign-ins.';
            adminLogAttempt($email, false, 'email_locked');
        } else {
            $admin = adminUserByEmail($email);

            // 恒定时间 — 即使账号不存在也跑一次 hash,避免 timing leak
            $dummy = '$2y$10$KIXwY8m6Bv4Bvq6X7yh0RuTJq8sJrZB3VlVTk0Pa6cQ9X9vQk5J/u';
            if (!$admin) {
                password_verify($pwd, $dummy);
                rateLimitFail($ipBucket);
                rateLimitFail($emailBucket);
                adminLogAttempt($email, false, 'no_such_user');
                $err = 'Invalid email or password.';
            } elseif ((int)$admin['is_active'] !== 1) {
                rateLimitFail($ipBucket);
                adminLogAttempt($email, false, 'inactive');
                $err = 'This admin account has been disabled.';
            } elseif (adminIsLocked($admin)) {
                $until = date('Y-m-d H:i', strtotime($admin['locked_until']));
                $err = "⚠ Account locked until $until UTC.";
                adminLogAttempt($email, false, 'locked');
            } elseif (!adminPasswordVerify($pwd, $admin['password_hash'])) {
                adminBumpFail((int)$admin['id']);
                rateLimitFail($ipBucket);
                rateLimitFail($emailBucket);
                adminLogAttempt($email, false, 'bad_password');
                $err = 'Invalid email or password.';
            } elseif ((int)$admin['totp_enabled'] === 1) {
                // 需要 TOTP
                if ($totp === '') {
                    $err = 'Enter your 6-digit authenticator code.';
                    $_POST['__ask_totp'] = 1;
                } elseif (!totpVerify((string)$admin['totp_secret'], $totp)) {
                    adminBumpFail((int)$admin['id']);
                    rateLimitFail($ipBucket);
                    rateLimitFail($emailBucket);
                    adminLogAttempt($email, false, 'totp_fail');
                    $err = 'Invalid authenticator code.';
                    $_POST['__ask_totp'] = 1;
                } else {
                    // 全过
                    adminLogin($admin);
                    rateLimitClear($ipBucket);
                    rateLimitClear($emailBucket);
                    adminLogAttempt($email, true, 'ok_2fa');
                    header('Location: ' . $redirectBack, true, 302);
                    exit;
                }
            } else {
                // 无 2FA — 直接登
                adminLogin($admin);
                rateLimitClear($ipBucket);
                rateLimitClear($emailBucket);
                adminLogAttempt($email, true, 'ok');
                header('Location: ' . $redirectBack, true, 302);
                exit;
            }
        }
    }
}

// 渲染前防缓存 + 安全头
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; form-action 'self'; frame-ancestors 'none'");

$askTotp = !empty($_POST['__ask_totp']);
$emailPrefill = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES);
$csrf = adminCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?= $isSetup ? 'Set up Admin · GlamEye' : 'Admin Sign In · GlamEye' ?></title>
  <link rel="icon" href="../favicon.ico" sizes="any" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" />
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{
      min-height:100vh;display:flex;align-items:center;justify-content:center;
      background:#0e0e10;color:#eaeaea;
      font-family:'Inter',system-ui,sans-serif;
      padding:2rem 1rem;
    }
    .card{
      width:100%;max-width:420px;background:#17171a;border:1px solid #2a2a2e;
      border-radius:12px;padding:2.5rem 2rem;box-shadow:0 24px 64px -16px rgba(0,0,0,.6);
    }
    .brand{
      text-align:center;font-family:'Cormorant Garamond',serif;font-size:1.8rem;
      letter-spacing:.18em;color:#d4a955;margin-bottom:.35rem;text-transform:uppercase;
    }
    .sub{
      text-align:center;font-size:.75rem;letter-spacing:.4em;color:#7a7a82;
      text-transform:uppercase;margin-bottom:2rem;
    }
    h1{
      font-family:'Cormorant Garamond',serif;font-weight:500;font-size:1.5rem;
      margin-bottom:.5rem;text-align:center;color:#fff;
    }
    .hint{font-size:.85rem;color:#9a9aa2;margin-bottom:1.5rem;text-align:center;line-height:1.6}
    label{display:block;margin-bottom:1rem}
    .lbl{display:block;font-size:.72rem;letter-spacing:.15em;text-transform:uppercase;color:#9a9aa2;margin-bottom:.4rem}
    input[type=email],input[type=password],input[type=text]{
      width:100%;padding:.75rem .9rem;background:#0e0e10;border:1px solid #2a2a2e;
      color:#fff;border-radius:6px;font-size:.95rem;font-family:inherit;
      transition:border-color .15s;
    }
    input:focus{outline:none;border-color:#d4a955}
    input[name=totp]{font-family:'SF Mono',Menlo,Consolas,monospace;letter-spacing:.4em;text-align:center;font-size:1.2rem}
    button{
      width:100%;padding:.85rem 1rem;background:#d4a955;color:#0e0e10;border:0;
      border-radius:6px;font-weight:600;letter-spacing:.05em;cursor:pointer;
      font-size:.95rem;transition:background .15s;font-family:inherit;
    }
    button:hover{background:#e3b966}
    .err{
      background:rgba(201,69,69,.12);border-left:3px solid #c94545;
      padding:.75rem .9rem;border-radius:4px;font-size:.85rem;color:#ff9b9b;
      margin-bottom:1.2rem;line-height:1.5;
    }
    .notice{
      background:rgba(212,169,85,.1);border-left:3px solid #d4a955;
      padding:.75rem .9rem;border-radius:4px;font-size:.85rem;color:#e3b966;
      margin-bottom:1.2rem;line-height:1.5;
    }
    .meta{
      margin-top:1.5rem;text-align:center;font-size:.72rem;color:#6a6a72;
      letter-spacing:.08em;line-height:1.7;
    }
    .meta a{color:#9a9aa2;text-decoration:none}
    .meta a:hover{color:#d4a955}
    .pw-meter{margin-top:.3rem;font-size:.7rem;color:#7a7a82}
    .strong{color:#7ec47e}
    .weak{color:#e0a06a}
  </style>
</head>
<body>
  <main class="card">
    <div class="brand">GlamEye</div>
    <div class="sub">Admin Console</div>

    <?php if ($isSetup): ?>
      <h1>Welcome — set up your admin</h1>
      <p class="hint">No admin account exists yet. Create the first one now. This will be the master account; you can add more from Settings later.</p>
    <?php elseif ($askTotp): ?>
      <h1>Two-factor authentication</h1>
      <p class="hint">Enter the 6-digit code from your authenticator app.</p>
    <?php else: ?>
      <h1>Sign in</h1>
      <p class="hint">GlamEye admin console — access restricted.</p>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />

      <?php if ($isSetup): ?>
        <label>
          <span class="lbl">Display name</span>
          <input type="text" name="display_name" required maxlength="100" value="<?= htmlspecialchars($_POST['display_name'] ?? 'Owner') ?>" />
        </label>
        <label>
          <span class="lbl">Email</span>
          <input type="email" name="email" required autocomplete="off" value="<?= $emailPrefill ?>" autofocus />
        </label>
        <label>
          <span class="lbl">Password (12+ chars, mixed case, digit, symbol)</span>
          <input type="password" name="password" required minlength="12" autocomplete="new-password" id="pw" />
          <div class="pw-meter" id="pwmeter">Strength: —</div>
        </label>
        <label>
          <span class="lbl">Confirm password</span>
          <input type="password" name="password_confirm" required minlength="12" autocomplete="new-password" />
        </label>
        <button type="submit">Create admin account & sign in</button>
      <?php elseif ($askTotp): ?>
        <input type="hidden" name="email" value="<?= $emailPrefill ?>" />
        <input type="hidden" name="password" value="<?= htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES) ?>" />
        <label>
          <span class="lbl">Authenticator code</span>
          <input type="text" name="totp" required inputmode="numeric" pattern="\d{6}" maxlength="6" autofocus />
        </label>
        <button type="submit">Verify & sign in</button>
      <?php else: ?>
        <label>
          <span class="lbl">Email</span>
          <input type="email" name="email" required autocomplete="username" value="<?= $emailPrefill ?>" autofocus />
        </label>
        <label>
          <span class="lbl">Password</span>
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button type="submit">Sign in</button>
      <?php endif; ?>
    </form>

    <div class="meta">
      Protected by rate limiting · session expires after 30 min idle<br>
      <?php if (!$isSetup): ?>
        <a href="../">← Back to store</a>
      <?php endif; ?>
    </div>
  </main>

  <?php if ($isSetup): ?>
  <script>
    (function(){
      const pw = document.getElementById('pw');
      const m  = document.getElementById('pwmeter');
      if(!pw||!m) return;
      pw.addEventListener('input', () => {
        const v = pw.value;
        let s = 0;
        if (v.length >= 12) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[a-z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        const lvls = ['—','very weak','weak','okay','strong','very strong'];
        m.textContent = 'Strength: ' + lvls[s];
        m.className = 'pw-meter ' + (s >= 4 ? 'strong' : (s >= 2 ? 'weak' : ''));
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
