-- ============================================================
-- GlamEye Lashes - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS glameyeshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE glameyeshop;

-- 产品表
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,           -- mink / faux / magnetic / tools
    style VARCHAR(50) DEFAULT '',            -- natural / wispy / dramatic / cat-eye
    name VARCHAR(200) NOT NULL,
    short_description VARCHAR(500) DEFAULT '',
    description TEXT,
    length_mm INT DEFAULT NULL,              -- 14, 18, 22, 25
    band_type VARCHAR(50) DEFAULT 'cotton',  -- cotton / clear / silk
    reusable_count INT DEFAULT NULL,         -- 15, 20, 25
    price DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2) DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT '',
    gallery_urls TEXT DEFAULT NULL,          -- JSON array of additional images
    stock INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
    is_new TINYINT(1) NOT NULL DEFAULT 0,
    is_bundle TINYINT(1) NOT NULL DEFAULT 0,             -- P2: 套装 SKU 标记
    bundle_items TEXT DEFAULT NULL,                       -- P2: JSON [{sku,qty}, ...]
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_is_bundle (is_bundle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户账号
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) DEFAULT NULL,         -- NULL for OAuth-only users
    first_name VARCHAR(100) NOT NULL DEFAULT '',
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    phone VARCHAR(64) DEFAULT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    is_subscribed TINYINT(1) NOT NULL DEFAULT 0,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_test_account TINYINT(1) NOT NULL DEFAULT 0,    -- admin-flagged test buyer (no real $ charge)
    oauth_provider VARCHAR(20) DEFAULT NULL,          -- google / tiktok / null
    oauth_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_oauth (oauth_provider, oauth_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) NOT NULL DEFAULT 'Home',
    full_name VARCHAR(200) NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) DEFAULT '',
    city VARCHAR(100) NOT NULL,
    state VARCHAR(64) NOT NULL,
    postal_code VARCHAR(32) NOT NULL,
    country VARCHAR(64) NOT NULL DEFAULT 'US',
    phone VARCHAR(64) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    customer_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    address_line VARCHAR(255) NOT NULL DEFAULT '',
    address_line2 VARCHAR(255) DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    state VARCHAR(64) NOT NULL DEFAULT '',
    postal_code VARCHAR(32) NOT NULL DEFAULT '',
    country VARCHAR(64) NOT NULL DEFAULT 'US',
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL,
    payment_session_id VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    notes TEXT,
    -- 物流追踪
    carrier VARCHAR(50) DEFAULT NULL,                  -- USPS / UPS / FedEx / DHL / Other
    tracking_number VARCHAR(100) DEFAULT NULL,
    tracking_url VARCHAR(500) DEFAULT NULL,            -- override (optional)
    shipped_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    estimated_delivery DATE DEFAULT NULL,
    -- 测试订单标记
    is_test TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_tracking (tracking_number),
    INDEX idx_is_test (is_test)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 物流状态历史（每次更新都追加，给买家看时间线）
CREATE TABLE IF NOT EXISTS order_tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,                       -- created / paid / processing / shipped / in_transit / out_for_delivery / delivered / exception
    description VARCHAR(500) DEFAULT '',
    location VARCHAR(200) DEFAULT '',
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(64) DEFAULT '',
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    source VARCHAR(50) NOT NULL DEFAULT 'website',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wholesale_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    contact VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    message TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论 (P2 Reviews)
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT DEFAULT NULL,                  -- NULL 允许 admin 后台手添 / seed
    order_id INT DEFAULT NULL,                 -- 关联订单(给"Verified Buyer"打底)
    reviewer_name VARCHAR(100) NOT NULL DEFAULT '',
    reviewer_location VARCHAR(100) DEFAULT '', -- "Los Angeles, CA"
    rating TINYINT NOT NULL,                   -- 1-5
    title VARCHAR(200) DEFAULT '',
    body TEXT NOT NULL,
    photo_urls TEXT DEFAULT NULL,              -- JSON array of /uploads/... 或外链
    status VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending / approved / rejected
    is_verified_buyer TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,      -- 首页 reviews 区精选
    helpful_count INT NOT NULL DEFAULT 0,
    seed_key VARCHAR(64) DEFAULT NULL,          -- 种子专用唯一键(用户提交始终为 NULL)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_seed (seed_key),            -- NULL 不参与唯一,seed 才幂等
    INDEX idx_product (product_id, status),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户上传的 UGC(已购客户的穿戴照,首页 grid 展示)
