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
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户账号
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL DEFAULT '',
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    phone VARCHAR(64) DEFAULT NULL,
    is_subscribed TINYINT(1) NOT NULL DEFAULT 0,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
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
('seo_blocked',        '1');  -- 默认拦截搜索引擎，上线就绪后改 0

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

-- Hero 图覆盖
UPDATE site_settings SET `value` = '/images/lash-photos/style-14-frost-split.jpg' WHERE `key` = 'hero_image_url';
