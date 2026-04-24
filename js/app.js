// ============================================================
// GLAMEYE - 应用程序脚本
// ============================================================

/**
 * 购物车管理
 */
const Cart = {
  items: [],

  // 添加商品到购物车
  addItem(name, price) {
    const existingItem = this.items.find((item) => item.name === name);
    if (existingItem) {
      existingItem.quantity += 1;
    } else {
      this.items.push({ name, price, quantity: 1 });
    }
    this.save();
    this.showNotification(`✨ "${name}" 已加入购物车！`);
  },

  // 移除商品
  removeItem(name) {
    this.items = this.items.filter((item) => item.name !== name);
    this.save();
  },

  // 获取总价
  getTotal() {
    return this.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
  },

  // 获取商品数量
  getCount() {
    return this.items.reduce((count, item) => count + item.quantity, 0);
  },

  // 清空购物车
  clear() {
    this.items = [];
    this.save();
  },

  // 保存到 localStorage
  save() {
    localStorage.setItem("cart", JSON.stringify(this.items));
  },

  // 从 localStorage 加载
  load() {
    const saved = localStorage.getItem("cart");
    this.items = saved ? JSON.parse(saved) : [];
  },

  // 显示通知
  showNotification(message) {
    const notification = document.createElement("div");
    notification.className = "notification";
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.add("show");
    }, 10);

    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 2500);
  },
};

/**
 * 全局函数 - 加入购物车
 */
function addToCart(name, price) {
  Cart.addItem(name, price);
}

/**
 * DOM 加载完成后的初始化
 */
document.addEventListener("DOMContentLoaded", function () {
  // 加载购物车
  Cart.load();

  // 支付方式提示
  const method = document.getElementById("payment-method");
  const note = document.getElementById("payment-note");
  if (method && note) {
    const updateNote = () => {
      const selected = method.value;
      note.textContent =
        selected === "paypal"
          ? "您已选择 PayPal 付款，我们将在提交后为您生成安全支付链接。"
          : "您已选择 Stripe 付款，提交后请继续完成信用卡支付。";
    };
    method.addEventListener("change", updateNote);
    updateNote();
  }

  // 平滑滚动链接
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });

  // 懒加载图片
  if ("IntersectionObserver" in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src || img.src;
          img.classList.add("loaded");
          observer.unobserve(img);
        }
      });
    });

    document.querySelectorAll("img[data-src]").forEach((img) => {
      imageObserver.observe(img);
    });
  }

  // 页面加载完成后的动画
  document.body.classList.add("loaded");
});

/**
 * 设置页面跟踪（可选）
 */
window.addEventListener("load", function () {
  // 页面加载完成
  console.log("✅ GLAMEYE 加载完成");
});

/**
 * 错误处理
 */
window.addEventListener("error", function (event) {
  console.error("❌ 错误:", event.message);
});

/**
 * 导出函数供 HTML 调用
 */
window.addToCart = addToCart;
window.Cart = Cart;
