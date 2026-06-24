<?php
// TikTok Login Kit (v2) — Authorization Code flow.
// Single endpoint handles BOTH the initial redirect (no params) and the callback (?code=..&state=..).
require_once __DIR__ . '/_helpers.php';

$db = getDb();
$clientKey    = settingValue($db, 'tiktok_client_key');
$clientSecret = settingValue($db, 'tiktok_client_secret');

if (!$clientKey || !$clientSecret) {
    oauthError('TikTok sign-in is not configured');
}

$redirectUri = oauthBaseUrl() . '/api/oauth/tiktok.php';

// --- Step 1: kick off ---
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $params = [
        'client_key'    => $clientKey,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'user.info.basic',
        'state'         => $state,
    ];
    oauthBegin('https://www.tiktok.com/v2/auth/authorize/?' . http_build_query($params), $state);
}

// --- Step 2: callback ---
if (!oauthVerifyState((string)($_GET['state'] ?? ''))) {
    oauthError('Invalid state — possible CSRF');
}
$code = (string)$_GET['code'];

// Exchange code for tokens
$tok = httpPost('https://open.tiktokapis.com/v2/oauth/token/', [
    'client_key'    => $clientKey,
    'client_secret' => $clientSecret,
    'code'          => $code,
    'grant_type'    => 'authorization_code',
    'redirect_uri'  => $redirectUri,
], ['Content-Type: application/x-www-form-urlencoded', 'Cache-Control: no-cache']);

if (empty($tok['access_token']) || empty($tok['open_id'])) {
    error_log('[TikTok OAuth] token exchange failed: ' . json_encode($tok));
    // 把 TikTok 的真实错误带回去给用户看,便于诊断 client_key/secret/redirect 错配
    $err = $tok['error_description']
        ?? $tok['error']
        ?? ($tok['data']['description'] ?? null)
        ?? ($tok['_raw'] ?? null)
        ?? ($tok['_curl_error'] ?? 'unknown');
    if (is_array($err)) $err = json_encode($err);
    $code = $tok['error'] ?? ($tok['data']['error_code'] ?? '');
    $msg  = 'TikTok token exchange failed';
    if ($code) $msg .= ' [' . $code . ']';
    $msg .= ': ' . substr((string)$err, 0, 240);
    oauthError($msg);
}

// Fetch user info (basic)
$ui = httpGet(
    'https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name',
    ['Authorization: Bearer ' . $tok['access_token']]
);
$user = $ui['data']['user'] ?? [];
$displayName = (string)($user['display_name'] ?? '');
$nameParts = explode(' ', $displayName, 2);

linkOrCreateOAuthUser(
    'tiktok',
    (string)$tok['open_id'],
    null, // TikTok does not return email by default
    $nameParts[0] ?? '',
    $nameParts[1] ?? '',
    (string)($user['avatar_url'] ?? '')
);

oauthRedirect('/account.html');