CREATE TABLE IF NOT EXISTS ugc_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT '',
    instagram_handle VARCHAR(100) DEFAULT '',  -- "@username" 不含 @
    related_product_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending / approved / rejected
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort (sort_order),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 升级:products 表加 bundle 标识与 components(JSON 数组,每项 {sku, qty})
-- MySQL 不支持 `ADD COLUMN IF NOT EXISTS`,用 information_schema 条件判断保证幂等
DROP PROCEDURE IF EXISTS add_bundle_cols;
DELIMITER //
CREATE PROCEDURE add_bundle_cols()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'is_bundle') THEN
    ALTER TABLE products ADD COLUMN is_bundle TINYINT(1) NOT NULL DEFAULT 0;
    ALTER TABLE products ADD INDEX idx_is_bundle (is_bundle);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'bundle_items') THEN
    ALTER TABLE products ADD COLUMN bundle_items TEXT DEFAULT NULL;
  END IF;
END //
DELIMITER ;
CALL add_bundle_cols();
DROP PROCEDURE add_bundle_cols;

-- TikTok 视频展示
CREATE TABLE IF NOT EXISTS tiktok_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(64) NOT NULL,            -- TikTok 视频 ID（数字）
    creator_handle VARCHAR(100) NOT NULL,     -- 作者 @handle（不含 @）
    video_url VARCHAR(500) NOT NULL,          -- 完整 TikTok URL
    title VARCHAR(255) DEFAULT '',
    description TEXT,
    cover_url VARCHAR(500) DEFAULT '',        -- 封面图
    related_product_id INT DEFAULT NULL,      -- 关联产品（可选）
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 站点配置（社交链接、TikTok handle 等）
CREATE TABLE IF NOT EXISTS site_settings (
    `key` VARCHAR(64) PRIMARY KEY,
    `value` TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 访客统计
CREATE TABLE IF NOT EXISTS page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(500) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) DEFAULT '',
    referer VARCHAR(500) DEFAULT '',
    country VARCHAR(8) DEFAULT '',
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_path (path(191)),
    INDEX idx_ip (ip),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'percent',
    value DECIMAL(10,2) NOT NULL,
    min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses INT DEFAULT NULL,
    used_count INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 默认社交媒体设置
-- ============================================================
INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
('social_tiktok',      'https://www.tiktok.com/@glameye'),
('social_instagram',   'https://instagram.com/glameye'),
('social_youtube',     'https://youtube.com/@glameye'),
('social_pinterest',   'https://pinterest.com/glameye'),
('social_facebook',    'https://facebook.com/glameye'),
('amazon_store_url',   ''),
('amazon_status',      'coming_soon'),
('hero_image_url',     '/images/lash-photos/style-14-frost-split.jpg'),
-- 多图轮播（JSON array）。如果非空，覆盖 hero_image_url。前端 5s 切换
('hero_image_urls',    '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-17-ice-split.jpg","/images/lash-photos/style-18-velvet-split.jpg"]'),
('hero_slide_interval','5000'),
('seo_blocked',        '1'),
-- 支付配置（PRIVATE - 不通过 api/settings.php 暴露）
('stripe_publishable_key', ''),
('stripe_secret_key',      ''),
('stripe_webhook_secret',  ''),
('stripe_mode',            'test'),  -- test / live
('paypal_client_id',       ''),
('paypal_secret',          ''),
('paypal_mode',            'sandbox');  -- sandbox / live

-- ============================================================
-- 种子产品：18 个 SKU 覆盖 mink/faux/magnetic/tools
-- 命名遵循行业风格（descriptive + 长度 mm + 风格），均为原创
-- 价格区间：$10-$58 对标主流美国 lash 品牌
-- ============================================================
INSERT IGNORE INTO products
  (sku, category, style, name, short_description, description, length_mm, band_type, reusable_count,
   price, compare_at_price, image_url, gallery_urls, stock, is_bestseller, is_new, sort_order)
