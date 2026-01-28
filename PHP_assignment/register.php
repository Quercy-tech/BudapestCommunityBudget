<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass1    = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if ($username === '' || $email === '' || $pass1 === '' || $pass2 === '') {
        $error = 'All fields are required';
    } elseif (strpos($username, ' ') !== false) {
        $error = 'Username cannot contain spaces';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email format is invalid';
    } elseif (!preg_match('/[a-z]/', $pass1) || !preg_match('/[A-Z]/', $pass1) || !preg_match('/[0-9]/', $pass1)) {
        $error = 'Password must include lowercase, uppercase, and a number';
    } elseif (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match';
    } else {
        $users = read_json(USERS_FILE);

        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $error = 'Username already exists';
                break;
            }
        }

        if ($error === '') {
            $users[] = [
                'id' => next_id($users),
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($pass1, PASSWORD_DEFAULT),
                'is_admin' => false,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            write_json(USERS_FILE, $users);
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="box" style="max-width: 500px; margin: 2rem auto;">
    <h1>Register</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
        <small class="muted">Username cannot contain spaces</small>
        
        <label>Email</label>
        <input type="email" name="email" required>
        
        <label>Password</label>
        <input type="password" name="password" required>
        <small class="muted">At least 8 characters, must include lowercase, uppercase, and a number</small>
        
        <label>Repeat password</label>
        <input type="password" name="password2" required>
        
        <button type="submit" style="width: 100%; margin-top: var(--spacing-md);">Register</button>
    </form>

    <p style="text-align: center; margin-top: var(--spacing-lg);">
        <a href="login.php">Already have an account?</a>
    </p>
</div>

</body>
</html>