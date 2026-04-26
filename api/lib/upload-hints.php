<?php
// ============================================================
// Upload Hints - 在 admin 上传位置统一展示"该上传什么样的图"提示
// 用法:
//   require_once __DIR__ . '/../api/lib/upload-hints.php';
//   echo uploadHint('hero', $lang);   // 输出 HTML 卡片
//
// 也提供 JSON 给前端 JS 做实时尺寸校验:
//   echo '<script>window.GE_UPLOAD_HINTS = ' . json_encode(uploadHintsJson()) . ';</script>';
// ============================================================

/** 单一来源:每个上传位置的尺寸 / 比例 / 用途 / 最低要求 */
function uploadHintsData(): array
{
    return [
        'hero' => [
            'icon' => '🎬',
            'use' => [
                'en' => 'Homepage banner — shown 100% width with text overlay on the left',
                'zh' => '首页横幅大图 — 全宽展示,文字叠在左侧',
            ],
            'best_size'  => '1920 × 1080',
            'aspect'     => '16:9',
            'min_size'   => '1280 × 720',
            'composition'=> [
                'en' => 'Landscape · keep the right 50% relatively empty so the headline stays readable',
                'zh' => '横向构图 · 主体偏左,右侧留白让标题文字可读',
            ],
            'format' => 'JPG / WebP',
            'max_kb' => 5000,
        ],
        'product' => [
            'icon' => '💄',
            'use' => [
                'en' => 'Product card (shop list) + product detail page',
                'zh' => '商品列表卡片 + 商品详情页主图',
            ],
            'best_size'  => '1200 × 1200',
            'aspect'     => '1:1 (square)',
            'min_size'   => '800 × 800',
            'composition'=> [
                'en' => 'Centered subject, soft seamless background, even lighting. Up to 5 angles for the gallery.',
                'zh' => '主体居中,纯色或柔和背景,均匀光线。可放最多 5 张多角度图。',
            ],
            'format' => 'JPG / WebP / PNG (透明背景)',
            'max_kb' => 3000,
        ],
        'category' => [
            'icon' => '🗂',
            'use' => [
                'en' => 'Category card on homepage — shown vertical 4:5',
                'zh' => '首页分类卡 — 竖向 4:5 展示',
            ],
            'best_size'  => '800 × 1000',
            'aspect'     => '4:5 (portrait)',
            'min_size'   => '600 × 750',
            'composition'=> [
                'en' => 'Vertical · subject in upper 60%, lower portion absorbs gradient overlay + text',
                'zh' => '竖向 · 主体在上 60%,底部 40% 会被渐变和分类文字覆盖',
            ],
            'format' => 'JPG / WebP',
            'max_kb' => 2000,
        ],
        'video_cover' => [
            'icon' => '🎥',
            'use' => [
                'en' => 'TikTok-style video cover thumbnail',
                'zh' => 'TikTok 风格竖屏视频封面',
            ],
            'best_size'  => '1080 × 1920',
            'aspect'     => '9:16 (portrait)',
            'min_size'   => '720 × 1280',
            'composition'=> [
                'en' => 'Vertical · keep important content centered (avoid top/bottom 10%)',
                'zh' => '竖屏 · 重点信息居中,上下各 10% 可能被 UI 遮挡',
            ],
            'format' => 'JPG / WebP',
            'max_kb' => 2000,
        ],
        'media' => [
            'icon' => '🖼',
            'use' => [
                'en' => 'General media library — for blog posts, custom banners, etc.',
                'zh' => '通用媒体库 — 给博客、自定义 banner 等用',
            ],
            'best_size'  => '1600 × 1200',
            'aspect'     => 'Any (4:3 or 16:9 most common)',
            'min_size'   => '800 × 600',
            'composition'=> [
                'en' => 'No restriction — system auto-generates 4 responsive variants on upload',
                'zh' => '无固定要求 · 上传后自动生成 4 档响应式版本',
            ],
            'format' => 'JPG / PNG / WebP / GIF',
            'max_kb' => 10000,
        ],
        'avatar' => [
            'icon' => '👤',
            'use' => [
                'en' => 'User profile picture',
                'zh' => '用户头像',
            ],
            'best_size'  => '400 × 400',
            'aspect'     => '1:1 (square)',
            'min_size'   => '200 × 200',
            'composition'=> [
                'en' => 'Centered face, will be cropped to circle on display',
                'zh' => '面部居中,前端会剪裁成圆形',
            ],
            'format' => 'JPG / PNG / WebP',
            'max_kb' => 1000,
        ],
    ];
}

/**
 * 渲染一个 upload hint 卡片(HTML)。
 * @param string $key   位置键 hero/product/category/video_cover/media/avatar
 * @param string $lang  'en' or 'zh'
 */
function uploadHint(string $key, string $lang = 'en'): string
{
    $data = uploadHintsData();
    if (!isset($data[$key])) return '';
    $h = $data[$key];
    $isZh = ($lang === 'zh');
    $L = function (array $arr) use ($isZh) { return $isZh ? ($arr['zh'] ?? $arr['en']) : ($arr['en'] ?? ''); };
    $rec = $isZh ? '推荐尺寸' : 'Recommended size';
    $useL  = $isZh ? '用途' : 'Used for';
    $aspL  = $isZh ? '比例' : 'Aspect';
    $minL  = $isZh ? '最低' : 'Minimum';
    $compL = $isZh ? '构图建议' : 'Composition tip';
    $fmtL  = $isZh ? '格式' : 'Format';
    $maxL  = $isZh ? '最大文件' : 'Max size';
    $maxMb = number_format($h['max_kb'] / 1000, 1) . ' MB';

    $bestEsc  = htmlspecialchars($h['best_size']);
    $minEsc   = htmlspecialchars($h['min_size']);
    $aspEsc   = htmlspecialchars($h['aspect']);
    $fmtEsc   = htmlspecialchars($h['format']);
    $useEsc   = htmlspecialchars($L($h['use']));
    $compEsc  = htmlspecialchars($L($h['composition']));
    $icon     = $h['icon'];

    return <<<HTML
<div class="upload-hint" data-hint-key="{$key}">
  <div class="upload-hint-head">
    <span class="upload-hint-icon">{$icon}</span>
    <div>
      <strong class="upload-hint-title">{$rec}: <span class="upload-hint-size">{$bestEsc}</span> <small>({$aspEsc})</small></strong>
      <small class="upload-hint-use">{$useEsc}</small>
    </div>
  </div>
  <ul class="upload-hint-list">
    <li><strong>{$compL}:</strong> {$compEsc}</li>
    <li><strong>{$minL}:</strong> {$minEsc} · <strong>{$fmtL}:</strong> {$fmtEsc} · <strong>{$maxL}:</strong> {$maxMb}</li>
  </ul>
</div>
HTML;
}

/** 给前端 JS 用的精简数据(尺寸校验用) */
function uploadHintsJson(): array
{
    $out = [];
    foreach (uploadHintsData() as $k => $h) {
        // 解析 best_size 和 min_size 为像素数
        $parse = function ($s) {
            if (preg_match('/(\d+)\s*[×x]\s*(\d+)/', $s, $m)) return ['w' => (int)$m[1], 'h' => (int)$m[2]];
            return null;
        };
        $out[$k] = [
            'best'   => $parse($h['best_size']),
            'min'    => $parse($h['min_size']),
            'aspect' => $h['aspect'],
            'max_kb' => $h['max_kb'],
        ];
    }
    return $out;
}
