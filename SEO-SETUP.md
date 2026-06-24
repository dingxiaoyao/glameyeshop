# GlamEye SEO 优化完整指南

让 Google / Bing 搜索结果里能找到 glameyeshop.com,从注册到见效全流程。

---

## 当前已做(代码层面)

| ✅ | 已完成 |
|---|---|
| sitemap.php | 动态生成所有页面 URL |
| robots.txt + robots.php | 允许 Googlebot 抓取 |
| 首页 / shop / about 完整 title + description | 关键词覆盖 |
| Open Graph + Twitter Card | 社交分享卡片 |
| OnlineStore JSON-LD schema | 首页结构化数据 |
| product.html 动态 meta tags | title / description / OG / Twitter / canonical(新增)|
| Product JSON-LD schema | 富片段:价格 / 库存 / 星级(新增)|

---

## 你需要做的 5 件事(按优先级)

### 🥇 第 1 件 — 注册 Google Search Console(必做!)

让 Google 知道你的站存在 + 主动抓取。

#### 操作步骤

1. 打开 https://search.google.com/search-console
2. 用 `info@glameyeshop.com` 登录
3. 添加资源 → 选 **网域 (Domain)** → 输入 `glameyeshop.com`
4. Google 让你做 **DNS 验证**:
   - 复制 Google 给的 TXT 记录(类似 `google-site-verification=abc123...`)
   - 登录你的域名注册商(Namecheap / GoDaddy / Cloudflare 等)
   - DNS 管理 → 添加 TXT 记录:
     - Type: `TXT`
     - Host: `@`
     - Value: `google-site-verification=abc123...`
     - TTL: `Automatic` 或 `300`
   - 保存
5. 回 Search Console 点 **验证** → 几分钟内通过

#### 验证通过后立刻做

**a. 提交 sitemap**:

```
左侧菜单 → 站点地图(Sitemaps)
新站点地图 URL 那里输入:sitemap.xml
点 "提交"
```

Google 会在 1-7 天内开始爬你的站。

**b. URL 检查 + 索引申请**(加速首页收录):

```
顶部搜索框 → 粘 https://glameyeshop.com/
回车 → 状态显示 "网址不在 Google 上"(正常,新站)
点 "请求编入索引"
等 1-3 天 Google 会爬一次
```

---

### 🥈 第 2 件 — 注册 Bing Webmaster Tools

10% 搜索流量,值得做。

1. https://www.bing.com/webmasters
2. 用 Google 账户登录(Bing 支持)
3. 直接从 Google Search Console **一键导入**(顶部按钮)
4. 完成,不用再做 DNS 验证

---

### 🥉 第 3 件 — 写产品博客内容(SEO 的核心)

你的 SKU 页面再多,Google 看到的也都是"产品",对长尾流量贡献小。**博客文章**才能带来"how to apply cluster lashes"这类搜索的流量。

#### 推荐内容方向(按搜索量)

| 文章主题 | 月搜索量(美国)| 难度 |
|---|---|---|
| **How to apply cluster lashes for beginners** | 12,000 | 中 |
| **Cluster lashes vs strip lashes** | 8,000 | 易 |
| **How long do DIY cluster lashes last?** | 5,500 | 易 |
| **Best cluster lashes for hooded eyes** | 4,000 | 中 |
| **How to remove cluster lashes without pulling** | 3,500 | 易 |
| **Fox eye lash tutorial step by step** | 7,000 | 中 |

#### 操作

GlamEye 当前没博客系统 — 如果你想要,告诉我加(我做一个 admin 可编辑的博客模块)。

**临时方案**:在 `/about.html` 添加 FAQ 区,塞 5-10 个高搜索量的问答 — 即使不是博客,Google 也会抓取。

---

### 第 4 件 — 加外链(让 Google 觉得你权威)

新站 Google 不会主动给排名,需要其他站给你"投票"。

#### 免费外链来源

| 站点 | 怎么拿 |
|---|---|
| **Reddit** | r/MakeupAddiction r/falsies 等版块,**真诚回答**别人提问,签名留你产品链接(不要硬广)|
| **Quora** | 答 "best cluster lashes" 类问题,提到 GlamEye |
| **Pinterest** | 上传产品图,描述里加 glameyeshop.com 链接 — Pinterest 是 SEO 强大杠杆 |
| **YouTube** | 教程视频描述里链回产品页 |
| **TikTok bio** | bio link 放 glameyeshop |
| **小型美妆博客** | 邮件邀请合作,送样品换博客评测(自然带 do-follow 链接)|

---

### 第 5 件 — 提升核心 Web Vitals(页面速度)

Google 排名因素之一。GlamEye 现在基础不错,但可优化。

#### 立刻测试 GlamEye

打开:https://pagespeed.web.dev/

输入 `https://glameyeshop.com/` → 测试 → 看分数:

- 绿色 90+ = 优秀
- 黄色 50-89 = 需要优化
- 红色 0-49 = 严重问题

如果分数 < 80,告诉我具体哪项红 / 黄,我帮你修。

---

## 时间预期(SEO 是慢工)

| 时间 | 阶段 |
|---|---|
| **第 1 周** | Google Search Console 验证 + sitemap 提交 |
| **第 2-4 周** | Google 开始爬首页 + 产品页(每天爬几个)|
| **第 1-3 月** | 产品页慢慢出现在搜索结果(长尾词如 "GlamEye natural cluster lashes")|
| **第 3-6 月** | 如果有博客 + 外链,中等搜索词开始有排名(如 "cluster lash kit")|
| **第 6-12 月** | 大流量词("false eyelashes")才有机会进 Top 10 |

SEO **不是 1 个月见效的**,但是**复利效应** — 第一年很慢,之后每月稳定增长。

---

## 你立刻该做的 ONE THING

```
1. https://search.google.com/search-console
2. 用 info@glameyeshop.com 登录
3. 添加 glameyeshop.com 资源
4. DNS 验证(找你域名注册商加 TXT 记录)
5. 验证通过 → 提交 sitemap.xml
6. URL 检查首页 → 请求索引
```

10 分钟搞定,后续 1-7 天 Google 开始爬你的站。

---

## SEO 验证工具(免费的)

| 用途 | 工具 |
|---|---|
| 检查 Google 收录了你哪些页 | Google 搜 `site:glameyeshop.com` |
| Schema.org 结构化数据测试 | https://search.google.com/test/rich-results |
| 模拟 Googlebot 看你的页 | https://www.google.com/webmasters/tools/render-resource(Search Console 内)|
| 关键词搜索量 | https://ads.google.com/aw/keywordplanner(Google Ads 内,免费)|
| 竞品分析 | https://ahrefs.com/free-seo-tools(免费版)|

---

## 一句话总结

**今天就去 Google Search Console 注册 + 验证域名 + 提交 sitemap**。

剩下的(博客 / 外链 / Web Vitals)是 3-6 个月的事,慢慢做。

代码层面我已经把 product.html 的 SEO 短板全修了(title + description + Schema + canonical 全动态注入),你点击产品页后 Google 抓取就能看到完整内容了。

push 完(等部署),用这工具验证修复:
👉 https://search.google.com/test/rich-results
输入 `https://glameyeshop.com/product.html?sku=GE-CK-NATURAL` → 应该显示绿勾 "找到了 Product 富媒体搜索结果"
