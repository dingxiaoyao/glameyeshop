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
('hero_image_url',     'https://images.unsplash.com/photo-1583241800698-9c2e0c47f1c8?w=1920&q=80&auto=format&fit=crop');

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
 14, 'clear', 20, 18.00, 24.00, 'https://images.unsplash.com/photo-1571875257727-256c39da42af?w=800&q=80&auto=format&fit=crop', NULL, 220, 1, 0, 1),

('GE-MINK-002', 'mink', 'wispy',    'Featherlight 16mm Mink',
 'Wispy effect, weightless feel · 16mm', 'Crisscross wispy pattern that opens up the eye without weighing down lids. Cotton band for all-day comfort.',
 16, 'cotton', 22, 22.00, NULL, 'https://images.unsplash.com/photo-1583241800698-9c2e0c47f1c8?w=800&q=80&auto=format&fit=crop', NULL, 180, 1, 0, 2),

('GE-MINK-003', 'mink', 'natural',  'Naked 18mm Mink',
 'Subtle volume + length · 18mm', 'Our most-worn lash. Adds noticeable lift without screaming "I am wearing lashes". Bestseller across NY/LA.',
 18, 'clear', 20, 24.00, NULL, 'https://images.unsplash.com/photo-1561306039-d92a5cef0c0c?w=800&q=80&auto=format&fit=crop', NULL, 240, 1, 0, 3),

('GE-MINK-004', 'mink', 'dramatic', 'Stellar 20mm Mink',
 'Dramatic length for evening · 20mm', 'Long mink hairs with subtle volume — ideal for date nights and parties. Tapered ends for natural blend.',
 20, 'cotton', 22, 32.00, NULL, 'https://images.unsplash.com/photo-1626807236036-69d289c84c1c?w=800&q=80&auto=format&fit=crop', NULL, 150, 0, 1, 4),

('GE-MINK-005', 'mink', 'volume',   'Volcano 22mm Mink',
 'Maximum volume + drama · 22mm', 'Densely packed 22mm hairs for full-coverage glam. Try with cat-eye liner for the ultimate look.',
 22, 'cotton', 25, 38.00, 48.00, 'https://images.unsplash.com/photo-1577897745441-b0815ea33fdc?w=800&q=80&auto=format&fit=crop', NULL, 120, 1, 0, 5),

('GE-MINK-006', 'mink', 'cat-eye',  'Cat Couture 22mm Mink',
 'Wing-tip cat-eye drama · 22mm', 'Outer corners extend 4mm longer for instant cat-eye lift. Wear with winged liner.',
 22, 'cotton', 22, 36.00, NULL, 'https://images.unsplash.com/photo-1591348278863-a8fb3887e2aa?w=800&q=80&auto=format&fit=crop', NULL, 110, 0, 1, 6),

('GE-MINK-007', 'mink', 'glamour',  'Diamond 25mm Mink',
 'Maximalist runway lash · 25mm', 'Our longest mink. Couture-grade, hand-tied, reusable up to 25 wears. For occasions where you want to be unforgettable.',
 25, 'cotton', 25, 48.00, 58.00, 'https://images.unsplash.com/photo-1627384113743-6bd5a479fffd?w=800&q=80&auto=format&fit=crop', NULL, 80, 1, 0, 7),

('GE-MINK-008', 'mink', 'glamour',  'Fox Eye 25mm Mink',
 'Snatched fox-eye effect · 25mm', 'Tapered design that lifts and elongates the outer corner — TikTok favorite for the "fox eye" look.',
 25, 'cotton', 25, 52.00, NULL, 'https://images.unsplash.com/photo-1559599189-fe84dea4eb79?w=800&q=80&auto=format&fit=crop', NULL, 90, 0, 1, 8),

-- ===== Faux Mink (Vegan) (5 SKUs) =====
('GE-FAUX-001', 'faux', 'natural',  'Daily Wisp 14mm Faux',
 'Vegan everyday wisp · 14mm', '100% vegan synthetic fibers. Lightweight, fluffy, perfect for office or casual.',
 14, 'clear', 12, 12.00, NULL, 'https://images.unsplash.com/photo-1620916297893-3e2a8a17ce11?w=800&q=80&auto=format&fit=crop', NULL, 320, 1, 0, 10),

