<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$projects = read_json(PROJECTS_FILE);

$userProjects = [];
foreach ($projects as $p) {
    if ((int)($p['owner_id'] ?? 0) === $userId) {
        $userProjects[] = $p;
    }
}

usort($userProjects, function ($a, $b) {
    $timeA = strtotime($a['submitted_at'] ?? '');
    $timeB = strtotime($b['submitted_at'] ?? '');
    return $timeB <=> $timeA;
});
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>My Projects</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>My Projects</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="submit-project.php">Submit project</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="box">
    <?php if (count($userProjects) === 0): ?>
        <p class="muted">You haven't submitted any projects yet.</p>
        <p><a href="submit-project.php">Submit your first project</a></p>
    <?php else: ?>
        <h2>Your Submitted Projects</h2>
        <ul>
            <?php foreach ($userProjects as $p): ?>
                <li style="margin: 12px 0;">
                    <strong>
                        <a href="project.php?id=<?= (int)$p['id'] ?>"><?= $p['title'] ?></a>
                    </strong>
                    <br>
                    <span class="muted">
                        Status: <strong><?= $p['status'] ?? 'pending' ?></strong>
                        <?php if (($p['status'] ?? '') === 'rework'): ?>
                            <span style="color: #ff9800;">(Needs rework)</span>
                        <?php elseif (($p['status'] ?? '') === 'rejected'): ?>
                            <span style="color: #f44336;">(Rejected)</span>
                        <?php elseif (($p['status'] ?? '') === 'pending'): ?>
                            <span style="color: #2196f3;">(Pending approval)</span>
                        <?php elseif (($p['status'] ?? '') === 'approved'): ?>
                            <span style="color: #4caf50;">(Approved)</span>
                        <?php elseif (($p['status'] ?? '') === 'closed'): ?>
                            <span style="color: #6b7280;">(Closed - Voting ended)</span>
                        <?php endif; ?>
                    </span>
                    <br>
                    <span class="muted">
                        Submitted: <?= $p['submitted_at'] ?? '' ?>
                        <?php if (!empty($p['approved_at'])): ?>
                            | Published: <?= $p['approved_at'] ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

</body>
</html>

