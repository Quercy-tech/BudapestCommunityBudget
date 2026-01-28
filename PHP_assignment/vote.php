<?php
require 'init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if (!in_array($action, ['vote', 'unvote']) || $projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$projects = update_project_statuses();
$votes = read_json(VOTES_FILE);
$userId = (int)$_SESSION['user']['id'];

$project = null;
foreach ($projects as $p) {
    if ((int)($p['id'] ?? 0) === $projectId) {
        $project = $p;
        break;
    }
}

$status = $project['status'] ?? '';
if (!$project || ($status !== 'approved' && $status !== 'closed')) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found or not available for voting']);
    exit;
}

if ($status === 'closed') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Voting period has ended']);
    exit;
}


$category = $project['category'] ?? '';

$userVotesInCategory = 0;
$hasVotedForThis = false;
foreach ($votes as $v) {
    if ((int)($v['user_id'] ?? 0) === $userId) {
            foreach ($projects as $p) {
                $pStatus = $p['status'] ?? '';
                if ((int)($p['id'] ?? 0) === (int)($v['project_id'] ?? 0) && 
                    ($pStatus === 'approved' || $pStatus === 'closed') &&
                    ($p['category'] ?? '') === $category) {
                    $userVotesInCategory++;
                    if ((int)($v['project_id'] ?? 0) === $projectId) {
                        $hasVotedForThis = true;
                    }
                    break;
                }
            }
    }
}

if ($action === 'vote') {
    if ($hasVotedForThis) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Already voted for this project']);
        exit;
    }
    
    if ($userVotesInCategory >= 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Maximum 3 votes per category']);
        exit;
    }
    
    $votes[] = [
        'user_id' => $userId,
        'project_id' => $projectId,
        'voted_at' => date('Y-m-d H:i:s'),
    ];
    
    write_json(VOTES_FILE, $votes);
    
    $voteCount = 0;
    foreach ($votes as $v) {
        if ((int)($v['project_id'] ?? 0) === $projectId) {
            $voteCount++;
        }
    }
    
    $userVotesInCategory = 0;
    foreach ($votes as $v) {
        if ((int)($v['user_id'] ?? 0) === $userId) {
            foreach ($projects as $p) {
                $pStatus = $p['status'] ?? '';
                if ((int)($p['id'] ?? 0) === (int)($v['project_id'] ?? 0) && 
                    ($pStatus === 'approved' || $pStatus === 'closed') &&
                    ($p['category'] ?? '') === $category) {
                    $userVotesInCategory++;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'votes' => $voteCount,
        'remaining' => 3 - $userVotesInCategory,
        'voted' => true,
    ]);
    exit;
    
} elseif ($action === 'unvote') {
    if (!$hasVotedForThis) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Not voted for this project']);
        exit;
    }
    
    $newVotes = [];
    foreach ($votes as $v) {
        if (!((int)($v['user_id'] ?? 0) === $userId && (int)($v['project_id'] ?? 0) === $projectId)) {
            $newVotes[] = $v;
        }
    }
    
    write_json(VOTES_FILE, $newVotes);
    
    $voteCount = 0;
    foreach ($newVotes as $v) {
        if ((int)($v['project_id'] ?? 0) === $projectId) {
            $voteCount++;
        }
    }
    
    $userVotesInCategory = 0;
    foreach ($newVotes as $v) {
        if ((int)($v['user_id'] ?? 0) === $userId) {
            foreach ($projects as $p) {
                $pStatus = $p['status'] ?? '';
                if ((int)($p['id'] ?? 0) === (int)($v['project_id'] ?? 0) && 
                    ($pStatus === 'approved' || $pStatus === 'closed') &&
                    ($p['category'] ?? '') === $category) {
                    $userVotesInCategory++;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'votes' => $voteCount,
        'remaining' => 3 - $userVotesInCategory,
        'voted' => false,
    ]);
    exit;
}

