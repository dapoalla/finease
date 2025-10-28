<?php
// Session timeout handling
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../auth/login.php' : 'auth/login.php'));
    exit;
}

// Check for session timeout (24 hours)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
    session_unset();
    session_destroy();
    header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../auth/login.php?timeout=1' : 'auth/login.php?timeout=1'));
    exit;
}

$_SESSION['last_activity'] = time();
?>