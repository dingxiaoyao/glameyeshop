# Stripe 集成上线指引

> 假设 `cd ~/glameyeshop && git push origin main` 已经把本次代码部署上去了。
> 这份文档帮你从 0 → 跑通 test 模式 → 切 live 模式。

---

## TL;DR · 7 分钟搞定

1. Stripe Dashboard → 拿 test pk_ / sk_ → 粘到 admin/settings
2. Stripe Dashboard → 加 webhook endpoint → 拿 whsec_ → 粘到 admin/settings
3. 用测试卡 `4242 4242 4242 4242` 走一遍购物流程,确认 admin 看到订单 paid
4. 没问题 → Dashboard 切到 live → 重复 1-2 步用 live 密钥

---

## Step 1 · 拿 API 密钥(2 分钟)

### 1.1 登录 Stripe Dashboard
打开 [dashboard.stripe.com](https://dashboard.stripe.com) 登录。**左上角 Mode 切换器确保是 "Test mode"**(橙色标识)。

### 1.2 复制密钥
- 打开 **Developers → API keys**(直链:`https://dashboard.stripe.com/test/apikeys`)
- 复制 **Publishable key**(`pk_test_xxx`)
- 点 **Reveal test key** 复制 **Secret key**(`sk_test_xxx`)

### 1.3 粘到 admin Settings
- 打开 `https://glameyeshop.com/admin/settings.php`(或你 staging 的地址)
- 滚动到 **💳 Payment Gateway · Stripe**
- 字段对应填入:
  - **Mode** → `Test (sandbox)`
  - **Publishable Key** → 粘 `pk_test_xxx`
  - **Secret Key** → 粘 `sk_test_xxx`
- **暂时先不点 Save**(等 webhook secret 一起填完一次性保存)

---

## Step 2 · 注册 Webhook 端点(3 分钟)

### 2.1 打开 Stripe Webhooks 页面
直链:[dashboard.stripe.com/test/webhooks/create](https://dashboard.stripe.com/test/webhooks/create)

### 2.2 填 Endpoint URL
```
https://glameyeshop.com/api/stripe-webhook.php
```
(admin Settings 页有"Copy"按钮一键复制,跟当前域名匹配)

### 2.3 选 Events to send
点 **"Select events"**,搜索并勾选这 **5 个**:

| Event | 我做什么 |
|---|---|
| `checkout.session.completed` | 订单 pending → paid + 加 tracking event |
| `checkout.session.async_payment_succeeded` | 异步支付方式(银行转账)到账后同上 |
| `checkout.session.async_payment_failed` | 异步支付失败 → 订单 status = payment_failed |
| `checkout.session.expired` | 用户没付款关闭页 → 订单 status = expired |
| `charge.refunded` | 退款(全额/部分) → 订单 status = refunded / partial_refund + 加 tracking event |

点 **Add events** → **Add endpoint**。

### 2.4 复制 Signing secret
端点创建完,Stripe 把你带到端点详情页。点 **"Signing secret" → Reveal**,复制 `whsec_xxx`。

### 2.5 粘到 admin Settings
回到 admin Settings,粘到 **Webhook Signing Secret** 字段 → 点 **Save**。

---

## Step 3 · 用测试卡验证(2 分钟)

### 3.1 走一遍完整流程
1. 打开主站,加车任一商品 → 进 `/cart.html` → `/checkout.html`
2. 填随便一个邮箱(自己的真邮箱也行)、随便美国地址(`123 Main St, Los Angeles, CA 90001`,Phone `5555555555`)
3. 选 Stripe → 同意条款 → 点 **Place Order**
4. 应该跳到 Stripe 托管支付页(`checkout.stripe.com`)
5. 用 **测试卡** 填:
   - 卡号:`4242 4242 4242 4242`
   - 到期日:任意未来日期(如 `12/30`)
   - CVC:任意 3 位(如 `123`)
   - 持卡人名 / ZIP:随便填
6. 点 **Pay** → 跳回 `/order-success.html?order_id=X&session_id=cs_test_xxx`

### 3.2 确认订单变 paid
打开 admin → Orders,你刚下的那单应该:
- 状态 **paid**(绿色)
- 点 📦 Tracking 看时间线,应该有一条 "Payment received via Stripe (USD 29.00)"

如果状态还是 **pending** → webhook 没收到 / 签名验证失败 / DB 没更新,见下方 Troubleshooting。

---

## Step 4 · 切到 Live 模式(发布前最后一步)

⚠️ **切之前**先确保 test 模式跑通了,这是不可逆的真钱流。

1. **Stripe Dashboard 左上角 Mode → Live**(橙色变蓝色)
2. 重复 Step 1.2:在 **dashboard.stripe.com/apikeys**(注意 URL 没 `/test/`)复制 `pk_live_xxx` 和 `sk_live_xxx`
3. 重复 Step 2:在 `dashboard.stripe.com/webhooks` 注册 **新的** webhook endpoint(live mode 的 webhook 跟 test 是分开的!),拿 live 的 `whsec_xxx`
4. 回 admin Settings → Mode 切到 **Live** → 粘 live 的 pk / sk / whsec → Save
5. 用真信用卡走一遍 $1 测试单(Stripe 不退手续费,但能确认链路通)

---

## Troubleshooting

### 订单一直停在 pending

**Stripe Dashboard → Developers → Webhooks → 你的端点 → Webhook attempts** 看响应日志:

| Stripe 显示的状态码 | 原因 | 修法 |
|---|---|---|
| `503 Webhook not configured` | Settings 里 webhook secret 没填 / 填错 | 重新到 Stripe 复制 whsec_,粘到 Settings |
| `400 Invalid signature` | webhook secret 跟 Stripe 端点不一致 | 重新对比 whsec_,Stripe Dashboard 也可点 "Roll secret" 重新生成 |
| `400 Malformed signature` | 请求被中间件改了(很少见) | 检查 Cloudflare / 反向代理设置 |
| `200 OK` 但订单还是 pending | webhook 收到了但 DB 没更新 | 检查 SSH 上服务器的 PHP error log:`tail -100 /var/log/nginx/error.log` 或 `/var/log/php*/error.log` |

### 跳转到 stripe-checkout.php 后报 503

- 503 + "Stripe not configured" → Secret Key 字段是空的,重新填
- 503 + "key does not match the configured mode" → Mode 选了 Live 但密钥是 `sk_test_`(或反之)

### Stripe 返回 400 Invalid api_key

- Secret key 复制时多了空格 / 引号 → 重新复制粘贴
- 或者把 test key 填到 live mode → 在 admin Settings 切 mode

---

## 安全 checklist(上线前过一遍)

- [x] `api/settings.php` 的白名单已经把 `*_secret` `whsec_*` `*_key` 设为不返回前端(api/config.php 已守门)
- [x] `stripe-checkout.php` 拿 secret 从 DB 而不是 env,跟 admin Settings 一致
- [x] `stripe-webhook.php` HMAC-SHA256 + 5 分钟容差防重放 + `hash_equals` 防时序攻击
- [x] orders 表 `payment_session_id` 是反查订单的兜底通道
- [x] webhook 处理是幂等的(`WHERE status IN ('pending','expired')` 重复事件不会覆盖已 paid)
- [ ] **生产前手动确认** Stripe Dashboard 设置:
  - Payment methods → Apple Pay / Google Pay 启用了吗?(默认开)
  - Branding → 上传 logo + 配色 → 提高托管支付页的转化率
  - Tax → 如果在加州卖,考虑接 Stripe Tax 自动算税(我现在 tax=0)
  - Customer email → 启用 receipt email(默认会发付款收据邮件)

---

## 进阶(后续可以做)

- **Apple Pay domain verification** — 提高 mobile Safari 用户转化率,需要把 Stripe 给的 `apple-developer-merchantid-domain-association` 文件放到 `.well-known/`
- **Refund UI** — admin/orders.php 加一个 "Refund" 按钮,点了直接调 Stripe Refunds API 退款
- **Stripe Customer** — 复购用户绑定 Stripe Customer ID,下次结算自动填卡(currently 都是 guest checkout)
- **Saved cards** — 让登录用户存卡(Stripe Setup Intents),复购一键支付
- **Tax** — Stripe Tax 自动算各州税率,US 跨州卖必须的(现在我设的 tax=0)

需要哪一项告诉我,我可以接着做。
