<?php

// admin.php - the admin page for doing admin things

session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$team_assigned = isset($user['team_id']) && $user['team_id'] !== null && $user['team_id'] > 0;
$user_has_team = $team_assigned ? true : false;

$current_year = date('Y');

// Handle form submission for updating league properties
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['league_name']) && isset($_POST['draft_year']) && isset($_POST['draft_rounds'])) {
    $league_name = $_POST['league_name'];
    $draft_year = $_POST['draft_year'];
    $draft_rounds = $_POST['draft_rounds'];

    // Update league name
    $update_league_stmt = $db->prepare('UPDATE league_properties SET name = ?');
    $update_league_stmt->execute([$league_name]);
    $message = "League name updated successfully.";

    // Check and update draft year if needed
    if ($draft_year == $current_year + 1) {
        $highest_year_stmt = $db->query('SELECT MAX(year) AS highest_year FROM draft_picks');
        $highest_year = $highest_year_stmt->fetch(PDO::FETCH_ASSOC)['highest_year'];

        if ($highest_year == $current_year + 1) {
            // Increment year by one on all draft picks in the DB
            $db->exec('UPDATE draft_picks SET year = year + 1');
            $message .= " Draft year incremented by one.";
        } elseif ($highest_year == $current_year + 2) {
            $message .= " Draft picks already set to next calendar year.";
        }
    }

    // Update draft rounds
    $update_rounds_stmt = $db->prepare('UPDATE league_properties SET draft_rounds = ?');
    $update_rounds_stmt->execute([$draft_rounds]);
}

