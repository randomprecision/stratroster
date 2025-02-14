<?php
$db = new PDO("sqlite:./stratroster.db");

// Fetch the background color
$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];

// Fetch all teams
$teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of team names by team ID
$team_names = [];
foreach ($teams as $team) {
    $team_names[$team['id']] = $team['team_name'];
}

// Fetch players for all teams, including MLB team information from player.team
$players_stmt = $db->query('SELECT *, team AS mlb_team FROM players ORDER BY last_name');
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch draft picks for all teams
$draft_picks_stmt = $db->query('SELECT * FROM draft_picks ORDER BY year, round');
$draft_picks = $draft_picks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users (managers) with their team ID
$users_stmt = $db->query('SELECT id, team_id, email FROM users');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of email addresses by team ID
$team_emails = [];
foreach ($users as $user) {
    if ($user['team_id']) {
        $team_emails[$user['team_id']] = $user['email'];
    }
}
$team_players = [];
$team_player_counts = [];
foreach ($players as $player) {
    $name = htmlspecialchars_decode($player['first_name'] . ' ' . $player['last_name']);

    // Apply styling based on player status
    $styled_name = $name;
    if ($player['majors']) {
        $styled_name = '<strong>' . $styled_name . '</strong>';
    }
    if ($player['dl']) {
        $styled_name = '<span style="color: orange;">' . $styled_name . '</span>';
    }
    if ($player['cut']) {
        $styled_name = '<span style="color: red;">' . $styled_name . '</span>';
    }

    if ($player['is_pitcher'] && $player['throws'] == 'L') {
        $styled_name .= '*';
    } elseif (!$player['is_pitcher'] && $player['bats'] == 'L') {
        $styled_name .= '*';
    } elseif (!$player['is_pitcher'] && $player['bats'] == 'S') {
        $styled_name .= '@';
    }
    if ($player['no_card'] == 1) {
        $styled_name .= '‡';
        $team_player_counts[$player['fantasy_team_id']]['no_cards'] = isset($team_player_counts[$player['fantasy_team_id']]['no_cards']) ? $team_player_counts[$player['fantasy_team_id']]['no_cards'] + 1 : 1;
    }

    // Include MLB team information
    $mlb_team = htmlspecialchars_decode($player['mlb_team']);
    $team_players[$player['fantasy_team_id']][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];

    $team_player_counts[$player['fantasy_team_id']]['total'] = isset($team_player_counts[$player['fantasy_team_id']]['total']) ? $team_player_counts[$player['fantasy_team_id']]['total'] + 1 : 1;

    if ($player['is_pitcher']) {
        $team_players[$player['fantasy_team_id']]['Pitchers'][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];
    } elseif ($player['is_catcher']) {
        $team_players[$player['fantasy_team_id']]['Catchers'][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];
    } elseif ($player['is_infielder']) {
        $team_players[$player['fantasy_team_id']]['Infielders'][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];
    } elseif ($player['is_outfielder']) {
        $team_players[$player['fantasy_team_id']]['Outfielders'][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];
    } elseif ($player['is_dh']) {
        $team_players[$player['fantasy_team_id']]['Designated Hitters'][] = ['name' => $styled_name, 'mlb_team' => $mlb_team];
    }
}

