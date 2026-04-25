<?php
// Shared OAuth helpers for Google + TikTok login
// All providers funnel through linkOrCreateOAuthUser() which finds-or-creates a
// user by oauth_provider+oauth_id, or links to an existing email account.

require_once __DIR__ . '/../config.php';

function settingValue(PDO $db, string $key): ?string {
    $stmt = $db->prepare('SELECT `value` FROM site_settings WHERE `key` = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $r = $stmt->fetch();
    return $r ? (string)$r['value'] : null;
}

function oauthBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'glameyeshop.com');
}

function oauthRedirect(string $url): void {
    header('Location: ' . $url, true, 302);
    exit;
}

function oauthError(string $msg): void {
    $q = '?error=' . urlencode($msg);
    oauthRedirect('/login.html' . $q);
}

// Initiates the OAuth flow by storing a CSRF state and redirecting.
function oauthBegin(string $authUrl, string $state): void {
    startUserSession();
    $_SESSION['oauth_state'] = $state;
    oauthRedirect($authUrl);
}

function oauthVerifyState(string $state): bool {
    startUserSession();
    $expected = $_SESSION['oauth_state'] ?? null;
    unset($_SESSION['oauth_state']);
    return $expected !== null && hash_equals($expected, $state);
}

/**
 * Find-or-create a user from an OAuth profile.
 *  $provider: 'google' | 'tiktok'
 *  $providerId: the unique user id from the provider (subject)
 *  $email: optional email returned by the provider
 *  $first/$last: best-effort name
 *  $avatar: optional avatar URL
 *
 * Returns the user id and starts a session.
 */
function linkOrCreateOAuthUser(string $provider, string $providerId, ?string $email, string $first = '', string $last = '', string $avatar = ''): int {
    $db = getDb();

    // 1) Already linked?
    $stmt = $db->prepare('SELECT id FROM users WHERE oauth_provider = :p AND oauth_id = :id LIMIT 1');
    $stmt->execute([':p' => $provider, ':id' => $providerId]);
    $row = $stmt->fetch();
    if ($row) {
        $uid = (int)$row['id'];
    } else if ($email) {
        // 2) Existing account by email — link it
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();
        if ($row) {
            $uid = (int)$row['id'];
            $stmt = $db->prepare('UPDATE users SET oauth_provider=:p, oauth_id=:id, avatar_url=COALESCE(NULLIF(avatar_url,""), :av) WHERE id=:uid');
            $stmt->execute([':p' => $provider, ':id' => $providerId, ':av' => $avatar, ':uid' => $uid]);
        } else {
            // 3) Create new user (no password)
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, first_name, last_name, oauth_provider, oauth_id, avatar_url)
                                  VALUES (:e, NULL, :f, :l, :p, :id, :av)');
            $stmt->execute([
                ':e' => $email, ':f' => $first ?: 'Friend', ':l' => $last,
                ':p' => $provider, ':id' => $providerId, ':av' => $avatar,
            ]);
            $uid = (int)$db->lastInsertId();
        }
    } else {
        // No email at all (TikTok sometimes withholds) — create placeholder using provider id
        $placeholderEmail = $provider . '_' . $providerId . '@oauth.local';
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $placeholderEmail]);
        $row = $stmt->fetch();
        if ($row) {
            $uid = (int)$row['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, first_name, last_name, oauth_provider, oauth_id, avatar_url)
                                  VALUES (:e, NULL, :f, :l, :p, :id, :av)');
            $stmt->execute([
                ':e' => $placeholderEmail, ':f' => $first ?: 'Friend', ':l' => $last,
                ':p' => $provider, ':id' => $providerId, ':av' => $avatar,
            ]);
            $uid = (int)$db->lastInsertId();
        }
    }

    startUserSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    return $uid;
}

function httpPost(string $url, array $data, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) return ['_curl_error' => $err];
    $j = json_decode($body, true);
    return is_array($j) ? $j : ['_raw' => $body];
}

function httpGet(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) return ['_curl_error' => $err];
    $j = json_decode($body, true);
    return is_array($j) ? $j : ['_raw' => $body];
}
