<?php
// Self-healing schema for account_banners(账户后台 banner 广告位)
// 跟 before-after 一样 — 首次访问自动建表 + 首次部署种 1 条 default

function ensureAccountBannerSchema(PDO $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS account_banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image_url VARCHAR(500) NOT NULL,
                title VARCHAR(200) DEFAULT '',
                subtitle VARCHAR(400) DEFAULT '',
                cta_text VARCHAR(60) DEFAULT '',
                cta_url VARCHAR(500) DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // sentinel:只在首次部署种入,以后无论怎么删都不复活
        $seeded = (int)$db->query(
            "SELECT COUNT(*) FROM site_settings WHERE `key` = 'account_banner_seeded'"
        )->fetchColumn();
        if (!$seeded) {
            $stmt = $db->prepare(
                "INSERT IGNORE INTO account_banners
                   (image_url, title, subtitle, cta_text, cta_url, sort_order, is_active)
                 VALUES (:img, :t, :st, :ct, :cu, 1, 1)"
            );
            $stmt->execute([
                ':img' => '/images/products/GE-CK-NATURAL/main-1024.jpg',
                ':t'   => 'Members get 10% off',
                ':st'  => 'Welcome to GlamEye — your first cluster kit ships free over $50.',
                ':ct'  => 'Shop Cluster Kits →',
                ':cu'  => '/shop.html',
            ]);
            $db->exec("INSERT INTO site_settings (`key`, `value`) VALUES ('account_banner_seeded', '1')
                       ON DUPLICATE KEY UPDATE `value` = '1'");
        }
    } catch (Throwable $e) {
        error_log('[account-banner-schema] bootstrap failed: ' . $e->getMessage());
    }
}