VALUES
-- ===== Mink Collection (8 SKUs) =====
('GE-MINK-001', 'mink', 'natural',  'Whisper 14mm Mink',
 'Soft natural everyday lash · 14mm', 'Lightweight 14mm mink hairs on a clear band. Perfect for office, daily wear, no-makeup makeup looks. Cruelty-free, reusable up to 20 wears.',
 14, 'clear', 20, 18.00, 24.00, '/images/lash-photos/style-09-feather-eye.jpg',
 '["/images/lash-photos/style-09-feather-alt.jpg","/images/products/lash-natural-18.jpg","/images/products/lash-wispy-14.jpg"]', 220, 1, 0, 1),

('GE-MINK-002', 'mink', 'wispy',    'Featherlight 16mm Mink',
 'Wispy effect, weightless feel · 16mm', 'Crisscross wispy pattern that opens up the eye without weighing down lids. Cotton band for all-day comfort.',
 16, 'cotton', 22, 22.00, NULL, '/images/lash-photos/style-09-feather-alt.jpg',
 '["/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-wispy-14.jpg","/images/products/lash-natural-18.jpg"]', 180, 1, 0, 2),

('GE-MINK-003', 'mink', 'natural',  'Naked 18mm Mink',
 'Subtle volume + length · 18mm', 'Our most-worn lash. Adds noticeable lift without screaming "I am wearing lashes". Bestseller across NY/LA.',
 18, 'clear', 20, 24.00, NULL, '/images/lash-photos/style-18-velvet-eye.jpg',
 '["/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]', 240, 1, 0, 3),

('GE-MINK-004', 'mink', 'dramatic', 'Stellar 20mm Mink',
 'Dramatic length for evening · 20mm', 'Long mink hairs with subtle volume — ideal for date nights and parties. Tapered ends for natural blend.',
 20, 'cotton', 22, 32.00, NULL, '/images/lash-photos/style-17-ice-split.jpg',
 '["/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-volume-18.jpg","/images/products/lash-drama-22.jpg"]', 150, 0, 1, 4),

('GE-MINK-005', 'mink', 'volume',   'Volcano 22mm Mink',
 'Maximum volume + drama · 22mm', 'Densely packed 22mm hairs for full-coverage glam. Try with cat-eye liner for the ultimate look.',
 22, 'cotton', 25, 38.00, 48.00, '/images/lash-photos/style-14-frost-split.jpg',
 '["/images/lash-photos/style-14-frost-eye.jpg","/images/lash-photos/style-17-ice-split.jpg","/images/products/lash-drama-22.jpg"]', 120, 1, 0, 5),

('GE-MINK-006', 'mink', 'cat-eye',  'Cat Couture 22mm Mink',
 'Wing-tip cat-eye drama · 22mm', 'Outer corners extend 4mm longer for instant cat-eye lift. Wear with winged liner.',
 22, 'cotton', 22, 36.00, NULL, '/images/lash-photos/style-14-frost-eye.jpg',
 '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-17-ice-split.jpg","/images/products/lash-bold-22.jpg"]', 110, 0, 1, 6),

('GE-MINK-007', 'mink', 'glamour',  'Diamond 25mm Mink',
 'Maximalist runway lash · 25mm', 'Our longest mink. Couture-grade, hand-tied, reusable up to 25 wears. For occasions where you want to be unforgettable.',
 25, 'cotton', 25, 48.00, 58.00, '/images/lash-photos/style-18-velvet-split.jpg',
 '["/images/lash-photos/style-18-velvet-eye.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-glamour-25.jpg"]', 80, 1, 0, 7),

('GE-MINK-008', 'mink', 'glamour',  'Fox Eye 25mm Mink',
 'Snatched fox-eye effect · 25mm', 'Tapered design that lifts and elongates the outer corner — TikTok favorite for the "fox eye" look.',
 25, 'cotton', 25, 52.00, NULL, '/images/lash-photos/style-17-ice-split.jpg',
 '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-18-velvet-eye.jpg","/images/products/lash-glamour-25.jpg"]', 90, 0, 1, 8),

