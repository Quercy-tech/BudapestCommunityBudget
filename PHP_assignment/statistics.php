<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'init.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: index.php');
    exit;
}

$projects = update_project_statuses();
$votes = read_json(VOTES_FILE);

$projectVotes = [];
foreach ($projects as $p) {
    $status = $p['status'] ?? '';
    if ($status === 'approved' || $status === 'closed') {
        $id = (int)($p['id'] ?? 0);
        $projectVotes[$id] = 0;
    }
}

foreach ($votes as $v) {
    $projectId = (int)($v['project_id'] ?? 0);
    if (isset($projectVotes[$projectId])) {
        $projectVotes[$projectId]++;
    }
}

$topProject = null;
$topVotes = 0;
foreach ($projects as $p) {
    $status = $p['status'] ?? '';
    if ($status === 'approved' || $status === 'closed') {
        $id = (int)($p['id'] ?? 0);
        $voteCount = $projectVotes[$id] ?? 0;
        if ($voteCount > $topVotes) {
            $topVotes = $voteCount;
            $topProject = $p;
            $topProject['vote_count'] = $voteCount;
        }
    }
}

$topByCategory = [];
foreach (CATEGORIES as $cat) {
    $categoryProjects = [];
    foreach ($projects as $p) {
        $status = $p['status'] ?? '';
        if (($status === 'approved' || $status === 'closed') && ($p['category'] ?? '') === $cat) {
            $id = (int)($p['id'] ?? 0);
            $categoryProjects[] = [
                'id' => $id,
                'title' => $p['title'] ?? '',
                'vote_count' => $projectVotes[$id] ?? 0,
            ];
        }
    }
    
    usort($categoryProjects, function ($a, $b) {
        $vc = ($b['vote_count'] <=> $a['vote_count']);
        if ($vc !== 0) return $vc;
        return ($b['id'] <=> $a['id']);
    });
    
    $topByCategory[$cat] = array_slice($categoryProjects, 0, 3);
}

$statuses = ['pending', 'approved', 'rework', 'rejected', 'closed'];

$byStatus = [];
foreach ($statuses as $status) {
    $byStatus[$status] = [];
    foreach (CATEGORIES as $cat) {
        $byStatus[$status][$cat] = 0;
    }
}

$byCategory = [];
foreach (CATEGORIES as $cat) {
    $byCategory[$cat] = [];
    foreach ($statuses as $status) {
        $byCategory[$cat][$status] = 0;
    }
}

foreach ($projects as $p) {
    $status = $p['status'] ?? 'pending';
    $category = $p['category'] ?? '';
    
    if (in_array($status, $statuses) && in_array($category, CATEGORIES)) {
        $byStatus[$status][$category]++;
        $byCategory[$category][$status]++;
    }
}

