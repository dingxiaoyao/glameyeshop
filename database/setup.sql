-- ============================================================
-- GlamEye Lashes - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS glameyeshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE glameyeshop;

-- 商品目录（取代硬编码价格）
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,           -- mink / faux / tools
    name VARCHAR(200) NOT NULL,
    short_description VARCHAR(500) DEFAULT '',
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2) DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT '',
    stock INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
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

-- 用户地址
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

-- 收藏 / 心愿单
CREATE TABLE IF NOT EXISTS user_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,                -- guest checkout 允许 NULL
    customer_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    address_line VARCHAR(255) NOT NULL DEFAULT '',
    address_line2 VARCHAR(255) DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    state VARCHAR(64) NOT NULL DEFAULT '',
    postal_code VARCHAR(32) NOT NULL DEFAULT '',
    country VARCHAR(64) NOT NULL DEFAULT 'US',
    -- 兼容旧字段（首项快照）
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL,           -- 总额 = subtotal + shipping + tax
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

-- 订单明细
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

-- 邮件订阅
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    source VARCHAR(50) NOT NULL DEFAULT 'website',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 批发询单
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

-- 折扣码（可选基础结构）
CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'percent',  -- percent / fixed
    value DECIMAL(10,2) NOT NULL,
    min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses INT DEFAULT NULL,
    used_count INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 种子数据：9 个 SKU（3 mink + 3 faux + 3 tools）
-- ============================================================
INSERT IGNORE INTO products (sku, category, name, short_description, description, price, compare_at_price, image_url, stock, sort_order) VALUES
('GE-MINK-NAT-18', 'mink', 'Natural Beauty Mink',          'Subtle everyday mink lashes',                   'Lightweight mink lashes designed for natural everyday wear. Cruelty-free.', 24.00, 32.00, '/images/products/lash-natural-18.jpg', 200, 1),
('GE-MINK-DRA-22', 'mink', 'Drama Queen Mink',             'Dramatic mink lashes with volume',               'Volume mink lashes for date nights and special occasions.',                42.00, NULL,  '/images/products/lash-drama-22.jpg',  120, 2),
('GE-MINK-GLM-25', 'mink', 'Glamour Mink',                 'Maximum length & volume',                        'Our most luxurious 25mm mink lashes. Reusable up to 25 wears.',            58.00, 72.00, '/images/products/lash-glamour-25.jpg',80,  3),
('GE-FAUX-WSP-14', 'faux', 'Wispy Daily Faux',             'Soft daily wear faux mink',                       'Vegan faux mink lashes with feathery wispy effect. Perfect for office.',  14.00, NULL,  '/images/products/lash-wispy-14.jpg',  300, 4),
('GE-FAUX-VOL-18', 'faux', 'Volume Whisper Faux',          'Voluminous faux mink',                            'Lightweight vegan lashes that add fullness without weight.',              19.00, NULL,  '/images/products/lash-volume-18.jpg', 250, 5),
('GE-FAUX-BLD-22', 'faux', 'Bold Wing Faux',               'Cat-eye dramatic faux mink',                      'Wing-tip styling for cat-eye drama. Vegan & cruelty-free.',               22.00, 28.00, '/images/products/lash-bold-22.jpg',   180, 6),
('GE-TOOL-GLUE',    'tools','Crystal Clear Lash Adhesive',  'Latex-free, lasts up to 16 hours',                'Skin-safe, latex-free lash adhesive. Goes on white, dries clear.',         12.00, NULL,  '/images/products/tool-glue.jpg',       400, 7),
('GE-TOOL-APPL',    'tools','Precision Gold Applicator',    'Stainless steel + gold-plated tweezers',          'Professional-grade applicator with non-slip gold-plated grip.',            16.00, 20.00, '/images/products/tool-applicator.jpg', 150, 8),
('GE-TOOL-CASE',    'tools','Luxury Lash Storage Case',     'Magnetic mirror case for 5 pairs',                'Travel-friendly magnetic case with built-in mirror, holds 5 pairs.',       28.00, NULL,  '/images/products/tool-case.jpg',       100, 9);
