<?php
// player_form.php - form for managers to add players to their teams

session_start();
$db = new PDO("sqlite:./stratroster.db");
$db->exec("PRAGMA busy_timeout = 5000");  // 5 seconds

// Fetch the background color
$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];
$league_stmt->closeCursor();  // Close cursor here

if (!isset($_SESSION['user_id'])) {
    die("User is not logged in.");
}

$user_id = $_SESSION['user_id'];
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_stmt->closeCursor();  // Close cursor here

$team_assigned = isset($user['team_id']) && $user['team_id'] !== null && $user['team_id'] > 0;
$user_has_team = $team_assigned ? true : false;

// Fetch all teams for admin users
$teams_stmt = $db->query('SELECT id, team_name FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
$teams_stmt->closeCursor();  // Close cursor here

// Fetch MLB teams
$mlb_teams_stmt = $db->query('SELECT * FROM mlb_teams ORDER BY team_name');
$mlb_teams = $mlb_teams_stmt->fetchAll(PDO::FETCH_ASSOC);
$mlb_teams_stmt->closeCursor();  // Close cursor here
// Handle form submission for deleting a player
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_player'])) {
    $delete_player_id = $_POST['delete_player_id'];

    // Verify if the ID is set and valid
    if (!empty($delete_player_id)) {
        // Delete the player from the database
        $delete_player_stmt = $db->prepare('DELETE FROM players WHERE id = ?');
        $deleted = $delete_player_stmt->execute([$delete_player_id]);
        $delete_player_stmt->closeCursor();  // Close cursor here
        $db = null; // Close the database connection

        // Check if delete was successful
        if ($deleted) {
            echo "Player ID $delete_player_id deleted successfully.";
            exit;
        } else {
            echo "Failed to delete player.";
            exit;
        }
    } else {
        echo "Invalid player ID.";
        exit;
    }
}

