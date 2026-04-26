<?php
// 公共 layout：所有 admin 页都 include 这个，传入 $pageTitle 与 $activeNav
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/i18n.php';
require_once __DIR__ . '/../api/lib/upload-hints.php';
requireAdminAuth();

// 杜绝浏览器/CDN 缓存 admin 页面输出 — admin 总是要看最新数据
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$lang = adminLang();
$pageTitle  = $pageTitle  ?? 'Admin';
$activeNav  = $activeNav  ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'zh' ? 'zh-CN' : 'en' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?= htmlspecialchars($pageTitle) ?> · GlamEye Admin</title>
  <link rel="icon" href="../favicon.ico" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600;700;800&display=swap" />
  <link rel="stylesheet" href="../css/styles.css" />
  <link rel="stylesheet" href="admin.css" />
</head>
<body>
  <header class="admin-header">
    <div class="admin-header-inner">
      <a href="index.php" class="brand" style="font-size:1.2rem;">
        <img src="../images/logo.png" alt="GlamEye" class="brand-logo" />
        <span style="margin-left:.5rem; color:var(--gold); letter-spacing:3px; text-transform:uppercase; font-size:.7rem; font-family:var(--sans);">Admin</span>
      </a>
      <div style="display:flex; gap: 1rem; align-items: center;">
        <span class="lang-switch">
          <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
          <a href="?lang=zh" class="<?= $lang === 'zh' ? 'active' : '' ?>">中</a>
        </span>
        <span style="color:var(--text-muted); font-size:.85rem;">
          👤 <?= htmlspecialchars($_SERVER['PHP_AUTH_USER'] ?? 'admin') ?>
        </span>
        <a href="../" class="muted small">← <?= htmlspecialchars(t('back_to_site')) ?></a>
      </div>
    </div>
  </header>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="admin-nav">
        <a href="index.php"     class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">📊 <?= htmlspecialchars(t('dashboard')) ?></a>
        <a href="orders.php"    class="<?= $activeNav === 'orders' ? 'active' : '' ?>">📦 <?= htmlspecialchars(t('orders')) ?></a>
        <a href="products.php"  class="<?= $activeNav === 'products' ? 'active' : '' ?>">💄 <?= htmlspecialchars(t('products')) ?></a>
        <a href="customers.php" class="<?= $activeNav === 'customers' ? 'active' : '' ?>">👥 <?= htmlspecialchars(t('customers')) ?></a>
        <a href="leads.php"     class="<?= $activeNav === 'leads' ? 'active' : '' ?>">✉️ <?= htmlspecialchars(t('leads')) ?></a>
        <a href="support.php"   class="<?= $activeNav === 'support' ? 'active' : '' ?>" id="nav-support">💬 <?= $lang === 'zh' ? '客户咨询' : 'Support' ?></a>
        <a href="analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">📈 <?= $lang === 'zh' ? '访客统计' : 'Analytics' ?></a>
        <a href="videos.php"    class="<?= $activeNav === 'videos' ? 'active' : '' ?>">🎬 <?= $lang === 'zh' ? 'TikTok 视频' : 'TikTok Videos' ?></a>
        <a href="media.php"     class="<?= $activeNav === 'media' ? 'active' : '' ?>">🖼 <?= $lang === 'zh' ? '媒体库' : 'Media Library' ?></a>
        <a href="settings.php"  class="<?= $activeNav === 'settings' ? 'active' : '' ?>">⚙️ <?= $lang === 'zh' ? '站点设置' : 'Settings' ?></a>
      </nav>
    </aside>
    <main class="admin-main">
