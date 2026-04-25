<?php
// Google OAuth 2.0 — Authorization Code flow.
// Single endpoint handles BOTH the initial redirect (no params) and the callback (?code=..&state=..).
require_once __DIR__ . '/_helpers.php';

$db = getDb();
$clientId     = settingValue($db, 'google_client_id');
$clientSecret = settingValue($db, 'google_client_secret');

if (!$clientId || !$clientSecret) {
    oauthError('Google sign-in is not configured');
}

$redirectUri = oauthBaseUrl() . '/api/oauth/google.php';

// --- Step 1: kick off ---
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $params = [
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    oauthBegin('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params), $state);
}

// --- Step 2: callback ---
if (!oauthVerifyState((string)($_GET['state'] ?? ''))) {
    oauthError('Invalid state — possible CSRF');
}
$code = (string)$_GET['code'];

// Exchange code for tokens
$tok = httpPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);
if (empty($tok['access_token'])) {
    error_log('[Google OAuth] token exchange failed: ' . json_encode($tok));
    oauthError('Could not exchange Google token');
}

// Fetch userinfo
$ui = httpGet('https://openidconnect.googleapis.com/v1/userinfo', [
    'Authorization: Bearer ' . $tok['access_token'],
]);
if (empty($ui['sub'])) {
    error_log('[Google OAuth] userinfo failed: ' . json_encode($ui));
    oauthError('Could not fetch Google profile');
}

linkOrCreateOAuthUser(
    'google',
    (string)$ui['sub'],
    $ui['email'] ?? null,
    (string)($ui['given_name'] ?? ''),
    (string)($ui['family_name'] ?? ''),
    (string)($ui['picture'] ?? '')
);

oauthRedirect('/account.html');