// Handle form submission for adding/editing a player
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['delete_player'])) {
    if (isset($_POST['upload_csv'])) {
        // CSV upload logic remains the same
    } else {
        // Manual player submission logic with validation and debugging
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $team = trim($_POST['mlb_team']);
        $bats = trim($_POST['bats']);
        $throws = trim($_POST['throws']);
        $position = trim($_POST['position']);
        $no_card = isset($_POST['no_card']) ? 1 : 0;
        $fantasy_team_id = $user['team_id'];  // Assign the fantasy_team_id from the user's team

        // Debug and validate required fields
        if (empty($first_name) || empty($last_name) || empty($team) || empty($bats) || empty($throws) || empty($position)) {
            $error = "All fields are required.";
            echo "Debug Info: First Name: $first_name, Last Name: $last_name, Team: $team, Bats: $bats, Throws: $throws, Position: $position, No Card: $no_card, Fantasy Team ID: $fantasy_team_id";
        } else {
            // Set position flags
            $is_catcher = $position === 'C' ? 1 : 0;
            $is_infielder = $position === 'IF' ? 1 : 0;
            $is_outfielder = $position === 'OF' ? 1 : 0;
            $is_pitcher = $position === 'P' ? 1 : 0;
            $is_dh = $position === 'DH' ? 1 : 0; // Add this flag for DH

            if (isset($_POST['edit_player_id']) && !empty($_POST['edit_player_id'])) {
                $edit_player_id = $_POST['edit_player_id'];

                // Update existing player
                $update_player_stmt = $db->prepare('UPDATE players SET first_name = ?, last_name = ?, team = ?, bats = ?, throws = ?, is_catcher = ?, is_infielder = ?, is_outfielder = ?, is_pitcher = ?, is_dh = ?, no_card = ? WHERE id = ?');
                $updated = $update_player_stmt->execute([$first_name, $last_name, $team, $bats, $throws, $is_catcher, $is_infielder, $is_outfielder, $is_pitcher, $is_dh, $no_card, $edit_player_id]);
                $update_player_stmt->closeCursor();  // Close cursor here
                $db = null; // Close the database connection
                
                echo "Debug Info: Updated Player - First Name: $first_name, Last Name: $last_name, Team: $team, Bats: $bats, Throws: $throws, Position: $position, No Card: $no_card, Fantasy Team ID: $fantasy_team_id, Edit Player ID: $edit_player_id";

                if ($updated) {
                    $success = "Player " . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . " updated successfully.";
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?team=' . $fantasy_team_id); // Redirect to reload the page
                    exit;
                } else {
                    $error = "Failed to update player. Please try again.";
                }
            } else {
                // Insert new player into the database
                $insert_player_stmt = $db->prepare('INSERT INTO players (first_name, last_name, team, bats, throws, is_catcher, is_infielder, is_outfielder, is_pitcher, is_dh, no_card, fantasy_team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $inserted = $insert_player_stmt->execute([$first_name, $last_name, $team, $bats, $throws, $is_catcher, $is_infielder, $is_outfielder, $is_pitcher, $is_dh, $no_card, $fantasy_team_id]);
                $insert_player_stmt->closeCursor();  // Close cursor here
                $db = null; // Close the database connection
                
                echo "Debug Info: Inserted Player - First Name: $first_name, Last Name: $last_name, Team: $team, Bats: $bats, Throws: $throws, Position: $position, No Card: $no_card, Fantasy Team ID: $fantasy_team_id";

                if ($inserted) {
                    $success = "New player " . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . " added successfully.";
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?team=' . $fantasy_team_id); // Redirect to reload the page
                    exit;
                } else {
                    $error = "Failed to add new player. Please try again.";
                }
            }
        }
    }
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $csv_file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($csv_file, 'r');

        // Read the first line
        $header = fgets($handle);

        // Strip BOM if present
        $bom = pack('H*', 'EFBBBF');
        $header = preg_replace("/^$bom/", '', $header);

        // Parse the CSV header
        $header = str_getcsv($header);
        // Validate CSV format
        $expected_columns = ['First Name', 'Last Name', 'MLB Team', 'Position', 'Bats', 'Throws', 'No Card'];

        // Check for header column mismatch
        $missing_columns = array_diff($expected_columns, $header);
        if (!empty($missing_columns)) {
            $error = "Invalid CSV format. Missing columns: " . implode(', ', $missing_columns);
            fclose($handle);
        } else {
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // Validate row length
                if (count($data) < count($expected_columns) - 1) { // Allow missing 'No Card'
                    $error = "Invalid CSV format. Each row must have at least " . (count($expected_columns) - 1) . " columns.";
                    fclose($handle);
                    break;
                }

                $first_name = $data[0];
                $last_name = $data[1];
                $team = $data[2];
                $position = $data[3];
                $bats = $data[4];
                $throws = $data[5];
                $no_card = isset($data[6]) && strtoupper($data[6]) == 'Y' ? 1 : 0; // Translate 'Y' to 1, default to 0 if not set
                $fantasy_team_id = $user['team_id'];

                // Set position flags
                $is_catcher = $position === 'C' ? 1 : 0;
                $is_infielder = $position === 'IF' ? 1 : 0;
                $is_outfielder = $position === 'OF' ? 1 : 0;
                $is_pitcher = $position === 'P' ? 1 : 0;
                $is_dh = $position === 'DH' ? 1 : 0; // Add this flag for DH

                // Insert player into the database
                $insert_player_stmt = $db->prepare('INSERT INTO players (first_name, last_name, team, bats, throws, is_catcher, is_infielder, is_outfielder, is_pitcher, is_dh, no_card, fantasy_team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $inserted = $insert_player_stmt->execute([$first_name, $last_name, $team, $bats, $throws, $is_catcher, $is_infielder, $is_outfielder, $is_pitcher, $is_dh, $no_card, $fantasy_team_id]);
                $insert_player_stmt->closeCursor();  // Close cursor here

                if ($inserted) {
                    $success = "CSV uploaded and players added successfully.";
                } else {
                    $error = "Failed to add new player. Please try again.";
                    fclose($handle);
                    break;
                }
            }
            fclose($handle);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?team=' . $fantasy_team_id); // Redirect to reload the page
            exit;
        }
    } else {
        $error = "Error uploading CSV file.";
    }
}

// Fetch the user's team roster again to refresh the list
$team_id = isset($_GET['team']) ? $_GET['team'] : $user['team_id'];
$roster_stmt = $db->prepare('SELECT * FROM players WHERE fantasy_team_id = ? ORDER BY last_name');
$roster_stmt->execute([$team_id]);
$roster = $roster_stmt->fetchAll(PDO::FETCH_ASSOC);
$roster_stmt->closeCursor(); // Close cursor here

