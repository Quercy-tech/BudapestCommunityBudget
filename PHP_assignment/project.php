<?php
require 'init.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$projects = update_project_statuses();
$votes = read_json(VOTES_FILE);
$users = read_json(USERS_FILE);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user']) && !empty($_SESSION['user']['is_admin'])) {
    $action = $_POST['action'] ?? '';
    
    if (in_array($action, ['approve', 'reject', 'rework'])) {
        foreach ($projects as $idx => $p) {
            if ((int)($p['id'] ?? 0) === $id) {
                if ($action === 'approve') {
                    $projects[$idx]['status'] = 'approved';
                    $projects[$idx]['approved_at'] = date('Y-m-d H:i:s');
                } elseif ($action === 'reject') {
                    $projects[$idx]['status'] = 'rejected';
                } elseif ($action === 'rework') {
                    $comment = trim($_POST['comment'] ?? '');
                    if ($comment !== '') {
                        if (!isset($projects[$idx]['admin_comments'])) {
                            $projects[$idx]['admin_comments'] = [];
                        }
                        $projects[$idx]['admin_comments'][] = [
                            'comment' => $comment,
                            'admin' => $_SESSION['user']['username'] ?? 'Admin',
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        $projects[$idx]['status'] = 'rework';
                    }
                }
                write_json(PROJECTS_FILE, $projects);
                header('Location: project.php?id=' . $id);
                exit;
            }
        }
    }
}

$project = null;
foreach ($projects as $p) {
    if ((int)($p['id'] ?? 0) === $id) {
        $project = $p;
        break;
    }
}

if (!$project) {
    header('Location: index.php');
    exit;
}

$loggedIn = isset($_SESSION['user']);
$isAdmin = $loggedIn && !empty($_SESSION['user']['is_admin']);
$isOwner = $loggedIn && ((int)($_SESSION['user']['id'] ?? 0) === (int)($project['owner_id'] ?? -1));

$status = $project['status'] ?? '';
$canView = ($status === 'approved' || $status === 'closed') || $isAdmin || $isOwner || ($status === 'rework' && $isOwner);
if (!$canView) {
    header('Location: index.php');
    exit;
}

$owner = null;
foreach ($users as $u) {
    if ((int)($u['id'] ?? 0) === (int)($project['owner_id'] ?? 0)) {
        $owner = $u;
        break;
    }
}