-- ===== Faux Mink (Vegan) (5 SKUs) =====
('GE-FAUX-001', 'faux', 'natural',  'Daily Wisp 14mm Faux',
 'Vegan everyday wisp · 14mm', '100% vegan synthetic fibers. Lightweight, fluffy, perfect for office or casual.',
 14, 'clear', 12, 12.00, NULL, '/images/lash-photos/style-09-feather-eye.jpg',
 '["/images/products/lash-wispy-14.jpg","/images/products/lash-natural-18.jpg","/images/lash-photos/style-09-feather-alt.jpg"]', 320, 1, 0, 10),

('GE-FAUX-002', 'faux', 'wispy',    'Pillow Talk 16mm Faux',
 'Soft wispy + voluminous · 16mm', 'Comfortable cotton band, all-day wear. Vegan & cruelty-free.',
 16, 'cotton', 15, 14.00, NULL, '/images/lash-photos/style-09-feather-alt.jpg',
 '["/images/products/lash-volume-18.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]', 280, 0, 0, 11),

('GE-FAUX-003', 'faux', 'volume',   'Volume Goddess 18mm Faux',
 'Voluminous vegan · 18mm', 'Indistinguishable from mink at half the price. Vegan-certified.',
 18, 'cotton', 15, 16.00, 22.00, '/images/lash-photos/style-18-velvet-eye.jpg',
 '["/images/products/lash-volume-18.jpg","/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-09-feather-alt.jpg"]', 250, 1, 0, 12),

('GE-FAUX-004', 'faux', 'dramatic', 'Bombshell 20mm Faux',
 'Dramatic vegan glam · 20mm', 'Bold volume for date night & special events. Vegan synthetic.',
 20, 'cotton', 15, 19.00, NULL, '/images/lash-photos/style-14-frost-split.jpg',
 '["/images/products/lash-drama-22.jpg","/images/lash-photos/style-14-frost-eye.jpg","/images/lash-photos/style-17-ice-split.jpg"]', 200, 0, 1, 13),

('GE-FAUX-005', 'faux', 'cat-eye',  'Wing It 22mm Faux',
 'Wing-tip vegan dramatic · 22mm', 'Cat-eye lift, vegan, reusable.',
 22, 'cotton', 15, 22.00, NULL, '/images/lash-photos/style-17-ice-split.jpg',
 '["/images/products/lash-bold-22.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-18-velvet-split.jpg"]', 180, 0, 1, 14),

-- ===== Magnetic Lashes (2 SKUs) =====
('GE-MAG-001', 'magnetic', 'natural',  'Magnet Wisp Set 16mm',
 'Magnetic eyeliner + lash kit · 16mm', 'No glue. Magnetic eyeliner included. 5 magnets along the band. Perfect for first-time users.',
 16, 'magnetic', 30, 28.00, 35.00, '/images/lash-photos/style-14-frost-eye.jpg',
 '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]', 150, 0, 1, 20),

('GE-MAG-002', 'magnetic', 'dramatic','Magnet Drama Set 22mm',
 'Magnetic dramatic + liner · 22mm', 'Same magnetic technology, dramatic 22mm length. Reusable 30+ wears. Liner included.',
 22, 'magnetic', 30, 34.00, NULL, '/images/lash-photos/style-17-ice-split.jpg',
 '["/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-drama-22.jpg"]', 110, 0, 1, 21),

-- ===== Tools & Accessories (3 SKUs) =====
('GE-TOOL-GLUE',  'tools', '',  'Crystal Clear Lash Adhesive · 5g',
 'Latex-free, 16-hour hold', 'Skin-safe, latex-free lash adhesive. Goes on white, dries clear. 16-hour hold.',
 NULL, NULL, NULL, 12.00, NULL, '/images/products/tool-glue.jpg',
 '["/images/products/tool-applicator.jpg","/images/products/tool-case.jpg"]', 400, 1, 0, 30),

('GE-TOOL-APPL',  'tools', '',  'Precision Gold Applicator',
 'Stainless steel + gold-plated', 'Pro-grade applicator with non-slip gold-plated grip. Ideal for both beginners and pros.',
 NULL, NULL, NULL, 16.00, 20.00, '/images/products/tool-applicator.jpg',
 '["/images/products/tool-case.jpg","/images/products/tool-glue.jpg"]', 180, 0, 0, 31),

