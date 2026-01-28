<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $postal = trim($_POST['postal_code'] ?? '');
    $image = trim($_POST['image_url'] ?? '');

    $titleLen = strlen($title);
    $descLen = strlen($description);

    $postalOk = false;
    if ($postal === '1007') {
        $postalOk = true;
    } elseif (preg_match('/^[0-9]{4}$/', $postal)) {
        $first = (int)$postal[0];
        $middle = (int)substr($postal, 1, 2);
        $last = (int)$postal[3];
        if ($first === 1 && $middle >= 1 && $middle <= 23 && $last >= 1 && $last <= 9) {
            $postalOk = true;
        }
    }

    $imageOk = true;
    if ($image !== '') {
        $imageOk = filter_var($image, FILTER_VALIDATE_URL) !== false;
    }

    if ($title === '' || $description === '' || $postal === '' || $category === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($titleLen < 10) {
        $error = 'Title must be at least 10 characters.';
    } elseif ($descLen < 150) {
        $error = 'Description must be at least 150 characters.';
    } elseif (!in_array($category, CATEGORIES)) {
        $error = 'Invalid category.';
    } elseif (!$postalOk) {
        $error = 'Postal code format is invalid.';
    } elseif (!$imageOk) {
        $error = 'Image URL is invalid.';
    } else {
        $projects = read_json(PROJECTS_FILE);

        $projects[] = [
            'id' => next_id($projects),
            'status' => 'pending',
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'postal_code' => $postal,
            'image_url' => ($image === '' ? null : $image),
            'owner_id' => (int)($_SESSION['user']['id'] ?? 0),
            'submitted_at' => date('Y-m-d H:i:s'),
            'approved_at' => '',
        ];

        write_json(PROJECTS_FILE, $projects);

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Submit project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div>
        <h1>Submit New Project</h1>
    </div>
    <nav>
        <a href="index.php">← Back to Home</a>
    </nav>
</header>

<div class="box">
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Title *</label>
        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
        <small class="muted">Minimum 10 characters.</small>

        <label>Description *</label>
        <textarea name="description" rows="6" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        <small class="muted">Minimum 150 characters.</small>

        <label>Category *</label>
        <select name="category" required>
            <option value="">-- choose --</option>
            <?php foreach (CATEGORIES as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= (($_POST['category'] ?? '') === $cat ? 'selected' : '') ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Postal code *</label>
        <input type="text" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>" required>
        <small class="muted">Budapest postal code (special rules), 1007 allowed.</small>

        <label>Image URL (optional)</label>
        <input type="url" name="image_url" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>">
        <small class="muted">If provided, it must be a valid URL.</small>

        <p class="muted" style="margin-top: var(--spacing-lg);">ID, owner, and submission date are set automatically.</p>
        <button type="submit" style="width: 100%; margin-top: var(--spacing-md);">Submit Project</button>
    </form>

    <p class="muted" style="margin-top: var(--spacing-lg); text-align: center;">
        Submitted projects are <strong>pending</strong> until an admin approves them.
    </p>
</div>

</body>
</html>