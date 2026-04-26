<?php
// ============================================================
// Image Processor - 自动生成多尺寸响应式图片
// 命名约定:abc.jpg → abc-320.webp / abc-320.jpg / abc-640.webp / ...
// 4 档:thumb 320w / card 640w / medium 1024w / large 1600w
// 默认输出 .webp(质量 78)+ 同尺寸 .jpg 兜底(质量 82)
// 前端用 <picture> + srcset 拿对应尺寸
// 依赖:GD(几乎所有 PHP 都有)+ imagewebp(PHP 7.1+ 都有)
// ============================================================

class ImageProcessor
{
    /** 4 档目标宽度 */
    public const SIZES = [
        'thumb'  => 320,    // 详情页缩略 / 列表小图
        'card'   => 640,    // 首页/列表卡片(2x retina up to 320 CSS px)
        'medium' => 1024,   // 详情页非首屏 / 平板
        'large'  => 1600,   // 详情页主图(2x retina up to 800 CSS px)
    ];
    public const WEBP_QUALITY = 78;
    public const JPEG_QUALITY = 82;

    /**
     * 处理一张本地图,生成 4 档 webp + jpg 兄弟文件。
     * @param string $absPath  绝对路径,如 /var/www/uploads/2026/04/abc.jpg
     * @return array{generated:array<string,string>, skipped:array<string>, errors:array<string>}
     *         generated 是 size => relative-filename 映射
     */
    public static function process(string $absPath): array
    {
        $result = ['generated' => [], 'skipped' => [], 'errors' => []];

        if (!is_file($absPath) || !is_readable($absPath)) {
            $result['errors'][] = "Not readable: $absPath";
            return $result;
        }

        // 不处理太小或不是 raster 的源图
        $info = @getimagesize($absPath);
        if (!$info) {
            $result['errors'][] = "Not a valid image: $absPath";
            return $result;
        }
        [$srcW, $srcH] = $info;
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $result['skipped'][] = "Unsupported mime $mime";
            return $result;
        }

        $dir  = dirname($absPath);
        $base = pathinfo($absPath, PATHINFO_FILENAME);

        // 加载源图
        $src = self::load($absPath, $mime);
        if (!$src) {
            $result['errors'][] = "Failed to load $absPath";
            return $result;
        }

        // 自动校正方向(EXIF)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($absPath);
            if (!empty($exif['Orientation'])) {
                $src = self::applyOrientation($src, (int)$exif['Orientation']);
            }
        }

        foreach (self::SIZES as $name => $targetW) {
            // 不放大:容差 5%(1000→1024 这种是无损,允许;但 320→1600 会跳)
            if ($srcW * 1.05 < $targetW) {
                $result['skipped'][] = "$name (src ${srcW}w too small for ${targetW}w)";
                continue;
            }
            $newW = $targetW;
            $newH = (int)round(imagesy($src) * ($newW / imagesx($src)));
            $resized = imagecreatetruecolor($newW, $newH);
            // 透明背景(给 png/webp)
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            // 高质量缩放
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, imagesx($src), imagesy($src));

            // webp
            $webpPath = "$dir/{$base}-{$targetW}.webp";
            if (!@imagewebp($resized, $webpPath, self::WEBP_QUALITY)) {
                $result['errors'][] = "imagewebp failed for $name";
            } else {
                @chmod($webpPath, 0644);
                $result['generated']["{$name}_webp"] = basename($webpPath);
            }

            // jpg(始终铺白底,避免透明区域变黑)
            $jpgFlat = imagecreatetruecolor($newW, $newH);
            $white = imagecolorallocate($jpgFlat, 255, 255, 255);
            imagefilledrectangle($jpgFlat, 0, 0, $newW, $newH, $white);
            imagecopy($jpgFlat, $resized, 0, 0, 0, 0, $newW, $newH);
            $jpgPath = "$dir/{$base}-{$targetW}.jpg";
            if (!@imagejpeg($jpgFlat, $jpgPath, self::JPEG_QUALITY)) {
                $result['errors'][] = "imagejpeg failed for $name";
            } else {
                @chmod($jpgPath, 0644);
                $result['generated']["{$name}_jpg"] = basename($jpgPath);
            }
            imagedestroy($jpgFlat);
            imagedestroy($resized);
        }

        imagedestroy($src);
        return $result;
    }

    /**
     * 给一个 URL 路径生成 variant URL。/uploads/2026/04/abc.jpg + 640 → /uploads/2026/04/abc-640.webp
     * 远程 URL(http/https)直接返回原值。
     */
    public static function variantUrl(string $url, int $width, string $ext = 'webp'): string
    {
        if (preg_match('#^https?://#i', $url)) return $url;
        if ($url === '') return $url;
        $info = pathinfo($url);
        $dir  = $info['dirname'] ?? '';
        $name = $info['filename'] ?? '';
        if ($dir === '' || $name === '') return $url;
        // 已经是 variant 形式 abc-640.jpg 时去掉后缀
        $name = preg_replace('/-(\d{2,4})$/', '', $name);
        return rtrim($dir, '/') . '/' . $name . '-' . $width . '.' . $ext;
    }

    private static function load(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($path);
            case 'image/png':  return @imagecreatefrompng($path);
            case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        }
        return null;
    }

    /** EXIF orientation 1..8 → 旋转/翻转后的新图 */
    private static function applyOrientation($img, int $orientation)
    {
        switch ($orientation) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 3: $img = imagerotate($img, 180, 0); break;
            case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($img, IMG_FLIP_VERTICAL); $img = imagerotate($img, 270, 0); break;
            case 6: $img = imagerotate($img, 270, 0); break;
            case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, 270, 0); break;
            case 8: $img = imagerotate($img, 90, 0); break;
        }
        return $img;
    }

    /**
     * 已生成?判断 abs 路径对应的 variant 文件是否齐全(用于回填脚本跳过已处理的)。
     * @param string $absPath 绝对路径
     * @return bool 4 档(对源图小于自身的档)是否都已生成
     */
    public static function isProcessed(string $absPath): bool
    {
        if (!is_file($absPath)) return false;
        $info = @getimagesize($absPath);
        if (!$info) return false;
        $srcW = $info[0];
        $dir  = dirname($absPath);
        $base = pathinfo($absPath, PATHINFO_FILENAME);
        foreach (self::SIZES as $w) {
            if ($srcW * 1.05 < $w) continue;
            if (!is_file("$dir/{$base}-{$w}.webp")) return false;
            if (!is_file("$dir/{$base}-{$w}.jpg")) return false;
        }
        return true;
    }
}
