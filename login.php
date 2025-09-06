<?php
// login.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/inc/config.php'; // adjust if your config path differs

// Already logged in? go home
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Good login: regenerate session, store essentials
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: index.php');
            exit;
        } else {
            $error = '❌ Wrong username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Small centered card using your palette */
    .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center}
    .login-card{width:100%;max-width:420px}
    .login-card h1{margin-bottom:10px}
  </style>
</head>
<body class="app">
  <div class="content login-wrap">
    <div class="card login-card">
      <h1>Sign in</h1>
      <?php if ($error): ?>
        <p class="danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="form-row">
          <input type="text" name="username" placeholder="Username" required autofocus>
        </div>
        <div class="form-row">
          <input type="password" name="password" placeholder="Password" required>
        </div>
        <button type="submit">Login</button>
      </form>
    </div>
  </div>
</body>
</html>
