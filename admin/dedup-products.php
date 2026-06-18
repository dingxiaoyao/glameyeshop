<?php
// admin/dedup-products.php — 查找 + 一键清理重复产品
// 场景:setup.sql 跑过 INSERT IGNORE 后,用户改了某产品的 SKU,下次 deploy
//      setup.sql 找不到旧 SKU 又插了新的一份,导致同 name/image 的两条记录
//
// 策略:按 name(忽略大小写+trim)分组,每组保留 id 最大的(默认运营最近编辑的),
//      其他全部软删 is_active=0(不真删除,可恢复)。可自定义保留规则。

$pageTitle = 'Dedup Products';
$activeNav = 'products';
require __DIR__ . '/_layout.php';

require_once __DIR__ . '/../api/config.php';
$db = getDb();

$action = $_POST['action'] ?? '';
$flash  = '';

// 处理一键去重提交
if ($action === 'dedup' && !empty($_POST['confirm'])) {
    $keepIds = $_POST['keep'] ?? [];  // [group_name => keep_id]
    $deactivated = 0;
    $db->beginTransaction();
    try {
        foreach ($keepIds as $groupKey => $keepId) {
            $ids = json_decode($_POST['group_ids'][$groupKey] ?? '[]', true);
            if (!is_array($ids)) continue;
            $toKill = array_diff($ids, [(int)$keepId]);
            foreach ($toKill as $killId) {
                $stmt = $db->prepare('UPDATE products SET is_active=0 WHERE id=:id');
                $stmt->execute([':id' => (int)$killId]);
                $deactivated++;
            }
        }
        $db->commit();
        $flash = "✓ Deactivated $deactivated duplicate(s). They are soft-deleted (is_active=0) and can be reactivated in admin/products.php.";
    } catch (Throwable $e) {
        $db->rollBack();
        $flash = "✗ Error: " . $e->getMessage();
    }
}

