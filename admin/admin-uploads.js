// ============================================================
// Admin: 上传图片实时尺寸校验
// 给所有 <label data-hint="<key>"> 内部的 <input type=file> 自动绑定 change:
// 上传前 new Image() 探测尺寸,跟 GE_UPLOAD_HINTS[key] 对比,展示提示。
// 提示会插入到 label.parentNode 之后,id 不冲突。
// ============================================================
(function () {
  'use strict';
  if (!window.GE_UPLOAD_HINTS) return;

  const HINTS = window.GE_UPLOAD_HINTS;

  function ensureWarnEl(triggerEl) {
    let w = triggerEl.parentNode.querySelector(':scope > .upload-warn');
    if (!w) {
      w = document.createElement('div');
      w.className = 'upload-warn';
      triggerEl.parentNode.insertBefore(w, triggerEl.nextSibling);
    }
    return w;
  }
  function show(el, level, html) {
    el.className = 'upload-warn ' + level;
    el.innerHTML = html;
    el.style.display = 'block';
  }
  function clear(el) { el.innerHTML = ''; el.style.display = 'none'; }

  function checkOne(file, hint) {
    return new Promise((resolve) => {
      const issues = [];

      // 文件大小
      const sizeKb = file.size / 1024;
      if (hint.max_kb && sizeKb > hint.max_kb) {
        issues.push({
          level: 'error',
          msg: `<strong>File too large:</strong> ${(sizeKb/1024).toFixed(1)} MB · max ${(hint.max_kb/1000).toFixed(1)} MB. Compress with <a href="https://squoosh.app" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">squoosh.app</a> first.`,
        });
      }

      // 视频跳过尺寸校验
      if (file.type.startsWith('video/')) {
        return resolve(issues);
      }

      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        URL.revokeObjectURL(url);
        const w = img.naturalWidth, h = img.naturalHeight;

        if (hint.min && (w < hint.min.w || h < hint.min.h)) {
          issues.push({
            level: 'error',
            msg: `<strong>Too small:</strong> uploaded ${w}×${h}, minimum is ${hint.min.w}×${hint.min.h}. Will look blurry on retina screens.`,
          });
        } else if (hint.best && (w < hint.best.w * 0.8 || h < hint.best.h * 0.8)) {
          issues.push({
            level: 'warn',
            msg: `<strong>Smaller than recommended:</strong> ${w}×${h} (recommended ${hint.best.w}×${hint.best.h}). It will work but might be slightly soft.`,
          });
        }

        // aspect ratio 校验
        if (hint.best) {
          const expected = hint.best.w / hint.best.h;
          const actual = w / h;
          const drift = Math.abs(actual - expected) / expected;
          if (drift > 0.15) {
            const got = (actual >= 1) ? `${actual.toFixed(2)}:1` : `1:${(1/actual).toFixed(2)}`;
            issues.push({
              level: 'warn',
              msg: `<strong>Aspect ratio mismatch:</strong> uploaded ${got}, recommended <strong>${hint.aspect}</strong>. Image will be cropped to fit — make sure the focal point is centered.`,
            });
          }
        }

        if (issues.length === 0) {
          issues.push({
            level: 'ok',
            msg: `<strong>✓ Looks good:</strong> ${w}×${h}, ${(sizeKb/1024).toFixed(2)} MB. Will be auto-optimized into 4 responsive variants.`,
          });
        }
        resolve(issues);
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        issues.push({ level: 'error', msg: `<strong>Cannot read image.</strong> Make sure it's a valid jpg/png/webp.` });
        resolve(issues);
      };
      img.src = url;
    });
  }

  document.addEventListener('change', async (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    // 找 [data-hint] 父级
    const trigger = input.closest('[data-hint]');
    if (!trigger) return;
    const key = trigger.dataset.hint;
    const hint = HINTS[key];
    if (!hint) return;

    const warn = ensureWarnEl(trigger);
    clear(warn);

    const files = Array.from(input.files || []);
    if (!files.length) return;

    // 多文件:逐个收集 issues,展示合并报告
    const reports = [];
    for (const f of files) {
      const issues = await checkOne(f, hint);
      reports.push({ file: f, issues });
    }
    // 渲染:每个文件一行,只展示最高优先级 issue
    const order = { error: 0, warn: 1, ok: 2 };
    const lines = reports.map(r => {
      r.issues.sort((a, b) => order[a.level] - order[b.level]);
      const top = r.issues[0];
      const tag = top.level === 'error' ? '✗' : (top.level === 'warn' ? '⚠' : '✓');
      const fname = r.file.name.length > 30 ? r.file.name.slice(0, 27) + '…' : r.file.name;
      return `<div style="margin:.15rem 0;"><code style="background:rgba(0,0,0,.2);padding:1px 5px;border-radius:2px;">${fname}</code> ${tag} ${top.msg}</div>`;
    });
    const overallLevel = reports.some(r => r.issues[0].level === 'error') ? 'error'
                       : (reports.some(r => r.issues[0].level === 'warn') ? 'warn' : 'ok');
    show(warn, overallLevel, lines.join(''));
  }, true);
})();