function formatPlayerName($player) {
    $name = htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
    if ($player['bats'] == 'L' && !$player['is_pitcher'] || ($player['is_pitcher'] && $player['throws'] == 'L')) {
        $name .= '*';
    }
    if ($player['bats'] == 'S' && !$player['is_pitcher']) {
        $name .= '@';
    }
    return $name;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Player Form</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: 'Lato', sans-serif;
        }
        .center {
            text-align: center;
            margin-top: 20px;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        .form-container {
            display: flex;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
        }
        .roster-box {
            margin-left: 20px;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .roster-list {
            list-style: none;
            padding: 0;
        }
        .roster-list h3 {
            margin: 10px 0 5px;
        }
        .roster-list li {
            margin: 5px 0;
            cursor: pointer;
        }
        .instructions-box {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 10px;
            background-color: #f0f0f0;
        }
        .roster-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .roster-box th, .roster-box td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .center {
            text-align: center;
        }
        .roster-box tr {
            cursor: pointer;
        }
        .roster-box tr:hover {
            background-color: #f1f1f1; /* Optional: Adds a highlight effect on hover */
        }
        .header-row, .non-selectable {
            user-select: none;
        }
    </style>
</head>
<body>
    <h2>Player Form</h2>

    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php elseif (isset($success)): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>
<div class="form-container">
    <?php if ($user['is_admin']): ?>
        <form method="GET" id="teamSelectForm">
            <label for="team">Select Team:</label>
            <select id="team" name="team" onchange="document.getElementById('teamSelectForm').submit()">
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>" <?= $team['id'] == $team_id ? 'selected' : '' ?>><?= htmlspecialchars($team['team_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required><br><br>

        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required><br><br>

        <label for="mlb_team">MLB Team:</label>
        <select id="mlb_team" name="mlb_team" required>
            <?php foreach ($mlb_teams as $team): ?>
                <option value="<?= htmlspecialchars($team['team_name']) ?>"><?= htmlspecialchars($team['team_name']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="bats">Bats:</label>
        <select id="bats" name="bats" required>
            <option value="R">R</option>
            <option value="L">L</option>
            <option value="S">S</option>
        </select><br><br>

        <label for="throws">Throws:</label>
        <select id="throws" name="throws" required>
            <option value="R">R</option>
            <option value="L">L</option>
        </select><br><br>

        <label for="position">Position:</label>
        <select id="position" name="position" required>
            <option value="C">C</option>
            <option value="IF">IF</option>
            <option value="OF">OF</option>
            <option value="DH">DH</option>
            <option value="P">P</option>
        </select><br><br>

        <label for="no_card">No Card:</label>
        <input type="checkbox" id="no_card" name="no_card"><br><br>

        <input type="hidden" id="edit_player_id" name="edit_player_id" value="">

        <button type="submit" id="submit-btn">Add Player</button><br><br>

        <label for="csv_file">Upload CSV of Players:</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" onchange="toggleRequired()"><br><br>
        <button type="submit" name="upload_csv">Upload CSV</button><br><br>

        <div class="instructions-box">
            <strong>CSV Format Instructions:</strong>
            <p>Please format your CSV file with the following columns:</p>
            <ul>
                <li><strong>First Name:</strong> Player's first name (e.g., John)</li>
                <li><strong>Last Name:</strong> Player's last name (e.g., Doe)</li>
                <li><strong>MLB Team:</strong> Player's MLB team (e.g., NYA). Use IL for multi-card players.</li>
                <li><strong>Position:</strong> C, IF, OF, DH, P</li>
                <li><strong>Bats:</strong> L, R, S</li>
                <li><strong>Throws:</strong> L, R</li>
                <li><strong>No Card:</strong> Y for No Card, else leave blank</li>
            </ul>
        </div>
    <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
    </form>
    <!-- Delete Form -->
    <form id="deleteForm" method="POST">
        <input type="hidden" id="delete_player_id" name="delete_player_id">
        <button type="button" id="deleteButton" style="background-color: red; color: white;">Delete Player</button>
    </form>

    <!-- Rest of the roster box code -->
    <div class="roster-box">
        <table>
            <thead>
                <tr>
                    <th class="non-selectable">Player Name</th>
                    <th class="non-selectable">MLB Team</th>
                </tr>
            </thead>
            <tbody id="non_pitchers">
                <tr class="header-row">
                    <td colspan="2"><b>All Players (Non-Pitchers)</b></td>
                </tr>
            </tbody>
            <tbody id="pitchers">
                <tr class="header-row">
                    <td colspan="2"><b>Pitchers</b></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userHasTeam = <?= json_encode($user_has_team) ?>;

    // Display error message if user has no team assigned
    if (!userHasTeam) {
        const errorMessage = document.createElement('h2');
        errorMessage.style.color = 'red';
        errorMessage.textContent = 'No team assigned. Changes will not be saved.';
        document.body.insertBefore(errorMessage, document.body.firstChild);
    }

    document.getElementById('deleteButton').addEventListener('click', function () {
        if (!userHasTeam) {
            alert('No team assigned. Changes will not be saved.');
            return;
        }

        const deletePlayerId = document.getElementById('delete_player_id').value;
        const firstName = document.getElementById('first_name').value;
        const lastName = document.getElementById('last_name').value;

        // Perform AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'player_form.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                if (xhr.responseText.includes('successfully')) {
                    alert(`Player ${firstName} ${lastName} deleted successfully.`);
                    location.reload(); // Reload the page to refresh the roster list
                } else {
                    alert('Failed to delete player.');
                }
            }
        };
        xhr.send('delete_player=1&delete_player_id=' + deletePlayerId);
    });

    const roster = <?= json_encode($roster) ?>;
    loadRoster(roster);
});
function loadRoster(roster) {
    const positions = ['non_pitchers', 'pitchers'];

    // Clear existing lists
    positions.forEach(position => {
        const element = document.getElementById(position);
        if (element) {
            element.innerHTML = '';
        }
    });

    const nonPitchers = []; // Collect non-pitchers separately
    const pitchers = []; // Collect pitchers separately

    roster.forEach(player => {
        const tr = document.createElement('tr');
        const tdName = document.createElement('td');
        const tdTeam = document.createElement('td');
        tdName.innerText = formatPlayerName(player);
        tdTeam.innerText = player.team;
        tr.appendChild(tdName);
        tr.appendChild(tdTeam);
        tr.addEventListener('click', function () {
            populateForm(player);
        });

        if (player.is_pitcher) {
            pitchers.push({ player, tr });
        } else {
            nonPitchers.push({ player, tr });
        }
    });

    // Sort non-pitchers alphabetically by last name
    nonPitchers.sort((a, b) => a.player.last_name.localeCompare(b.player.last_name));
    nonPitchers.forEach(({ tr }) => {
        const element = document.getElementById('non_pitchers');
        if (element) {
            element.appendChild(tr);
        }
    });

    // Sort pitchers alphabetically by last name
    pitchers.sort((a, b) => a.player.last_name.localeCompare(b.player.last_name));
    pitchers.forEach(({ tr }) => {
        const element = document.getElementById('pitchers');
        if (element) {
            element.appendChild(tr);
        }
    });
}