// Fetch the league name and draft rounds from league_properties table
$league_stmt = $db->query('SELECT name, draft_rounds FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch the list of teams
$teams_stmt = $db->query('SELECT * FROM teams');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the list of users
$users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for updating user team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_team'])) {
    $user_id = $_POST['user_id'];
    $team_id = $_POST['team_id'];

    // Allow setting team to none
    if ($team_id == 'none') {
        $update_stmt = $db->prepare('UPDATE users SET team_id = NULL WHERE id = ?');
        $update_stmt->execute([$user_id]);
    } else {
        $update_stmt = $db->prepare('UPDATE users SET team_id = ? WHERE id = ?');
        $update_stmt->execute([$team_id, $user_id]);
    }

    $message = "User team updated successfully.";

    // Refresh the users list after updating a team
    $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for editing or deleting a team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['edit_team_id']) || isset($_POST['delete_team_id']))) {
    if (isset($_POST['edit_team_id']) && isset($_POST['edit_team_name'])) {
        $edit_team_id = $_POST['edit_team_id'];
        $edit_team_name = $_POST['edit_team_name'];

        // Check if the team name already exists
        $check_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE team_name = ? AND id != ?');
        $check_stmt->execute([$edit_team_name, $edit_team_id]);
        $exists = $check_stmt->fetchColumn();

        if ($exists) {
            $message = "Team " . htmlspecialchars($edit_team_name) . " already exists.";
        } else {
            $update_team_stmt = $db->prepare('UPDATE teams SET team_name = ? WHERE id = ?');
            $update_team_stmt->execute([$edit_team_name, $edit_team_id]);
            $message = "Team name updated successfully.";
        }
    } elseif (isset($_POST['delete_team_id'])) {
        $delete_team_id = $_POST['delete_team_id'];
        $delete_team_stmt = $db->prepare('DELETE FROM teams WHERE id = ?');
        $delete_team_stmt->execute([$delete_team_id]);
        $message = "Team deleted successfully.";
    }

    // Refresh the teams list after modifying a team
    $teams_stmt = $db->query('SELECT * FROM teams');
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for updating user team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_team'])) {
    $user_id = $_POST['user_id'];
    $team_id = $_POST['team_id'];

    // Allow setting team to none
    if ($team_id == 'none') {
        $update_stmt = $db->prepare('UPDATE users SET team_id = NULL WHERE id = ?');
        $update_stmt->execute([$user_id]);
    } else {
        $update_stmt = $db->prepare('UPDATE users SET team_id = ? WHERE id = ?');
        $update_stmt->execute([$team_id, $user_id]);
    }

    $message = "User team updated successfully.";

    // Refresh the users list after updating a team
    $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for editing or deleting a team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['edit_team_id']) || isset($_POST['delete_team_id']))) {
    if (isset($_POST['edit_team_id']) && isset($_POST['edit_team_name'])) {
        $edit_team_id = $_POST['edit_team_id'];
        $edit_team_name = $_POST['edit_team_name'];

        // Check if the team name already exists
        $check_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE team_name = ? AND id != ?');
        $check_stmt->execute([$edit_team_name, $edit_team_id]);
        $exists = $check_stmt->fetchColumn();

        if ($exists) {
            $message = "Team " . htmlspecialchars($edit_team_name) . " already exists.";
        } else {
            $update_team_stmt = $db->prepare('UPDATE teams SET team_name = ? WHERE id = ?');
            $update_team_stmt->execute([$edit_team_name, $edit_team_id]);
            $message = "Team name updated successfully.";
        }
    } elseif (isset($_POST['delete_team_id'])) {
        $delete_team_id = $_POST['delete_team_id'];
        $delete_team_stmt = $db->prepare('DELETE FROM teams WHERE id = ?');
        $delete_team_stmt->execute([$delete_team_id]);
        $message = "Team deleted successfully.";
    }

    // Refresh the teams list after modifying a team
    $teams_stmt = $db->query('SELECT * FROM teams');
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for managing user details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manage_user_id']) && isset($_POST['manage_username']) && isset($_POST['manage_email'])) {
    $manage_user_id = $_POST['manage_user_id'];
    $manage_username = $_POST['manage_username'];
    $manage_email = $_POST['manage_email'];
    $manage_password = $_POST['manage_password'];
    $manage_is_admin = isset($_POST['manage_is_admin']) ? 1 : 0;

    if (empty($manage_password)) {
        // Update user details without changing the password
        $update_user_stmt = $db->prepare('UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?');
        $update_user_stmt->execute([$manage_username, $manage_email, $manage_is_admin, $manage_user_id]);
    } else {
        // Update user details including the password
        $update_user_stmt = $db->prepare('UPDATE users SET username = ?, email = ?, password = ?, is_admin = ? WHERE id = ?');
        $update_user_stmt->execute([$manage_username, $manage_email, password_hash($manage_password, PASSWORD_BCRYPT), $manage_is_admin, $manage_user_id]);
    }

    $message = "User details updated successfully.";

    // Refresh the users list after updating a user
    $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Handle form submission for creating a new team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['team_name'])) {
    $new_team_name = $_POST['team_name'];

    // Check if the team name already exists
    $check_team_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE team_name = ?');
    $check_team_stmt->execute([$new_team_name]);
    $exists = $check_team_stmt->fetchColumn();

    if ($exists) {
        $message = "Team " . htmlspecialchars($new_team_name) . " already exists.";
    } else {
        // Insert new team into the database
        $insert_team_stmt = $db->prepare('INSERT INTO teams (team_name) VALUES (?)');
        $insert_team_stmt->execute([$new_team_name]);
        $team_id = $db->lastInsertId();

        $message = "Team " . htmlspecialchars($new_team_name) . " has been added.";

        // Refresh the teams list after adding a new team
        $teams_stmt = $db->query('SELECT * FROM teams');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle form submission for creating a new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_username']) && isset($_POST['new_email']) && isset($_POST['new_password'])) {
    $new_username = $_POST['new_username'];
    $new_email = $_POST['new_email'];
    $new_password = $_POST['new_password'];
    $new_is_admin = isset($_POST['new_is_admin']) ? 1 : 0; // Capture the is_admin value

    // Check if the username or email already exists
    $check_user_stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
    $check_user_stmt->execute([$new_username, $new_email]);
    $exists = $check_user_stmt->fetchColumn();

    if ($exists) {
        $message = "Username or email already exists.";
    } else {
        // Insert new user into the database
        $insert_user_stmt = $db->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)');
        $insert_user_stmt->execute([$new_username, $new_email, password_hash($new_password, PASSWORD_BCRYPT), $new_is_admin]);
        $message = "New user " . htmlspecialchars($new_username) . " created successfully.";

        // Refresh the users list after adding a new user
        $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle form submission for resetting the draft and clearing recent trades
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_draft'])) {
    try {
        // Begin transaction
        $db->beginTransaction();

        // Clear the draft_picks table
        $db->exec('DELETE FROM draft_picks');

        // Clear the recent trades table
        $db->exec('DELETE FROM trades');

        // Fetch all teams
        $teams_stmt = $db->query('SELECT * FROM teams');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Insert draft picks for the next two years (10 rounds each year) for each team
        $current_year = date('Y');
        foreach ($teams as $team) {
            for ($year = $current_year; $year <= $current_year + 1; $year++) {
                for ($round = 1; $round <= 10; $round++) {
                    $insert_draft_pick_stmt = $db->prepare('INSERT INTO draft_picks (team_id, round, year) VALUES (?, ?, ?)');
                    $insert_draft_pick_stmt->execute([$team['id'], $round, $year]);
                }
            }
        }

        // Commit transaction
        $db->commit();

        $message = "Draft reset successfully and recent trades cleared.";
    } catch (PDOException $e) {
        // Rollback transaction if an error occurs
        $db->rollBack();
        $message = "Failed to reset draft: " . $e->getMessage();
    }
}
// Handle database initialization
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_init']) && $_POST['confirm_init'] === 'YES') {
    try {
        $current_user_id = $_SESSION['user_id'];

        // Begin transaction
        $db->beginTransaction();

        // Preserve the current user
        $current_user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $current_user_stmt->execute([$current_user_id]);
        $current_user = $current_user_stmt->fetch(PDO::FETCH_ASSOC);

        // List of tables to reset sequence
        $tables = ['teams', 'players', 'draft_picks'];

        // Drop and recreate the tables except for the current user
        $db->exec('DELETE FROM users WHERE id != ' . $current_user_id);
        foreach ($tables as $table) {
            $db->exec('DELETE FROM ' . $table);
            $db->exec("UPDATE SQLITE_SEQUENCE SET SEQ=0 WHERE NAME='$table';");
        }

        // Commit transaction
        $db->commit();

        $message = "Database initialized successfully. (you will be logged out)";

        // Log out the current user
        session_destroy();
        header('Location: login.php?message=' . urlencode($message));
        exit;
    } catch (PDOException $e) {
        // Rollback transaction if an error occurs
        $db->rollBack();
        $message = "Failed to initialize database: " . $e->getMessage();
    }
}