('GE-TOOL-CASE',  'tools', '',  'Luxury Lash Storage Case',
 'Magnetic mirror case · 5 pairs', 'Travel-friendly magnetic case with built-in mirror. Holds 5 pairs of lashes safely.',
 NULL, NULL, NULL, 28.00, NULL, '/images/products/tool-case.jpg',
 '["/images/products/tool-applicator.jpg","/images/products/tool-glue.jpg"]', 100, 0, 0, 32);

-- ============================================================
-- 升级旧库 + 给每个产品配 3-4 张 gallery 图
-- 策略：主图用最匹配风格的真照片，gallery 混合其他真照 + SVG 产品图
-- ============================================================

-- ===== Mink 8 SKU =====
UPDATE products SET image_url = '/images/lash-photos/style-09-feather-eye.jpg',
    gallery_urls = '["/images/lash-photos/style-09-feather-alt.jpg","/images/products/lash-natural-18.jpg","/images/products/lash-wispy-14.jpg"]'
  WHERE sku = 'GE-MINK-001';

UPDATE products SET image_url = '/images/lash-photos/style-09-feather-alt.jpg',
    gallery_urls = '["/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-wispy-14.jpg","/images/products/lash-natural-18.jpg"]'
  WHERE sku = 'GE-MINK-002';

UPDATE products SET image_url = '/images/lash-photos/style-18-velvet-eye.jpg',
    gallery_urls = '["/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]'
  WHERE sku = 'GE-MINK-003';

UPDATE products SET image_url = '/images/lash-photos/style-17-ice-split.jpg',
    gallery_urls = '["/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-volume-18.jpg","/images/products/lash-drama-22.jpg"]'
  WHERE sku = 'GE-MINK-004';

UPDATE products SET image_url = '/images/lash-photos/style-14-frost-split.jpg',
    gallery_urls = '["/images/lash-photos/style-14-frost-eye.jpg","/images/lash-photos/style-17-ice-split.jpg","/images/products/lash-drama-22.jpg"]'
  WHERE sku = 'GE-MINK-005';

UPDATE products SET image_url = '/images/lash-photos/style-14-frost-eye.jpg',
    gallery_urls = '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-17-ice-split.jpg","/images/products/lash-bold-22.jpg"]'
  WHERE sku = 'GE-MINK-006';

UPDATE products SET image_url = '/images/lash-photos/style-18-velvet-split.jpg',
    gallery_urls = '["/images/lash-photos/style-18-velvet-eye.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-glamour-25.jpg"]'
  WHERE sku = 'GE-MINK-007';

UPDATE products SET image_url = '/images/lash-photos/style-17-ice-split.jpg',
    gallery_urls = '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-18-velvet-eye.jpg","/images/products/lash-glamour-25.jpg"]'
  WHERE sku = 'GE-MINK-008';

-- ===== Faux 5 SKU =====
UPDATE products SET image_url = '/images/lash-photos/style-09-feather-eye.jpg',
    gallery_urls = '["/images/products/lash-wispy-14.jpg","/images/products/lash-natural-18.jpg","/images/lash-photos/style-09-feather-alt.jpg"]'
  WHERE sku = 'GE-FAUX-001';

UPDATE products SET image_url = '/images/lash-photos/style-09-feather-alt.jpg',
    gallery_urls = '["/images/products/lash-volume-18.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]'
  WHERE sku = 'GE-FAUX-002';

UPDATE products SET image_url = '/images/lash-photos/style-18-velvet-eye.jpg',
    gallery_urls = '["/images/products/lash-volume-18.jpg","/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-09-feather-alt.jpg"]'
  WHERE sku = 'GE-FAUX-003';

UPDATE products SET image_url = '/images/lash-photos/style-14-frost-split.jpg',
    gallery_urls = '["/images/products/lash-drama-22.jpg","/images/lash-photos/style-14-frost-eye.jpg","/images/lash-photos/style-17-ice-split.jpg"]'
  WHERE sku = 'GE-FAUX-004';

