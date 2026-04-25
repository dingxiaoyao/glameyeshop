#!/usr/bin/env bash
# ============================================================
# GlamEye - HTTPS one-shot setup script
# 在您的 Mac 终端运行（会自动 SSH 到 Vultr 服务器执行）
#
# 工作内容：
#   1. 在服务器上安装 certbot (Let's Encrypt 客户端)
#   2. 更新 nginx vhost：server_name 加 glameyeshop.com / www.glameyeshop.com
#   3. 申请 SSL 证书并自动配置 HTTPS + 自动续期
#   4. 强制 HTTP → HTTPS 301 跳转
#   5. 启用 HTTP/2 + 安全响应头
# ============================================================
set -euo pipefail

KEY_PATH="$HOME/.ssh/glameye_deploy"
DEPLOY_PATH="/var/www/glameye"
DOMAIN_PRIMARY="glameyeshop.com"
DOMAIN_WWW="www.glameyeshop.com"
CERT_EMAIL=""

cyan()   { printf "\033[36m%s\033[0m\n" "$*"; }
green()  { printf "\033[32m%s\033[0m\n" "$*"; }
yellow() { printf "\033[33m%s\033[0m\n" "$*"; }
red()    { printf "\033[31m%s\033[0m\n" "$*"; }
section(){ echo; cyan "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; cyan "  $*"; cyan "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; }
trap 'red "❌ 在第 $LINENO 行失败，把上面输出贴给 Claude。"' ERR

section "Phase 0: 收集参数"
read -rp "服务器 IP（默认 173.199.124.17）> " SERVER_HOST
SERVER_HOST=${SERVER_HOST:-173.199.124.17}
read -rp "SSH 用户名（默认 root）          > " SERVER_USER
SERVER_USER=${SERVER_USER:-root}
read -rp "证书通知邮箱（必填，Let's Encrypt 用来续期提醒）> " CERT_EMAIL
if [[ -z "$CERT_EMAIL" ]]; then red "❌ 邮箱必填"; exit 1; fi

[[ -f "$KEY_PATH" ]] || { red "❌ 找不到 $KEY_PATH，先跑 setup-deploy.sh"; exit 1; }

section "Phase 1: 测试 DNS 是否生效"
echo "▶ 解析 $DOMAIN_PRIMARY ..."
RESOLVED=$(dig +short A $DOMAIN_PRIMARY @8.8.8.8 | head -1)
echo "   → $RESOLVED"
if [[ "$RESOLVED" != "$SERVER_HOST" ]]; then
  yellow "⚠️  $DOMAIN_PRIMARY 解析到 '$RESOLVED'，但服务器是 '$SERVER_HOST'"
  yellow "   GoDaddy DNS 还没生效（最多需要 30 分钟）。"
  read -rp "确认继续？(y/N) " CONT
  [[ "$CONT" =~ ^[Yy]$ ]] || exit 0
else
  green "✅ DNS 正确指向服务器"
fi

SSH="ssh -i $KEY_PATH -o BatchMode=yes $SERVER_USER@$SERVER_HOST"

section "Phase 2: 安装 certbot"
$SSH bash <<'EOSSH'
set -e
export DEBIAN_FRONTEND=noninteractive
if ! command -v certbot &>/dev/null; then
  apt-get update -qq
  apt-get install -y -qq certbot python3-certbot-nginx
  echo "✅ certbot installed"
else
  echo "✓ certbot already installed: $(certbot --version)"
fi
EOSSH

section "Phase 3: 更新 nginx vhost (加 glameyeshop.com server_name)"
$SSH "DOMAIN_PRIMARY='$DOMAIN_PRIMARY' DOMAIN_WWW='$DOMAIN_WWW' SERVER_HOST='$SERVER_HOST' DEPLOY_PATH='$DEPLOY_PATH' bash -s" <<'EOSSH'
set -e
PHP_SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)
[[ -n "$PHP_SOCK" ]] || { echo "❌ 找不到 PHP-FPM sock"; exit 1; }

cat > /etc/nginx/sites-available/glameye <<NGINX
# HTTP - 接受 IP 和域名（certbot 用这个验证）
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name $DOMAIN_PRIMARY $DOMAIN_WWW $SERVER_HOST _;
    root $DEPLOY_PATH;
    index index.html index.php;

    # certbot HTTP-01 验证目录
    location ^~ /.well-known/acme-challenge/ { root /var/www/html; allow all; }

    location ~ /\.(git|env|htaccess) { deny all; return 404; }
    location ~ \.(sql|md|log|sh)\$    { deny all; return 404; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
    }

    location /admin/ { try_files \$uri \$uri/ /admin/index.php; }

    location ~* \.(jpg|jpeg|png|gif|svg|webp|css|js|ico|woff2?)\$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    location / { try_files \$uri \$uri/ =404; }

    error_page 404 /404.html;
    location = /404.html { internal; }

    add_header X-Content-Type-Options "nosniff";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
}
NGINX
mkdir -p /var/www/html
ln -sf /etc/nginx/sites-available/glameye /etc/nginx/sites-enabled/glameye
nginx -t && systemctl reload nginx
echo "✅ nginx vhost 已更新（HTTP 阶段）"
EOSSH

