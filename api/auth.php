<?php
// ============================================================
// User authentication endpoints
// Routes (action via ?action= or JSON body):
//   POST  signup    - register new user
//   POST  login     - email + password
//   POST  logout    - clear session
//   GET   me        - current user info
//   POST  update    - update profile
//   POST  change-password
// ============================================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ("$method:$action") {
        case 'POST:signup':   handleSignup(); break;
        case 'POST:login':    handleLogin(); break;
        case 'POST:logout':   handleLogout(); break;
        case 'GET:me':        handleMe(); break;
        case 'POST:update':   handleUpdate(); break;
        case 'POST:change-password': handleChangePassword(); break;
        default: sendJson(['error' => 'Unknown action'], 400);
    }
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
} catch (Throwable $e) {
    sendError('Server error', 500, $e);
}

function handleSignup(): void {
    $in = readInput();
    $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pwd   = (string)($in['password'] ?? '');
    $first = trim((string)($in['first_name'] ?? ''));
    $last  = trim((string)($in['last_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $sub   = !empty($in['is_subscribed']) ? 1 : 0;

    if (!$email)               sendJson(['error' => 'Invalid email'], 422);
    if (strlen($pwd) < 8)      sendJson(['error' => 'Password must be at least 8 characters'], 422);
    if (!$first)               sendJson(['error' => 'First name required'], 422);
    if (mb_strlen($first) > 100 || mb_strlen($last) > 100) sendJson(['error' => 'Name too long'], 422);

    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) sendJson(['error' => 'Email already registered'], 409);

    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, first_name, last_name, phone, is_subscribed) VALUES (:email, :hash, :first, :last, :phone, :sub)');
    $stmt->execute([
        ':email' => $email, ':hash' => $hash, ':first' => $first,
        ':last'  => $last,  ':phone' => $phone ?: null, ':sub' => $sub,
    ]);
    $uid = (int)$db->lastInsertId();

    if ($sub) {
        $stmt = $db->prepare('INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES (:e, :s)');
        $stmt->execute([':e' => $email, ':s' => 'signup']);
    }

    startUserSession();
    $_SESSION['user_id'] = $uid;
    sendJson(['success' => true, 'user' => ['id' => $uid, 'email' => $email, 'first_name' => $first]]);
}

function handleLogin(): void {
    $in = readInput();
    $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pwd   = (string)($in['password'] ?? '');

    if (!$email || !$pwd) sendJson(['error' => 'Email and password required'], 422);

    $db = getDb();
    $stmt = $db->prepare('SELECT id, email, password_hash, first_name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 时序攻击防护：始终调用 password_verify
    $hash = $user['password_hash'] ?? '$2y$10$invalidhashinvalidhashinvalidhashinvalidhashinvalidhash';
    if (!password_verify($pwd, $hash) || !$user) {
        sendJson(['error' => 'Invalid email or password'], 401);
    }

    startUserSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    sendJson(['success' => true, 'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name']
    ]]);
}

function handleLogout(): void {
    startUserSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(SESSION_COOKIE, '', time() - 3600,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    sendJson(['success' => true]);
}

function handleMe(): void {
    $u = currentUser();
    if (!$u) sendJson(['user' => null]);
    sendJson(['user' => $u]);
}

function handleUpdate(): void {
    $u = requireUser();
    $in = readInput();
    $first = trim((string)($in['first_name'] ?? $u['first_name']));
    $last  = trim((string)($in['last_name']  ?? $u['last_name']));
    $phone = trim((string)($in['phone']      ?? $u['phone'] ?? ''));
    $sub   = isset($in['is_subscribed']) ? (!empty($in['is_subscribed']) ? 1 : 0) : (int)$u['is_subscribed'];

    if (!$first || mb_strlen($first) > 100) sendJson(['error' => 'Invalid first name'], 422);

    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET first_name=:f, last_name=:l, phone=:p, is_subscribed=:s WHERE id=:id');
    $stmt->execute([
        ':f' => $first, ':l' => $last, ':p' => $phone ?: null, ':s' => $sub, ':id' => $u['id'],
    ]);
    sendJson(['success' => true]);
}

function handleChangePassword(): void {
    $u = requireUser();
    $in = readInput();
    $old = (string)($in['current_password'] ?? '');
    $new = (string)($in['new_password'] ?? '');
    if (strlen($new) < 8) sendJson(['error' => 'New password must be at least 8 characters'], 422);

    $db = getDb();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $u['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($old, $row['password_hash'])) {
        sendJson(['error' => 'Current password is incorrect'], 401);
    }
    $hash = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE users SET password_hash=:h WHERE id=:id');
    $stmt->execute([':h' => $hash, ':id' => $u['id']]);
    sendJson(['success' => true]);
}
