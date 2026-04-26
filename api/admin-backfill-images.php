<?php
// ============================================================
// 回填:把数据库里所有本地路径的图片(products + settings 的 hero/social)
// 都跑一遍 ImageProcessor,生成 4 档 webp+jpg 兄弟文件。
// 幂等:已生成齐全的会跳过。可分批,通过 ?offset= 和 ?limit= 控制。
// 也可以加 ?dry=1 看会处理哪些,不实际写文件。
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/image-processor.php';
requireAdminAuth();

set_time_limit(0);
ignore_user_abort(true);

$dry    = !empty($_GET['dry']);
$offset = max(0, intval($_GET['offset'] ?? 0));
$limit  = max(1, min(200, intval($_GET['limit'] ?? 50)));

/** 一次返回的报告 */
$report = [
    'dry_run'   => $dry,
    'offset'    => $offset,
    'limit'     => $limit,
    'processed' => 0,
    'skipped'   => 0,
    'errors'    => [],
    'details'   => [],
    'next_offset' => null,
];

// 1) 收集所有本地图路径(去重)
$urls = [];

try {
    $db = getDb();

    // products 主图 + 画廊
    $stmt = $db->query("SELECT id, image_url, gallery_urls FROM products");
    while ($row = $stmt->fetch()) {
        if (!empty($row['image_url'])) $urls[] = $row['image_url'];
        if (!empty($row['gallery_urls'])) {
            $g = json_decode($row['gallery_urls'], true);
            if (is_array($g)) foreach ($g as $u) if ($u) $urls[] = $u;
        }
    }

    // site_settings 里的 hero / social / 其他可能的图
    $stmt = $db->query("SELECT `key`, `value` FROM site_settings");
    while ($row = $stmt->fetch()) {
        $v = $row['value'] ?? '';
        if (!$v) continue;
        // JSON 数组(hero_image_urls)
        if ($v[0] === '[') {
            $arr = json_decode($v, true);
            if (is_array($arr)) foreach ($arr as $u) if (is_string($u)) $urls[] = $u;
        } else {
            $urls[] = $v;
        }
    }
} catch (PDOException $e) {
    error_log('[backfill-images] settings query: ' . $e->getMessage());
}

// 也扫描 /uploads 和 /images 目录下所有原图(避免漏掉数据库没引用的)
$root = realpath(__DIR__ . '/..');
foreach (['uploads', 'images'] as $dirRel) {
    $dirAbs = $root . '/' . $dirRel;
    if (!is_dir($dirAbs)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirAbs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $name = $f->getFilename();
        // 跳过已经是 variant 的(abc-320.webp / abc-1024.jpg)
        if (preg_match('/-(320|640|1024|1600)\.(webp|jpe?g|png)$/i', $name)) continue;
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $name)) continue;
        $urls[] = '/' . $dirRel . '/' . str_replace($dirAbs . '/', '', $f->getPathname());
    }
}

// 去重,只保留本地、像图片的
$urls = array_values(array_unique(array_filter($urls, function ($u) {
    if (!is_string($u) || $u === '') return false;
    if (preg_match('#^https?://#i', $u)) return false;
    return (bool)preg_match('/\.(jpe?g|png|webp)$/i', $u);
})));
sort($urls);

$total = count($urls);
$batch = array_slice($urls, $offset, $limit);

foreach ($batch as $url) {
    $rel = ltrim($url, '/');
    $abs = $root . '/' . $rel;
    if (!is_file($abs)) {
        $report['errors'][] = "missing: $url";
        continue;
    }
    if (ImageProcessor::isProcessed($abs)) {
        $report['skipped']++;
        continue;
    }
    if ($dry) {
        $report['details'][] = ['url' => $url, 'action' => 'would-process'];
        $report['processed']++;
        continue;
    }
    try {
        $r = ImageProcessor::process($abs);
        $report['processed']++;
        $report['details'][] = ['url' => $url, 'generated' => array_values($r['generated']), 'errors' => $r['errors']];
        if (!empty($r['errors'])) {
            foreach ($r['errors'] as $e) $report['errors'][] = "$url: $e";
        }
    } catch (Throwable $e) {
        $report['errors'][] = "$url: " . $e->getMessage();
    }
}

$report['total_local_images'] = $total;
$report['done_in_this_batch'] = count($batch);
$report['next_offset'] = ($offset + $limit) < $total ? ($offset + $limit) : null;

sendJson($report);