UPDATE products SET image_url = '/images/lash-photos/style-17-ice-split.jpg',
    gallery_urls = '["/images/products/lash-bold-22.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-18-velvet-split.jpg"]'
  WHERE sku = 'GE-FAUX-005';

-- ===== Magnetic 2 SKU =====
UPDATE products SET image_url = '/images/lash-photos/style-14-frost-eye.jpg',
    gallery_urls = '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-09-feather-eye.jpg","/images/products/lash-natural-18.jpg"]'
  WHERE sku = 'GE-MAG-001';

UPDATE products SET image_url = '/images/lash-photos/style-17-ice-split.jpg',
    gallery_urls = '["/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-14-frost-split.jpg","/images/products/lash-drama-22.jpg"]'
  WHERE sku = 'GE-MAG-002';

-- ===== Tools 3 SKU =====
UPDATE products SET image_url = '/images/products/tool-glue.jpg',
    gallery_urls = '["/images/products/tool-applicator.jpg","/images/products/tool-case.jpg"]'
  WHERE sku = 'GE-TOOL-GLUE';
UPDATE products SET image_url = '/images/products/tool-applicator.jpg',
    gallery_urls = '["/images/products/tool-case.jpg","/images/products/tool-glue.jpg"]'
  WHERE sku = 'GE-TOOL-APPL';
UPDATE products SET image_url = '/images/products/tool-case.jpg',
    gallery_urls = '["/images/products/tool-applicator.jpg","/images/products/tool-glue.jpg"]'
  WHERE sku = 'GE-TOOL-CASE';

-- ============================================================
-- 种子套装(P2 Bundles):3 个 SKU,按 list price 减 ~20% 定价
-- bundle_items 是 JSON 数组,前端 PDP 解析展示组件,加车按 bundle 自身 price
-- ============================================================
INSERT IGNORE INTO products
  (sku, category, style, name, short_description, description,
   length_mm, band_type, reusable_count, price, compare_at_price,
   image_url, gallery_urls, stock, is_bestseller, is_new,
   is_bundle, bundle_items, sort_order)
VALUES
('GE-BUNDLE-START', 'bundle', 'starter', 'Starter Kit Bundle',
 'New to lashes? Everything you need — lashes, glue, applicator. Save $10.',
 'Whisper 14mm Mink + Crystal Clear Lash Adhesive + Precision Gold Applicator. Everything you need to nail your first pair on the first try. Includes our easiest-to-apply natural mink lash, latex-free skin-safe glue, and pro-grade gold-plated applicator.',
 NULL, NULL, NULL, 36.00, 46.00,
 '/images/lash-photos/style-09-feather-eye.jpg',
 '["/images/lash-photos/style-09-feather-alt.jpg","/images/products/tool-glue.jpg","/images/products/tool-applicator.jpg"]',
 80, 1, 1,
 1, '[{"sku":"GE-MINK-001","qty":1},{"sku":"GE-TOOL-GLUE","qty":1},{"sku":"GE-TOOL-APPL","qty":1}]', 100),

('GE-BUNDLE-TRIO', 'bundle', 'glam', 'Glam Trio Bundle',
 'Three signature mink lashes — day, date night, red carpet. Save $22.',
 'Naked 18mm + Volcano 22mm + Diamond 25mm. The ultimate three-pair set covering everyday wear, party drama, and runway-level glam. Each lash is hand-tied premium mink with up to 25 wears.',
 NULL, NULL, NULL, 88.00, 110.00,
 '/images/lash-photos/style-18-velvet-eye.jpg',
 '["/images/lash-photos/style-14-frost-split.jpg","/images/lash-photos/style-18-velvet-split.jpg","/images/lash-photos/style-17-ice-split.jpg"]',
 50, 1, 0,
 1, '[{"sku":"GE-MINK-003","qty":1},{"sku":"GE-MINK-005","qty":1},{"sku":"GE-MINK-007","qty":1}]', 101),

