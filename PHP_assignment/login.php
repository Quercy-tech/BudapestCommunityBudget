<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $users = read_json(USERS_FILE);

    foreach ($users as $u) {
        if ($u['username'] === $username &&
            password_verify($password, $u['password_hash'])) {

            $_SESSION['user'] = $u;
            header('Location: index.php');
            exit;
        }
    }

    $error = 'Invalid username or password';
}
?>
<!doctype html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="box" style="max-width: 400px; margin: 2rem auto;">
    <h1>Login</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
        
        <label>Password</label>
        <input type="password" name="password" required>
        
        <button type="submit" style="width: 100%; margin-top: var(--spacing-md);">Login</button>
    </form>

    <p style="text-align: center; margin-top: var(--spacing-lg);">
        <a href="register.php">Create account</a>
    </p>
</div>

</body>
</html>