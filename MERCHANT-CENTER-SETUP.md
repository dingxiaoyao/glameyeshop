# GlamEye → Google Shopping 完整配置教程

把 GlamEye 产品同步到 **Google Shopping**(免费 listings + 付费 Shopping ads)。
从注册到出 ad 全流程,**第一次配置约 30 分钟,审核 3-7 天**。

---

## 已经技术准备好的

| ✅ | 已完成 |
|---|---|
| Product Feed XML 接口 | `/merchant-feed.xml` 动态从 DB 拉所有 active 产品 |
| 字段符合 Google spec | id, title, description, image_link, price, sale_price, availability, brand, shipping by zone, etc. |
| HTTPS | https://glameyeshop.com 已上 SSL |
| 法务页 | terms / privacy / refund / shipping 4 页齐全 |
| admin 配置入口 | `/admin/merchant-center.php`(健康检查 + feed 预览) |

---

## 第 1 步 — 注册 Google Merchant Center

### 1.1 进入

打开 https://merchants.google.com/ → 用 `info@glameyeshop.com` 登录(同 Google Ads / GSC / GA 账号)

### 1.2 创建账户

首次进去会问几个问题:

| 问题 | 怎么填 |
|---|---|
| **业务名称** | `GlamEye` |
| **国家/地区** | `美国(United States)`(主市场,后面可加) |
| **时区** | `America/Los_Angeles` 或你常用的 |
| **业务所在地** | 填你的真实工商注册地或常驻地 |
| **是否在 Shopify/WooCommerce 等平台?** | 选 **No, I use my own website** |

### 1.3 添加业务信息

- **网站 URL**:`https://glameyeshop.com`
- **客服邮箱**:`info@glameyeshop.com`
- **客服电话**(可选,但加了通过率更高)

---

## 第 2 步 — 验证网站所有权 + Claim URL

Google 要确认 https://glameyeshop.com 是你的。

### 方法 A(推荐 · 最快):Search Console 自动同步

如果你之前按 `SEO-SETUP.md` 注册了 Google Search Console 并验证了 glameyeshop.com 域名 ——
Merchant Center 会**自动检测到**并显示 "✓ Already verified",直接 Claim 即可。

### 方法 B:HTML meta tag

如果没有 Search Console,选 "HTML tag":

1. Google 给你一段:
   ```html
   <meta name="google-site-verification" content="abc123XYZ..." />
   ```

2. 复制 content 里那串字符,告诉我,我帮你加到 `index.html` 的 `<head>` 里
   (或者你自己加:打开 index.html,在 `<head>` 区贴这一行,push)

3. 部署完后回 Merchant Center 点 **Verify**

### 方法 C:HTML file

下载 google5xxxxxx.html → 我帮你放到网站根目录(不推荐,留垃圾文件)

---

## 第 3 步 — 配置运费 + 税

### 3.1 运费(Shipping)

Merchant Center 后台 → **Tools** → **Shipping and returns** → **Add shipping service**

| 字段 | 填什么 |
|---|---|
| Service name | `Standard Shipping` |
| Country | `United States` |
| Currency | `USD` |
| Delivery time | `3-7 business days` |
| Service area | All US |
| Shipping rate | **Flat rate $5.99 USD**(或勾 "Free over $50") |

> 我们的 feed XML **已经按 site_settings.shipping_zones 自动输出 `<g:shipping>` 信息**,
> 但 Merchant Center 后台手动配置一遍最稳(GMC 会取 max(feed, manual))。

### 3.2 退货政策(Returns)

同界面 → **Returns** tab → **Add return policy**

| 字段 | 填什么 |
|---|---|
| Return window | `14 days` |
| Return shipping cost | `Customer pays` |
| Return policy URL | `https://glameyeshop.com/refund.html` |

### 3.3 税(Tax)— 仅 US

**Tools → Sales tax** → 选 **Use rates from external service: Stripe**(如果你 Stripe 已设 tax)
或勾 **I don't charge sales tax** 然后在 checkout 由 Stripe 算。

---

## 第 4 步 — 提交 Product Feed(核心!)

### 4.1 打开 feed 配置

Merchant Center → 左侧 **Products** → **Feeds** → 右上角 **+ Add primary feed**

### 4.2 配置 feed

| 字段 | 填什么 |
|---|---|
| Country of sale | `United States` |
| Language | `English` |
| Destination | ✅ Shopping ads · ✅ Free listings(都勾) |
| Feed name | `GlamEye Live Feed` |
| Input method | **Scheduled fetch**(关键!选这个) |

### 4.3 Scheduled fetch 配置

| 字段 | 填什么 |
|---|---|
| Fetch URL | `https://glameyeshop.com/merchant-feed.xml` |
| Fetch frequency | `Daily`(每天拉一次) |
| Fetch time | `02:00 PT`(美国凌晨,流量低) |
| Username / Password | **留空**(我们的接口公开) |

### 4.4 点 **Create feed**

Google 会**立刻拉一次**测试。1-5 分钟后,Feed 列表里会显示拉取状态:

- ✅ Items: 100 / Errors: 0 → 完美
- ⚠ Warnings → 点开看具体警告(常见:image too small、description too short)
- ❌ Errors → 修了再拉

---

