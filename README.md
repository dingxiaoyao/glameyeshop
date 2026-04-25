# GLAMEYE

GLAMEYE 是一个高端玻璃眼镜在线商店示例项目，包含静态前端、PHP API、Stripe / PayPal 支付占位与简易管理后台。

## 目录结构

- `index.html` — 商店首页
- `checkout.html` — 结账页（支持 Stripe / PayPal 占位）
- `admin/index.php` — 管理后台（受 HTTP Basic Auth 保护）
- `api/` — PHP 后端接口
  - `config.php` — 数据库与公共函数（含价格清单、auth 校验）
  - `create-order.php` — 创建订单（**服务端权威计算金额**，不信任客户端）
  - `get-orders.php` — 查询订单（**仅管理员**）
  - `wholesale-lead.php` — 批发询单
  - `stripe-checkout.php` / `stripe-webhook.php` — Stripe（webhook 强制签名验证）
  - `paypal-checkout.php` / `paypal-capture.php` — PayPal
  - `send-email.php` — 邮件发送占位
- `database/setup.sql` — 数据库表结构
- `.github/workflows/deploy.yml` — 自动部署到 Vultr 服务器
- `.htaccess` — 安全响应头、缓存、压缩

## 必需环境变量（生产环境）

| 变量 | 说明 |
|---|---|
| `APP_ENV` | 设为 `production` 启用更严格的安全检查 |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | 数据库连接（生产环境禁止使用 `root` 或空密码） |
| `ADMIN_USER` / `ADMIN_PASS` | 管理后台 Basic Auth 凭据（**必须设置**，否则后台与 `get-orders.php` 返回 503） |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook 签名密钥（**必须设置**，否则 webhook 拒绝请求） |

可在服务器的 nginx/apache 配置或 systemd unit 中设置这些环境变量，**不要提交到 Git**。

## 本地运行

```bash
# 1. 导入数据库（已存在表请运行 setup.sql 注释中的 ALTER 语句）
mysql -u root -p < database/setup.sql

# 2. 设置环境变量
export APP_ENV=development
export DB_USER=glameye
export DB_PASS=your_password
export ADMIN_USER=admin
export ADMIN_PASS=$(openssl rand -hex 16)
export STRIPE_WEBHOOK_SECRET=$(openssl rand -hex 32)

# 3. 启动 PHP 内置服务器
php -S localhost:8000
```

## 部署

推送至 `main` 分支会通过 GitHub Actions 自动 SSH 部署到 Vultr。所需 secrets：
`SERVER_HOST` / `SERVER_USER` / `SERVER_SSH_KEY` / `DEPLOY_PATH`。

## 安全改进记录（本次审阅修复）

- ✅ 后台 `admin/` 增加 HTTP Basic Auth
- ✅ `create-order.php` 改为服务端权威计算金额（按产品名查表）
- ✅ `stripe-webhook.php` 强制 HMAC SHA-256 签名校验 + 5 分钟时间戳容差
- ✅ 所有 PHP 错误不再向客户端泄露 PDOException 原文
- ✅ orders 表新增 `customer_name` 字段
- ✅ `get-orders.php` 受 admin auth 保护
- ✅ 输入校验加严（手机号正则、字段长度、产品白名单、支付方式白名单）
- ✅ 添加 `.htaccess` 安全头和敏感文件保护
- ✅ 首页添加 Open Graph / Twitter Card meta

## 待办

- [ ] 替换 `api/stripe-checkout.php` 与 `api/paypal-checkout.php` 的占位实现为真正的 SDK 集成
- [ ] 在 `api/send-email.php` 接入 SMTP 或 SendGrid
- [ ] 为前端结账表单接入 AJAX，避免提交后跳转到 JSON 响应
