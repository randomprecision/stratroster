<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Fetch the background color

$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the user's team ID
$user_stmt = $db->prepare('SELECT team_id FROM users WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user_team = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_team) {
    echo "Team not found for the user.";
    exit;
}

// Fetch the players for the user's team
$players_stmt = $db->prepare('SELECT * FROM players WHERE fantasy_team_id = ? ORDER BY last_name');
$players_stmt->execute([$user_team['team_id']]);
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the draft picks for the user's team
$draft_picks_stmt = $db->prepare('SELECT draft_picks.*, teams.team_name AS original_team_name
                                   FROM draft_picks
                                   JOIN teams ON draft_picks.original_team_id = teams.id
                                   WHERE draft_picks.team_id = ?
                                   ORDER BY draft_picks.year, draft_picks.round');
$draft_picks_stmt->execute([$user_team['team_id']]);
$draft_picks = $draft_picks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize draft picks by year
$draft_picks_by_year = [];
foreach ($draft_picks as $draft_pick) {
    $draft_picks_by_year[$draft_pick['year']][] = $draft_pick;
}

// Function to format draft picks as ranges with original team name
function format_draft_picks($picks) {
    $ranges = [];
    $current_range = [];
    $current_team = null;

    foreach ($picks as $i => $pick) {
        if (empty($current_range)) {
            $current_range[] = $pick['round'];
            $current_team = $pick['original_team_name'];
        } elseif ($pick['round'] == $current_range[count($current_range) - 1] + 1 && $pick['original_team_name'] == $current_team) {
            $current_range[] = $pick['round'];
        } else {
            if (count($current_range) > 1) {
                $ranges[] = $current_team . ' round ' . $current_range[0] . '-' . $current_range[count($current_range) - 1];
            } else {
                $ranges[] = $current_team . ' round ' . $current_range[0];
            }
            $current_range = [$pick['round']];
            $current_team = $pick['original_team_name'];
        }
    }
    if (!empty($current_range)) {
        if (count($current_range) > 1) {
            $ranges[] = $current_team . ' round ' . $current_range[0] . '-' . $current_range[count($current_range) - 1];
        } else {
            $ranges[] = $current_team . ' round ' . $current_range[0];
        }
    }
    return implode(', ', $ranges);
}

// Categorize players and count total and no card players
$catchers = [];
$infielders = [];
$outfielders = [];
$pitchers = [];
$total_players = 0;
$total_no_cards = 0;

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
        $total_no_cards++;
    }

    if ($player['is_pitcher']) {
        $pitchers[] = $name;
    } elseif ($player['is_catcher']) {
        $catchers[] = $name;
    } elseif ($player['is_infielder']) {
        $infielders[] = $name;
    } elseif ($player['is_outfielder']) {
        $outfielders[] = $name;
    }
    $total_players++;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Team</title>
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
        .team-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
        }
        h3 {
            color: #333;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin: 5px 0;
        }
        .center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="team-container">
        <h2>Your Team</h2>

        <h3>Catchers</h3>
        <ul>
            <?php foreach ($catchers as $catcher): ?>
                <li><?= $catcher ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Infielders</h3>
        <ul>
            <?php foreach ($infielders as $infielder): ?>
                <li><?= $infielder ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Outfielders</h3>
        <ul>
            <?php foreach ($outfielders as $outfielder): ?>
                <li><?= $outfielder ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Pitchers</h3>
        <ul>
            <?php foreach ($pitchers as $pitcher): ?>
                <li><?= $pitcher ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Draft Picks</h3>
        <ul>
            <?php foreach ($draft_picks_by_year as $year => $picks): ?>
                <li><strong><?= htmlspecialchars($year) ?></strong></li>
                <ul>
                    <?php
                    $own_picks = array_filter($picks, function($pick) use ($user_team) {
                        return $pick['team_id'] == $user_team['team_id'];
                    });

                    if (!empty($own_picks)) {
                        echo '<li>' . format_draft_picks($own_picks) . '</li>';
                    }
                    ?>
                </ul>
            <?php endforeach; ?>
        </ul>

        <p>Total Players: <?= $total_players ?>, Total No Cards: <?= $total_no_cards ?></p>
    </div>

    <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>

