<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Fetch league properties
$league_stmt = $db->query('SELECT background_color, in_season, no_cards_tradeable, options FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];
$in_season = $league['in_season'];
$no_cards_tradeable = $league['no_cards_tradeable']; // Fetch no_cards_tradeable
$league_options = $league['options']; // Fetch league options

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the user's team ID and team name (do this BEFORE form handling)
$user_stmt = $db->prepare('SELECT t.id as team_id, t.team_name as team_name, t.y1nc, t.y2nc, t.options as team_options
                           FROM users u
                           JOIN teams t ON u.team_id = t.id
                           WHERE u.id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user_team = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_team) {
    echo "Team not found for the user.";
    exit;
}

// Initialize team options
$team_options = $user_team['team_options'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_changes'])) {
    try {
        $db->beginTransaction();

        // Fetch current options for the team (again, if needed)
        $options_stmt = $db->prepare('SELECT options FROM teams WHERE id = ?');
        $options_stmt->execute([$user_team['team_id']]);
        $team_options = $options_stmt->fetch(PDO::FETCH_ASSOC)['options'];

        $players_stmt = $db->prepare('SELECT id, majors FROM players WHERE fantasy_team_id = ?');
        $players_stmt->execute([$user_team['team_id']]);  // using team_id instead of user_id here
        $players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($players as $player) {
            $player_id = $player['id'];
            $majors = isset($_POST["majors_$player_id"]) ? $_POST["majors_$player_id"] : 0;
            $cut = isset($_POST["cut_$player_id"]) ? $_POST["cut_$player_id"] : 0;

            // Check if in-season and player is being removed from majors
            if ($in_season && $player['majors'] && !$majors) {
                $team_options++;
                $update_team_stmt = $db->prepare('UPDATE teams SET options = ? WHERE id = ?');
                $update_team_stmt->execute([$team_options, $user_team['team_id']]);
                echo "<script>alert('This move will constitute a roster option. You have " . ($league_options - $team_options) . " options remaining this season.');</script>";
            }

            $update_stmt = $db->prepare('UPDATE players SET majors = ?, cut = ? WHERE id = ?');
            $update_stmt->execute([$majors, $cut, $player_id]);
        }

        $db->commit();
        echo "<script>alert('Changes applied successfully.');</script>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<script>alert('Error applying changes: " . htmlspecialchars($e->getMessage()) . "');</script>";
    }
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

// Set the draft year
$today = new DateTime();
$first_saturday_in_march = new DateTime('first saturday of march ' . $today->format('Y'));
if ($today > $first_saturday_in_march) {
    $draft_year = $today->format('Y') + 1;
} else {
    $draft_year = $today->format('Y');
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

    if ($player['majors']) {
        $name = '<strong>' . $name . '</strong>';
    }

    if ($player['cut']) {
        $name = '<span style="color: red;"><strong>' . $player['first_name'] . ' ' . $player['last_name'] . '</strong></span>';
    } elseif (!$player['majors']) {
        $name = htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
    }

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

    $mlb_team = htmlspecialchars($player['team']);

    if ($player['is_pitcher']) {
        $pitchers[] = ['name' => $name, 'team' => $mlb_team, 'id' => $player['id'], 'majors' => $player['majors'], 'cut' => $player['cut']];
    } elseif ($player['is_catcher']) {
        $catchers[] = ['name' => $name, 'team' => $mlb_team, 'id' => $player['id'], 'majors' => $player['majors'], 'cut' => $player['cut']];
    } elseif ($player['is_infielder']) {
        $infielders[] = ['name' => $name, 'team' => $mlb_team, 'id' => $player['id'], 'majors' => $player['majors'], 'cut' => $player['cut']];
    } elseif ($player['is_outfielder']) {
        $outfielders[] = ['name' => $name, 'team' => $mlb_team, 'id' => $player['id'], 'majors' => $player['majors'], 'cut' => $player['cut']];
    }
    $total_players++;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Team</title>
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
        .bold-red {
            font-weight: bold;
            color: red;
        }
        .no-bullets {
            list-style-type: none;
            padding: 0;
        }
        @media screen and (max-width: 768px) {
            .team-column {
                display: block;
                width: 100%;
            }
        }
    </style>
    <script>
            function confirmOption(playerId, isMajors, playerName) {
                let optionsRemaining = <?= $league['options'] ?> - <?= $team_options ?>;
                if (<?= $in_season ?> && isMajors == 1) {
                    if (!confirm(`This move will constitute a roster option. You have ${optionsRemaining} options remaining this season.`)) {
                        document.getElementById(`majors_${playerId}`).checked = true;
                    }
                } else {
                    alert(`Adding player ${playerName} to majors.`);
                }
            }

            function toggleCut(playerId) {
                const majorsCheckbox = document.getElementById(`majors_${playerId}`);
                const cutCheckbox = document.getElementById(`cut_${playerId}`);

                if (majorsCheckbox && cutCheckbox) {
                    cutCheckbox.disabled = majorsCheckbox.checked;
                    majorsCheckbox.disabled = cutCheckbox.checked;
                }
            }
    </script>
</head>
<body>
    <div class="team-container">
        <h2><?= htmlspecialchars($user_team['team_name']) ?></h2>
        <form method="POST" action="manage_team.php">
            <div class="team-column team-column-left">
                <h3>Catchers (<?= count($catchers) ?>)</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>MLB Team</th>
                        <th>Majors</th>
                        <?php if (!$in_season): ?>
                            <th>Cut</th>
                        <?php endif; ?>
                    </tr>
                    <?php foreach ($catchers as $catcher): ?>
                        <tr>
                            <td><?= $catcher['name'] ?></td>
                            <td><?= $catcher['team'] ?></td>
                            <td>
                                <input type="checkbox" id="majors_<?= $catcher['id'] ?>" name="majors_<?= $catcher['id'] ?>" value="1" <?= $catcher['majors'] ? 'checked' : '' ?> onchange="toggleCut(<?= $catcher['id'] ?>); confirmOption(<?= $catcher['id'] ?>, this.checked ? 1 : 0, '<?= htmlspecialchars($catcher['name']) ?>')">
                            </td>
                            <?php if (!$in_season): ?>
                                <td>
                                    <input type="checkbox" id="cut_<?= $catcher['id'] ?>" name="cut_<?= $catcher['id'] ?>" value="1" class="bold-red" <?= $catcher['cut'] ? 'checked' : '' ?> onchange="toggleCut(<?= $catcher['id'] ?>);">
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <h3>Infielders (<?= count($infielders) ?>)</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>MLB Team</th>
                        <th>Majors</th>
                        <?php if (!$in_season): ?>
                            <th>Cut</th>
                        <?php endif; ?>
                    </tr>
                    <?php foreach ($infielders as $infielder): ?>
                        <tr>
                            <td><?= $infielder['name'] ?></td>
                            <td><?= $infielder['team'] ?></td>
                            <td>
                                <input type="checkbox" id="majors_<?= $infielder['id'] ?>" name="majors_<?= $infielder['id'] ?>" value="1" <?= $infielder['majors'] ? 'checked' : '' ?> onchange="toggleCut(<?= $infielder['id'] ?>); confirmOption(<?= $infielder['id'] ?>, this.checked ? 1 : 0, '<?= htmlspecialchars($infielder['name']) ?>')">
                            </td>
                            <?php if (!$in_season): ?>
                                <td>
                                    <input type="checkbox" id="cut_<?= $infielder['id'] ?>" name="cut_<?= $infielder['id'] ?>" value="1" class="bold-red" <?= $infielder['cut'] ? 'checked' : '' ?> onchange="toggleCut(<?= $infielder['id'] ?>);">
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="team-column team-column-right">
                <h3>Outfielders (<?= count($outfielders) ?>)</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>MLB Team</th>
                        <th>Majors</th>
                        <?php if (!$in_season): ?>
                            <th>Cut</th>
                        <?php endif; ?>
                    </tr>
                        <?php foreach ($outfielders as $outfielder): ?>
                        <tr>
                            <td><?= $outfielder['name'] ?></td>
                            <td><?= $outfielder['team'] ?></td>
                            <td>
                                <input type="checkbox" id="majors_<?= $outfielder['id'] ?>" name="majors_<?= $outfielder['id'] ?>" value="1" <?= $outfielder['majors'] ? 'checked' : '' ?> onchange="toggleCut(<?= $outfielder['id'] ?>); confirmOption(<?= $outfielder['id'] ?>, this.checked ? 1 : 0, '<?= htmlspecialchars($outfielder['name']) ?>')">
                            </td>
                            <?php if (!$in_season): ?>
                                <td>
                                    <input type="checkbox" id="cut_<?= $outfielder['id'] ?>" name="cut_<?= $outfielder['id'] ?>" value="1" class="bold-red" <?= $outfielder['cut'] ? 'checked' : '' ?> onchange="toggleCut(<?= $outfielder['id'] ?>);">
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                </table>
                </div>
                <h3>Pitchers (<?= count($pitchers) ?>)</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>MLB Team</th>
                        <th>Majors</th>
                        <?php if (!$in_season): ?>
                            <th>Cut</th>
                        <?php endif; ?>
                    </tr>
                    <?php foreach ($pitchers as $pitcher): ?>
                        <tr>
                            <td><?= $pitcher['name'] ?></td>
                            <td><?= $pitcher['team'] ?></td>
                            <td>
                                <input type="checkbox" id="majors_<?= $pitcher['id'] ?>" name="majors_<?= $pitcher['id'] ?>" value="1" <?= $pitcher['majors'] ? 'checked' : '' ?> onchange="toggleCut(<?= $pitcher['id'] ?>); confirmOption(<?= $pitcher['id'] ?>, this.checked ? 1 : 0, '<?= htmlspecialchars($pitcher['name']) ?>')">
                            </td>
                            <?php if (!$in_season): ?>
                                <td>
                                    <input type="checkbox" id="cut_<?= $pitcher['id'] ?>" name="cut_<?= $pitcher['id'] ?>" value="1" class="bold-red" <?= $pitcher['cut'] ? 'checked' : '' ?> onchange="toggleCut(<?= $pitcher['id'] ?>);">
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="center">
                <button type="submit" name="apply_changes" onclick="return confirm('Are you sure you want to apply the changes?');">Apply Changes</button>
            </div>
        </form>

        <p>Total Players: <?= $total_players ?>, Total No Cards: <?= $total_no_cards ?></p>

        <h3>Draft Picks</h3>
        <?php foreach ($draft_picks_by_year as $year => $picks): ?>
            <strong><?= htmlspecialchars($year) ?></strong>
            <div class="no-bullets">
                <?= format_draft_picks($picks, $team_names) ?>
                <?php if ($no_cards_tradeable == 1): ?>
                    <?php if ($year == $draft_year): ?>
                        <div>No Card Rights: <?= htmlspecialchars($user_team['y1nc']) ?></div>
                    <?php elseif ($year == $draft_year + 1): ?>
                        <div>No Card Rights: <?= htmlspecialchars($user_team['y2nc']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>
