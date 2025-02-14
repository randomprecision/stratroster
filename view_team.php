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

// Fetch the user's team ID and team name
$user_stmt = $db->prepare('SELECT t.id as team_id, t.team_name as team_name
                           FROM users u
                           JOIN teams t ON u.team_id = t.id
                           WHERE u.id = ?');
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
$team_stmt = $db->query('SELECT id, team_name FROM teams');
$team_names = [];
while ($team = $team_stmt->fetch(PDO::FETCH_ASSOC)) {
    $team_names[$team['id']] = $team['team_name'];
}

$draft_picks_stmt = $db->prepare('SELECT draft_picks.*,
                                  original_teams.team_name AS original_team_name
                                  FROM draft_picks
                                  JOIN teams ON draft_picks.team_id = teams.id
                                  LEFT JOIN teams original_teams ON draft_picks.original_team_id = original_teams.id
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
function format_draft_picks($picks, $team_names) {
    $ranges = [];
    $current_range = [];
    $current_team_name = '';

    foreach ($picks as $i => $pick) {
        $original_team_name = $pick['original_team_name'] ?? $team_names[$pick['team_id']] ?? 'Unknown';

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

    // Apply boldface if player.majors is set
    if (!empty($player['majors'])) {
        $name = "<strong>$name</strong>";
    }

    // Apply red text if player.cut is set
    if (!empty($player['cut'])) {
        $name = "<span style='color:red'>$name</span>";
    }

    $mlb_team = htmlspecialchars($player['team']); // Fetch the MLB team

    if ($player['is_pitcher']) {
        $pitchers[] = ['name' => $name, 'team' => $mlb_team];
    } elseif ($player['is_catcher']) {
        $catchers[] = ['name' => $name, 'team' => $mlb_team];
    } elseif ($player['is_infielder']) {
        $infielders[] = ['name' => $name, 'team' => $mlb_team];
    } elseif ($player['is_outfielder']) {
        $outfielders[] = ['name' => $name, 'team' => $mlb_team];
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
            overflow: hidden; /* Prevent body scroll */
        }
        .team-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
            width: 90%;
            height: 80vh; /* Set the maximum height for the container */
            overflow-y: auto; /* Enable vertical scrolling */
        }
        .team-column {
            display: inline-block;
            width: 45%;
            vertical-align: top;
        }
        .team-column-left,
        .team-column-right {
            margin-bottom: 20px;
        }
        h3 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .center {
            text-align: center;
            margin-top: 20px;
        }
        @media screen and (max-width: 768px) {
            .team-column {
                display: block;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="team-container">
        <h2><?= htmlspecialchars($user_team['team_name']) ?></h2>

        <div class="team-column team-column-left">
            <h3>Catchers (<?= count($catchers) ?>)</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>MLB Team</th>
                </tr>
                <?php foreach ($catchers as $catcher): ?>
                    <tr>
                        <td><?= $catcher['name'] ?></td>
                        <td><?= $catcher['team'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h3>Infielders (<?= count($infielders) ?>)</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>MLB Team</th>
                </tr>
                <?php foreach ($infielders as $infielder): ?>
                    <tr>
                        <td><?= $infielder['name'] ?></td>
                        <td><?= $infielder['team'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h3>Outfielders (<?= count($outfielders) ?>)</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>MLB Team</th>
                </tr>
                <?php foreach ($outfielders as $outfielder): ?>
                    <tr>
                        <td><?= $outfielder['name'] ?></td>
                        <td><?= $outfielder['team'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="team-column team-column-right">
            <h3>Pitchers (<?= count($pitchers) ?>)</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>MLB Team</th>
                </tr>
                <?php foreach ($pitchers as $pitcher): ?>
                    <tr>
                        <td><?= $pitcher['name'] ?></td>
                        <td><?= $pitcher['team'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <p>Total Players: <?= $total_players ?>, Total No Cards: <?= $total_no_cards ?></p>

        <h3>Draft Picks</h3>
        <?php foreach ($draft_picks_by_year as $year => $picks): ?>
            <strong><?= htmlspecialchars($year) ?></strong>
            <div class="no-bullets">
                <?= format_draft_picks($picks, $team_names) ?>
                <?php if ($year == $draft_year && $no_cards_tradeable): ?>
                    <div>No Card Rights: <?= htmlspecialchars($user_team['y1nc']) ?></div>
                <?php elseif ($year == $draft_year + 1 && $no_cards_tradeable): ?>
                    <div>No Card Rights: <?= htmlspecialchars($user_team['y2nc']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>
