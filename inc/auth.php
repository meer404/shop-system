<?php
// inc/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

// If the user is not logged in, send to login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// (Optional helpers)
function current_username() {
    return $_SESSION['username'] ?? '';
}
