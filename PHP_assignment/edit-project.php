<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$projects = read_json(PROJECTS_FILE);

$project = null;
foreach ($projects as $p) {
    if ((int)($p['id'] ?? 0) === $id) {
        $project = $p;
        break;
    }
}

if (!$project) {
    header('Location: projects-own.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$isOwner = (int)($project['owner_id'] ?? 0) === $userId;
$isRework = ($project['status'] ?? '') === 'rework';

if (!$isOwner || !$isRework) {
    header('Location: projects-own.php');
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
        foreach ($projects as $idx => $p) {
            if ((int)($p['id'] ?? 0) === $id) {
                $oldData = $projects[$idx];
                
                if (!isset($projects[$idx]['history'])) {
                    $projects[$idx]['history'] = [];
                }
                
                $changes = [];
                if (($oldData['title'] ?? '') !== $title) {
                    $changes['title'] = ['old' => $oldData['title'] ?? '', 'new' => $title];
                }
                if (($oldData['description'] ?? '') !== $description) {
                    $changes['description'] = ['old' => $oldData['description'] ?? '', 'new' => $description];
                }
                if (($oldData['category'] ?? '') !== $category) {
                    $changes['category'] = ['old' => $oldData['category'] ?? '', 'new' => $category];
                }
                if (($oldData['postal_code'] ?? '') !== $postal) {
                    $changes['postal_code'] = ['old' => $oldData['postal_code'] ?? '', 'new' => $postal];
                }
                if (($oldData['image_url'] ?? '') !== $image) {
                    $changes['image_url'] = ['old' => $oldData['image_url'] ?? '', 'new' => $image];
                }
                
                if (!empty($changes)) {
                    $projects[$idx]['history'][] = [
                        'changes' => $changes,
                        'edited_at' => date('Y-m-d H:i:s'),
                    ];
                }
                
                $projects[$idx]['title'] = $title;
                $projects[$idx]['description'] = $description;
                $projects[$idx]['category'] = $category;
                $projects[$idx]['postal_code'] = $postal;
                $projects[$idx]['image_url'] = ($image === '' ? null : $image);
                $projects[$idx]['status'] = 'pending';
                $projects[$idx]['submitted_at'] = date('Y-m-d H:i:s');
                
                write_json(PROJECTS_FILE, $projects);
                header('Location: project.php?id=' . $id);
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div>
        <h1>Edit Project</h1>
    </div>
    <nav>
        <a href="project.php?id=<?= $id ?>">← Back to Project</a>
        <a href="projects-own.php">My Projects</a>
    </nav>
</header>

<div class="box">
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!empty($project['admin_comments'])): ?>
        <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-lg); background: #fff3cd; border: 1px solid var(--warning-color); border-radius: var(--border-radius-sm);">
            <h3 style="margin-top: 0;">Admin Feedback</h3>
            <?php foreach (array_reverse($project['admin_comments']) as $comment): ?>
                <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); background: white; border-radius: var(--border-radius-sm); border-left: 4px solid var(--warning-color);">
                    <div style="font-weight: 600; margin-bottom: var(--spacing-xs);">
                        <?= $comment['admin'] ?? 'Admin' ?> 
                        <span class="muted" style="font-weight: normal; font-size: 0.85rem;">
                            (<?= $comment['created_at'] ?? '' ?>)
                        </span>
                    </div>
                    <div><?= nl2br($comment['comment'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Title *</label>
        <input type="text" name="title" value="<?= htmlspecialchars($project['title'] ?? '') ?>" required>
        <small class="muted">Minimum 10 characters.</small>

        <label>Description *</label>
        <textarea name="description" rows="6" required><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
        <small class="muted">Minimum 150 characters.</small>

        <label>Category *</label>
        <select name="category" required>
            <option value="">-- choose --</option>
            <?php foreach (CATEGORIES as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= (($project['category'] ?? '') === $cat ? 'selected' : '') ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Postal code *</label>
        <input type="text" name="postal_code" value="<?= htmlspecialchars($project['postal_code'] ?? '') ?>" required>
        <small class="muted">Budapest postal code (special rules), 1007 allowed.</small>

        <label>Image URL (optional)</label>
        <input type="url" name="image_url" value="<?= htmlspecialchars($project['image_url'] ?? '') ?>">
        <small class="muted">If provided, it must be a valid URL.</small>

        <button type="submit" style="width: 100%; margin-top: var(--spacing-md);">Resubmit for Approval</button>
    </form>
</div>

</body>
</html>

