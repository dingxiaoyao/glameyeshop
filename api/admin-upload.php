<?php
// ============================================================
// 文件上传 (admin only)
// 接收 multipart/form-data, field: file
// 校验 MIME + 扩展名 + 大小
// 保存到 /uploads/YYYY/MM/<random>.<ext>
// 返回 { url: '/uploads/2026/04/abc.jpg', size: 12345 }
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/image-processor.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// 限制 + 白名单
const MAX_IMAGE_SIZE = 10 * 1024 * 1024;     // 10MB
const MAX_VIDEO_SIZE = 200 * 1024 * 1024;    // 200MB
const ALLOWED = [
    // image
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    // video
    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
];

if (empty($_FILES['file'])) {
    sendJson(['error' => 'No file field. Use multipart/form-data with field name "file".'], 422);
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize (check server PHP config)',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'Partial upload',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION => 'PHP extension blocked upload',
    ];
    sendJson(['error' => $errors[$file['error']] ?? 'Upload failed (code ' . $file['error'] . ')'], 422);
}

// 检测 MIME（忽略客户端发的，自己用 finfo 检测）
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!isset(ALLOWED[$mime])) {
    sendJson(['error' => "Unsupported type: $mime. Allowed: jpg, png, webp, gif, mp4, webm, mov"], 422);
}

$isVideo = str_starts_with($mime, 'video/');
$maxSize = $isVideo ? MAX_VIDEO_SIZE : MAX_IMAGE_SIZE;
if ($file['size'] > $maxSize) {
    $maxMb = $maxSize / 1024 / 1024;
    sendJson(['error' => "File too large. Max ${maxMb}MB for " . ($isVideo ? 'videos' : 'images')], 422);
}

// 目标路径：uploads/YYYY/MM/<rand>.<ext>
$ext = ALLOWED[$mime];
$year = date('Y');
$month = date('m');
$dir = __DIR__ . '/../uploads/' . $year . '/' . $month;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    sendError('Cannot create upload directory', 500);
}

$rand = bin2hex(random_bytes(8));
$filename = $rand . '.' . $ext;
$destPath = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    sendError('Failed to save file', 500);
}
@chmod($destPath, 0644);

$publicUrl = '/uploads/' . $year . '/' . $month . '/' . $filename;

// 图片自动生成 4 档响应式版本(thumb/card/medium/large × webp+jpg)
$variants = [];
$processError = null;
if (!$isVideo) {
    try {
        $r = ImageProcessor::process($destPath);
        $variants = $r['generated'];
        if (!empty($r['errors'])) {
            $processError = implode('; ', $r['errors']);
            error_log('[admin-upload] image-processor errors: ' . $processError);
        }
    } catch (Throwable $e) {
        $processError = $e->getMessage();
        error_log('[admin-upload] image-processor exception: ' . $processError);
    }
}

sendJson([
    'success'        => true,
    'url'            => $publicUrl,
    'size'           => filesize($destPath),
    'mime'           => $mime,
    'is_video'       => $isVideo,
    'filename'       => $filename,
    'variants'       => $variants,        // { thumb_webp:'abc-320.webp', card_webp:'abc-640.webp', ... }
    'process_error'  => $processError,    // null 表示正常
]);
