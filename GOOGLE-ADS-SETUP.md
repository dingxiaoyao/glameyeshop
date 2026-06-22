# GlamEye × Google Ads 配置完整教程

从零开始,把广告点击 → 实际成交全链路打通。

---

## 你现在的位置 — 技术准备

GlamEye 站点**代码层面已全部就绪**(commit `9c68d5a`):

- ✅ `order-success.html` 自动发 Google Ads conversion(带订单金额/币种/order_id)
- ✅ 同时发 GA4 `purchase` 事件(用于 GA 报表 + Google Ads 联动)
- ✅ 严格规则:status ∈ `paid/processing/shipped/delivered` + 非 test 订单 + 同订单不重复触发
- ✅ admin/settings.php 有 **🎯 Google Ads 转化追踪** 卡片(填 2 个字段即可)

你需要做的就是 **去 Google Ads 后台拿 2 个字符串填进去**,然后投广告。

---

## 第 0 步 — 准备一个 Google 账户

任意 Google 账户都行(推荐用 `info@glameyeshop.com` 这个新建的域名邮箱,跟品牌一致)。

---

## 第 1 步 — 注册 Google Ads 账户

1. 访问 https://ads.google.com
2. 点 **Start now** / **开始**
3. Google 会问你"想达到什么目标":选 **"Get more website sales or sign-ups"**
4. 它会进**简易模式**(Smart campaigns)— **关键:** 拉到底部找 **"Switch to Expert Mode"**(切换到专家模式),否则没法配自定义 conversion

> 简易模式是给完全不懂广告的人,不适合电商。专家模式才有你需要的所有功能。

5. **跳过创建第一个 campaign**(底部有小字"Create an account without a campaign")— 我们要先建 conversion,再投广告
6. 填账单信息(币种选 **USD**,时区选 **America/Los_Angeles** 或你的目标市场时区)
7. 绑信用卡(**不投广告不扣钱**,只是开户必需)
8. 完成后会拿到一个 Google Ads 账户 ID(例如 `123-456-7890`,9 位)

---

## 第 2 步 — 创建 Conversion Action(转化目标)

这步定义"什么算一次成功转化"。我们要的就是"客户在 order-success 页支付成功"。

1. Google Ads 后台顶部菜单 → **Tools (扳手图标 🔧)** → **Conversions**
2. 点蓝色按钮 **+ New conversion action**
3. 选 **Website**(网站转化)
4. 输入你的域名 `glameyeshop.com` → 点 **Scan**
   - Google 会扫描你的站点,可能弹出"我们检测到 GA4 tag,要不要直接用 GA4 的 conversion 事件?"
   - **不选** GA4 自动 — 选下方 **Create conversion actions manually**

### 配置项 — 严格按这个填

| 字段 | 填什么 | 为什么 |
|---|---|---|
| **Goal** | `Purchase`(购买) | 这是电商最高价值的转化 |
| **Conversion name** | `GlamEye Purchase` | 自己看得懂就行 |
| **Value** | ⭐ **Use different values for each conversion** | 关键!用每单实际金额 |
| Default value | `1.00 USD`(兜底) | 万一传不到金额时的默认值 |
| **Count** | ⭐ **Every**(每次) | 同客户买 2 次算 2 次转化 |
| **Click-through conversion window** | `30 days` | 用户 30 天内购买都算这次广告的功劳 |
| **Engaged-view conversion window** | `3 days` | YouTube 视频广告看完没点,3 天内买也算 |
| **View-through conversion window** | `1 day` | 看了 banner 没点,1 天内买也算 |
| **Attribution model** | ⭐ **Data-driven**(数据驱动) | Google ML 自动算多触点权重(2024+ 默认推荐) |
| **Include in 'Conversions'** | ✓ Yes | 让这个 conversion 参与出价优化 |

> Attribution model 如果你账户太新(< 30 天)没数据,Google 可能只让你选 Last click。先用着,有数据后回来切到 Data-driven。

5. 点 **Create and continue**

---

## 第 3 步 — 拿到 Conversion ID + Label

创建完后,Google 会让你选**怎么安装 tag**。

