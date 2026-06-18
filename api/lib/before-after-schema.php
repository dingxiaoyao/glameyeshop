<?php
// 自动 bootstrap before_after_pairs 表 + 种子数据
// 如果 setup.sql 部署时未执行(因 mysql 2>/dev/null 吞错或 awk 过滤误伤),
// API 第一次被访问时自动建表 — 不依赖部署流程是否成功。
//
// 全部操作幂等:CREATE TABLE IF NOT EXISTS + INSERT IGNORE。
// 失败静默(用 try/catch),不会因为这个 helper 把主请求拖死。

function ensureBeforeAfterSchema(PDO $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS before_after_pairs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                before_image_url VARCHAR(500) NOT NULL,
                before_label VARCHAR(100) NOT NULL DEFAULT 'Before',
                after_image_url VARCHAR(500) NOT NULL,
                after_label VARCHAR(100) NOT NULL DEFAULT 'After',
                alt_text VARCHAR(200) DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // ⚠️ 重要:只在「首次部署」种入,以后无论用户怎么删都不复活
        // 用 site_settings 里 ba_seeded sentinel 标记,COUNT==0 不再触发种子
        // (修复:之前用 COUNT==0 判断 → 用户全删后下次访问 API 又种回来 → 删不掉)
        $seeded = (int)$db->query(
            "SELECT COUNT(*) FROM site_settings WHERE `key` = 'ba_seeded'"
        )->fetchColumn();
        if (!$seeded) {
            $stmt = $db->prepare(
                "INSERT IGNORE INTO before_after_pairs
                   (id, before_image_url, before_label, after_image_url, after_label, alt_text, sort_order, is_active)
                 VALUES (:id, :bi, :bl, :ai, :al, :alt, :sort, 1)"
            );
            $stmt->execute([
                ':id' => 1,
                ':bi' => '/images/lash-photos/style-09-feather-eye-640.jpg',
                ':bl' => 'Before',
                ':ai' => '/images/lash-photos/style-18-velvet-eye-640.jpg',
                ':al' => 'After · Naked 18mm',
                ':alt' => 'Naked 18mm Mink before/after',
                ':sort' => 1,
            ]);
            $stmt->execute([
                ':id' => 2,
                ':bi' => '/images/lash-photos/style-14-frost-eye-640.jpg',
                ':bl' => 'Daytime',
                ':ai' => '/images/lash-photos/style-18-velvet-split-640.jpg',
                ':al' => 'Night Out · Diamond 25mm',
                ':alt' => 'Diamond 25mm day-to-night',
                ':sort' => 2,
            ]);
            // 标记已 seed,以后不再触发(即使用户全删也不会复活)
            $db->exec("INSERT INTO site_settings (`key`, `value`) VALUES ('ba_seeded', '1')
                       ON DUPLICATE KEY UPDATE `value` = '1'");
        }

        // 文案默认值(只在 key 不存在时插入)
        $db->exec("
            INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
              ('ba_section_subtitle', 'What 18mm of premium mink does to your eyes — no filter, no retouching.'),
              ('ba_section_footer',   'More real-customer transformations coming — tag @glameye on Instagram to be featured.')
        ");
    } catch (Throwable $e) {
        error_log('[before-after-schema] bootstrap failed: ' . $e->getMessage());
        // 静默继续 — 让 API 主流程自己处理 SQL 错误
    }
}