// Handle database backup
if (isset($_GET['backup']) && $_GET['backup'] == 'true') {
    $dbFile = '/var/www/stratroster/stratroster.db'; // Path to your SQLite database file
    $backupFile = 'stratroster_backup_' . date('Y-m-d_H-i-s') . '.db'; // Backup file name with timestamp

    if (file_exists($dbFile)) {
        error_log("File exists: $dbFile");
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($backupFile));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbFile));
        readfile($dbFile);
        exit;
    } else {
        $message = "Failed to find the database file.";
        error_log("File not found: $dbFile");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .form-container {
            margin-top: 20px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <h2>Admin Dashboard</h2>
    <?php if (isset($message)): ?>
        <p class="success"><?= $message ?></p>
    <?php endif; ?>

    <!-- User management table -->
    <table>
        <tr>
            <th>Username</th>
            <th>Team</th>
            <th>Action</th>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= $user['team_name'] ? htmlspecialchars($user['team_name']) : 'No Team' ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="update_user_team" value="1">
                        <select name="team_id">
                            <option value="none">No Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['id'] ?>" <?= $user['team_id'] == $team['id'] ? 'selected' : '' ?>><?= htmlspecialchars($team['team_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Additional admin functionalities -->
    <div class="form-container">
        <h3>Add New Team</h3>
        <form method="POST">
            <label for="new_team_name">Team Name:</label>
            <input type="text" id="new_team_name" name="team_name" required>
            <button type="submit">Add Team</button>
        </form>
    </div>
    <div class="form-container">
        <h3>Edit League Properties</h3>
        <form method="POST">
            <label for="league_name">League Name:</label>
            <input type="text" id="league_name" name="league_name" value="<?= htmlspecialchars($league['name']) ?>" required>

            <label for="draft_year">First Draft Year:</label>
            <input type="radio" id="current_year" name="draft_year" value="<?= $current_year ?>" checked>
            <label for="current_year"><?= $current_year ?></label>
            <input type="radio" id="next_year" name="draft_year" value="<?= $current_year + 1 ?>">
            <label for="next_year"><?= $current_year + 1 ?></label>

            <label for="draft_rounds">Draft Rounds:</label>
            <input type="number" id="draft_rounds" name="draft_rounds" value="<?= htmlspecialchars($league['draft_rounds']) ?>" min="1" max="20" required>

            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Manage Users</h3>
        <form method="POST">
            <label for="manage_user_id">Select User:</label>
            <select id="manage_user_id" name="manage_user_id" onchange="this.form.submit()">
                <option value="">Select a user</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if (isset($_POST['manage_user_id']) && $_POST['manage_user_id'] != ''): ?>
            <?php
            $manage_user_id = $_POST['manage_user_id'];
            $selected_user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $selected_user_stmt->execute([$manage_user_id]);
            $selected_user = $selected_user_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <form method="POST">
                <input type="hidden" name="manage_user_id" value="<?= $manage_user_id ?>">
                <label for="manage_username">Username:</label>
                <input type="text" id="manage_username" name="manage_username" value="<?= htmlspecialchars($selected_user['username']) ?>" required>
                <label for="manage_email">Email:</label>
                <input type="email" id="manage_email" name="manage_email" value="<?= htmlspecialchars($selected_user['email']) ?>" required>
                <label for="manage_password">Password (leave blank to keep current):</label>
                <input type="password" id="manage_password" name="manage_password">
                <label for="manage_is_admin">Admin:</label>
                <input type="checkbox" id="manage_is_admin" name="manage_is_admin" value="1" <?= $selected_user['is_admin'] ? 'checked' : '' ?>>
                <button type="submit">Save Changes</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <h3>Create New User</h3>
        <form method="POST">
            <label for="new_username">Username:</label>
            <input type="text" id="new_username" name="new_username" required>
            <label for="new_email">Email:</label>
            <input type="email" id="new_email" name="new_email" required>
            <label for="new_password">Password:</label>
            <input type="password" id="new_password" name="new_password" required>
            <label for="new_is_admin">Admin:</label>
            <input type="checkbox" id="new_is_admin" name="new_is_admin" value="1">
            <button type="submit">Create User</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Backup Database</h3>
        <form method="GET">
            <input type="hidden" name="backup" value="true">
            <button type="submit">Download Backup</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Reset Draft (and clear recent trades)</h3>
        <form method="POST">
            <button type="submit" name="reset_draft">Reset Draft</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Initialize Database (you will be logged out)</h3>
        <form method="POST">
            <label for="confirm_init">Type 'YES' to confirm initialization:</label>
            <input type="text" id="confirm_init" name="confirm_init" required>
            <button type="submit">Initialize Database</button>
        </form>
    </div>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userHasTeam = <?= json_encode($user_has_team) ?>;
    console.log('User has team:', userHasTeam); // Debugging output

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
    const positions = ['C', 'IF', 'OF', 'DH', 'P'];
    positions.forEach(position => {
        const element = document.getElementById(position);
        if (element) {
            element.innerHTML = ''; // Clear existing list
        }
    });

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

        let position = '';
        if (player.is_catcher) position = 'C';
        else if (player.is_infielder) position = 'IF';
        else if (player.is_outfielder) position = 'OF';
        else if (player.is_dh) position = 'DH';
        else if (player.is_pitcher) position = 'P';

        const element = document.getElementById(position);
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
    document.getElementById('team').value = player.team;
    document.getElementById('bats').value = player.bats;
    document.getElementById('throws').value = player.throws;
    document.getElementById('position').value = player.is_catcher ? 'C' : player.is_infielder ? 'IF' : player.is_outfielder ? 'OF' : player.is_dh ? 'DH' : player.is_pitcher ? 'P' : '';
    document.getElementById('no_card').checked = player.no_card === 1;
    document.getElementById('submit-btn').innerText = 'Add/Edit Player';
    document.getElementById('delete_player_id').value = player.id; // Set player ID for deletion

    console.log('delete_player_id set to:', player.id); // Log the value
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
</script>

