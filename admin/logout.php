<?php
// Admin logout — 销毁 session 并跳回登录页
require_once __DIR__ . '/../api/lib/admin-session.php';
adminLogout();
header('Location: login.php', true, 302);
exit;
