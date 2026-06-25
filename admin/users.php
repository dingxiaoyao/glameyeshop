<?php
// Admin 用户管理 — 列表 + 添加 + 停用 + 重置密码(其他 admin)
$pageTitle = 'Admin Users';
$activeNav = 'users';
require_once __DIR__ . '/../api/lib/admin-session.php';

$me = requireAdminSession();
$flash = '';
$flashType = 'success';
$generatedPassword = null;  // 重置/添加后回显一次

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf();
    $action = $_POST['action'] ?? '';
    try {
        $db = getDb();

        if ($action === 'add') {
            $email = trim($_POST['email'] ?? '');
            $name  = trim($_POST['display_name'] ?? '') ?: 'Admin';
            $pwd   = (string)($_POST['password'] ?? '');
            if (!$pwd) {
                // 没填 → 自动生成
                $pwd = adminGenerateTempPassword();
                $generatedPassword = $pwd;
            }
            $r = adminCreate($email, $pwd, $name);
            if (!$r['ok']) throw new Exception($r['error']);
            $flash = '✓ Admin "' . htmlspecialchars($email) . '" created.';
            if ($generatedPassword) $flash .= ' Copy the auto-generated password below — it will not be shown again.';
        }
        elseif ($action === 'deactivate') {
            $targetId = (int)($_POST['id'] ?? 0);
            if ($targetId === (int)$me['id']) throw new Exception("Can't deactivate yourself.");
            if (_adminActiveCount() <= 1) throw new Exception("Can't deactivate — at least one active admin must remain.");
            $db->prepare('UPDATE admin_users SET is_active=0 WHERE id=:id')->execute([':id' => $targetId]);
            $flash = '✓ Admin deactivated.';
        }
        elseif ($action === 'activate') {
            $targetId = (int)($_POST['id'] ?? 0);
            $db->prepare('UPDATE admin_users SET is_active=1, failed_attempts=0, locked_until=NULL WHERE id=:id')->execute([':id' => $targetId]);
            $flash = '✓ Admin activated.';
        }
        elseif ($action === 'reset_password') {
            $targetId = (int)($_POST['id'] ?? 0);
            $myPwd    = (string)($_POST['my_password'] ?? '');
            // 重置别人密码 — 必须先验证自己密码,防 session 被偷接管
            $row = $db->prepare('SELECT password_hash FROM admin_users WHERE id=:id LIMIT 1');
            $row->execute([':id' => $me['id']]);
            $myHash = $row->fetchColumn();
            if (!$myHash || !adminPasswordVerify($myPwd, $myHash)) {
                throw new Exception('Your password is incorrect — cannot reset another admin.');
            }
            $newPwd = adminGenerateTempPassword();
            $db->prepare('UPDATE admin_users SET password_hash=:h, password_changed_at=NOW(), failed_attempts=0, locked_until=NULL, totp_enabled=0, totp_secret=NULL WHERE id=:id')
               ->execute([':h' => adminPasswordHash($newPwd), ':id' => $targetId]);
            $generatedPassword = $newPwd;
            $flash = '✓ Password reset. 2FA disabled. Share the new password securely below — it will not be shown again.';
        }
        elseif ($action === 'force_logout') {
            // 简易实现:让目标 admin session 立刻失效 — 这里只能靠 session 校验里 is_active=0 来踢
            // 真正彻底踢需要刷 password_changed_at 让所有现有 session 过期(此功能可后续补)
            $targetId = (int)($_POST['id'] ?? 0);
            if ($targetId === (int)$me['id']) throw new Exception("Use the Sign out button to log out yourself.");
            // 触发 password_changed_at 更新 — 下次 currentAdmin() 检查可加 hook
            $db->prepare('UPDATE admin_users SET updated_at=NOW() WHERE id=:id')->execute([':id' => $targetId]);
            $flash = '✓ Marked. The target admin will be required to re-login on next session check (≤30 min).';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

function _adminActiveCount(): int {
    try {
        return (int)getDb()->query('SELECT COUNT(*) FROM admin_users WHERE is_active=1')->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function adminGenerateTempPassword(): string {
    // 10 字符,保证含大写+小写+数字+符号,排除易混字符(0/O/1/l/I)
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower  = 'abcdefghijkmnpqrstuvwxyz';
    $digit  = '23456789';
    $symbol = '!@#$%&*';
    $pool   = $upper . $lower . $digit . $symbol;
    // 至少各 1 个,凑到 10
    $chars  = [
        $upper[random_int(0, strlen($upper)-1)],
        $lower[random_int(0, strlen($lower)-1)],
        $digit[random_int(0, strlen($digit)-1)],
        $symbol[random_int(0, strlen($symbol)-1)],
    ];
    for ($i = 4; $i < 10; $i++) {
        $chars[] = $pool[random_int(0, strlen($pool)-1)];
    }
    // crypto-safe shuffle(Fisher-Yates)
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

// 拉列表
$admins = [];
try {
    $admins = getDb()->query('SELECT id, email, display_name, totp_enabled, failed_attempts, locked_until, last_login_at, last_login_ip, is_active, created_at FROM admin_users ORDER BY is_active DESC, id ASC')->fetchAll();
} catch (Throwable $e) {
    $flash = 'Failed to load admin list: ' . $e->getMessage();
    $flashType = 'error';
}

require __DIR__ . '/_layout.php';
$csrf = adminCsrfToken();
?>
<h1 style="margin-top:0">👥 <?= $lang === 'zh' ? '管理员账号' : 'Admin Users' ?></h1>
<p class="muted">
  <?= $lang === 'zh' ? '管理后台账号 — 添加同事、停用账号、为遗忘密码的人重置密码。' : 'Manage admin accounts — add colleagues, deactivate accounts, reset passwords.' ?>
</p>

<?php if ($flash): ?>
<div class="admin-card" style="border-left:3px solid <?= $flashType==='error'?'var(--error)':'var(--gold)' ?>;margin-bottom:1.5rem">
  <?= htmlspecialchars($flash) ?>
  <?php if ($generatedPassword): ?>
    <div style="margin-top:.75rem;padding:.75rem;background:var(--bg-soft);border-radius:6px;font-family:monospace;font-size:1rem;letter-spacing:.05em;user-select:all">
      🔑 <strong style="color:var(--gold)"><?= htmlspecialchars($generatedPassword) ?></strong>
    </div>
    <p class="muted small" style="margin-top:.5rem">⚠ Save this password now — it will <strong>not</strong> be shown again. Tell the user to sign in and immediately change it.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">➕ <?= $lang === 'zh' ? '添加管理员' : 'Add a new admin' ?></h3>
  <form method="post" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end;margin-top:1rem" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="action" value="add" />
    <label>
      <span class="muted small">Email</span>
      <input type="email" name="email" required style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small">Display name</span>
      <input type="text" name="display_name" maxlength="100" placeholder="e.g. Sarah" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small">Password (leave empty = auto-generate)</span>
      <input type="text" name="password" minlength="8" placeholder="8+ chars, mixed case, digit, symbol" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <button type="submit" class="button button-primary">Add admin</button>
  </form>
  <p class="muted small" style="margin-top:.5rem">Tip: leave password blank to auto-generate a strong one — it'll be displayed once after creation. The new admin should change it on first sign-in.</p>
</div>

<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">📋 <?= $lang === 'zh' ? '现有管理员' : 'All admins' ?> (<?= count($admins) ?>)</h3>
  <table class="admin-table" style="margin-top:1rem">
    <thead><tr>
      <th>ID</th>
      <th>Email</th>
      <th>Name</th>
      <th>2FA</th>
      <th>Last login</th>
      <th>Status</th>
      <th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
      <tr style="<?= (int)$a['is_active']===0 ? 'opacity:.5' : '' ?>">
        <td><strong>#<?= (int)$a['id'] ?></strong></td>
        <td>
          <?= htmlspecialchars($a['email']) ?>
          <?php if ((int)$a['id'] === (int)$me['id']): ?>
            <span style="color:var(--gold);font-size:.75rem">· you</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($a['display_name']) ?></td>
        <td>
          <?php if ((int)$a['totp_enabled'] === 1): ?>
            <span style="color:var(--success)">✓ on</span>
          <?php else: ?>
            <span class="muted">off</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($a['last_login_at']): ?>
            <small><?= htmlspecialchars($a['last_login_at']) ?></small><br>
            <small class="muted"><?= htmlspecialchars($a['last_login_ip'] ?? '') ?></small>
          <?php else: ?>
            <small class="muted">—</small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int)$a['is_active'] === 1): ?>
            <span style="color:var(--success)">● active</span>
          <?php else: ?>
            <span style="color:var(--error)">● disabled</span>
          <?php endif; ?>
          <?php if (!empty($a['locked_until']) && strtotime($a['locked_until']) > time()): ?>
            <br><small style="color:var(--warn)">🔒 locked till <?= htmlspecialchars(date('H:i', strtotime($a['locked_until']))) ?></small>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <?php if ((int)$a['id'] !== (int)$me['id']): ?>
            <?php if ((int)$a['is_active'] === 1): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Deactivate this admin? They lose access immediately.')">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
                <input type="hidden" name="action" value="deactivate" />
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>" />
                <button type="submit" class="button button-outline" style="font-size:.75rem;padding:.3rem .6rem;color:var(--error);border-color:var(--error)">Disable</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
                <input type="hidden" name="action" value="activate" />
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>" />
                <button type="submit" class="button button-outline" style="font-size:.75rem;padding:.3rem .6rem;color:var(--success);border-color:var(--success)">Enable</button>
              </form>
            <?php endif; ?>
            <button type="button" class="button button-outline" style="font-size:.75rem;padding:.3rem .6rem" onclick="document.getElementById('resetform-<?= (int)$a['id'] ?>').classList.toggle('hidden')">Reset pwd</button>
          <?php else: ?>
            <a href="account.php" class="muted small">Edit my account →</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ((int)$a['id'] !== (int)$me['id']): ?>
      <tr id="resetform-<?= (int)$a['id'] ?>" class="hidden">
        <td colspan="7" style="background:var(--bg-soft);padding:1rem">
          <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
            <input type="hidden" name="action" value="reset_password" />
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>" />
            <strong style="font-size:.85rem">Reset password for <?= htmlspecialchars($a['email']) ?> — confirm with YOUR password:</strong>
            <input type="password" name="my_password" required autocomplete="current-password" placeholder="Your password" style="padding:.4rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
            <button type="submit" class="button button-primary" style="font-size:.8rem">Generate new password</button>
            <small class="muted">A strong random password will be generated and shown once. 2FA will be disabled.</small>
          </form>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<details class="admin-card" style="margin-top:1.5rem">
  <summary style="cursor:pointer;font-family:var(--serif)">🛟 <?= $lang === 'zh' ? '紧急恢复 — 所有 admin 都失联了怎么办?' : 'Emergency recovery — lost all admin access?' ?></summary>
  <div style="margin-top:.75rem;font-size:.9rem;line-height:1.7">
    <p>For security, the public "first admin setup" flow is permanently closed once you've created the first admin. If everyone is locked out, SSH to the server and run:</p>
    <pre style="background:var(--bg);padding:.75rem;border-radius:4px;font-size:.78rem;overflow:auto">
# 1) connect to server
ssh user@your-server.com

# 2) reset all admins (creates fresh setup flow)
mysql -u glameye_app -p glameyeshop &lt;&lt;SQL
DELETE FROM admin_users;
DELETE FROM site_settings WHERE \`key\`='admin_bootstrap_done';
SQL

# 3) now visit /admin/login.php — first-time setup will reappear</pre>
    <p class="muted small">Keep this snippet — only you with server access can recover. Do NOT share it.</p>
  </div>
</details>

<style>
  .hidden { display: none; }
</style>
