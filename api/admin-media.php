<?php
// 列出 uploads/ 下所有文件 + 删除
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];
$uploadsRoot = realpath(__DIR__ . '/../uploads');

if ($method === 'GET') {
    $files = [];
    if ($uploadsRoot && is_dir($uploadsRoot)) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsRoot, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iter as $f) {
            if ($f->isFile()) {
                $relPath = substr($f->getPathname(), strlen($uploadsRoot));
                $relPath = str_replace('\\', '/', $relPath);
                $files[] = [
                    'url'      => '/uploads' . $relPath,
                    'name'     => $f->getFilename(),
                    'size'     => $f->getSize(),
                    'mtime'    => date('c', $f->getMTime()),
                    'is_video' => in_array(strtolower($f->getExtension()), ['mp4','webm','mov'], true),
                ];
            }
        }
        // 按修改时间倒序
        usort($files, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));
    }
    sendJson(['files' => $files]);
}

if ($method === 'DELETE') {
    $url = $_GET['url'] ?? '';
    // 安全：只允许删 /uploads/ 下的文件
    if (!str_starts_with($url, '/uploads/')) sendJson(['error' => 'Invalid path'], 422);
    $target = realpath(__DIR__ . '/..' . $url);
    // 必须在 uploads 目录内，防 path traversal
    if (!$target || !$uploadsRoot || !str_starts_with($target, $uploadsRoot)) {
        sendJson(['error' => 'Path not allowed'], 422);
    }
    if (!file_exists($target)) sendJson(['error' => 'Not found'], 404);
    if (!unlink($target)) sendError('Delete failed', 500);
    sendJson(['success' => true]);
}

sendJson(['error' => 'Method not allowed'], 405);
