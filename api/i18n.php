<?php
// ============================================================
// Admin i18n - bilingual EN / 中文
// 用法：在 admin 页面顶部 require，然后 t('orders') 翻译，
//       通过 cookie glameye_admin_lang 切换语言
// ============================================================

const I18N_COOKIE = 'glameye_admin_lang';

const TRANSLATIONS = [
    'admin_panel'      => ['en' => 'Admin Panel',         'zh' => '管理面板'],
    'orders'           => ['en' => 'Orders',              'zh' => '订单'],
    'products'         => ['en' => 'Products',            'zh' => '商品'],
    'users'            => ['en' => 'Customers',           'zh' => '客户'],
    'leads'            => ['en' => 'Wholesale Leads',     'zh' => '批发询单'],
    'subscribers'      => ['en' => 'Subscribers',         'zh' => '订阅者'],
    'order_management' => ['en' => 'Order Management',    'zh' => '订单管理'],
    'total_orders'     => ['en' => 'Total Orders',        'zh' => '总订单'],
    'pending'          => ['en' => 'Pending',             'zh' => '待支付'],
    'paid'             => ['en' => 'Paid',                'zh' => '已支付'],
    'shipped'          => ['en' => 'Shipped',             'zh' => '已发货'],
    'delivered'        => ['en' => 'Delivered',           'zh' => '已送达'],
    'cancelled'        => ['en' => 'Cancelled',           'zh' => '已取消'],
    'refunded'         => ['en' => 'Refunded',            'zh' => '已退款'],
    'processing'       => ['en' => 'Processing',          'zh' => '处理中'],
    'total_revenue'    => ['en' => 'Total Revenue',       'zh' => '总销售额'],
    'filter_status'    => ['en' => 'Filter by status',    'zh' => '状态筛选'],
    'all'              => ['en' => 'All',                 'zh' => '全部'],
    'refresh'          => ['en' => 'Refresh',             'zh' => '刷新'],
    'order_id'         => ['en' => '#',                   'zh' => '#'],
    'date'             => ['en' => 'Date',                'zh' => '时间'],
    'customer'         => ['en' => 'Customer',            'zh' => '客户'],
    'items'            => ['en' => 'Items',               'zh' => '商品'],
    'amount'           => ['en' => 'Amount',              'zh' => '金额'],
    'payment'          => ['en' => 'Payment',             'zh' => '支付'],
    'address'          => ['en' => 'Address',             'zh' => '地址'],
    'status'           => ['en' => 'Status',              'zh' => '状态'],
    'no_orders'        => ['en' => 'No orders yet.',      'zh' => '暂无订单。'],
    'loading'          => ['en' => 'Loading...',          'zh' => '加载中...'],
    'load_failed'      => ['en' => 'Load failed',         'zh' => '加载失败'],
    'logged_in_as'     => ['en' => 'Logged in as',        'zh' => '当前登录'],
    'back_to_site'     => ['en' => 'Back to site',        'zh' => '返回前台'],
    'language'         => ['en' => 'Language',            'zh' => '语言'],
    'english'          => ['en' => 'English',             'zh' => 'English'],
    'chinese'          => ['en' => '中文',                'zh' => '中文'],
];

function adminLang(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'zh'], true)) {
        setcookie(I18N_COOKIE, $_GET['lang'], time() + 86400 * 365, '/');
        return $_GET['lang'];
    }
    return $_COOKIE[I18N_COOKIE] ?? 'en';
}

function t(string $key): string {
    $lang = adminLang();
    return TRANSLATIONS[$key][$lang] ?? TRANSLATIONS[$key]['en'] ?? $key;
}

function tjs(): string {
    // 把所有翻译序列化为 JS 对象供前端使用
    $lang = adminLang();
    $out = [];
    foreach (TRANSLATIONS as $k => $v) $out[$k] = $v[$lang] ?? $v['en'];
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