('GE-BUNDLE-VEGAN', 'bundle', 'vegan', 'Vegan Daily Duo Bundle',
 'Two everyday vegan wisps + travel mirror case. Save $12.',
 'Daily Wisp 14mm Faux + Pillow Talk 16mm Faux + Luxury Lash Storage Case. 100% vegan, cruelty-free, and travel-ready. Two everyday wispy lashes plus a magnetic mirror case to keep them safe wherever you go.',
 NULL, NULL, NULL, 42.00, 54.00,
 '/images/lash-photos/style-09-feather-alt.jpg',
 '["/images/lash-photos/style-09-feather-eye.jpg","/images/products/tool-case.jpg","/images/products/lash-wispy-14.jpg"]',
 70, 0, 1,
 1, '[{"sku":"GE-FAUX-001","qty":1},{"sku":"GE-FAUX-002","qty":1},{"sku":"GE-TOOL-CASE","qty":1}]', 102);

-- ============================================================
-- 种子 UGC(P2): 6 张 mock 客户照,用现有 lash 写真充当
-- 用 image_url + instagram_handle 组合做幂等 (没 unique key,所以 INSERT IGNORE 实际不会跳;
-- 给 status='approved' 时直接 SELECT NOT EXISTS 兜底)
-- ============================================================
INSERT INTO ugc_submissions (image_url, caption, instagram_handle, status, sort_order)
SELECT * FROM (
  SELECT '/images/lash-photos/style-09-feather-eye.jpg' AS image_url,
         'Wearing the Daily Wisp Faux to brunch — so light I forgot I had them on!' AS caption,
         'olivia.p' AS instagram_handle, 'approved' AS status, 0 AS sort_order
  UNION ALL SELECT '/images/lash-photos/style-18-velvet-eye.jpg', 'Naked 18mm + winged liner = my ultimate date-night look 🖤', 'lauren.c.lashes', 'approved', 1
  UNION ALL SELECT '/images/lash-photos/style-14-frost-eye.jpg', 'These magnetic lashes are GENIUS. No glue, perfect every time.', 'tasha.b', 'approved', 2
  UNION ALL SELECT '/images/lash-photos/style-18-velvet-split.jpg', 'Diamond 25mm for the wedding. Got 5 compliments at the bar.', 'camilav', 'approved', 3
  UNION ALL SELECT '/images/lash-photos/style-09-feather-alt.jpg', 'Featherlight wisp for everyday — coworkers asked if I got extensions!', 'rileyh', 'approved', 4
  UNION ALL SELECT '/images/lash-photos/style-14-frost-split.jpg', 'Volcano 22mm + cat eye = main character energy', 'briT.glam', 'approved', 5
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM ugc_submissions WHERE image_url = seed.image_url AND instagram_handle = seed.instagram_handle);

-- Hero 图覆盖
UPDATE site_settings SET `value` = '/images/lash-photos/style-14-frost-split.jpg' WHERE `key` = 'hero_image_url';

-- ============================================================
-- 种子评论:每个 bestseller 3-5 条 approved 真实质感评论 + 1-2 条 featured
-- 用 INSERT IGNORE + reviewer_name 唯一性兜底,避免重复种子
-- product_id 用子查询:不依赖 auto_increment 顺序
-- ============================================================
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Sarah M.', 'Los Angeles, CA', 5, 'Most comfortable mink lashes I own',
  'I have super sensitive eyes and these don''t bother me at all — even after 10 hours. The cotton band is so light I literally forget I have them on. Will buy again.',
  'approved', 1, 1, 18, 'seed:001' FROM products WHERE sku = 'GE-MINK-007';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Jasmine R.', 'Brooklyn, NY', 5, 'Looks identical to mink',
  'I''ve tried Lilly Lashes and Velour, and these vegan ones rival both at half the price. The wisp pattern is gorgeous. Got compliments all night.',
  'approved', 1, 1, 22, 'seed:002' FROM products WHERE sku = 'GE-FAUX-003';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Maya K.', 'Austin, TX', 5, 'Game-changer combo',
  'Bought the Whisper 14mm + applicator + glue. Used to take me 20 min, now under 5. The applicator is genuinely the best one I''ve used.',
  'approved', 1, 1, 14, 'seed:003' FROM products WHERE sku = 'GE-MINK-001';

