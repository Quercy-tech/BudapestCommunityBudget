<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: index.php');
    exit;
}

$projects = read_json(PROJECTS_FILE);
$pendingProjects = [];

foreach ($projects as $p) {
    if (($p['status'] ?? '') === 'pending') {
        $pendingProjects[] = $p;
    }
}

$grouped = [];
foreach (CATEGORIES as $cat) {
    $grouped[$cat] = [];
}

foreach ($pendingProjects as $p) {
    $cat = $p['category'] ?? '';
    if (in_array($cat, CATEGORIES)) {
        $grouped[$cat][] = $p;
    }
}

foreach ($grouped as $cat => $list) {
    usort($list, function ($a, $b) {
        $timeA = strtotime($a['submitted_at'] ?? '');
        $timeB = strtotime($b['submitted_at'] ?? '');
        return $timeB <=> $timeA;
    });
    $grouped[$cat] = $list;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin - Pending Projects</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Admin - Pending Projects</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="statistics.php">Statistics</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="box">
    <h2>Pending Projects by Category</h2>
    
    <?php 
    $hasAny = false;
    foreach ($grouped as $cat => $list) {
        if (count($list) > 0) {
            $hasAny = true;
            break;
        }
    }
    ?>
    
    <?php if (!$hasAny): ?>
        <p class="muted">No pending projects.</p>
    <?php else: ?>
        <?php foreach ($grouped as $cat => $list): ?>
            <?php if (count($list) > 0): ?>
                <div class="category" style="margin-top: 20px;">
                    <h3><?= $cat ?></h3>
                    <ul>
                        <?php foreach ($list as $p): ?>
                            <li style="margin: 8px 0;">
                                <a href="project.php?id=<?= (int)$p['id'] ?>"><?= $p['title'] ?></a>
                                <span class="muted">
                                    — Submitted: <?= $p['submitted_at'] ?? '' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>