section "Phase 4: 申请 Let's Encrypt 证书"
$SSH "DOMAIN_PRIMARY='$DOMAIN_PRIMARY' DOMAIN_WWW='$DOMAIN_WWW' CERT_EMAIL='$CERT_EMAIL' bash -s" <<'EOSSH'
set -e
# certbot --nginx 会自动修改 nginx 配置加 SSL
certbot --nginx \
  -d "$DOMAIN_PRIMARY" \
  -d "$DOMAIN_WWW" \
  --email "$CERT_EMAIL" \
  --agree-tos --no-eff-email \
  --redirect \
  --non-interactive
echo "✅ 证书已颁发并配置"
EOSSH

section "Phase 5: 加固 nginx 配置（HTTP/2 + HSTS + 现代 SSL）"
$SSH "DOMAIN_PRIMARY='$DOMAIN_PRIMARY' DOMAIN_WWW='$DOMAIN_WWW' SERVER_HOST='$SERVER_HOST' DEPLOY_PATH='$DEPLOY_PATH' bash -s" <<'EOSSH'
set -e
PHP_SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)
SSL_CERT="/etc/letsencrypt/live/$DOMAIN_PRIMARY/fullchain.pem"
SSL_KEY="/etc/letsencrypt/live/$DOMAIN_PRIMARY/privkey.pem"

cat > /etc/nginx/sites-available/glameye <<NGINX
# HTTP - 仅做 ACME 验证 + 跳转到 HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN_PRIMARY $DOMAIN_WWW $SERVER_HOST _;

    location ^~ /.well-known/acme-challenge/ { root /var/www/html; allow all; }
    location / { return 301 https://\$host\$request_uri; }
}

# HTTPS - www 跳转到 apex
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name $DOMAIN_WWW;

    ssl_certificate     $SSL_CERT;
    ssl_certificate_key $SSL_KEY;

    return 301 https://$DOMAIN_PRIMARY\$request_uri;
}

# HTTPS - 主站点
server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    http2 on;
    server_name $DOMAIN_PRIMARY $SERVER_HOST _;

    ssl_certificate     $SSL_CERT;
    ssl_certificate_key $SSL_KEY;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5:!3DES;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    root $DEPLOY_PATH;
    index index.html index.php;

    # 安全头
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # 禁敏感文件
    location ~ /\.(git|env|htaccess) { deny all; return 404; }
    location ~ \.(sql|md|log|sh)\$    { deny all; return 404; }

    # PHP
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
        fastcgi_param HTTPS on;
    }

    # admin 默认走 PHP
    location /admin/ { try_files \$uri \$uri/ /admin/index.php; }

    # 静态资源缓存
    location ~* \.(jpg|jpeg|png|gif|svg|webp|css|js|ico|woff2?)\$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    location / { try_files \$uri \$uri/ =404; }

    error_page 404 /404.html;
    location = /404.html { internal; }

    # gzip
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml application/json application/javascript image/svg+xml;
}
NGINX

nginx -t && systemctl reload nginx
echo "✅ HTTPS 配置生效"

# 验证 certbot 续期任务
systemctl list-timers certbot.timer 2>/dev/null | grep -q certbot && \
  echo "✅ 自动续期已启用 (certbot.timer)" || \
  echo "⚠️  certbot.timer 未启用 - 手动开启：systemctl enable --now certbot.timer"
EOSSH

section "Phase 6: smoke test"
sleep 2
echo "▶ HTTP → HTTPS 跳转"
HTTP_CODE=$(curl -sI -o /dev/null -w "%{http_code}" "http://$DOMAIN_PRIMARY/")
HTTP_LOC=$(curl -sI "http://$DOMAIN_PRIMARY/" | grep -i "^location:" | tr -d '\r')
echo "   HTTP $HTTP_CODE → $HTTP_LOC"

echo "▶ HTTPS 首页"
HTTPS_CODE=$(curl -sI -o /dev/null -w "%{http_code}" "https://$DOMAIN_PRIMARY/")
echo "   HTTPS $HTTPS_CODE"

echo "▶ 证书信息"
echo | openssl s_client -servername $DOMAIN_PRIMARY -connect $DOMAIN_PRIMARY:443 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates 2>/dev/null | head -4

section "✨ HTTPS 配置完成"
green "现在可访问："
echo "  • https://$DOMAIN_PRIMARY/"
echo "  • https://$DOMAIN_WWW/  (会 301 到 apex)"
echo
green "证书自动续期：每天 2 次检查 (certbot.timer)，到期前 30 天自动续"
echo
yellow "如果浏览器还是提示不安全，按 Cmd+Shift+R 强刷一次清缓存。"
