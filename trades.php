<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Fetch the background color
$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];

// Ensure the user is an admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Fetch all teams and sort them alphabetically by team name
$teams_stmt = $db->query('SELECT id, team_name, y1nc, y2nc FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of team names by team ID
$team_names = [];
foreach ($teams as $team) {
    $team_names[$team['id']] = $team['team_name'];
}

// Function to log trades
function log_trade($team_a_id, $team_b_id, $team_a_players, $team_b_players, $team_a_draft_picks, $team_b_draft_picks) {
    global $db;
    $stmt = $db->prepare('INSERT INTO trade_log (team_a_id, team_b_id, team_a_players, team_b_players, team_a_draft_picks, team_b_draft_picks) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $team_a_id,
        $team_b_id,
        json_encode($team_a_players),
        json_encode($team_b_players),
        json_encode($team_a_draft_picks),
        json_encode($team_b_draft_picks)
    ]);
}

// Function to get recent trades
function get_recent_trades() {
    global $db;
    $stmt = $db->query('SELECT * FROM trade_log ORDER BY trade_date DESC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch player names and draft pick details
function fetch_trade_details($ids, $type) {
    global $db, $team_names;
    if ($type == 'player') {
        $stmt = $db->prepare('SELECT id, first_name, last_name FROM players WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($item) {
            return $item['first_name'] . ' ' . $item['last_name'];
        }, $details);
    } elseif ($type == 'draft_pick') {
        $stmt = $db->prepare('SELECT id, round, year, IFNULL(original_team_id, team_id) AS original_team_id FROM draft_picks WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($item) use ($team_names) {
            return $team_names[$item['original_team_id']] . ' ' . $item['year'] . ' #' . $item['round'];
        }, $details);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_trade'])) {
        // Perform the trade
        $team_a_id = $_POST['team_a'];
        $team_b_id = $_POST['team_b'];
        $team_a_players = isset($_POST['team_a_player']) ? $_POST['team_a_player'] : [];
        $team_b_players = isset($_POST['team_b_player']) ? $_POST['team_b_player'] : [];
        $team_a_draft_picks = isset($_POST['team_a_draft_pick']) ? $_POST['team_a_draft_pick'] : [];
        $team_b_draft_picks = isset($_POST['team_b_draft_pick']) ? $_POST['team_b_draft_pick'] : [];
        $team_a_no_card_rights = isset($_POST['team_a_no_card']) ? $_POST['team_a_no_card'] : [];
        $team_b_no_card_rights = isset($_POST['team_b_no_card']) ? $_POST['team_b_no_card'] : [];
        
        // Fetch the no_cards_tradeable value
        $tradeable_stmt = $db->query('SELECT no_cards_tradeable FROM league_properties LIMIT 1');
        $tradeable = $tradeable_stmt->fetch(PDO::FETCH_ASSOC);
        $no_cards_tradeable = $tradeable['no_cards_tradeable'];

        // Move players and draft picks between teams
        $move_players_stmt = $db->prepare('UPDATE players SET fantasy_team_id = ? WHERE id = ?');
        foreach ($team_a_players as $player_id) {
            $move_players_stmt->execute([$team_b_id, $player_id]);
        }
        foreach ($team_b_players as $player_id) {
            $move_players_stmt->execute([$team_a_id, $player_id]);
        }

        $move_draft_picks_stmt = $db->prepare('UPDATE draft_picks SET team_id = ?, original_team_id = IFNULL(original_team_id, ?) WHERE id = ?');
        foreach ($team_a_draft_picks as $pick_id) {
            $move_draft_picks_stmt->execute([$team_b_id, $team_a_id, $pick_id]);
        }
        foreach ($team_b_draft_picks as $pick_id) {
            $move_draft_picks_stmt->execute([$team_a_id, $team_b_id, $pick_id]);
        }

        $move_no_card_rights_stmt = $db->prepare('UPDATE teams SET y1nc = y1nc + ?, y2nc = y2nc + ? WHERE id = ?');

        foreach ($team_a_no_card_rights as $no_card_right) {
            if ($no_card_right === '1') {
                $move_no_card_rights_stmt->execute([-1, 0, $team_a_id]);
                $move_no_card_rights_stmt->execute([1, 0, $team_b_id]);
            } else {
                $move_no_card_rights_stmt->execute([0, -1, $team_a_id]);
                $move_no_card_rights_stmt->execute([0, 1, $team_b_id]);
    }
}

        // Log the trade
        log_trade($team_a_id, $team_b_id, $team_a_players, $team_b_players, $team_a_draft_picks, $team_b_draft_picks);

        // Confirmation message
        $confirmation_message = "Trade completed: Team A traded players and draft picks with Team B.";
    } else {
       
        // Prepare trade details for confirmation
        $team_a_id = $_POST['team_a'];
        $team_b_id = $_POST['team_b'];
        $team_a_players = isset($_POST['team_a_player']) ? $_POST['team_a_player'] : [];
        $team_b_players = isset($_POST['team_b_player']) ? $_POST['team_b_player'] : [];
        $team_a_draft_picks = isset($_POST['team_a_draft_pick']) ? $_POST['team_a_draft_pick'] : [];
        $team_b_draft_picks = isset($_POST['team_b_draft_pick']) ? $_POST['team_b_draft_pick'] : [];

        $team_a_players_names = array_map(function($player_id) use ($db) {
            $stmt = $db->prepare('SELECT first_name, last_name FROM players WHERE id = ?');
            $stmt->execute([$player_id]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            return htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
        }, $team_a_players);

        $team_b_players_names = array_map(function($player_id) use ($db) {
            $stmt = $db->prepare('SELECT first_name, last_name FROM players WHERE id = ?');
            $stmt->execute([$player_id]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            return htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
        }, $team_b_players);

        $team_a_draft_picks_details = array_map(function($pick_id) use ($db, $teams) {
            $stmt = $db->prepare('SELECT round, year, original_team_id FROM draft_picks WHERE id = ?');
            $stmt->execute([$pick_id]);
            $pick = $stmt->fetch(PDO::FETCH_ASSOC);
            return "Round {$pick['round']}, Year {$pick['year']} ({$teams[$pick['original_team_id']]})";
        }, $team_a_draft_picks);

        $team_b_draft_picks_details = array_map(function($pick_id) use ($db, $teams) {
            $stmt = $db->prepare('SELECT round, year, original_team_id FROM draft_picks WHERE id = ?');
            $stmt->execute([$pick_id]);
            $pick = $stmt->fetch(PDO::FETCH_ASSOC);
            return "Round {$pick['round']}, Year {$pick['year']} ({$teams[$pick['original_team_id']]})";
        }, $team_b_draft_picks);

        $team_a_name = htmlspecialchars($team_names[$team_a_id]);
        $team_b_name = htmlspecialchars($team_names[$team_b_id]);

        $confirmation_message = "You are about to trade " . implode(', ', $team_a_players_names) . " and " . implode(', ', $team_a_draft_picks_details) . " from $team_a_name to $team_b_name for " . implode(', ', $team_b_players_names) . " and " . implode(', ', $team_b_draft_picks_details) . ".";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Trade Players and Draft Picks</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: 'Lato', sans-serif;
        }
        .center {
            text-align: center;
            margin-top: 20px;
        }
        .team-section {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .team-header {
            font-size: 1.5em;
            margin-bottom: 10px;
            text-align: center;
        }
        .selectable-box {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            background-color: white; /* Set background color to white */
        }
        .selectable-box.selected {
            border-color: black; /* Add black border for selected items */
            background-color: rgba(255, 255, 0, 0.8); /* Use yellow with increased opacity */
        }
        .recent-trades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .recent-trades-table th, .recent-trades-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .recent-trades-table th {
            background-color: #f0f0f0;
        }
        .recent-trades-table td {
            background-color: white; /* Set the background color of the table data to white */
        }
        .team-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .button-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 20px;
        }
        .prepare-button {
            width: 150px;
            height: 40px;
            font-size: 1em;
        }
    </style>
</head>
<body>
    <h2>Trade Players and Draft Picks</h2>

    <form method="POST" id="tradeForm">
        <div class="team-container">
            <div class="team-section" id="team_a_section">
                <div class="team-header" id="team_a_header">Team A</div>
                <select name="team_a" id="team_a" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="team_a_players"></div>
                <div id="team_a_draft_picks"></div>
            </div>

            <div class="button-container">
                <button type="submit" class="prepare-button">Prepare Trade</button>
            </div>

            <div class="team-section" id="team_b_section">
                <div class="team-header" id="team_b_header">Team B</div>
                <select name="team_b" id="team_b" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="team_b_players"></div>
                <div id="team_b_draft_picks"></div>
            </div>
        </div>
    </form>
<div id="recent-trades-container">
    <h3>Recent Trades</h3>
    <table class="recent-trades-table" id="recent-trades-table">
            <thead>
                <tr>
                    <th>Trade ID</th>
                    <th>Team A</th>
                    <th>Team B</th>
                    <th>Team A Players</th>
                    <th>Team B Players</th>
                    <th>Team A Draft Picks</th>
                    <th>Team B Draft Picks</th>
                    <?php if ($no_cards_tradeable == 1): ?>
                        <th>NCR</th>
                    <?php endif; ?>
                    <th>Trade Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
                <tbody>
                    <?php foreach (get_recent_trades() as $trade): ?>
                        <tr>
                            <td><?= $trade['id'] ?></td>
                            <td><?= htmlspecialchars($team_names[$trade['team_a_id']]) ?></td>
                            <td><?= htmlspecialchars($team_names[$trade['team_b_id']]) ?></td>
                            <td><?= htmlspecialchars(implode(', ', fetch_trade_details(json_decode($trade['team_a_players'], true), 'player'))) ?></td>
                            <td><?= htmlspecialchars(implode(', ', fetch_trade_details(json_decode($trade['team_b_players'], true), 'player'))) ?></td>
                            <td><?= htmlspecialchars(implode(', ', fetch_trade_details(json_decode($trade['team_a_draft_picks'], true), 'draft_pick'))) ?></td>
                            <td><?= htmlspecialchars(implode(', ', fetch_trade_details(json_decode($trade['team_b_draft_picks'], true), 'draft_pick'))) ?></td>
                            <?php if ($no_cards_tradeable == 1): ?>
                                <td>
                                    <?php
                                    $team_a_nc_rights = json_decode($trade['team_a_no_card_rights'], true) ?? [];
                                    $team_b_nc_rights = json_decode($trade['team_b_no_card_rights'], true) ?? [];
                                    $nc_rights = array_merge($team_a_nc_rights, $team_b_nc_rights);
                                    echo htmlspecialchars(implode(', ', array_map(function($nc) {
                                        return $nc['num'] . ' (' . $nc['year'] . ')';
                                    }, $nc_rights)));
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($trade['trade_date']) ?></td>
                            <td><button onclick="rollbackTrade(<?= $trade['id'] ?>)">Rollback</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
    </table>
</div>
<p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
</body>
<script>
    const teams = <?= json_encode($teams) ?>.reduce((acc, team) => {
        acc[team.id] = team;
        return acc;
    }, {});

    const teamASelect = document.getElementById('team_a');
    const teamBSelect = document.getElementById('team_b');
    const teamAHeader = document.getElementById('team_a_header');
    const teamBHeader = document.getElementById('team_b_header');
    const teamAPlayersList = document.getElementById('team_a_players');
    const teamBPlayersList = document.getElementById('team_b_players');
    const teamADraftPicksList = document.getElementById('team_a_draft_picks');
    const teamBDraftPicksList = document.getElementById('team_b_draft_picks');
    const tradeForm = document.getElementById('tradeForm');

    teamASelect.addEventListener('change', function() {
        const teamName = teamASelect.selectedOptions[0].text;
        teamAHeader.textContent = teamName;
        fetchPlayersAndPicks(teamASelect.value, 'team_a');
    });

    teamBSelect.addEventListener('change', function() {
        const teamName = teamBSelect.selectedOptions[0].text;
        teamBHeader.textContent = teamName;
        fetchPlayersAndPicks(teamBSelect.value, 'team_b');
    });

function fetchPlayersAndPicks(teamId, team) {
    fetch(`fetch_team_data.php?team_id=${teamId}`)
        .then(response => response.json())
        .then(data => {
            const playersList = team === 'team_a' ? teamAPlayersList : teamBPlayersList;
            const draftPicksList = team === 'team_a' ? teamADraftPicksList : teamBDraftPicksList;

            // Sort players alphabetically by last name
            data.players.sort((a, b) => a.last_name.localeCompare(b.last_name));

            playersList.innerHTML = '';
            data.players.forEach(player => {
                const div = document.createElement('div');
                div.classList.add('selectable-box');
                div.dataset.id = player.id;
                div.dataset.type = 'player';
                div.dataset.team = team;
                let name = `${player.first_name} ${player.last_name}`;
                if (player.is_pitcher && player.throws === 'L') {
                    name += '*';
                } else if (!player.is_pitcher && player.bats === 'L') {
                    name += '*';
                } else if (!player.is_pitcher && player.bats === 'S') {
                    name += '@';
                }
                if (player.no_card === 1) {
                    name += '‡';
                }
                div.textContent = name;
                div.onclick = toggleSelection;
                playersList.appendChild(div);
            });

            draftPicksList.innerHTML = '';
            data.draft_picks.forEach(pick => {
                const div = document.createElement('div');
                div.classList.add('selectable-box');
                div.dataset.id = pick.id;
                div.dataset.type = 'draft_pick';
                div.dataset.team = team;
                const originalTeamName = teams[pick.original_team_id] ? teams[pick.original_team_id].team_name : teams[teamId].team_name;
                div.textContent = `Round ${pick.round}, Year ${pick.year} (${originalTeamName})`;
                div.onclick = toggleSelection;
                draftPicksList.appendChild(div);
            });

            // Display no_card rights
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            const noCardsList = team === 'team_a' ? teamADraftPicksList : teamBDraftPicksList; // Adjusted to match your layout
            for (let i = 0; i < data.y1nc; i++) {
                const div = document.createElement('div');
                div.classList.add('selectable-box');
                div.dataset.type = 'no_card';
                div.dataset.team = team;
                div.textContent = `${currentYear}: No Card Right`;
                div.onclick = toggleSelection;
                noCardsList.appendChild(div);
            }
            for (let i = 0; i < data.y2nc; i++) {
                const div = document.createElement('div');
                div.classList.add('selectable-box');
                div.dataset.type = 'no_card';
                div.dataset.team = team;
                div.textContent = `${nextYear}: No Card Right`;
                div.onclick = toggleSelection;
                noCardsList.appendChild(div);
            }
        })
        .catch(error => console.error('Error fetching data:', error));
}

    function toggleSelection(event) {
        const element = event.target;
        element.classList.toggle('selected');
        const selected = element.classList.contains('selected');
        const input = document.querySelector(`input[name="${element.dataset.team}_${element.dataset.type}[]"][value="${element.dataset.id}"]`);
        if (selected && !input) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `${element.dataset.team}_${element.dataset.type}[]`;
            hiddenInput.value = element.dataset.id;
            if (tradeForm) {
                tradeForm.appendChild(hiddenInput);
            } else {
                console.error('Form not found');
            }
        } else if (!selected && input) {
            if (tradeForm) {
                tradeForm.removeChild(input);
            } else {
                console.error('Form not found');
            }
        }
    }

    tradeForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent the default form submission

        // Build the confirmation message
        const teamAPlayers = Array.from(document.querySelectorAll('.selectable-box[data-team="team_a"].selected')).map(box => box.textContent).join(', ');
        const teamBPlayers = Array.from(document.querySelectorAll('.selectable-box[data-team="team_b"].selected')).map(box => box.textContent).join(', ');

        const confirmationMessage = `Are you sure you want to confirm this trade?\n\nTeam A: ${teamAPlayers}\n\nTeam B: ${teamBPlayers}`;

        if (confirm(confirmationMessage)) {
            // If confirmed, submit the form
            const confirmInput = document.createElement('input');
            confirmInput.type = 'hidden';
            confirmInput.name = 'confirm_trade';
            confirmInput.value = '1';
            tradeForm.appendChild(confirmInput);
            tradeForm.submit();
        }
    });

    function rollbackTrade(tradeId) {
        if (confirm('Are you sure you want to rollback this trade?')) {
            fetch(`rollback_trade.php?trade_id=${tradeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Trade rollback successful.');
                        location.reload();  // Refresh the page to reflect changes
                    } else {
                        alert('Failed to rollback trade.');
                    }
                })
                .catch(error => console.error('Error rolling back trade:', error));
        }
    }
</script>
</html>
