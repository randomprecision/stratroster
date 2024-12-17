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

// Fetch players for all teams
$players_stmt = $db->query('SELECT * FROM players ORDER BY last_name');
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
    $name = htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
    if ($player['is_pitcher'] && $player['throws'] == 'L') {
        $name .= '*';
    } elseif (!$player['is_pitcher'] && $player['bats'] == 'L') {
        $name .= '*';
    } elseif (!$player['is_pitcher'] && $player['bats'] == 'S') {
        $name .= '@';
    }
    if ($player['no_card'] == 1) {
        $name .= '‡';
        $team_player_counts[$player['fantasy_team_id']]['no_cards'] = isset($team_player_counts[$player['fantasy_team_id']]['no_cards']) ? $team_player_counts[$player['fantasy_team_id']]['no_cards'] + 1 : 1;
    }

    $team_player_counts[$player['fantasy_team_id']]['total'] = isset($team_player_counts[$player['fantasy_team_id']]['total']) ? $team_player_counts[$player['fantasy_team_id']]['total'] + 1 : 1;

    if ($player['is_pitcher']) {
        $team_players[$player['fantasy_team_id']]['Pitchers'][] = $name;
    } elseif ($player['is_catcher']) {
        $team_players[$player['fantasy_team_id']]['Catchers'][] = $name;
    } elseif ($player['is_infielder']) {
        $team_players[$player['fantasy_team_id']]['Infielders'][] = $name;
    } elseif ($player['is_outfielder']) {
        $team_players[$player['fantasy_team_id']]['Outfielders'][] = $name;
    }
}

// Organize draft picks by team and year
$team_draft_picks = [];
foreach ($draft_picks as $draft_pick) {
    $team_draft_picks[$draft_pick['team_id']][$draft_pick['year']][] = $draft_pick;
}

// Function to format draft picks as ranges with team name
function format_draft_picks($picks, $team_name) {
    $ranges = [];
    $current_range = [];
    foreach ($picks as $i => $pick) {
        if (empty($current_range)) {
            $current_range[] = $pick['round'];
        } elseif ($pick['round'] == $current_range[count($current_range) - 1] + 1) {
            $current_range[] = $pick['round'];
        } else {
            if (count($current_range) > 1) {
                $ranges[] = "$team_name round " . $current_range[0] . '-' . $current_range[count($current_range) - 1];
            } else {
                $ranges[] = "$team_name round " . $current_range[0];
            }
            $current_range = [$pick['round']];
        }
    }
    if (!empty($current_range)) {
        if (count($current_range) > 1) {
            $ranges[] = "$team_name round " . $current_range[0] . '-' . $current_range[count($current_range) - 1];
        } else {
            $ranges[] = "$team_name round " . $current_range[0];
        }
    }
    return implode(', ', $ranges);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Full Rosters</title>
    <style>
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
            text-align: center;
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
        }
        .footer {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="roster-container">
        <?php foreach ($teams as $team): ?>
            <div class="team">
                <h2><?= htmlspecialchars($team['team_name']) ?></h2>
                <?php if (isset($team_emails[$team['id']])): ?>
                    <p><a href="mailto:<?= htmlspecialchars($team_emails[$team['id']]) ?>">E-mail <?= htmlspecialchars($team['team_name']) ?> manager</a></p>
                <?php endif; ?>

                <h3>Catchers</h3>
                <ul>
                    <?php if (!empty($team_players[$team['id']]['Catchers'])): ?>
                        <?php foreach ($team_players[$team['id']]['Catchers'] as $catcher): ?>
                            <li><?= $catcher ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No catchers found for this team.</li>
                    <?php endif; ?>
                </ul>

                <h3>Infielders</h3>
                <ul>
                    <?php if (!empty($team_players[$team['id']]['Infielders'])): ?>
                        <?php foreach ($team_players[$team['id']]['Infielders'] as $infielder): ?>
                            <li><?= $infielder ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No infielders found for this team.</li>
                    <?php endif; ?>
                </ul>

                <h3>Outfielders</h3>
                <ul>
                    <?php if (!empty($team_players[$team['id']]['Outfielders'])): ?>
                        <?php foreach ($team_players[$team['id']]['Outfielders'] as $outfielder): ?>
                            <li><?= $outfielder ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No outfielders found for this team.</li>
                    <?php endif; ?>
                </ul>

                <h3>Pitchers</h3>
                <ul>
                    <?php if (!empty($team_players[$team['id']]['Pitchers'])): ?>
                        <?php foreach ($team_players[$team['id']]['Pitchers'] as $pitcher): ?>
                            <li><?= $pitcher ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No pitchers found for this team.</li>
                    <?php endif; ?>
                </ul>

                <h3>Draft Picks</h3>
                <ul>
                    <?php if (!empty($team_draft_picks[$team['id']])): ?>
                        <?php foreach ($team_draft_picks[$team['id']] as $year => $picks): ?>
                            <li><strong><?= htmlspecialchars($year) ?></strong></li>
                            <ul>
                                <li><?= format_draft_picks($picks, $team_names[$team['id']]) ?></li>
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
</body>
</html>

