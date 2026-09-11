<?php
function qa_is_logged_in()
{
    return !empty($_SESSION['qa_user_id']);
}

function qa_current_user()
{
    if (!qa_is_logged_in()) {
        return null;
    }

    return array(
        'user_id' => isset($_SESSION['qa_user_id']) ? (int) $_SESSION['qa_user_id'] : 0,
        'username' => isset($_SESSION['qa_username']) ? $_SESSION['qa_username'] : '',
        'display_name' => isset($_SESSION['qa_display_name']) ? $_SESSION['qa_display_name'] : '',
        'roles' => isset($_SESSION['qa_roles']) && is_array($_SESSION['qa_roles']) ? $_SESSION['qa_roles'] : array()
    );
}

function qa_user_has_role($roleCode)
{
    $user = qa_current_user();
    if (!$user) {
        return false;
    }
    return in_array($roleCode, $user['roles'], true);
}

function qa_require_login()
{
    if (!qa_is_logged_in()) {
        $returnTo = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : qa_url('dashboard.php');
        header('Location: ' . qa_url('login.php') . '?return=' . urlencode($returnTo));
        exit;
    }
}

function qa_login_user($row, $roles)
{
    session_regenerate_id(true);
    $_SESSION['qa_user_id'] = (int) $row['user_id'];
    $_SESSION['qa_username'] = $row['username'];

    $displayName = trim(
        (isset($row['prefix']) ? $row['prefix'] : '') .
        (isset($row['first_name']) ? $row['first_name'] : '') . ' ' .
        (isset($row['last_name']) ? $row['last_name'] : '')
    );
    $_SESSION['qa_display_name'] = $displayName !== '' ? $displayName : $row['username'];
    $_SESSION['qa_roles'] = $roles;
}

function qa_logout_user()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

function qa_csrf_token()
{
    if (empty($_SESSION['qa_csrf'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['qa_csrf'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['qa_csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $_SESSION['qa_csrf'] = sha1(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['qa_csrf'];
}

function qa_verify_csrf($token)
{
    if (empty($_SESSION['qa_csrf']) || !is_string($token)) {
        return false;
    }
    if (function_exists('hash_equals')) {
        return hash_equals($_SESSION['qa_csrf'], $token);
    }
    return $_SESSION['qa_csrf'] === $token;
}
