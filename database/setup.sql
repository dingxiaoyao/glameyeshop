CREATE DATABASE IF NOT EXISTS glameyeshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE glameyeshop;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL DEFAULT '',
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wholesale_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    contact VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 升级现有数据库的迁移语句（已存在 orders 表时使用）：
-- ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id;
-- ALTER TABLE orders ADD INDEX idx_created_at (created_at), ADD INDEX idx_email (email);