$voteCount = 0;
foreach ($votes as $v) {
    if ((int)($v['project_id'] ?? 0) === $id) {
        $voteCount++;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div>
        <h1 style="margin: 0;"><?= $project['title'] ?></h1>
    </div>
    <nav>
        <a href="index.php">← Back to Home</a>
        <?php if ($isAdmin): ?>
            <a href="admin.php">Admin</a>
            <a href="statistics.php">Statistics</a>
        <?php endif; ?>
    </nav>
</header>

<div class="box">
    <div style="display: flex; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); flex-wrap: wrap;">
        <strong>Status:</strong> 
        <span class="status-badge status-<?= $project['status'] ?? 'pending' ?>">
            <?= strtoupper($project['status'] ?? 'pending') ?>
        </span>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
        <div>
            <strong class="muted" style="display: block; font-size: 0.85rem; margin-bottom: var(--spacing-xs);">Category</strong>
            <span><?= $project['category'] ?? '' ?></span>
        </div>
        <div>
            <strong class="muted" style="display: block; font-size: 0.85rem; margin-bottom: var(--spacing-xs);">Postal code</strong>
            <span><?= $project['postal_code'] ?? '' ?></span>
        </div>
        <?php if ($owner): ?>
            <div>
                <strong class="muted" style="display: block; font-size: 0.85rem; margin-bottom: var(--spacing-xs);">Submitted by</strong>
                <span><?= $owner['username'] ?? 'Unknown' ?></span>
            </div>
        <?php endif; ?>
        <div>
            <strong class="muted" style="display: block; font-size: 0.85rem; margin-bottom: var(--spacing-xs);">Submitted</strong>
            <span><?= $project['submitted_at'] ?? '' ?></span>
        </div>
        <?php if (!empty($project['approved_at'])): ?>
            <div>
                <strong class="muted" style="display: block; font-size: 0.85rem; margin-bottom: var(--spacing-xs);">Published</strong>
                <span><?= $project['approved_at'] ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($project['image_url'])): ?>
        <div style="margin: var(--spacing-xl) 0;">
            <img src="<?= $project['image_url'] ?>" alt="Project image" style="max-width: 100%; width: 100%;">
        </div>
    <?php endif; ?>
    
    <h3>Description</h3>
    <div style="line-height: 1.8; color: var(--text-primary);">
        <?= nl2br($project['description'] ?? '') ?>
    </div>
    
    <?php if (!empty($project['history'])): ?>
        <div style="margin-top: var(--spacing-xl); padding: var(--spacing-lg); background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
            <h3 style="margin-top: 0;">Edit History</h3>
            <?php foreach (array_reverse($project['history']) as $historyEntry): ?>
                <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); background: white; border-radius: var(--border-radius-sm);">
                    <div class="muted" style="margin-bottom: var(--spacing-sm); font-size: 0.85rem;">
                        Edited: <?= $historyEntry['edited_at'] ?? '' ?>
                    </div>
                    <?php foreach ($historyEntry['changes'] ?? [] as $field => $change): ?>
                        <div style="margin-bottom: var(--spacing-sm);">
                            <strong><?= ucfirst(str_replace('_', ' ', $field)) ?>:</strong>
                            <div style="margin-left: var(--spacing-md); margin-top: var(--spacing-xs);">
                                <div style="color: var(--danger-color); text-decoration: line-through;">
                                    Old: <?= htmlspecialchars($change['old'] ?? '') ?>
                                </div>
                                <div style="color: var(--success-color);">
                                    New: <?= htmlspecialchars($change['new'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (($project['status'] ?? '') === 'rework' && !empty($project['admin_comments'])): ?>
        <div style="margin-top: var(--spacing-xl); padding: var(--spacing-lg); background: #fff3cd; border: 1px solid var(--warning-color); border-radius: var(--border-radius-sm);">
            <h3 style="margin-top: 0;">Admin Comments</h3>
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
            <?php if ($isOwner): ?>
                <div style="margin-top: var(--spacing-md);">
                    <a href="edit-project.php?id=<?= $id ?>" class="btn-primary" style="display: inline-block;">
                        Edit & Resubmit Project
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (($project['status'] ?? '') === 'approved' || ($project['status'] ?? '') === 'closed'): ?>
        <div style="margin-top: var(--spacing-xl); padding: var(--spacing-lg); background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%); border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);">
            <p style="margin: 0;"><strong>Votes:</strong> <strong style="font-size: 1.5rem; color: var(--primary-color);"><?= $voteCount ?></strong></p>
            <?php if (($project['status'] ?? '') === 'closed'): ?>
                <p style="margin: var(--spacing-sm) 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                    Voting closed on <?= $project['closed_at'] ?? '' ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($isAdmin && ($project['status'] ?? '') === 'pending'): ?>
        <div style="margin-top: var(--spacing-xl); padding: var(--spacing-lg); background: #fef3c7; border: 1px solid var(--warning-color); border-radius: var(--border-radius-sm);">
            <h3 style="margin-top: 0;">Admin Actions</h3>
            <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; margin-bottom: var(--spacing-md);">
                <form method="post" style="flex: 1; min-width: 150px;">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        ✓ Approve & Publish
                    </button>
                </form>
                <form method="post" style="flex: 1; min-width: 150px;">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" style="width: 100%; background: var(--danger-color); color: white; padding: var(--spacing-sm) var(--spacing-lg); border: none; border-radius: var(--border-radius-sm); cursor: pointer; font-weight: 500;">
                        ✗ Reject
                    </button>
                </form>
            </div>
            <form method="post" style="margin-top: var(--spacing-md);">
                <input type="hidden" name="action" value="rework">
                <label style="display: block; margin-bottom: var(--spacing-sm);">
                    <strong>Request Changes (Rework):</strong>
                </label>
                <textarea name="comment" rows="4" style="width: 100%; padding: var(--spacing-sm); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); margin-bottom: var(--spacing-sm);" placeholder="Enter your feedback and requested changes..." required></textarea>
                <button type="submit" style="background: var(--warning-color); color: white; padding: var(--spacing-sm) var(--spacing-lg); border: none; border-radius: var(--border-radius-sm); cursor: pointer; font-weight: 500;">
                    ↻ Send for Rework
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

</body>
</html>