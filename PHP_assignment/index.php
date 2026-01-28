<?php
require 'init.php';

update_project_statuses();

$catFilter = selected_category();
$userId = isset($_SESSION['user']) ? (int)$_SESSION['user']['id'] : null;
$projectsByCategory = fetch_approved_projects_grouped($catFilter, $userId);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Budapest Community Budget</title>
    <link rel="stylesheet" href="style.css">
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const voteButtons = document.querySelectorAll('.vote-btn');
        
        voteButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const projectId = this.dataset.projectId;
                const action = this.dataset.action;
                const category = this.dataset.category;
                const button = this;
                const projectItem = button.closest('.project-item');
                
                if (projectItem && projectItem.classList.contains('voting-closed')) {
                    alert('Voting period has ended for this project');
                    return;
                }
                
                button.disabled = true;
                
                const formData = new FormData();
                formData.append('action', action);
                formData.append('project_id', projectId);
                
                fetch('vote.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            try {
                                const json = JSON.parse(text);
                                throw new Error(json.message || 'Request failed');
                            } catch (e) {
                                if (e instanceof Error) throw e;
                                throw new Error('Server error: ' + text.substring(0, 100));
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const voteCountEl = projectItem.querySelector('.vote-count-' + projectId);
                        if (voteCountEl) {
                            voteCountEl.textContent = data.votes;
                            const voteText = projectItem.querySelector('.vote-count-' + projectId).nextElementSibling;
                            if (voteText && voteText.classList.contains('muted')) {
                                voteText.textContent = ' vote' + (data.votes !== 1 ? 's' : '');
                            }
                        }
                        
                        const categoryDiv = projectItem.closest('.category');
                        const remainingVotesEl = categoryDiv.querySelector('.remaining-votes-' + category.replace(/\s+/g, '-'));
                        if (remainingVotesEl) {
                            const strongEl = remainingVotesEl.querySelector('strong');
                            if (strongEl) {
                                strongEl.textContent = data.remaining;
                            }
                        }
                        
                        if (data.voted) {
                            button.textContent = 'Withdraw vote';
                            button.dataset.action = 'unvote';
                            if (!projectItem.querySelector('.voted-indicator')) {
                                const indicator = document.createElement('span');
                                indicator.className = 'voted-indicator';
                                indicator.textContent = '✓ Voted';
                                button.parentNode.insertBefore(indicator, button.nextSibling);
                            }
                        } else {
                            button.textContent = 'Vote';
                            button.dataset.action = 'vote';
                            const indicator = projectItem.querySelector('.voted-indicator');
                            if (indicator) {
                                indicator.remove();
                            }
                            
                            if (data.remaining === 0) {
                                button.remove();
                                const noVotesSpan = document.createElement('span');
                                noVotesSpan.className = 'muted';
                                noVotesSpan.style.fontSize = '0.85rem';
                                noVotesSpan.textContent = '(No votes remaining)';
                                projectItem.appendChild(noVotesSpan);
                            }
                        }
                        
                        button.disabled = false;
                        
                        const allVoteButtons = categoryDiv.querySelectorAll('.vote-btn');
                        allVoteButtons.forEach(btn => {
                            if (data.remaining === 0 && btn.dataset.action === 'vote' && !btn.dataset.projectId.includes(projectId)) {
                                btn.disabled = true;
                            } else if (data.remaining > 0 && btn.dataset.action === 'vote') {
                                btn.disabled = false;
                            }
                        });
                    } else {
                        alert(data.message || 'An error occurred');
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Vote error:', error);
                    alert('Error: ' + error.message);
                    button.disabled = false;
                });
            });
        });
    });
    </script>
</head>
<body>

<header>
  <div>
    <h1>Budapest Community Budget</h1>
  </div>

    <nav>
        <?php if (isset($_SESSION['user'])): ?>
            Hello <strong><?= htmlspecialchars($_SESSION['user']['username']) ?></strong>
            <a href="projects-own.php">My Projects</a>
            <a href="submit-project.php">Submit project</a>
            <?php if (!empty($_SESSION['user']['is_admin'])): ?>
                <a href="admin.php">Admin</a>
                <a href="statistics.php">Statistics</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>

  <form method="get">
    <label>
      Category:
      <select name="category">
        <option value="all">All</option>
        <?php foreach (CATEGORIES as $cat): ?>
          <option value="<?= $cat ?>" <?= ($catFilter === $cat ? 'selected' : '') ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Filter</button>
  </form>
</header>

<div class="box">
  <?php foreach ($projectsByCategory as $cat => $projects): ?>
    <div class="category">
      <h2><?= $cat ?></h2>
      
      <?php if ($userId !== null && count($projects) > 0): ?>
        <?php 
        $remaining = $projects[0]['remaining_votes'] ?? 3;
        ?>
        <div class="muted remaining-votes-<?= str_replace(' ', '-', $cat) ?>" style="margin-bottom: var(--spacing-md); padding: var(--spacing-sm); background: var(--bg-tertiary); border-radius: var(--border-radius-sm);">
          Remaining votes in this category: <strong style="color: var(--primary-color);"><?= $remaining ?></strong>
        </div>
      <?php endif; ?>

      <?php if (count($projects) === 0): ?>
        <div class="muted" style="text-align: center; padding: var(--spacing-lg);">No approved projects yet.</div>
      <?php else: ?>
        <ul>
          <?php foreach ($projects as $p): ?>
            <li class="project-item <?= !($p['voting_open'] ?? true) || ($p['status'] ?? '') === 'closed' ? 'voting-closed' : '' ?>">
              <a href="project.php?id=<?= (int)$p['id'] ?>"><?= $p['title'] ?></a>
              <span style="color: var(--text-secondary);">—</span>
              <strong class="vote-count-<?= (int)$p['id'] ?>" style="color: var(--primary-color);"><?= (int)$p['vote_count'] ?></strong> <span class="muted">vote<?= (int)$p['vote_count'] !== 1 ? 's' : '' ?></span>
              <?php if (($p['status'] ?? '') === 'closed'): ?>
                <span class="status-badge status-closed" style="margin-left: var(--spacing-sm); font-size: 0.7rem;">CLOSED</span>
              <?php elseif (!empty($p['approved_at'])): ?>
                <span class="muted" style="font-size: 0.85rem;">(published <?= $p['approved_at'] ?>)</span>
              <?php endif; ?>
              
              <?php if ($userId !== null): ?>
                <?php if (($p['voting_open'] ?? true) && ($p['status'] ?? '') !== 'closed'): ?>
                  <?php if ($p['has_voted'] ?? false): ?>
                    <button class="vote-btn" data-project-id="<?= (int)$p['id'] ?>" data-action="unvote" data-category="<?= $cat ?>">
                      Withdraw vote
                    </button>
                    <span class="voted-indicator">✓ Voted</span>
                  <?php else: ?>
                    <?php if (($p['remaining_votes'] ?? 0) > 0): ?>
                      <button class="vote-btn" data-project-id="<?= (int)$p['id'] ?>" data-action="vote" data-category="<?= $cat ?>">
                        Vote
                      </button>
                    <?php else: ?>
                      <span class="muted" style="font-size: 0.85rem;">(No votes remaining)</span>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="voting-closed-label">Voting closed</span>
                <?php endif; ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>