// 查找重复 — 按 name 分组(可选:也可改用 image_url 或 short_description)
$stmt = $db->query("
  SELECT LOWER(TRIM(name)) AS group_key, COUNT(*) AS cnt
  FROM products
  WHERE is_active = 1
  GROUP BY LOWER(TRIM(name))
  HAVING cnt > 1
  ORDER BY cnt DESC
");
$dupGroups = $stmt->fetchAll();

// 拉每组的具体记录
$groupDetails = [];
foreach ($dupGroups as $g) {
    $key = $g['group_key'];
    $rows = $db->prepare("
      SELECT id, sku, name, category, price, stock, image_url, sort_order, updated_at
      FROM products
      WHERE is_active = 1 AND LOWER(TRIM(name)) = :name
      ORDER BY updated_at DESC, id DESC
    ");
    $rows->execute([':name' => $key]);
    $groupDetails[$key] = $rows->fetchAll();
}
?>

<h1 style="margin-top:0">🧹 <?= $lang === "zh" ? "清理重复产品" : "Dedup Products" ?></h1>
<p class="muted"><?= $lang === "zh" ? "查找 + 清理因 setup.sql 在 SKU 改后重新插入造成的重复产品。" : "Find and clean duplicate products caused by setup.sql re-inserting after SKU was changed manually." ?></p>

<?php if ($flash): ?>
<div class="admin-card" style="border:1px solid var(--gold);background:rgba(185,146,78,.1);margin-bottom:1rem;">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php if (empty($dupGroups)): ?>
<div class="admin-card">
  <p style="color:var(--success,#2c9)"><strong><?= $lang === "zh" ? "✓ 未发现重复产品" : "✓ No duplicates found" ?></strong></p>
  <p class="muted small">All active product names are unique. If you still see odd records, check by image_url or SKU pattern manually in <a href="products.php">/admin/products.php</a>.</p>
</div>
<?php else: ?>
<form method="post" id="dedup-form">
  <input type="hidden" name="action" value="dedup" />

  <div class="admin-card" style="margin-bottom:1.5rem;background:rgba(238,90,90,.05);border:1px solid var(--error);">
    <strong style="color:var(--error)">⚠ <?= count($dupGroups) ?> duplicate group(s) found</strong>
    <p class="muted small" style="margin-top:.5rem">For each group, select the record you want to <strong>KEEP</strong>. The others will be soft-deleted (is_active=0, recoverable via admin/products.php with "Show deleted" toggle).</p>
    <p class="muted small">Default: keeps the most recently updated record (likely the one you manually edited).</p>
  </div>

  <?php foreach ($dupGroups as $i => $g): $key = $g['group_key']; $rows = $groupDetails[$key]; ?>
  <div class="admin-card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;font-size:1.1rem;">
      Group #<?= $i+1 ?>: "<?= htmlspecialchars($rows[0]['name']) ?>" <span class="muted small">(<?= count($rows) ?> copies)</span>
    </h3>
    <input type="hidden" name="group_ids[<?= htmlspecialchars($key) ?>]" value='<?= htmlspecialchars(json_encode(array_column($rows, 'id'))) ?>' />
    <table class="admin-table">
      <thead><tr>
        <th style="width:60px;"><?= $lang === "zh" ? "保留?" : "Keep?" ?></th>
        <th style="width:40px;">ID</th>
        <th>SKU</th>
        <th><?= $lang === "zh" ? "分类" : "Category" ?></th>
        <th><?= $lang === "zh" ? "价格" : "Price" ?></th>
        <th><?= $lang === "zh" ? "库存" : "Stock" ?></th>
        <th><?= $lang === "zh" ? "图片" : "Image" ?></th>
        <th><?= $lang === "zh" ? "最近更新" : "Last Updated" ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $idx => $p): ?>
        <tr>
          <td style="text-align:center;">
            <input type="radio" name="keep[<?= htmlspecialchars($key) ?>]" value="<?= (int)$p['id'] ?>" <?= $idx === 0 ? 'checked' : '' ?> required />
          </td>
          <td><strong>#<?= (int)$p['id'] ?></strong></td>
          <td><code style="color:var(--gold);font-size:.85em;"><?= htmlspecialchars($p['sku']) ?></code></td>
          <td><?= htmlspecialchars($p['category']) ?></td>
          <td>$<?= number_format($p['price'], 2) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td>
            <?php if ($p['image_url']): ?>
            <img src="..<?= htmlspecialchars($p['image_url']) ?>" style="width:36px;height:28px;object-fit:cover;border-radius:3px;background:var(--bg-soft);"
                 onerror="this.outerHTML='<small class=&quot;muted&quot;>missing</small>'" />
            <?php else: ?><span class="muted small">—</span><?php endif; ?>
          </td>
          <td><small class="muted"><?= htmlspecialchars($p['updated_at']) ?></small></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:1rem;align-items:center;margin-top:1.5rem;flex-wrap:wrap;">
    <label style="display:flex;align-items:center;gap:.5rem;">
      <input type="checkbox" name="confirm" required />
      <strong>I confirm: deactivate the unselected records (soft-delete, recoverable)</strong>
    </label>
    <button type="submit" class="button button-primary" style="background:var(--error);border-color:var(--error);">
      🧹 Dedup Now (<?= array_sum(array_map(fn($g) => $g['cnt'] - 1, $dupGroups)) ?> to deactivate)
    </button>
    <a href="products.php" class="button button-outline"><?= $lang === "zh" ? "取消" : "Cancel" ?></a>
  </div>
</form>
<?php endif; ?>

<details class="admin-card" style="margin-top:2rem;">
  <summary style="cursor:pointer;font-family:var(--serif);">🔧 Why does this happen?</summary>
  <div style="margin-top:.75rem;font-size:.9rem;line-height:1.7;color:var(--text);">
    <p>The bootstrap script <code>database/setup.sql</code> runs on every deploy and contains:</p>
    <pre style="background:var(--bg);padding:.75rem;border-radius:4px;font-size:.78rem;overflow:auto;">INSERT IGNORE INTO products (sku, ...) VALUES ('GE-CK-NATURAL', ...);</pre>
    <p>The <code>INSERT IGNORE</code> only skips when the <strong>same SKU</strong> already exists. If you renamed a product's SKU in admin (e.g. <code>GE-CK-NATURAL</code> → <code>GE-CK-NATURAL-V2</code>), the next deploy:</p>
    <ol style="padding-left:1.5rem;">
      <li>Sees no <code>GE-CK-NATURAL</code> in DB</li>
      <li>Inserts a fresh <code>GE-CK-NATURAL</code> from the template</li>
      <li>You now have BOTH your renamed <code>-V2</code> AND the auto-inserted original</li>
    </ol>
    <p><strong>Permanent fix:</strong> The setup.sql seed section will be wrapped in a sentinel check (commit pending) — once a product family is seeded, it won't re-seed even if SKUs change.</p>
  </div>
</details>
