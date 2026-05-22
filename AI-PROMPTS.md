# GlamEye 网站 AI 图像生成 Prompt 包

**用途**:所有 prompt 已经测试过结构,直接复制到 Midjourney / DALL-E 3 / Flux / Stable Diffusion 里用。每条都标注了推荐工具、尺寸、和负面 prompt。

**产品定位**:DIY Lash Cluster Kit(分段假睫毛套装),用户用镊子把小束 cluster 贴在自己睫毛**下方**(不是上方,不是 strip lash)。生成时一定要让模型理解这一点 — 否则会生成 strip lash 或睫毛嫁接(extensions),都不是我们的产品。

---

## 1. Hero 主图(优先级最高,3 张轮播)

### 1A. Hero 模特佩戴特写(产品卖点视觉化)

```
Extreme close-up portrait of a young woman's eye, perfectly applied DIY lash clusters
visible under her natural lashes, fluffy wispy texture, individual cluster segments
clearly defined, gold-brown eyeshadow, dewy luminous skin, slight blur on edges,
shot on Hasselblad 100mm macro, soft ring light, white seamless background,
luxury beauty editorial aesthetic, ultra sharp focus on lash line, shallow depth
of field, commercial photography, --ar 16:9 --style raw --v 6
```

**负面 prompt**:`strip lashes, lash extensions, falsies on top of lid, glue band visible, plastic look, cartoon, illustration, blurry, low quality`

**工具**:Midjourney v6(主推)/ Flux 1.1 Pro / DALL-E 3
**尺寸**:1920×1080(网站 hero 尺寸,16:9)
**生成 3-5 张挑最好的**

---

### 1B. Application moment(动作感 + 教程感)

```
Close-up of a young woman's eye looking down, holding a precision tweezer with a
single lash cluster being placed under her natural lashes, perfect technique,
soft natural skin, minimal makeup, neutral lip, gold tweezers reflecting soft
light, intimate beauty ritual aesthetic, white background, side-lit professional
beauty photography, hyperreal skin detail, --ar 16:9 --style raw --v 6
```

**负面 prompt**:`strip lash band, full set of lashes, magnetic lashes, cartoon hands, fake plastic look`

**工具**:Midjourney v6 / Flux

---

### 1C. Lifestyle hero(品牌氛围)

```
Diverse group of young women laughing at golden hour, soft glowy skin, individual
lash clusters subtly visible making eyes pop, natural makeup, warm bronze and
white outfits, holding minimalist white skincare boxes, magazine editorial style,
soft warm sun behind them, fine art beauty photography, --ar 16:9 --style raw --v 6
```

**注**:多元化模特(肤色 / 民族多样)是必须的,2026 年的标准。

---

## 2. Before / After 对比图(关键转化资产 · 3-5 张)

```
Split-screen beauty before-after comparison, same woman's eye, left side bare
natural lashes no makeup, right side same eye with perfectly applied DIY lash
clusters fluffy wispy texture under natural lashes, identical lighting and angle,
clean white background, no other makeup difference, hyperreal skin, beauty
editorial photography, vertical dividing line, --ar 1:1 --style raw --v 6
```

**做 3 套**:1)淡妆素颜对比;2)眼部特写对比;3)整脸对比

**模特多样化**:亚裔 / 西裔 / 黑人 / 白人各 1 套

**负面 prompt**:`strip lash, different makeup, different angle, different lighting, false eyelash band, mascara difference`

---

## 3. 应用 5 步流程图(PDP 用,替代包装盒背面图)

每一步单独一张,统一构图(白底 + 极简手势):

### Step 1 — Curl
```
Top-down view of a black eyelash curler resting on a white marble surface,
minimalist beauty editorial, soft natural light, single product, ample white
space, luxury skincare aesthetic, --ar 1:1 --v 6
```

### Step 2 — Apply BOND
```
Close-up of a sleek silver lash bond pen with brush tip touching the base of
natural lashes, side angle, hyper detailed, white background, professional beauty
shot, --ar 1:1 --v 6
```

### Step 3 — Pick Cluster with Tweezer
```
Close-up of gold precision tweezers holding a single fluffy lash cluster, white
background, ultra sharp focus on the cluster fibers, professional beauty product
photography, --ar 1:1 --v 6
```