1. 选 **Use Google tag manager** ❌ 不要(我们直接装 gtag)
2. 选 **Install the tag yourself** ✅ 选这个

它会显示 2 段代码:

### 段 ① — Global site tag(我们不需要复制,GlamEye 站已经有了 GA4 gtag.js)

```html
<!-- Global site tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-1234567890"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'AW-1234567890');
</script>
```

### 段 ② — Event snippet(这里有我们要的两个值)

```html
<!-- Event snippet for GlamEye Purchase conversion page -->
<script>
  gtag('event', 'conversion', {
      'send_to': 'AW-1234567890/abcdEFGH123',
      'value': 1.0,
      'currency': 'USD',
      'transaction_id': ''
  });
</script>
```

**只看 `send_to` 这一行**:

```
'send_to': 'AW-1234567890/abcdEFGH123'
              ↑              ↑
        Conversion ID    Conversion Label
```

- **Conversion ID** = `AW-1234567890`(以 AW- 开头)
- **Conversion Label** = `abcdEFGH123`(11 位左右随机字符)

把这两个**分别记下来**。

---

## 第 4 步 — 在 GlamEye admin 填值

1. 登录 https://glameyeshop.com/admin/settings.php
2. 滚到 **🎯 Google Ads 转化追踪** 卡片
3. 填:
   - **Conversion ID (AW-…)**: `AW-1234567890`
   - **Conversion Label**: `abcdEFGH123`
4. 点页面底部 **💾 保存全部设置**

完成。代码层面**马上生效**,任何下一笔真实订单都会自动上报。

---

## 第 5 步 — 验证 conversion 在工作

### 方法 A — 用 Chrome 扩展 Tag Assistant Companion(推荐,最快)