## 第 5 步 — 审核(等 3-7 天)

提交 feed 后,Google 会:

1. **24 小时内**爬几个产品 URL 检查页面是否真实存在 / 价格是否一致 / 政策页是否齐全
2. **3-7 天**审核账号是否合规
3. 通过后产品自动出现在 **google.com/shopping** 搜索结果里(免费!)

### 期间你可能收到的邮件

| 邮件主题 | 含义 | 怎么办 |
|---|---|---|
| "Account suspended for policy violation" | 政策不通过 | 大概率是:缺退货页 / 价格不对 / 图片不清。点邮件里 "Request review" |
| "Items disapproved" | 部分产品被拒 | 进 Diagnostics 看具体 SKU + 原因,改完再拉 feed |
| "Account verified ✓" | 通过! | 24 小时内 google.com/shopping 能搜到 |

---

## 第 6 步(可选)— 关联 Google Ads 投付费 Shopping ads

免费 listings 上去了,但流量有限。投 Shopping ads 转化率最高。

### 6.1 关联账号

Merchant Center 右上角齿轮 → **Linked accounts** → **Google Ads** → Link

### 6.2 创建 Shopping campaign

去 Google Ads → **+ New campaign** → **Sales** goal → **Shopping** type → 选刚关联的 Merchant Center → **Standard Shopping campaign**(新手友好)

| 字段 | 推荐 |
|---|---|
| Daily budget | `$5/day` 起步,跑 1 周看数据 |
| Bidding | `Maximize clicks` 起步,有数据后换 `Target ROAS` |
| Networks | ✅ Google search · ✅ Search partners · ✅ YouTube |
| Locations | `United States` |
| Ad groups | 1 个 "All Products" 起步 |

### 6.3 出价策略升级路径

| 月销售额 | 建议出价 |
|---|---|
| < $500 | Maximize clicks(拿流量学习数据)|
| $500-2000 | Maximize conversion value |
| > $2000 + 有 30+ 转化 | Target ROAS 设 400%(每 $1 广告费换 $4 收入)|

---

## 部署 + 验证

### push 上线

```bash
cd ~/glameyeshop && git push origin main
```

等 2-3 分钟部署完。

### 检查 feed 是否能访问

```bash
curl -sI https://glameyeshop.com/merchant-feed.xml | head -5
```

应该看到 `HTTP/2 200` 和 `Content-Type: application/xml`。

或者直接浏览器打开 https://glameyeshop.com/merchant-feed.xml 看到一堆 `<item>...</item>` 就 OK。

### admin 后台健康检查

登录后台 → 左侧 **🛍 Google Shopping**(`/admin/merchant-center.php`),看到:

- 📡 Feed URL(可复制)
- 🩺 Pre-flight check(产品数 / 缺图 / 短描述 / HTTPS / 4 法务页)
- 🔍 Preview feed 按钮(直接看 XML 输出)

---

## 常见审核被拒原因 + 修法

| 拒因 | 修法 |
|---|---|
| **Missing return policy page** | 后台 Refund 页已建,检查 GMC Returns 是否填了 https://glameyeshop.com/refund.html |
| **Inaccurate availability** | 产品 stock 改成 0 时 feed 自动变 out_of_stock,**但 GMC 缓存 24h** — 等一天 |
| **Inaccurate price** | feed 价格和落地页价格不一致(我们用同一个 DB,不会出错。但促销定时器可能错位) |
| **Image quality too low** | 主图必须 ≥250×250 px,推荐 800×800+。GlamEye 现有图都是 1024,OK |
| **Promotional overlay on image** | 主图上不能有 "Sale" / "Free Shipping" 等文字。GlamEye 现有图清洁 |
| **Adult content** | 不会触发(美妆) |
| **Untrusted store** | 新站常见。1)开 Google Customer Reviews 收集评分 2)开 Trustpilot 3)等 30 天信任建立 |

---

## 一句话总结

```
1. https://merchants.google.com → 注册账号
2. 验证域名(Search Console 已有 → 自动通过)
3. 配运费 + 退货页
4. Products → Feeds → Add primary feed → Scheduled fetch
5. URL 填: https://glameyeshop.com/merchant-feed.xml
6. 等 3-7 天审核
7.(可选)关联 Google Ads → Shopping campaign
```

30 分钟搞定配置,7 天内产品就能出现在 google.com/shopping 搜索结果里(**免费!**),
再投 Shopping ads 进一步放大。

---

## 后续优化(在通过之后做)

| 优化项 | 怎么做 | 预期效果 |
|---|---|---|
| **Google Customer Reviews** | GMC → Programs → Customer Reviews → 启用 | 5⭐ 评分显示在 Shopping 卡片,CTR +30% |
| **Free listings 优化** | 改 product title 加关键词:`GlamEye Naked 18mm Mink Cluster Lashes - DIY Extensions Reusable 20 Wears` | 排名上升 |
| **促销标签 Promotions** | Merchant Center → Marketing → Promotions(免费) | 产品卡片右上角显示 "10% off" 标签 |
| **Local inventory ads** | 如果有线下店或 pop-up | 显示 "Available nearby" |
| **Multi-country expansion** | Merchant Center → Country → Add(CA / UK / EU 一个个加) | 全球流量 |