-- 更多评论(non-featured)— 给每个 bestseller 拉到 3+ 条
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Aaliyah J.', 'Atlanta, GA', 5, 'Reusable forever',
  'On wear #18 with this pair and they still look brand new. Just clean the band gently and they bounce right back.',
  'approved', 1, 0, 9, 'seed:004' FROM products WHERE sku = 'GE-MINK-007';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Priya S.', 'Houston, TX', 4, 'Beautiful but takes practice',
  'The lashes are stunning. First application took me a few tries but by night two I had it down. Worth the learning curve.',
  'approved', 1, 0, 6, 'seed:005' FROM products WHERE sku = 'GE-MINK-007';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Lauren C.', 'Chicago, IL', 5, 'My everyday lash',
  'Naked 18mm is exactly what the name says — looks like nothing, but everything. Coworkers asked if I got eyelash extensions.',
  'approved', 1, 0, 12, 'seed:006' FROM products WHERE sku = 'GE-MINK-003';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Tasha B.', 'Miami, FL', 5, 'Perfect for everyday wear',
  'Fits my eye shape perfectly. Trim a tiny bit off the outer corner and they''re flawless.',
  'approved', 1, 0, 5, 'seed:007' FROM products WHERE sku = 'GE-MINK-003';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Riley H.', 'Seattle, WA', 5, 'Wispy goodness',
  'These are so fluffy and soft. Comfortable for all-day wear at work and I just touch them up before going out.',
  'approved', 1, 0, 8, 'seed:008' FROM products WHERE sku = 'GE-MINK-002';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Dana T.', 'Phoenix, AZ', 4, 'Lighter than expected',
  'Thought wispy meant flimsy but no — these hold their shape beautifully. Re-curling between wears keeps them perfect.',
  'approved', 1, 0, 4, 'seed:009' FROM products WHERE sku = 'GE-MINK-002';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Camila V.', 'San Diego, CA', 5, 'Volume queen',
  'The Volcano 22mm is straight DRAMA. Wore them to a wedding and got 5 compliments at the bar. Worth every penny.',
  'approved', 1, 0, 11, 'seed:010' FROM products WHERE sku = 'GE-MINK-005';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Bri T.', 'Las Vegas, NV', 5, 'For when you want to be SEEN',
  'Bold, full, dramatic. Not for the office but absolutely for any night out. Held up through dancing all night.',
  'approved', 1, 0, 7, 'seed:011' FROM products WHERE sku = 'GE-MINK-005';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Olivia P.', 'Portland, OR', 5, 'My go-to vegan option',
  'Daily Wisp Faux is my favorite. Looks expensive but feels like nothing. Restocking 2 pairs.',
  'approved', 1, 0, 9, 'seed:012' FROM products WHERE sku = 'GE-FAUX-001';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Sienna L.', 'Denver, CO', 4, 'Great value',
  'For $12 these are unreal. Look just like my $30 mink lashes from another brand.',
  'approved', 1, 0, 6, 'seed:013' FROM products WHERE sku = 'GE-FAUX-001';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Morgan E.', 'Boston, MA', 5, 'Volume Goddess delivers',
  'Vegan and voluminous. Cotton band is comfortable, never poked my eye. Perfect for date nights.',
  'approved', 1, 0, 7, 'seed:014' FROM products WHERE sku = 'GE-FAUX-003';

INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Reese K.', 'Nashville, TN', 5, 'Holds 16-hour days',
  'Lash adhesive doesn''t budge. I''ve worn it from morning meetings into late dinners and it''s still perfect.',
  'approved', 1, 0, 13, 'seed:015' FROM products WHERE sku = 'GE-TOOL-GLUE';
INSERT IGNORE INTO reviews
  (product_id, reviewer_name, reviewer_location, rating, title, body, status, is_verified_buyer, is_featured, helpful_count, seed_key)
SELECT id, 'Hannah W.', 'Minneapolis, MN', 5, 'Gold applicator is gorgeous',
  'Looks like jewelry. Grippy enough that I don''t fumble and drop my lash mid-application like I used to with cheap plastic ones.',
  'approved', 1, 0, 8, 'seed:016' FROM products WHERE sku = 'GE-TOOL-APPL';
