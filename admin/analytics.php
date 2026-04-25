<?php $pageTitle = 'Analytics'; $activeNav = 'analytics'; require __DIR__ . '/_layout.php'; ?>
<h1>📈 <?= $lang === 'zh' ? '访客统计' : 'Visitor Analytics' ?></h1>

<div class="filter-bar" style="margin-bottom:1.5rem;">
  <label class="muted small">
    <input type="checkbox" id="show-bots" /> Include bots/crawlers
  </label>
  <button id="refresh-btn" class="filter-btn" style="margin-left:auto;">🔄 Refresh</button>
</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label"><?= $lang === 'zh' ? '今日访问' : 'Today Views' ?></div>
    <div class="kpi-value" id="kpi-today-views">—</div>
    <div class="kpi-trend"><span id="kpi-today-uniq">—</span> unique IPs</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= $lang === 'zh' ? '本周访问' : '7-Day Views' ?></div>
    <div class="kpi-value" id="kpi-week-views">—</div>
    <div class="kpi-trend"><span id="kpi-week-uniq">—</span> unique IPs</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= $lang === 'zh' ? '本月访问' : '30-Day Views' ?></div>
    <div class="kpi-value" id="kpi-month-views">—</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= $lang === 'zh' ? '累计访问' : 'Total Views' ?></div>
    <div class="kpi-value" id="kpi-total-views">—</div>
    <div class="kpi-trend"><span id="kpi-total-ips">—</span> total unique IPs</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Bot Traffic</div>
    <div class="kpi-value" id="kpi-bot-views" style="color: var(--text-muted);">—</div>
  </div>
</div>

<div class="admin-card">
  <h3><?= $lang === 'zh' ? '30 天访问曲线' : '30-Day Traffic' ?></h3>
  <div class="chart-bars" id="traffic-chart"></div>
  <div class="chart-labels" id="traffic-labels"></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
  <div class="admin-card">
    <h3><?= $lang === 'zh' ? '热门页面' : 'Top Pages' ?></h3>
    <div id="top-pages"></div>
  </div>
  <div class="admin-card">
    <h3><?= $lang === 'zh' ? '流量来源' : 'Top Referrers' ?></h3>
    <div id="top-referrers"></div>
  </div>
</div>

<div class="admin-card">
  <h3><?= $lang === 'zh' ? 'Top 访客 IP' : 'Top Visitor IPs' ?></h3>
  <div id="top-ips" style="overflow-x:auto;"></div>
</div>

<div class="admin-card">
  <h3><?= $lang === 'zh' ? '最近 50 条访问' : 'Last 50 Visits' ?></h3>
  <div id="recent" style="overflow-x:auto;"></div>
</div>

<script>
(function () {
  const showBots = document.getElementById('show-bots');
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function load() {
    const url = '../api/admin-analytics.php' + (showBots.checked ? '?bots=1' : '');
    try {
      const r = await fetch(url, { credentials:'include' });
      const j = await r.json();
      const k = j.kpi;
      document.getElementById('kpi-today-views').textContent = k.today_views;
      document.getElementById('kpi-today-uniq').textContent  = k.today_uniq;
      document.getElementById('kpi-week-views').textContent  = k.week_views;
      document.getElementById('kpi-week-uniq').textContent   = k.week_uniq;
      document.getElementById('kpi-month-views').textContent = k.month_views;
      document.getElementById('kpi-total-views').textContent = k.total_views;
      document.getElementById('kpi-total-ips').textContent   = k.total_uniq_ips;
      document.getElementById('kpi-bot-views').textContent   = k.bot_views;

      // Chart
      const chart = j.chart_30d || [];
      const max = Math.max(...chart.map(d => Number(d.views)), 1);
      const cEl = document.getElementById('traffic-chart');
      const lEl = document.getElementById('traffic-labels');
      cEl.innerHTML = ''; lEl.innerHTML = '';
      if (chart.length === 0) {
        cEl.innerHTML = '<div style="margin:auto; color:var(--text-muted)">No data yet</div>';
      } else {
        chart.forEach(d => {
          const bar = document.createElement('div');
          bar.className = 'chart-bar';
          bar.style.height = (Number(d.views) / max * 100) + '%';
          bar.dataset.tooltip = `${d.d}: ${d.views} views, ${d.uniq} unique`;
          cEl.appendChild(bar);
          const lbl = document.createElement('span');
          lbl.textContent = d.d.slice(5);
          lEl.appendChild(lbl);
        });
      }

      // Top pages
      const tp = j.top_pages || [];
      document.getElementById('top-pages').innerHTML = tp.length
        ? '<table class="admin-table"><thead><tr><th>Path</th><th style="text-align:right;">Views</th><th style="text-align:right;">Unique</th></tr></thead><tbody>'
          + tp.map(p => `<tr><td><code style="color:var(--gold);">${escape(p.path)}</code></td><td style="text-align:right;">${p.views}</td><td style="text-align:right;">${p.uniq}</td></tr>`).join('')
          + '</tbody></table>'
        : '<p class="muted">No data.</p>';

      // Top referrers
      const tr = j.top_referrers || [];
      document.getElementById('top-referrers').innerHTML = tr.length
        ? '<table class="admin-table"><thead><tr><th>Source</th><th style="text-align:right;">Views</th></tr></thead><tbody>'
          + tr.map(r => `<tr><td>${escape(r.source)}</td><td style="text-align:right;">${r.views}</td></tr>`).join('')
          + '</tbody></table>'
        : '<p class="muted">No data.</p>';

      // Top IPs
      const ips = j.top_ips || [];
      document.getElementById('top-ips').innerHTML = ips.length
        ? '<table class="admin-table"><thead><tr><th>IP</th><th>Views</th><th>Last Seen</th><th>Recent Paths</th></tr></thead><tbody>'
          + ips.map(r => `<tr>
              <td><code style="color:var(--gold);">${escape(r.ip)}</code></td>
              <td><strong>${r.views}</strong></td>
              <td><small class="muted">${escape(r.last_seen)}</small></td>
              <td><small class="muted">${escape(r.recent_paths)}</small></td>
            </tr>`).join('')
          + '</tbody></table>'
        : '<p class="muted">No data.</p>';

      // Recent
      const recent = j.recent || [];
      document.getElementById('recent').innerHTML = recent.length
        ? '<table class="admin-table"><thead><tr><th>Time</th><th>IP</th><th>Path</th><th>Referer</th><th>UA</th></tr></thead><tbody>'
          + recent.map(r => `<tr style="${r.is_bot==1?'opacity:.5':''}">
              <td><small class="muted">${escape(r.created_at)}</small></td>
              <td><code style="color:var(--gold);">${escape(r.ip)}</code>${r.is_bot==1?' <small style="color:var(--text-muted)">[bot]</small>':''}</td>
              <td><small>${escape(r.path)}</small></td>
              <td><small class="muted" style="display:block; max-width:160px; overflow:hidden; text-overflow:ellipsis;">${escape(r.referer || '—')}</small></td>
              <td><small class="muted" style="display:block; max-width:200px; overflow:hidden; text-overflow:ellipsis;">${escape(r.user_agent || '—')}</small></td>
            </tr>`).join('')
          + '</tbody></table>'
        : '<p class="muted">No data.</p>';
    } catch (e) {
      alert('Load failed: ' + e.message);
    }
  }

  showBots.addEventListener('change', load);
  document.getElementById('refresh-btn').addEventListener('click', load);
  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
