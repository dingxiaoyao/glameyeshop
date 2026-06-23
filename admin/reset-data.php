<?php
$pageTitle = 'Reset Customer Data';
$activeNav = 'reset-data';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

$db = getDb();
$flash = '';
$flashType = 'info';

// 要清空的表(用户行为相关)— 保留 products / settings / banners / before-after / leads / videos / ugc / reviews
// admin 不用 users 表(走 PHP_AUTH_USER),清空 users 不会影响 admin 登录
const CLEAR_TABLES = [
    'users',                          // 注册用户
    'orders',                         // 订单(CASCADE 会带走 order_items)
    'order_items',                    // 订单条目(理论上 CASCADE 自动清,显式列出更稳)
    'wishlist_items',                 // 心愿单
    'user_carts',                     // 购物车
    'password_reset_tokens',          // 密码重置 token
    'rate_limit_log',                 // 限频记录
    'stripe_webhook_events',          // Stripe webhook 幂等日志(可重建)
];

// 跑统计:每个表当前行数
function tableCount(PDO $db, string $t): int {
    try {
        return (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    } catch (Throwable $_) { return -1; }  // 表不存在
}

$counts = [];
foreach (CLEAR_TABLES as $t) $counts[$t] = tableCount($db, $t);

// 处理重置请求
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'reset'
    && !empty($_POST['confirm_word'])
    && trim((string)$_POST['confirm_word']) === 'RESET') {

    $cleared = [];
    $errors = [];
    try {
        $db->beginTransaction();
        // 关 FK check 临时(防 InnoDB CASCADE 顺序导致 'cannot delete' 错误)
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (CLEAR_TABLES as $t) {
            try {
                $n = $counts[$t];
                if ($n < 0) continue;  // 表不存在跳过
                $db->exec("TRUNCATE TABLE `$t`");
                $cleared[$t] = $n;
            } catch (Throwable $e) {
                $errors[$t] = $e->getMessage();
            }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $db->commit();
        $flash = '✓ 已清空 ' . count($cleared) . ' 个表共 '
               . array_sum($cleared) . ' 条记录:' . "\n"
               . implode("\n", array_map(fn($t, $n) => "  $t: $n 条 → 0", array_keys($cleared), $cleared))
               . ($errors ? "\n\n⚠ 部分失败:\n" . implode("\n", array_map(fn($t, $e) => "  $t: $e", array_keys($errors), $errors)) : '');
        $flashType = 'success';
        // 重新统计
        foreach (CLEAR_TABLES as $t) $counts[$t] = tableCount($db, $t);
    } catch (Throwable $e) {
        $db->rollBack();
        $flash = '✗ 失败:' . $e->getMessage();
        $flashType = 'error';
    }
}

$totalRows = array_sum(array_filter($counts, fn($n) => $n > 0));
?>

<h1 style="margin-top:0">⚠️ <?= $lang === 'zh' ? '清空客户数据' : 'Reset Customer Data' ?></h1>
<p class="muted">
  <?= $lang === 'zh'
    ? '一键清空所有注册用户 + 订单 + 购物车 + 心愿单 + 重置 token + 限频日志 + Stripe webhook 日志。<strong style="color:var(--error)">不可恢复!</strong>'
    : 'Wipe all customer registrations + orders + carts + wishlists + reset tokens + rate-limit logs + Stripe webhook logs. <strong style="color:var(--error)">Cannot be undone!</strong>' ?>
</p>

<?php if ($flash): ?>
<div class="admin-card" style="margin-bottom:1.5rem;border:1px solid <?= $flashType === 'success' ? 'var(--success,#2c9)' : 'var(--error)' ?>;background:rgba(<?= $flashType === 'success' ? '95,207,128,.08' : '238,90,90,.08' ?>);">
  <pre style="margin:0;white-space:pre-wrap;font-family:ui-monospace,Menlo,monospace;font-size:.85rem;line-height:1.6;color:var(--text);"><?= htmlspecialchars($flash) ?></pre>
</div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem;background:rgba(95,207,128,.05);border:1px solid var(--success,#2c9);">
  <strong style="color:var(--success,#2c9)">✓ <?= $lang === 'zh' ? '保留不动:' : 'Will be kept (untouched):' ?></strong>
  <p class="muted small" style="margin-top:.5rem;line-height:1.7;">
    products(产品)· site_settings(全部设置:Stripe / GA / Google Ads 配置 / SMTP / Hero slideshow 等)·
    account_banners(账户广告)· before_after_pairs(对比图)·
    reviews(评论)· ugc_submissions(用户晒图)· videos(TikTok 视频)·
    wholesale_leads(批发询单)· newsletter_subscribers(邮件订阅)
  </p>
  <p class="muted small" style="margin-top:.5rem;line-height:1.7;">
    <strong><?= $lang === 'zh' ? 'admin 登录' : 'Admin login' ?>:</strong>
    <?= $lang === 'zh'
        ? 'admin 走 HTTP Basic Auth(不依赖 users 表),清空 users 不影响你登录后台。'
        : 'Admin uses HTTP Basic Auth (independent of users table), so clearing users won\'t lock you out.' ?>
  </p>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;background:rgba(238,90,90,.05);border:1px solid var(--error);">
  <strong style="color:var(--error)">⚠ <?= $lang === 'zh' ? '将被 TRUNCATE 的表:' : 'Tables to be TRUNCATEd:' ?></strong>
  <table class="admin-table" style="margin-top:.5rem;">
    <thead><tr>
      <th><?= $lang === 'zh' ? '表名' : 'Table' ?></th>
      <th><?= $lang === 'zh' ? '当前行数' : 'Current rows' ?></th>
      <th><?= $lang === 'zh' ? '说明' : 'Description' ?></th>
    </tr></thead>
    <tbody>
      <?php
      $descs = [
        'users' => $lang === 'zh' ? '所有注册账户(邮箱、密码哈希、个人资料)' : 'All registered customer accounts',
        'orders' => $lang === 'zh' ? '所有订单(含 test 和真实)' : 'All orders (test + real)',
        'order_items' => $lang === 'zh' ? '订单条目(订单清空后随 CASCADE 自动清,显式列出)' : 'Order line items (auto-cleared via CASCADE)',
        'wishlist_items' => $lang === 'zh' ? '心愿单' : 'Customer wishlists',
        'user_carts' => $lang === 'zh' ? '服务器端购物车(localStorage 不在此 — 客户端那份得让用户自己清)' : 'Server-side carts',
        'password_reset_tokens' => $lang === 'zh' ? '密码重置 token' : 'Password reset tokens',
        'rate_limit_log' => $lang === 'zh' ? '限频日志(登录/注册/order-status 等)' : 'Rate-limit logs',
        'stripe_webhook_events' => $lang === 'zh' ? 'Stripe webhook 幂等日志(可重建)' : 'Stripe webhook idempotency log (rebuildable)',
      ];
      foreach (CLEAR_TABLES as $t):
        $n = $counts[$t];
      ?>
      <tr>
        <td><code style="color:var(--gold);"><?= htmlspecialchars($t) ?></code></td>
        <td><?= $n < 0 ? '<small class="muted">N/A(表不存在)</small>' : '<strong>' . $n . '</strong>' ?></td>
        <td><small class="muted"><?= htmlspecialchars($descs[$t] ?? '') ?></small></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p style="margin-top:1rem;font-size:1rem;">
    <?= $lang === 'zh' ? '总计将删除' : 'Total rows to delete' ?>:
    <strong style="color:var(--error);font-size:1.2rem;"><?= $totalRows ?></strong>
    <?= $lang === 'zh' ? '条记录' : 'rows' ?>
  </p>
</div>

<form method="post" id="reset-form" onsubmit="return confirm('<?= $lang === 'zh' ? '再次确认:这操作不可恢复!确定继续吗?' : 'Final confirmation: this CANNOT be undone! Continue?' ?>');">
  <input type="hidden" name="action" value="reset" />
  <div class="admin-card" style="background:var(--bg-soft);">
    <p style="margin:0 0 .75rem;">
      <?= $lang === 'zh'
        ? '输入大写单词 <code style="background:var(--bg);padding:.2rem .5rem;border-radius:3px;color:var(--error);">RESET</code> 以解锁按钮:'
        : 'Type uppercase word <code style="background:var(--bg);padding:.2rem .5rem;border-radius:3px;color:var(--error);">RESET</code> to unlock the button:' ?>
    </p>
    <input type="text" name="confirm_word" id="confirm-word" autocomplete="off" required
           placeholder="<?= $lang === 'zh' ? '输入 RESET' : 'Type RESET' ?>"
           style="width:200px;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.5rem .75rem;border-radius:4px;font-family:ui-monospace,Menlo,monospace;font-size:1rem;letter-spacing:2px;" />
    <button type="submit" id="reset-btn" disabled
            style="margin-left:1rem;background:var(--error);border-color:var(--error);color:#fff;opacity:.5;cursor:not-allowed;padding:.6rem 1.5rem;border-radius:4px;border:none;font-size:.9rem;">
      🗑 <?= $lang === 'zh' ? '清空 ' . $totalRows . ' 条记录' : 'Reset ' . $totalRows . ' rows now' ?>
    </button>
  </div>
</form>

<script>
  // 输入 RESET 才解锁按钮
  const inp = document.getElementById('confirm-word');
  const btn = document.getElementById('reset-btn');
  inp.addEventListener('input', () => {
    const ok = inp.value === 'RESET';
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.5';
    btn.style.cursor = ok ? 'pointer' : 'not-allowed';
  });
</script>
