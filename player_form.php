<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the user details
$user_id = $_SESSION['user_id'];
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Check if the user has an assigned team
if ($user['team_id'] === null) {
    $error = "You must be assigned to a team to access the player form.";
}

// Define MLB teams
$mlb_teams = [
    "AL East" => ["BAL", "BOS", "NYA", "TBA", "TOR"],
    "AL Central" => ["CHA", "CLE", "DET", "KCA", "MIN"],
    "AL West" => ["HOU", "LAA", "OAK", "SEA", "TEX"],
    "NL East" => ["ATL", "MIA", "NYN", "PHI", "WAS"],
    "NL Central" => ["CHN", "CIN", "MIL", "PIT", "STL"],
    "NL West" => ["ARI", "COL", "LAN", "SDN", "SFN"],
    "Other" => ["IL"] // "IL" for players who got cards in both leagues
];

// Handle form submission if the user has a team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($error)) {
    if (isset($_POST['upload_csv'])) {
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
                    $no_card = isset($data[6]) && $data[6] == '1' ? 1 : 0; // Default to 0 if not set
                    $fantasy_team_id = $user['team_id'];

                    // Set position flags
                    $is_catcher = $position === 'C' ? 1 : 0;
                    $is_infielder = $position === 'IF' ? 1 : 0;
                    $is_outfielder = $position === 'OF' ? 1 : 0;
                    $is_pitcher = $position === 'P' ? 1 : 0;

                    // Insert player into the database
                    $insert_player_stmt = $db->prepare('INSERT INTO players (first_name, last_name, team, bats, throws, is_catcher, is_infielder, is_outfielder, is_pitcher, no_card, fantasy_team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $insert_player_stmt->execute([$first_name, $last_name, $team, $bats, $throws, $is_catcher, $is_infielder, $is_outfielder, $is_pitcher, $no_card, $fantasy_team_id]);
                }
                if (!isset($error)) {
                    $success = "CSV uploaded and players added successfully.";
                }
                fclose($handle);
            }
        } else {
            $error = "Error uploading CSV file.";
        }
    } else {
        // Manual player submission logic
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $team = $_POST['team'];
        $bats = $_POST['bats'];
        $throws = $_POST['throws'];
        $position = $_POST['position'];
        $no_card = isset($_POST['no_card']) ? 1 : 0;
        $fantasy_team_id = $user['team_id'];  // Assign the fantasy_team_id from the user's team

        // Set position flags
        $is_catcher = $position === 'C' ? 1 : 0;
        $is_infielder = $position === 'IF' ? 1 : 0;
        $is_outfielder = $position === 'OF' ? 1 : 0;
        $is_pitcher = $position === 'P' ? 1 : 0;

        // Insert new player into the database
        $insert_player_stmt = $db->prepare('INSERT INTO players (first_name, last_name, team, bats, throws, is_catcher, is_infielder, is_outfielder, is_pitcher, no_card, fantasy_team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $inserted = $insert_player_stmt->execute([$first_name, $last_name, $team, $bats, $throws, $is_catcher, $is_infielder, $is_outfielder, $is_pitcher, $no_card, $fantasy_team_id]);

        // Check if insert was successful
        if ($inserted) {
            $success = "New player " . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . " added successfully.";
        } else {
            $error = "Failed to add new player. Please try again.";
        }
    }
}

// Fetch the user's team roster
$roster_stmt = $db->prepare('SELECT * FROM players WHERE fantasy_team_id = ? ORDER BY last_name');
$roster_stmt->execute([$user['team_id']]);
$roster = $roster_stmt->fetchAll(PDO::FETCH_ASSOC);

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
            font-family: monospace;
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
    </style>
    <script>
        function populateForm(player) {
            document.getElementById('first_name').value = player.first_name;
            document.getElementById('last_name').value = player.last_name;
            document.getElementById('team').value = player.team;
            document.getElementById('bats').value = player.bats;
            document.getElementById('throws').value = player.throws;
            document.getElementById('position').value = player.position;
            document.getElementById('no_card').checked = player.no_card === 1;
            document.getElementById('submit-btn').innerText = 'Add/Edit Player';
        }

        function loadRoster(roster) {
            roster.forEach(player => {
                const li = document.createElement('li');
                li.innerText = formatPlayerName(player);
                li.onclick = () => populateForm(player);
                let position = '';
                if (player.is_catcher) position = 'C';
                else if (player.is_infielder) position = 'IF';
                else if (player.is_outfielder) position = 'OF';
                else if (player.is_pitcher) position = 'P';
                document.getElementById(position).appendChild(li);
            });
        }

        function formatPlayerName(player) {
            let name = player.first_name + ' ' + player.last_name;
            if (player.bats === 'L' && !player.is_pitcher || (player.is_pitcher && player.throws === 'L')) {
                name += '*';
            }
            if (player.bats === 'S' && !player.is_pitcher) {
                name += '@';
            }
            return name;
        }

        function toggleRequired() {
            const csvFile = document.getElementById('csv_file');
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const team = document.getElementById('team');
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

        document.addEventListener('DOMContentLoaded', () => {
            const roster = <?= json_encode($roster) ?>;
            loadRoster(roster);
        });
    </script>
</head>
<body>
    <h2>Player Form</h2>

    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php elseif (isset($success)): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" enctype="multipart/form-data">
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required><br><br>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required><br><br>
            <label for="team">Current Year Team:</label>
            <select id="team" name="team" required>
                <?php foreach ($mlb_teams as $division => $teams): ?>
                    <optgroup label="<?= $division ?>">
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= $team ?>"><?= $team ?></option>
                        <?php endforeach; ?>
                    </optgroup>
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
                <option value="P">P</option>
            </select><br><br>

            <label for="no_card">No Card:</label>
            <input type="checkbox" id="no_card" name="no_card"><br><br>

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
                    <li><strong>MLB Team:</strong> Player's MLB team (e.g., NYA)</li>
                    <li><strong>Position:</strong> C, IF, OF, P</li>
                    <li><strong>Bats:</strong> L, R, S</li>
                    <li><strong>Throws:</strong> L, R</li>
                    <li><strong>No Card:</strong> 1 if the player has no card, else 0</li>
                </ul>
            </div>
        </form>

        <div class="roster-box">
            <ul class="roster-list">
                <h3>Catchers</h3>
                <ul id="C"></ul>
                <h3>Infielders</h3>
                <ul id="IF"></ul>
                <h3>Outfielders</h3>
                <ul id="OF"></ul>
                <h3>Pitchers</h3>
                <ul id="P"></ul>
            </ul>
        </div>
    </div>
    <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>

