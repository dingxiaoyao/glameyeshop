# GlamEye 邮箱配置指南

要让 `hello@glameyeshop.com`、`support@glameyeshop.com` 等邮箱能收发邮件，您有 4 个方案。**推荐方案 A**（免费 + 5 分钟搞定）。

---

## 方案 A：ImprovMX 邮件转发（**推荐 · 免费**）

把发到 `*@glameyeshop.com` 的邮件**转发到您的 Gmail/iCloud 个人邮箱**。免费 25 个别名，无需迁移 DNS。

### 步骤
1. 注册：https://improvmx.com → 用 Gmail 账号登录即可
2. Add Domain → 输入 `glameyeshop.com`
3. ImprovMX 会显示需要您加的 2 条 MX 记录
4. **登录 GoDaddy** → My Products → 您的域名 → DNS → Manage Zones → Add Record
   - **MX 记录 1**：
     - Type: `MX`
     - Name: `@`
     - Priority: `10`
     - Value: `mx1.improvmx.com`
     - TTL: `1 Hour`
   - **MX 记录 2**：
     - Type: `MX`
     - Name: `@`
     - Priority: `20`
     - Value: `mx2.improvmx.com`
   - **TXT 记录**（防止邮件进垃圾箱，强烈建议）：
     - Type: `TXT`
     - Name: `@`
     - Value: `v=spf1 include:spf.improvmx.com ~all`
5. 在 ImprovMX 后台添加别名：
   - `hello@glameyeshop.com` → `您的Gmail@gmail.com`
   - `support@glameyeshop.com` → 同上
   - `wholesale@glameyeshop.com` → 同上
   - `press@glameyeshop.com` → 同上
6. 等 5-30 分钟 DNS 生效，给 hello@glameyeshop.com 发个测试邮件

### 怎么用 GlamEye 邮箱发件
- ImprovMX 默认只能收，发件还是用您 Gmail
- 想用 `hello@glameyeshop.com` **发件**：
  - 在 Gmail Settings → Accounts → "Send mail as" → Add another email address
  - SMTP server: `smtp.improvmx.com` Port: 587
  - Username: `hello@glameyeshop.com`
  - Password: ImprovMX 后台 Settings → SMTP credentials 生成
  - 收件人收到的是 `hello@glameyeshop.com` 发出的邮件

---

## 方案 B：Cloudflare Email Routing（免费 + 加 DDoS 防护）

需要先把 GoDaddy 的 nameserver 改到 Cloudflare（免费）。
- 优点：除了邮件还白送 CDN/SSL/DDoS 防护
- 缺点：要换 NS，有几小时切换期

**推荐如果您打算长期运营**。教程：https://developers.cloudflare.com/email-routing/get-started/

---

## 方案 C：Google Workspace（**$6/月起 · 专业**）

- 真正的专业邮箱（不是转发）
- 自带 Google Drive / Calendar / Meet
- 更可靠，营销邮件不易进垃圾箱
- 注册：https://workspace.google.com → 选 Business Starter $6/月
- 按引导添加 MX 记录到 GoDaddy

**适合您要扩张团队、需要多人协作的时候。**

---

## 方案 D：GoDaddy Workspace Email（$1.99/月）

- GoDaddy 自家的邮箱服务
- 1 个邮箱 1.99/月，便宜但功能基础
- 控制台：GoDaddy → My Products → 选 Workspace Email
- 优点：DNS 自动配，0 设置成本
- 缺点：界面老旧，营销邮件易进垃圾箱

---

## 我推荐的路线图

| 阶段 | 推荐 |
|---|---|
| **现在（刚上线）** | **方案 A · ImprovMX 免费转发**，5 分钟搞定，邮件统一进您 Gmail |
| **6 个月后**（每天有 10+ 客户邮件） | 升级 Google Workspace $6/月 |
| **认真做品牌**（投放广告、防 DDoS） | Cloudflare（同时邮件 + CDN + 安全） |

---

## 给 GoDaddy 的总配置清单（A 方案 + DMARC 强烈建议）

GoDaddy DNS 管理页面应该有这些记录：

| Type | Name | Value | TTL |
|---|---|---|---|
| A | @ | 173.199.124.17 | 1 Hour |
| A | www | 173.199.124.17 | 1 Hour |
| MX | @ | mx1.improvmx.com (Priority 10) | 1 Hour |
| MX | @ | mx2.improvmx.com (Priority 20) | 1 Hour |
| TXT | @ | `v=spf1 include:spf.improvmx.com ~all` | 1 Hour |
| TXT | _dmarc | `v=DMARC1; p=none; rua=mailto:hello@glameyeshop.com` | 1 Hour |

DMARC 那条加了之后，您可以在 hello 邮箱里收到每周邮件投递报告，知道有没有人冒充您发邮件。

---

设好后告诉我，我帮您测试 MX 记录。