// Organize draft picks by team and year
$team_draft_picks = [];
foreach ($draft_picks as $draft_pick) {
    $team_draft_picks[$draft_pick['team_id']][$draft_pick['year']][] = $draft_pick;
}
// Function to format draft picks as ranges with team name
function format_draft_picks($picks, $team_names, $team_id) {
    $ranges = [];
    $current_range = [];
    $current_team_name = '';

    foreach ($picks as $i => $pick) {
        $original_team_name = $team_names[$pick['original_team_id']] ?? $team_names[$team_id];

        if (empty($current_range) || $original_team_name != $current_team_name) {
            if (!empty($current_range)) {
                if (count($current_range) > 1) {
                    $ranges[] = "$current_team_name round " . $current_range[0] . '-' . $current_range[count($current_range) - 1];
                } else {
                    $ranges[] = "$current_team_name round " . $current_range[0];
                }
            }
            $current_range = [$pick['round']];
            $current_team_name = $original_team_name;
        } elseif ($pick['round'] == end($current_range) + 1) {
            $current_range[] = $pick['round'];
        } else {
            if (count($current_range) > 1) {
                $ranges[] = "$current_team_name round " . $current_range[0] . '-' . $current_range[count($current_range) - 1];
            } else {
                $ranges[] = "$current_team_name round " . $current_range[0];
            }
            $current_range = [$pick['round']];
            $current_team_name = $original_team_name;
        }
    }

    if (!empty($current_range)) {
        if (count($current_range) > 1) {
            $ranges[] = "$current_team_name round " . $current_range[0] . '-' . $current_range[count($current_range) - 1];
        } else {
            $ranges[] = "$current_team_name round " . $current_range[0];
        }
    }

    return implode(', ', $ranges);
}

// Function to get player type counts
function getPlayerCounts($players) {
    $counts = [
        'Catchers' => count($players['Catchers'] ?? []),
        'Infielders' => count($players['Infielders'] ?? []),
        'Outfielders' => count($players['Outfielders'] ?? []),
        'Designated Hitters' => count($players['Designated Hitters'] ?? []),
        'Pitchers' => count($players['Pitchers'] ?? []),
    ];
    return $counts;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Full Rosters</title>
    <style>
    /* Your existing styles */
    body {
        background-color: <?= htmlspecialchars($background_color) ?>;
        font-family: 'Lato', sans-serif;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }
    .roster-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        text-align: left;
        padding: 20px;
        border: 2px solid black;
        border-radius: 10px;
        background-color: #f9f9f9;
        max-height: 80vh;
        overflow-y: auto;
    }
    .team {
        border: 1px solid black;
        padding: 10px;
        border-radius: 5px;
        background-color: #ffffff;
    }
    h2 {
        color: #333;
    }
    h3 {
        color: #666;
    }
    hr {
        border: 1px solid black;
    }
    ul {
        list-style: none;
        padding: 0;
    }
    li {
        margin: 5px 0;
        display: flex;
        justify-content: space-between;
    }
    .footer {
        margin-top: 20px;
    }
    .player-list span {
        flex: 1;
    }

    /* Add these new styles for the search function */
    .search-container {
        display: flex;
        justify-content: center;
        margin: 20px;
    }
    .search-input {
        padding: 10px;
        width: 200px;
        border: 1px solid black;
        border-radius: 5px;
    }
    .search-button {
        padding: 10px;
        margin-left: 10px;
        border: none;
        border-radius: 5px;
        background-color: blue;
        color: white;
        cursor: pointer;
    }
    </style>