function formatPlayerName(player) {
    let name = player.first_name + ' ' + player.last_name;
    if (player.bats === 'L' && !player.is_pitcher || (player.is_pitcher && player.throws === 'L')) {
        name += ' *'; // Add space before the asterisk for left-handed bats/throws
    }
    if (player.bats === 'S' && !player.is_pitcher) {
        name += ' @'; // Add space before the at symbol for switch hitters
    }
    if (player.no_card === 1) {
        name += ' ‡'; // Add double cross symbol for no-card players
    }
    return name;
}

function populateForm(player) {
    document.getElementById('first_name').value = player.first_name;
    document.getElementById('last_name').value = player.last_name;
    // Ensure this matches the dropdown ID
    document.getElementById('mlb_team').value = player.team;
    document.getElementById('bats').value = player.bats;
    document.getElementById('throws').value = player.throws;
    document.getElementById('position').value = player.is_catcher ? 'C' : player.is_infielder ? 'IF' : player.is_outfielder ? 'OF' : player.is_dh ? 'DH' : player.is_pitcher ? 'P' : '';
    document.getElementById('no_card').checked = player.no_card === 1;
    document.getElementById('submit-btn').innerText = 'Update Player';
    document.getElementById('delete_player_id').value = player.id; // Set player ID for deletion
    document.getElementById('edit_player_id').value = player.id; // Set player ID for editing
}

function toggleRequired() {
    const csvFile = document.getElementById('csv_file');
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const team = document.getElementById('mlb_team');
    const bats = document.getElementById('bats');
    const throws = document.getElementById('throws');
    const position = document.getElementById('position');
    
    if (csvFile.files.length > 0) {
        firstName.required = false;
        lastName.required = false;
        team.required = false;
        bats.required = false;
        throws.required = false;
        position.required = false;
    } else {
        firstName.required = true;
        lastName.required = true;
        team.required = true;
        bats.required = true;
        throws.required = true;
        position.required = true;
    }
}
</script>

