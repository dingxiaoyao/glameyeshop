-- ============================================================
-- GlamEye 数据库 schema（幂等：可重复执行）
-- ============================================================

CREATE DATABASE IF NOT EXISTS glameyeshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE glameyeshop;

-- 订单主表
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    -- 收货地址
    address_line VARCHAR(255) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    postal_code VARCHAR(32) NOT NULL DEFAULT '',
    country VARCHAR(64) NOT NULL DEFAULT 'CN',
    -- 兼容老字段（单品快捷字段，新订单也会写入第一项）
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_session_id VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单明细表（支持多商品）
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 批发询单
CREATE TABLE IF NOT EXISTS wholesale_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    contact VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 升级旧库（幂等：列已存在时忽略；workflow 用 || true 包裹）
-- ============================================================
-- ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id;
-- ALTER TABLE orders ADD COLUMN address_line VARCHAR(255) NOT NULL DEFAULT '' AFTER phone;
-- ALTER TABLE orders ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '' AFTER address_line;
-- ALTER TABLE orders ADD COLUMN postal_code VARCHAR(32) NOT NULL DEFAULT '' AFTER city;
-- ALTER TABLE orders ADD COLUMN country VARCHAR(64) NOT NULL DEFAULT 'CN' AFTER postal_code;
-- ALTER TABLE orders ADD COLUMN payment_session_id VARCHAR(255) NULL AFTER payment_method;
-- ALTER TABLE orders ADD COLUMN notes TEXT NULL AFTER status;
-- ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- ALTER TABLE orders ADD INDEX idx_status (status);