</head>
<body>
    <!-- Add this section for search functionality in the body -->
    <div class="search-container">
        <input type="text" class="search-input" placeholder="Search for players..." id="search-input" onkeyup="searchPlayers()">
    </div>

    <div class="roster-container" id="roster-container">
        <?php foreach ($teams as $team): ?>
            <div class="team">
            <h2><?= htmlspecialchars($team['team_name']) ?></h2>
            <?php if (isset($team_emails[$team['id']])): ?>
                <p><a href="mailto:<?= htmlspecialchars($team_emails[$team['id']]) ?>">E-mail <?= htmlspecialchars($team['team_name']) ?> manager</a></p>
            <?php endif; ?>

            <?php $counts = getPlayerCounts($team_players[$team['id']]); ?>

            <h3>Catchers - <?= $counts['Catchers'] ?></h3>
            <ul>
                <?php if (!empty($team_players[$team['id']]['Catchers'])): ?>
                    <?php foreach ($team_players[$team['id']]['Catchers'] as $catcher): ?>
                        <li class="player-list">
                            <span><?= $catcher['name'] ?></span>
                            <span><?= htmlspecialchars($catcher['mlb_team']) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No catchers found for this team.</li>
                <?php endif; ?>
            </ul>

            <h3>Infielders - <?= $counts['Infielders'] ?></h3>
            <ul>
                <?php if (!empty($team_players[$team['id']]['Infielders'])): ?>
                    <?php foreach ($team_players[$team['id']]['Infielders'] as $infielder): ?>
                        <li class="player-list">
                            <span><?= $infielder['name'] ?></span>
                            <span><?= htmlspecialchars($infielder['mlb_team']) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No infielders found for this team.</li>
                <?php endif; ?>
            </ul>
            <h3>Outfielders - <?= $counts['Outfielders'] ?></h3>
            <ul>
                <?php if (!empty($team_players[$team['id']]['Outfielders'])): ?>
                    <?php foreach ($team_players[$team['id']]['Outfielders'] as $outfielder): ?>
                        <li class="player-list">
                            <span><?= $outfielder['name'] ?></span>
                            <span><?= htmlspecialchars($outfielder['mlb_team']) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No outfielders found for this team.</li>
                <?php endif; ?>
            </ul>

            <?php if (isset($team_players[$team['id']]['Designated Hitters']) && !empty($team_players[$team['id']]['Designated Hitters'])): ?>
                <h3>Designated Hitters - <?= $counts['Designated Hitters'] ?></h3>
                <ul>
                    <?php foreach ($team_players[$team['id']]['Designated Hitters'] as $dh): ?>
                        <li class="player-list">
                            <span><?= $dh['name'] ?></span>
                            <span><?= htmlspecialchars($dh['mlb_team']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Pitchers - <?= $counts['Pitchers'] ?></h3>
            <ul>
                <?php if (!empty($team_players[$team['id']]['Pitchers'])): ?>
                    <?php foreach ($team_players[$team['id']]['Pitchers'] as $pitcher): ?>
                        <li class="player-list">
                            <span><?= $pitcher['name'] ?></span>
                            <span><?= htmlspecialchars($pitcher['mlb_team']) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No pitchers found for this team.</li>
                <?php endif; ?>
            </ul>

            <h3>Draft Picks</h3>
            <ul class="draft-picks">
                <?php if (!empty($team_draft_picks[$team['id']])): ?>
                    <?php foreach ($team_draft_picks[$team['id']] as $year => $picks): ?>
                        <li><strong><?= htmlspecialchars($year) ?></strong></li>
                        <ul>
                            <li><?= format_draft_picks($picks, $team_names, $team['id']) ?></li>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No draft picks found for this team.</li>
                <?php endif; ?>
            </ul>

            <p>Total Players: <?= $team_player_counts[$team['id']]['total'] ?? 0 ?>, Total No Cards: <?= $team_player_counts[$team['id']]['no_cards'] ?? 0 ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="footer">
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>

    <script>
    function searchPlayers() {
        const input = document.getElementById('search-input').value.toLowerCase();
        const rosterContainer = document.getElementById('roster-container');
        const teams = rosterContainer.querySelectorAll('.team');

        teams.forEach(team => {
            const players = team.querySelectorAll('.player-list span:first-child');
            const draftPicks = team.querySelectorAll('.draft-picks > li, .draft-picks > ul');
            let found = false;

            players.forEach(player => {
                if (player.textContent.toLowerCase().includes(input)) {
                    found = true;
                    player.parentElement.style.display = '';
                } else {
                    player.parentElement.style.display = 'none';
                }
            });

            if (!found) {
                team.style.display = 'none';
            } else {
                team.style.display = '';
            }

            if (input === '') {
                draftPicks.forEach(draftPick => draftPick.style.display = '');
            } else {
                draftPicks.forEach(draftPick => draftPick.style.display = 'none');
            }
        });
    }

    document.getElementById('search-input').addEventListener('keyup', searchPlayers);
    </script>
</body>
</html>