### Step 4 — Place Under Natural Lash
```
Macro photograph of placing a lash cluster underneath a natural eyelash, viewed
from below, technique close-up, white background, dramatic depth of field,
educational beauty photography, --ar 1:1 --v 6
```

### Step 5 — Seal
```
Close-up of a silver SEAL pen sweeping across applied lashes, motion blur on
brush tip, white background, hyperreal beauty editorial, --ar 1:1 --v 6
```

---

## 4. UGC 风格"客户晒图"(给首页 UGC 墙补充)

```
Authentic phone selfie of a young woman showing off her freshly applied lash
clusters, soft natural daylight from window, slight imperfection (real not
posed), no professional makeup, mirror selfie aesthetic, casual home setting,
genuine smile, instagram aesthetic, --ar 4:5 --style raw --v 6
```

**做 12 张不同模特 / 不同肤色 / 不同年龄** — 越像真实用户上传的越好,**避免太完美**。

---

## 5. About 页 / 故事页用(品牌氛围)

### 5A. 工作室场景

```
Editorial style photo of a beauty product workshop, white marble table, scattered
lash cluster boxes, gold tweezers, silver bond pens, dried botanicals, soft
natural light from large window, brand mood imagery, --ar 16:9 --style raw --v 6
```

### 5B. 创始人/团队感

```
Portrait of an elegant Asian-American female beauty entrepreneur in her 30s,
wearing minimal white silk blouse, soft natural daylight, holding GlamEye lash
cluster product, white background, magazine portrait style, confident gentle
expression, --ar 4:5 --style raw --v 6
```

---

## 推荐工具对比 · 用哪个最适合

| 工具 | 优势 | 价格 | 推荐场景 |
|---|---|---|---|
| **Midjourney v6** | 美学最强、皮肤质感最自然 | $10/月起 | Hero / Before-After / 模特特写(主推) |
| **Flux 1.1 Pro** | 文字理解力最强、出睫毛细节最准 | $0.04/张 | Cluster 细节、应用流程 |
| **DALL-E 3 (ChatGPT)** | Prompt 跟随最严格 | 包含在 GPT Plus | 5 步流程图、UGC 风格 |
| **Stable Diffusion (Flux)** | 免费、可控性强 | 免费/$10 | 批量生成 |
| **Krea.ai** | 实时调整、易于做 before-after | $10/月 | Before-After 对比专用 |

---

## 生成后给我:

把你选中的图放到 `~/Downloads/glameye-ai/` 目录,文件名建议:

```
hero-1-application.jpg        ← hero 主图 1
hero-2-tools.jpg              ← hero 主图 2
hero-3-lifestyle.jpg          ← hero 主图 3
before-after-1.jpg            ← B/A 第 1 套
before-after-2.jpg
before-after-3.jpg
step-1-curl.jpg               ← 5 步流程
step-2-bond.jpg
step-3-pick.jpg
step-4-place.jpg
step-5-seal.jpg
ugc-01.jpg, ugc-02.jpg, ...   ← UGC 网格
about-workshop.jpg
about-founder.jpg
```

告诉我"图都放好了",我会:
1. 压缩 + 响应式生成(WEBP+JPG 四档)
2. 替换网站 hero + B/A + 5 步流程图 + UGC 网格 + About 页
3. SQL UPDATE + index.html + product.html + about.html 全部串好
4. commit + 等你 push

---

## 几个生成时的关键技巧

1. **跑多张挑最好** — Midjourney 一次出 4 张,前 5-10 次出图通常都不够好,坚持 reroll
2. **保持 prompt 一致性** — 同一组 hero 三张要用接近的相机/灯光 prompt,视觉才统一
3. **避免"长睫毛过夸张"** — 模型有惯性会生成 50mm 戏剧睫毛,加 negative prompt 控制
4. **模特多样化** — 美国市场必须有多种肤色,不然投放 ads 会被打"刻板印象"标
5. **不要让 AI 写英文字** — 生成时会胡乱写品牌名,如果需要 logo/文字,我后期 PS 加
