<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php", TRUE, 307);
    exit(1);
}


// remove all session variables
session_unset();

// remove the session cookie only
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// header('Clear-Site-Data: "cache", "cookies"');   // too extreme

header("Location: ../index.php", TRUE, 307);