1. 装 [Tag Assistant Companion](https://chromewebstore.google.com/detail/tag-assistant-companion/jmekfmbnaedfebfnmakmokmlfpblbfdm)(Chrome 扩展)
2. 也装 [Google Tag Assistant](https://tagassistant.google.com/) 开 debug 模式
3. 输 `https://glameyeshop.com` 进 debug session
4. 走完真实下单流程(用 Stripe test 模式 + 真卡 $5 测试品都行,但 **不要勾 test 账号**)
5. 在 order-success 页 Tag Assistant 应该显示:
   ```
   ✅ AW-1234567890 conversion fired
       value: 5.00 USD
       transaction_id: 42
   ✅ G-0LESHNQ1LG purchase event
       value: 5.00
       items: [...]
   ```

### 方法 B — 等数据进 Google Ads 后台

1. 让真实客户下一单(或自己用真信用卡买 $5 试品)
2. 等 **3-12 小时**(Google Ads 不是实时的)
3. 回 Google Ads → Tools → Conversions → 看你的 `GlamEye Purchase` 行
4. **Status** 列应该从 `No recent conversions` 变成 **`Recording conversions`**

> 没看到?常见原因:
> - admin 字段填错(label 多一个空格 / 大小写错)
> - 你测的订单勾了 test 账号(代码故意跳过 test 单避免污染数据)
> - 浏览器装了广告拦截扩展(uBlock / AdBlock)

---

## 第 6 步 — 投放第一个广告活动

转化追踪在跑后,可以开始投广告了。GlamEye 这种新电商,**强烈推荐先跑 Performance Max**(全自动,Google 帮你跑遍 Search/Display/YouTube/Gmail/Maps)。

### 建议预算配置

| 阶段 | 日预算 | 出价策略 | 目标 |
|---|---|---|---|
| 第 1 周(冷启动) | $20/天 | **Maximize conversions** | 不设 ROAS,让 Google 先收集数据 |
| 第 2-3 周(学习期) | $30-50/天 | **Maximize conversion value** | 让 Google 优化高客单价订单 |
| 第 4 周后(稳定) | 按 ROAS 加预算 | **Target ROAS = 300%** | $1 广告费收 $3 GMV |

### 创建 Performance Max campaign

1. Google Ads 后台 → **+ Create** → **Campaign**
2. Goal 选 **Sales**
3. Type 选 **Performance Max**
4. Conversion goal 选我们刚建的 **GlamEye Purchase**(默认就会勾)
5. Budget: 设日预算(建议 $20-30 起步)
6. Bidding: 起步 **Maximize conversions**(后期改 Maximize conversion value)
7. Audience signal(可选 — 给 Google 灵感):
   - Search terms: `false eyelashes`, `DIY lash kit`, `cluster lashes`, `lash strips`
   - Demographics: Female, 18-44
   - Interests: Beauty & Fashion, Makeup
8. Asset group(广告素材):
   - **5-15 张图**: 上传你的 cluster kit 产品图、模特佩戴图、对比图
   - **5+ 短文案**(30 字符): "DIY lash kit · all-day hold", "Cluster lashes · cruelty-free", "Pro lash tools · ships free"
   - **5+ 长文案**(90 字符): "From everyday natural to fox-eye drama — GlamEye cluster kits with bond+seal+remover."
   - **1-5 个视频**(可选,YouTube 显示):上传 TikTok 那种 30 秒 教程
9. Final URL: `https://glameyeshop.com/shop.html`
10. **Launch**

---

## 几个避坑提醒

| 坑 | 怎么避 |
|---|---|
| ❌ **没设转化金额 = Google 不知道哪些客户值钱** | 上面 ⭐ Use different values for each conversion 必选 |
| ❌ **预算太低 Google 学不到东西** | 至少 $20/天 × 2 周(总 ~$280 学习成本) |
| ❌ **天天换素材让算法重启学习** | 学习期(7-14 天)不要改 campaign/asset/出价 |
| ❌ **不看 Search terms 报告 = 烧钱在不相关词** | 每周 Tools → Insights and reports → Search terms → 加负面关键词 |
| ❌ **Test 账户订单被算进转化** | 我代码层面已经跳过 `?test=1` 订单了,不用担心 |
| ❌ **iOS 隐私 → 跨设备追踪不准** | Google Ads 用 Enhanced Conversions(下面有详细说明)能补回大部分 |

---

## 第 7 步(进阶)— 启用 Enhanced Conversions

iOS 14.5+ 限制了 cookie 追踪,会少 20-40% 转化归因。**Enhanced Conversions** 通过把客户的 email(SHA256 hashed)发给 Google,大幅提升匹配率。

GlamEye 当前**没启用** Enhanced Conversions(需要在 conversion event 里加 `email` 字段)。如果你想加,告诉我:

```js
// 改 order-success.html fireConversion 加一段:
gtag('set', 'user_data', {
  email: o.email  // 客户邮箱,Google 自动 SHA256
});
gtag('event', 'conversion', { ... });
```

我可以加上,需要客户在 GDPR 区域(欧盟)合规弹窗同意。

---

## 第 8 步(进阶)— 上传 Customer Match List

把你现有客户的邮箱列表上传 Google Ads,用于:
- **Lookalike audience**: Google 找像他们的人投广告
- **重定向**: 给老客户投回头客优惠

GlamEye admin 可以导出:**admin/customers.php → Export CSV**(如果还没这个按钮告诉我加)

---

## 一句话总结

1. 注册 Google Ads → 切 Expert Mode
2. Tools → Conversions → New → **Purchase + different values + 30/3/1 day + Data-driven**
3. 抄 send_to 里 `/` 前后两段
4. GlamEye admin/settings.php 填进去 → Save
5. 用 Tag Assistant 验证 → 看到绿勾 ✅
6. Create campaign → Performance Max → $20/天 → Launch

任何一步卡住,截图发我具体在哪。

---

## 附录:Conversion 与 GA4 的关系

| 系统 | 做什么 | 数据来源 |
|---|---|---|
| **GA4** (`G-XXXX`) | 看用户路径、漏斗、留存 | gtag.js `purchase` 事件 |
| **Google Ads** (`AW-XXXX`) | 优化广告投放 + Smart Bidding | gtag.js `conversion` 事件 |
| **两者联动** | GA4 受众导入 Google Ads | 在 GA4 → Admin → Product links → Google Ads link |

GlamEye 现在**两个都发**,所以 GA4 报表里看得到购买,Google Ads 也能用同一笔转化优化广告。
