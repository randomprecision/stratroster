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

// Fetch players on the trade block
$players_stmt = $db->prepare('SELECT * FROM players WHERE tradeblock = 1 ORDER BY fantasy_team_id, last_name');
$players_stmt->execute();
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group players by team
$team_players = [];
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
    }

    $team_players[$player['fantasy_team_id']][] = ['name' => $styled_name, 'mlb_team' => htmlspecialchars_decode($player['team'])];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Trading Block</title>
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
        .trading-block-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
            width: 80%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .team-section {
            text-align: left;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
        }
        h3 {
            color: #666;
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
    <div class="trading-block-container">
        <h1>Trading Block</h1>
        <?php if (empty($team_players)): ?>
            <p>No players are currently on the trading block.</p>
        <?php else: ?>
            <?php foreach ($team_players as $team_id => $players): ?>
                <div class="team-section">
                    <h2><?= htmlspecialchars($team_names[$team_id]) ?></h2>
                    <ul>
                        <?php foreach ($players as $player): ?>
                            <li><?= $player['name'] ?> (<?= htmlspecialchars($player['mlb_team']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="footer">
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>
