<?php
require_once __DIR__ . '/../api/config.php';

// 必须通过 HTTP Basic Auth 验证
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>GLAMEYE 管理面板</title>
  <link rel="stylesheet" href="../css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container">
      <a href="../index.html" class="brand">GLAMEYE Admin</a>
      <nav>
        <a href="../index.html">返回首页</a>
      </nav>
    </div>
  </header>

  <main class="container admin-page">
    <section class="admin-panel">
      <h1>管理面板</h1>
      <p>欢迎，<?= htmlspecialchars($_SERVER['PHP_AUTH_USER'] ?? '管理员', ENT_QUOTES, 'UTF-8') ?>。</p>

      <h2 style="margin-top:1.5rem;">最近订单</h2>
      <div id="orders-table" style="overflow-x:auto;margin-top:1rem;">
        <p>加载中…</p>
      </div>

      <div class="admin-actions" style="margin-top:1.5rem;">
        <a href="../api/get-orders.php" class="button button-secondary" target="_blank" rel="noopener">原始 JSON</a>
        <a href="../checkout.html" class="button button-primary">返回结账</a>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <p>© 2026 GLAMEYE. 版权所有。</p>
    </div>
  </footer>

  <script>
    // 通过同源请求获取订单（浏览器会带上当前 Basic Auth 凭据）
    fetch('../api/get-orders.php', { credentials: 'include' })
      .then((r) => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then((data) => {
        const container = document.getElementById('orders-table');
        const orders = (data && data.orders) || [];
        if (orders.length === 0) {
          container.innerHTML = '<p>暂无订单。</p>';
          return;
        }
        const escape = (s) =>
          String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
          );
        const rows = orders
          .map(
            (o) => `
              <tr>
                <td>${escape(o.id)}</td>
                <td>${escape(o.created_at)}</td>
                <td>${escape(o.customer_name)}</td>
                <td>${escape(o.product_name)} × ${escape(o.quantity)}</td>
                <td>¥${escape(o.amount)}</td>
                <td>${escape(o.email)}</td>
                <td>${escape(o.phone)}</td>
                <td>${escape(o.payment_method)}</td>
                <td>${escape(o.status)}</td>
              </tr>`
          )
          .join('');
        container.innerHTML = `
          <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
            <thead>
              <tr style="background:#f3f4f6;">
                <th style="padding:0.5rem;text-align:left;">#</th>
                <th style="padding:0.5rem;text-align:left;">时间</th>
                <th style="padding:0.5rem;text-align:left;">客户</th>
                <th style="padding:0.5rem;text-align:left;">商品</th>
                <th style="padding:0.5rem;text-align:left;">金额</th>
                <th style="padding:0.5rem;text-align:left;">邮箱</th>
                <th style="padding:0.5rem;text-align:left;">电话</th>
                <th style="padding:0.5rem;text-align:left;">支付</th>
                <th style="padding:0.5rem;text-align:left;">状态</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>`;
      })
      .catch((err) => {
        document.getElementById('orders-table').innerHTML =
          '<p style="color:#ef4444;">加载失败：' + err.message + '</p>';
      });
  </script>
</body>
</html>