('GE-FAUX-002', 'faux', 'wispy',    'Pillow Talk 16mm Faux',
 'Soft wispy + voluminous · 16mm', 'Comfortable cotton band, all-day wear. Vegan & cruelty-free.',
 16, 'cotton', 15, 14.00, NULL, 'https://images.unsplash.com/photo-1597225244660-1cd128c64284?w=800&q=80&auto=format&fit=crop', NULL, 280, 0, 0, 11),

('GE-FAUX-003', 'faux', 'volume',   'Volume Goddess 18mm Faux',
 'Voluminous vegan · 18mm', 'Indistinguishable from mink at half the price. Vegan-certified.',
 18, 'cotton', 15, 16.00, 22.00, 'https://images.unsplash.com/photo-1633113085479-66d4d83c2d4e?w=800&q=80&auto=format&fit=crop', NULL, 250, 1, 0, 12),

('GE-FAUX-004', 'faux', 'dramatic', 'Bombshell 20mm Faux',
 'Dramatic vegan glam · 20mm', 'Bold volume for date night & special events. Vegan synthetic.',
 20, 'cotton', 15, 19.00, NULL, 'https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?w=800&q=80&auto=format&fit=crop', NULL, 200, 0, 1, 13),

('GE-FAUX-005', 'faux', 'cat-eye',  'Wing It 22mm Faux',
 'Wing-tip vegan dramatic · 22mm', 'Cat-eye lift, vegan, reusable.',
 22, 'cotton', 15, 22.00, NULL, 'https://images.unsplash.com/photo-1571645163064-77faa9676a46?w=800&q=80&auto=format&fit=crop', NULL, 180, 0, 1, 14),

-- ===== Magnetic Lashes (2 SKUs) =====
('GE-MAG-001', 'magnetic', 'natural',  'Magnet Wisp Set 16mm',
 'Magnetic eyeliner + lash kit · 16mm', 'No glue. Magnetic eyeliner included. 5 magnets along the band. Perfect for first-time users.',
 16, 'magnetic', 30, 28.00, 35.00, 'https://images.unsplash.com/photo-1583241475880-083f84372725?w=800&q=80&auto=format&fit=crop', NULL, 150, 0, 1, 20),

('GE-MAG-002', 'magnetic', 'dramatic','Magnet Drama Set 22mm',
 'Magnetic dramatic + liner · 22mm', 'Same magnetic technology, dramatic 22mm length. Reusable 30+ wears. Liner included.',
 22, 'magnetic', 30, 34.00, NULL, 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=800&q=80&auto=format&fit=crop', NULL, 110, 0, 1, 21),

-- ===== Tools & Accessories (3 SKUs) =====
('GE-TOOL-GLUE',  'tools', '',  'Crystal Clear Lash Adhesive · 5g',
 'Latex-free, 16-hour hold', 'Skin-safe, latex-free lash adhesive. Goes on white, dries clear. 16-hour hold.',
 NULL, NULL, NULL, 12.00, NULL, 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&q=80&auto=format&fit=crop', NULL, 400, 1, 0, 30),

('GE-TOOL-APPL',  'tools', '',  'Precision Gold Applicator',
 'Stainless steel + gold-plated', 'Pro-grade applicator with non-slip gold-plated grip. Ideal for both beginners and pros.',
 NULL, NULL, NULL, 16.00, 20.00, 'https://images.unsplash.com/photo-1631214539947-c4c4d8b3e0c9?w=800&q=80&auto=format&fit=crop', NULL, 180, 0, 0, 31),

('GE-TOOL-CASE',  'tools', '',  'Luxury Lash Storage Case',
 'Magnetic mirror case · 5 pairs', 'Travel-friendly magnetic case with built-in mirror. Holds 5 pairs of lashes safely.',
 NULL, NULL, NULL, 28.00, NULL, 'https://images.unsplash.com/photo-1617897903246-719242758050?w=800&q=80&auto=format&fit=crop', NULL, 100, 0, 0, 32);