$statusLabels = ['Pending', 'Approved', 'Rework', 'Rejected', 'Closed'];
$categoryLabels = CATEGORIES;
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statistics</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-xl);
            margin: var(--spacing-xl) 0;
        }
        .chart-wrapper {
            background: var(--bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        .chart-wrapper canvas {
            max-height: 400px;
        }
        @media (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Statistics</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="admin.php">Admin</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="box">
    <h2>Project with Most Votes</h2>
    <?php if ($topProject): ?>
        <div style="padding: 16px; background: #e3f2fd; border-radius: 6px; margin: 16px 0;">
            <h3 style="margin-top: 0;">
                <a href="project.php?id=<?= (int)$topProject['id'] ?>">
                    <?= $topProject['title'] ?>
                </a>
            </h3>
            <p><strong>Votes:</strong> <?= $topProject['vote_count'] ?></p>
            <p><strong>Category:</strong> <?= $topProject['category'] ?? '' ?></p>
        </div>
    <?php else: ?>
        <p class="muted">No approved projects yet.</p>
    <?php endif; ?>
</div>

<div class="box">
    <h2>Top 3 Projects by Category</h2>
    
    <?php foreach (CATEGORIES as $cat): ?>
        <div class="category" style="margin-top: 20px;">
            <h3><?= $cat ?></h3>
            <?php if (count($topByCategory[$cat] ?? []) > 0): ?>
                <ol>
                    <?php foreach ($topByCategory[$cat] as $idx => $p): ?>
                        <li style="margin: 8px 0;">
                            <a href="project.php?id=<?= (int)$p['id'] ?>">
                                <?= $p['title'] ?>
                            </a>
                            <span class="muted"> — <?= $p['vote_count'] ?> vote<?= $p['vote_count'] !== 1 ? 's' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="muted">No approved projects in this category yet.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="box">
    <h2>Projects by Category and Status</h2>
    
    <div class="charts-container">
        <div class="chart-wrapper">
            <h3 style="margin-top: 0; margin-bottom: var(--spacing-md);">By Status</h3>
            <canvas id="chartByStatus"></canvas>
        </div>
        
        <div class="chart-wrapper">
            <h3 style="margin-top: 0; margin-bottom: var(--spacing-md);">By Category</h3>
            <canvas id="chartByCategory"></canvas>
        </div>
    </div>
</div>

<script>
const categoryColors = {
    'Local small project': 'rgba(30, 64, 175, 0.8)',
    'Local large project': 'rgba(234, 88, 12, 0.8)',
    'Equal opportunity Budapest': 'rgba(22, 101, 52, 0.8)',
    'Green Budapest': 'rgba(59, 130, 246, 0.8)'
};

const statusColors = {
    'pending': 'rgba(30, 64, 175, 0.8)',
    'rework': 'rgba(234, 88, 12, 0.8)',
    'approved': 'rgba(22, 101, 52, 0.8)',
    'rejected': 'rgba(59, 130, 246, 0.8)',
    'closed': 'rgba(107, 114, 128, 0.8)'
};

const byStatusData = {
    labels: <?= json_encode($statusLabels) ?>,
    datasets: [
        <?php foreach (CATEGORIES as $cat): ?>
        {
            label: '<?= $cat ?>',
            data: [
                <?= $byStatus['pending'][$cat] ?? 0 ?>,
                <?= $byStatus['approved'][$cat] ?? 0 ?>,
                <?= $byStatus['rework'][$cat] ?? 0 ?>,
                <?= $byStatus['rejected'][$cat] ?? 0 ?>,
                <?= $byStatus['closed'][$cat] ?? 0 ?>
            ],
            backgroundColor: categoryColors['<?= $cat ?>'],
            borderColor: categoryColors['<?= $cat ?>'].replace('0.8', '1'),
            borderWidth: 1
        },
        <?php endforeach; ?>
    ]
};

const byCategoryData = {
    labels: <?= json_encode($categoryLabels) ?>,
    datasets: [
        {
            label: 'Pending',
            data: [<?= implode(', ', array_map(function($cat) use ($byCategory) { return $byCategory[$cat]['pending']; }, CATEGORIES)) ?>],
            backgroundColor: statusColors['pending'],
            borderColor: statusColors['pending'].replace('0.8', '1'),
            borderWidth: 1
        },
        {
            label: 'Rework',
            data: [<?= implode(', ', array_map(function($cat) use ($byCategory) { return $byCategory[$cat]['rework']; }, CATEGORIES)) ?>],
            backgroundColor: statusColors['rework'],
            borderColor: statusColors['rework'].replace('0.8', '1'),
            borderWidth: 1
        },
        {
            label: 'Approved',
            data: [<?= implode(', ', array_map(function($cat) use ($byCategory) { return $byCategory[$cat]['approved']; }, CATEGORIES)) ?>],
            backgroundColor: statusColors['approved'],
            borderColor: statusColors['approved'].replace('0.8', '1'),
            borderWidth: 1
        },
        {
            label: 'Rejected',
            data: [<?= implode(', ', array_map(function($cat) use ($byCategory) { return $byCategory[$cat]['rejected'] ?? 0; }, CATEGORIES)) ?>],
            backgroundColor: statusColors['rejected'],
            borderColor: statusColors['rejected'].replace('0.8', '1'),
            borderWidth: 1
        },
        {
            label: 'Closed',
            data: [<?= implode(', ', array_map(function($cat) use ($byCategory) { return $byCategory[$cat]['closed'] ?? 0; }, CATEGORIES)) ?>],
            backgroundColor: statusColors['closed'],
            borderColor: statusColors['closed'].replace('0.8', '1'),
            borderWidth: 1
        }
    ]
};

new Chart(document.getElementById('chartByStatus'), {
    type: 'bar',
    data: byStatusData,
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            title: {
                display: false
            }
        },
        scales: {
            x: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    stepSize: 10
                }
            },
            y: {
                stacked: true
            }
        }
    }
});

new Chart(document.getElementById('chartByCategory'), {
    type: 'bar',
    data: byCategoryData,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            title: {
                display: false
            }
        },
        scales: {
            x: {
                stacked: true
            },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    stepSize: 10
                }
            }
        }
    }
});
</script>

</body>
</html>
