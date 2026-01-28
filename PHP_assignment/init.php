<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DATA_DIR = __DIR__ . '/data';
const USERS_FILE = DATA_DIR . '/users.json';
const PROJECTS_FILE = DATA_DIR . '/projects.json';
const VOTES_FILE = DATA_DIR . '/votes.json';

const CATEGORIES = [
    'Local small project',
    'Local large project',
    'Equal opportunity Budapest',
    'Green Budapest',
];

function read_json($path)
{
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_json($path, $data)
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

function ensure_storage()
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, '[]');
    if (!file_exists(PROJECTS_FILE)) file_put_contents(PROJECTS_FILE, '[]');
    if (!file_exists(VOTES_FILE)) file_put_contents(VOTES_FILE, '[]');
}

function next_id($items)
{
    $max = 0;
    foreach ($items as $it) {
        $id = isset($it['id']) ? (int)$it['id'] : 0;
        if ($id > $max) $max = $id;
    }
    return $max + 1;
}

function ensure_admin_user()
{
    $users = read_json(USERS_FILE);

    foreach ($users as $u) {
        if (($u['username'] ?? null) === 'admin') {
            return;
        }
    }

    $users[] = [
        'id' => next_id($users),
        'username' => 'admin',
        'email' => 'admin@local.test',
        'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
        'is_admin' => true,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    write_json(USERS_FILE, $users);
}
function selected_category()
{
    $raw = $_GET['category'] ?? 'all';
    if ($raw === '' || $raw === 'all') return null;
    return in_array($raw, CATEGORIES) ? $raw : null;
}

function count_votes_for_project($projectId, $votes)
{
    $count = 0;
    foreach ($votes as $v) {
        if ((int)($v['project_id'] ?? 0) === $projectId) {
            $count++;
        }
    }
    return $count;
}

function has_user_voted($userId, $projectId, $votes)
{
    foreach ($votes as $v) {
        if ((int)($v['user_id'] ?? 0) === $userId && (int)($v['project_id'] ?? 0) === $projectId) {
            return true;
        }
    }
    return false;
}

function count_user_votes_in_category($userId, $category, $projects, $votes)
{
    $count = 0;
    foreach ($votes as $v) {
        if ((int)($v['user_id'] ?? 0) === $userId) {
            foreach ($projects as $p) {
                $status = $p['status'] ?? '';
                if ((int)($p['id'] ?? 0) === (int)($v['project_id'] ?? 0) && 
                    ($status === 'approved' || $status === 'closed') &&
                    ($p['category'] ?? '') === $category) {
                    $count++;
                    break;
                }
            }
        }
    }
    return $count;
}

function is_voting_open($approvedAt)
{
    if (!$approvedAt) return false;
    $approvedTime = strtotime($approvedAt);
    $twoWeeksLater = $approvedTime + (14 * 24 * 60 * 60);
    return time() <= $twoWeeksLater;
}

function update_project_statuses()
{
    $projects = read_json(PROJECTS_FILE);
    $updated = false;
    
    foreach ($projects as $idx => $p) {
        if (($p['status'] ?? '') === 'approved' && !empty($p['approved_at'])) {
            $approvedTime = strtotime($p['approved_at']);
            $twoWeeksLater = $approvedTime + (14 * 24 * 60 * 60);
            
            if (time() > $twoWeeksLater) {
                $projects[$idx]['status'] = 'closed';
                $projects[$idx]['closed_at'] = date('Y-m-d H:i:s');
                $updated = true;
            }
        }
    }
    
    if ($updated) {
        write_json(PROJECTS_FILE, $projects);
    }
    
    return $projects;
}

function fetch_approved_projects_grouped($categoryFilter, $userId = null)
{
    $projects = update_project_statuses();
    $votes = read_json(VOTES_FILE);

    $grouped = [];
    foreach (CATEGORIES as $cat) {
        $grouped[$cat] = [];
    }

    foreach ($projects as $p) {
        $status = $p['status'] ?? '';
        if ($status !== 'approved' && $status !== 'closed') {
            continue;
        }

        $cat = (string)($p['category'] ?? '');
        if (!in_array($cat, CATEGORIES, true)) {
            continue;
        }

        if ($categoryFilter !== null && $cat !== $categoryFilter) {
            continue;
        }

        $id = (int)($p['id'] ?? 0);
        $approvedAt = (string)($p['approved_at'] ?? '');
        $isClosed = ($status === 'closed');
        $votingOpen = !$isClosed && is_voting_open($approvedAt);
        
        $projectData = [
            'id' => $id,
            'title' => (string)($p['title'] ?? ''),
            'category' => $cat,
            'approved_at' => $approvedAt,
            'status' => $status,
            'vote_count' => count_votes_for_project($id, $votes),
            'voting_open' => $votingOpen,
        ];
        
        if ($userId !== null) {
            $projectData['has_voted'] = has_user_voted($userId, $id, $votes);
            $projectData['remaining_votes'] = 3 - count_user_votes_in_category($userId, $cat, $projects, $votes);
        }

        $grouped[$cat][] = $projectData;
    }

    foreach ($grouped as $cat => $list) {
        usort($list, function ($a, $b) {
            $vc = ($b['vote_count'] <=> $a['vote_count']);
            if ($vc !== 0) return $vc;
            return ($b['id'] <=> $a['id']);
        });
        $grouped[$cat] = $list;
    }

    if ($categoryFilter !== null) {
        return [$categoryFilter => $grouped[$categoryFilter]];
    }

    return $grouped;
}

ensure_storage();
ensure_admin_user();

