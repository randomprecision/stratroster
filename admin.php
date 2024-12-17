<?php
session_start();

// admin page for doing admin things

$db = new PDO("sqlite:./stratroster.db");

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}

$current_year = date("Y"); // Define current_year before using it

// Fetch current values for league properties
$league_stmt = $db->query('SELECT background_color, name, draft_rounds, draft_year FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = isset($league['background_color']) ? $league['background_color'] : '#FFFFFF'; // Default to white if not set
$league_name = isset($league['name']) ? $league['name'] : 'My League';
$draft_rounds = isset($league['draft_rounds']) ? $league['draft_rounds'] : 10; // Default to 10 if not set
$draft_year = isset($league['draft_year']) ? $league['draft_year'] : $current_year;

// Fetch the list of teams
$teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the list of users
$users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the list of draft picks
$draft_picks_stmt = $db->query('SELECT dp.id, dp.round, dp.year, dp.team_id, t.team_name FROM draft_picks dp JOIN teams t ON dp.team_id = t.id');
$draft_picks = $draft_picks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure the user has a team assigned
$user_id = $_SESSION['user_id'];
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$team_assigned = isset($user['team_id']) && $user['team_id'] !== null && $user['team_id'] > 0;
$user_has_team = $team_assigned ? true : false;

// Set default values if not set
$league_name = $league['name'] ?? 'My League';
$draft_year = $league['draft_year'] ?? date('Y');
$draft_rounds = $league['draft_rounds'] ?? 10; // Default to 10 if not set
$background_color = $league['background_color'] ?? '#FFFFFF'; // Default to white if not set

// Handle form submission for editing league properties
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_league_properties'])) {
    $league_name = $_POST['league_name'];
    $draft_year = $_POST['draft_year'];
    $draft_rounds = $_POST['draft_rounds'];
    $background_color = $_POST['background_color'];

    // Check for custom color
    if ($background_color === 'custom') {
        $custom_color = $_POST['custom_background_color'];
        if (preg_match('/^[a-fA-F0-9]{6}$/', $custom_color)) {
            $background_color = '#' . $custom_color;
        } else {
            $settings_message = "Invalid custom color. Please enter a valid 6-digit hex code.";
        }
    }

    if (!isset($settings_message)) {
        try {
            // Update league properties in the database
            $db->beginTransaction();
            $update_league_properties_stmt = $db->prepare('UPDATE league_properties SET name = ?, draft_year = ?, draft_rounds = ?, background_color = ? WHERE id = 1');
            $update_league_properties_stmt->execute([$league_name, $draft_year, $draft_rounds, $background_color]);

            if ($update_league_properties_stmt->rowCount() > 0) {
                $settings_message = "League properties updated successfully.";
            } else {
                $settings_message = "Error: No league properties were updated.";
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $settings_message = "Error updating league properties: " . $e->getMessage();
            error_log("Error updating league properties: " . $e->getMessage());
        }

        // Refresh the league properties after updating
        $league_properties_stmt = $db->query('SELECT * FROM league_properties LIMIT 1');
        $league_properties = $league_properties_stmt->fetch(PDO::FETCH_ASSOC);

        // Update variables with refreshed values
        $league_name = $league_properties['name'];
        $draft_year = $league_properties['draft_year'];
        $draft_rounds = $league_properties['draft_rounds'];
        $background_color = $league_properties['background_color'];

        echo "<div class='confirmation'>League properties applied successfully.</div>";
    }
}

// Fetch current values for league properties
$league_stmt = $db->query('SELECT background_color, name, draft_rounds, draft_year FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = isset($league['background_color']) ? $league['background_color'] : '#FFFFFF'; // Default to white if not set
$league_name = isset($league['name']) ? $league['name'] : 'My League';
$draft_rounds = isset($league['draft_rounds']) ? $league['draft_rounds'] : 10; // Default to 10 if not set
$draft_year = isset($league['draft_year']) ? $league['draft_year'] : $current_year;

// Handle form submission for adding a new team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_team'])) {
    $new_team_name = trim($_POST['new_team_name']);

    // Check if the team name already exists
    $check_team_stmt = $db->prepare('SELECT 1 FROM teams WHERE team_name = ? COLLATE NOCASE');
    if ($check_team_stmt->execute([$new_team_name])) {
        $existing_team = $check_team_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_team) {
            $message = "Error: Team name is already in use.";
        } else {
            // Insert new team into the database
            $insert_team_stmt = $db->prepare('INSERT INTO teams (team_name) VALUES (?)');
            if ($insert_team_stmt->execute([$new_team_name])) {
                $team_id = $db->lastInsertId(); // Get the ID of the newly inserted team
                $message = "New team " . htmlspecialchars($new_team_name) . " added successfully.";

                // Retrieve league properties
                $league_stmt = $db->query('SELECT draft_year, draft_rounds FROM league_properties LIMIT 1');
                $league = $league_stmt->fetch(PDO::FETCH_ASSOC);
                $draft_year = $league['draft_year'];
                $draft_rounds = $league['draft_rounds'];

                if ($draft_year && $draft_rounds) {
                    // Insert draft picks for the new team for the next two years
                    $insert_draft_pick_stmt = $db->prepare('INSERT INTO draft_picks (team_id, round, year) VALUES (?, ?, ?)');
                    for ($year = $draft_year; $year <= $draft_year + 1; $year++) {
                        for ($round = 1; $round <= $draft_rounds; $round++) {
                            if (!$insert_draft_pick_stmt->execute([$team_id, $round, $year])) {
                                $message .= " Error inserting draft pick for year $year and round $round.";
                            }
                        }
                    }
                } else {
                    $message = "Error: Invalid league properties.";
                }

                // Refresh the list of teams after adding a new team
                $teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
                $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = "Error inserting the new team.";
            }
        }
    } else {
        $message = "Error checking for existing team name.";
    }
}

// Handle form submission for assigning users to teams
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_team'])) {
    $team_assignment_message = '';
    foreach ($users as $user) {
        $user_id = $user['id'];
        if (isset($_POST["team_id_$user_id"]) && isset($_POST["user_id_$user_id"])) {
            $team_id = $_POST["team_id_$user_id"] === 'none' ? null : $_POST["team_id_$user_id"];

            // Check if the team assignment has changed
            if ($user['team_id'] != $team_id) {
                // Update user's team assignment in the database
                $update_team_stmt = $db->prepare('UPDATE users SET team_id = ? WHERE id = ?');
                $update_team_stmt->execute([$team_id, $user_id]);

                // Get team name
                $team_name = 'None';
                if ($team_id !== null) {
                    foreach ($teams as $team) {
                        if ($team['id'] == $team_id) {
                            $team_name = $team['team_name'];
                            break;
                        }
                    }
                }

                // Append message for each change
                $team_assignment_message .= "User " . htmlspecialchars($user['username']) . " assigned to team " . htmlspecialchars($team_name) . ".\n";
            }
        }
    }

    // Refresh the list of users
    $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for managing user details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_changes']) && isset($_POST['manage_user_id'])) {
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
    } elseif (isset($_POST['delete_user']) && isset($_POST['manage_user_id'])) {
        $manage_user_id = $_POST['manage_user_id'];

        // Delete user from the database
        $delete_user_stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $delete_user_stmt->execute([$manage_user_id]);

        $message = "User deleted successfully.";

        // Refresh the users list after deleting a user
        $users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle form submission for creating a new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_username']) && isset($_POST['new_email']) && isset($_POST['new_password'])) {
    $new_username = $_POST['new_username'];
    $new_email = $_POST['new_email'];
    $new_password = $_POST['new_password'];
    $new_is_admin = isset($_POST['new_is_admin']) ? 1 : 0;

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

// Handle form submission for managing team details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_team_changes']) && isset($_POST['manage_team_id'])) {
        $manage_team_id = $_POST['manage_team_id'];
        $manage_team_name = $_POST['manage_team_name'];

        // Update team details in the database
        $update_team_stmt = $db->prepare('UPDATE teams SET team_name = ? WHERE id = ?');
        $update_team_stmt->execute([$manage_team_name, $manage_team_id]);

        $message = "Team details updated successfully.";

        // Refresh the teams list after updating a team
        $teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($_POST['delete_team']) && isset($_POST['manage_team_id'])) {
        $manage_team_id = $_POST['manage_team_id'];

        // Delete draft picks where the team is involved, unless original_team_id is set
        $delete_draft_picks_stmt = $db->prepare('DELETE FROM draft_picks WHERE team_id = ? AND original_team_id IS NULL');
        $delete_draft_picks_stmt->execute([$manage_team_id]);

        // Delete team from the database
        $delete_team_stmt = $db->prepare('DELETE FROM teams WHERE id = ?');
        $delete_team_stmt->execute([$manage_team_id]);

        $message = "Team and its associated draft picks deleted successfully.";

        // Refresh the teams list after deleting a team
        $teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($_POST['edit_league_properties'])) {
       
    }
}

// Handle form submission for assigning draft picks
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_draft_pick'])) {
    $from_team_id = $_POST['from_team'];
    $to_team_id = $_POST['to_team'];
    $draft_pick_id = $_POST['draft_pick'];

    // Fetch the current team_id of the draft pick to use it as original_team_id
    $draft_pick_stmt = $db->prepare('SELECT team_id FROM draft_picks WHERE id = ?');
    $draft_pick_stmt->execute([$draft_pick_id]);
    $current_team_id = $draft_pick_stmt->fetchColumn();

    // Update the draft pick's team and set original_team_id
    $update_draft_pick_stmt = $db->prepare('UPDATE draft_picks SET team_id = ?, original_team_id = ? WHERE id = ?');
    $update_draft_pick_stmt->execute([$to_team_id, $current_team_id, $draft_pick_id]);

    $message = "Draft pick successfully assigned from " . htmlspecialchars($teams[$from_team_id]['team_name']) . " to " . htmlspecialchars($teams[$to_team_id]['team_name']) . ".";
}

// Handle form submission for managing team details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_team_changes']) && isset($_POST['manage_team_id'])) {
        $manage_team_id = $_POST['manage_team_id'];
        $manage_team_name = $_POST['manage_team_name'];

        // Update team details in the database
        $update_team_stmt = $db->prepare('UPDATE teams SET team_name = ? WHERE id = ?');
        $update_team_stmt->execute([$manage_team_name, $manage_team_id]);

        $message = "Team details updated successfully.";

        // Refresh the teams list after updating a team
        $teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($_POST['delete_team']) && isset($_POST['manage_team_id'])) {
        $manage_team_id = $_POST['manage_team_id'];

        // Delete draft picks where the team is involved, unless original_team_id is set
        $delete_draft_picks_stmt = $db->prepare('DELETE FROM draft_picks WHERE team_id = ? AND original_team_id IS NULL');
        $delete_draft_picks_stmt->execute([$manage_team_id]);

        // Delete team from the database
        $delete_team_stmt = $db->prepare('DELETE FROM teams WHERE id = ?');
        $delete_team_stmt->execute([$manage_team_id]);

        $message = "Team and its associated draft picks deleted successfully.";

        // Refresh the teams list after deleting a team
        $teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Function to backup the SQLite database and serve it for download
function backupSQLiteDatabase($db_path) {
    // Generate the timestamped filename
    $timestamp = date('Y-m-d-His');
    $backup_filename = "stratroster-{$timestamp}.sql";

    // Set headers to force download
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$backup_filename\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    // Use passthru to directly stream the SQLite dump
    $command = "sqlite3 $db_path .dump 2>&1"; // Capture stderr
    ob_start();
    passthru($command, $return_var);
    $output = ob_get_clean();

    // Check for errors
    if ($return_var !== 0) {
        error_log("Error creating backup: " . $output);
        echo "Error creating backup: " . htmlspecialchars($output);
    } else {
        echo $output;
    }

    exit;
}

// Function to initialize the database
function initializeDatabase($db) {
    try {
        // List of tables to clear
        $tables = ['teams', 'league_properties', 'draft_picks', 'users', 'players', 'trades', 'trade_log'];

        // Begin a transaction
        $db->beginTransaction();

        // Delete all entries from the tables and reset auto-increment sequences
        foreach ($tables as $table) {
            $db->exec("DELETE FROM $table");
            $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
        }

        // Insert default league properties
        $default_league_properties = [
            'name' => 'My League',
            'image' => null, // Assuming no default image
            'draft_rounds' => 10,
            'background_color' => '#FFFFFF',
            'draft_year' => date('Y')
        ];

        $insert_league_properties_stmt = $db->prepare('INSERT INTO league_properties (name, image, draft_rounds, background_color, draft_year) VALUES (?, ?, ?, ?, ?)');
        $insert_league_properties_stmt->execute([
            $default_league_properties['name'],
            $default_league_properties['image'],
            $default_league_properties['draft_rounds'],
            $default_league_properties['background_color'],
            $default_league_properties['draft_year']
        ]);

        // Insert the admin user with a hashed password and set ID to 1
        $admin_username = 'admin';
        $admin_password = password_hash('stratroster', PASSWORD_DEFAULT);
        $is_admin = 1;

        $insert_admin_stmt = $db->prepare('INSERT INTO users (id, username, password, is_admin) VALUES (?, ?, ?, ?)');
        $insert_admin_stmt->execute([1, $admin_username, $admin_password, $is_admin]);

        // Commit the transaction
        $db->commit();

        return "Database initialized successfully.";
    } catch (Exception $e) {
        // Rollback the transaction if something went wrong
        $db->rollBack();
        return "Error initializing database: " . $e->getMessage();
    }
}

// Check if the backup request is made
if (isset($_GET['backup']) && $_GET['backup'] === 'true') {
    $db_path = './stratroster.db';
    backupSQLiteDatabase($db_path);
}

// Check if the initialize request is made
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_init']) && $_POST['confirm_init'] === 'YES') {
    $db_path = './stratroster.db';
    $initialize_message = initializeDatabase($db);
    echo "<pre>$initialize_message</pre>";
    
    // Add a script to redirect after showing the message
    echo "<script>
        setTimeout(function() {
            window.location.href = 'logout.php';
        }, 5000); // Redirect after 5 seconds
    </script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: 'Lato', sans-serif;
            padding: 20px;
        }
        .form-container {
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h3 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
    </style>
</head>
<body>

<!-- User Team Assignment Section -->
<div class="form-container">
    <h3>Assign Users to Teams</h3>
    <?php if (isset($team_assignment_message)): ?>
        <p><?= htmlspecialchars($team_assignment_message) ?></p>
    <?php endif; ?>
    <form method="POST" action="admin.php">
        <table>
            <tr>
                <th>Username</th>
                <th>Current Team</th>
                <th>Assign Team</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['team_name'] ?? 'None') ?></td>
                    <td>
                        <select name="team_id_<?= $user['id'] ?>">
                            <option value="none">None</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['id'] ?>" <?= $user['team_id'] == $team['id'] ? 'selected' : '' ?>><?= htmlspecialchars($team['team_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="user_id_<?= $user['id'] ?>" value="<?= $user['id'] ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" name="assign_team">Assign</button>
    </form>
</div>

<!-- Add New Team Section -->
<div class="form-container">
    <h3>Add New Team</h3>
    <?php if (isset($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="POST" action="admin.php">
        <label for="new_team_name">Team Name:</label>
        <input type="text" id="new_team_name" name="new_team_name" required>
        <button type="submit" name="add_team">Add Team</button>
    </form>
</div>

<!-- Edit League Properties Section -->
<div class="form-container">
    <h3>Edit League Properties</h3>
    <?php if (isset($settings_message)): ?>
        <p><?= htmlspecialchars($settings_message) ?></p>
    <?php endif; ?>
    <form method="POST" action="admin.php">
        <label for="league_name">League Name:</label>
        <input type="text" id="league_name" name="league_name" value="<?= htmlspecialchars($league_name) ?>" required>

        <label for="draft_year">First Draft Year:</label>
        <input type="radio" id="current_year" name="draft_year" value="<?= $current_year ?>" <?= $draft_year == $current_year ? 'checked' : '' ?>>
        <label for="current_year"><?= $current_year ?></label>
        <input type="radio" id="next_year" name="draft_year" value="<?= $current_year + 1 ?>" <?= $draft_year == $current_year + 1 ? 'checked' : '' ?>>
        <label for="next_year"><?= $current_year + 1 ?></label>

        <label for="draft_rounds">Draft Rounds:</label>
        <input type="number" id="draft_rounds" name="draft_rounds" value="<?= htmlspecialchars($draft_rounds) ?>" min="1" max="20" required>

        <label for="background_color">Background Color:</label>
        <select id="background_color" name="background_color" onchange="toggleCustomColorInput(this.value)" required>
            <option value="#FFB6C1" <?= $background_color == '#FFB6C1' ? 'selected' : '' ?>>Light Pink</option>
            <option value="#FFD700" <?= $background_color == '#FFD700' ? 'selected' : '' ?>>Gold</option>
            <option value="#ADFF2F" <?= $background_color == '#ADFF2F' ? 'selected' : '' ?>>Green Yellow</option>
            <option value="#F0E68C" <?= $background_color == '#F0E68C' ? 'selected' : '' ?>>Khaki</option>
            <option value="#D3D3D3" <?= $background_color == '#D3D3D3' ? 'selected' : '' ?>>Light Grey</option>
            <option value="#B0C4DE" <?= $background_color == '#B0C4DE' ? 'selected' : '' ?>>Light Steel Blue</option>
            <option value="#ADD8E6" <?= $background_color == '#ADD8E6' ? 'selected' : '' ?>>Light Blue</option>
            <option value="#E6E6FA" <?= $background_color == '#E6E6FA' ? 'selected' : '' ?>>Lavender</option>
            <option value="#FFE4E1" <?= $background_color == '#FFE4E1' ? 'selected' : '' ?>>Misty Rose</option>
            <option value="#FAFAD2" <?= $background_color == '#FAFAD2' ? 'selected' : '' ?>>Light Goldenrod Yellow</option>
            <option value="#98FB98" <?= $background_color == '#98FB98' ? 'selected' : '' ?>>Pale Green</option>
            <option value="#FFFACD" <?= $background_color == '#FFFACD' ? 'selected' : '' ?>>Lemon Chiffon</option>
            <option value="#F0FFF0" <?= $background_color == '#F0FFF0' ? 'selected' : '' ?>>Honeydew</option>
            <option value="#FFF0F5" <?= $background_color == '#FFF0F5' ? 'selected' : '' ?>>Lavender Blush</option>
            <option value="#E0FFFF" <?= $background_color == '#E0FFFF' ? 'selected' : '' ?>>Light Cyan</option>
            <option value="#F8F8FF" <?= $background_color == '#F8F8FF' ? 'selected' : '' ?>>Ghost White</option>
            <option value="#FFEFD5" <?= $background_color == '#FFEFD5' ? 'selected' : '' ?>>Papaya Whip</option>
            <option value="#FFF5EE" <?= $background_color == '#FFF5EE' ? 'selected' : '' ?>>Seashell</option>
            <option value="custom" <?= preg_match('/^#[a-fA-F0-9]{6}$/', $background_color) ? 'selected' : '' ?>>Custom</option>
        </select>
        <input type="text" id="custom_background_color" name="custom_background_color" placeholder="Enter hex code" style="display:none;" value="<?= preg_match('/^#[a-fA-F0-9]{6}$/', $background_color) ? htmlspecialchars(ltrim($background_color, '#')) : '' ?>">

        <button type="submit" name="edit_league_properties">Apply</button>
    </form>
</div>

<script>
function toggleCustomColorInput(value) {
    var customColorInput = document.getElementById('custom_background_color');
    if (value === 'custom') {
        customColorInput.style.display = 'inline';
    } else {
        customColorInput.style.display = 'none';
    }
}
window.onload = function() {
    toggleCustomColorInput(document.getElementById('background_color').value);
};
</script>

<!-- Manage Users Section -->
<div class="form-container">
    <h3>Manage Users</h3>
    <form method="POST" action="admin.php">
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
        <form method="POST" action="admin.php">
            <input type="hidden" name="manage_user_id" value="<?= $manage_user_id ?>">
            <label for="manage_username">Username:</label>
            <input type="text" id="manage_username" name="manage_username" value="<?= htmlspecialchars($selected_user['username']) ?>" required>
            <label for="manage_email">Email:</label>
            <input type="email" id="manage_email" name="manage_email" value="<?= htmlspecialchars($selected_user['email']) ?>" required>
            <label for="manage_password">Password (leave blank to keep current):</label>
            <input type="password" id="manage_password" name="manage_password">
            <label for="manage_is_admin">Admin:</label>
            <input type="checkbox" id="manage_is_admin" name="manage_is_admin" value="1" <?= $selected_user['is_admin'] ? 'checked' : '' ?>>
            <button type="submit" name="save_changes">Save Changes</button>
            <button type="submit" name="delete_user" onclick="return confirm('Are you sure you want to delete this user?');">Delete User</button>
        </form>
    <?php endif; ?>
</div>
<!-- Manage Teams Section -->
<div class="form-container">
    <h3>Manage Teams</h3>
    <form method="POST" action="admin.php">
        <label for="manage_team_id">Select Team:</label>
        <select id="manage_team_id" name="manage_team_id" onchange="this.form.submit()">
            <option value="">Select a team</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (isset($_POST['manage_team_id']) && $_POST['manage_team_id'] != ''): ?>
        <?php
        $manage_team_id = $_POST['manage_team_id'];
        $selected_team_stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
        $selected_team_stmt->execute([$manage_team_id]);
        $selected_team = $selected_team_stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <form method="POST" action="admin.php">
            <input type="hidden" name="manage_team_id" value="<?= $manage_team_id ?>">
            <label for="manage_team_name">Team Name:</label>
            <input type="text" id="manage_team_name" name="manage_team_name" value="<?= htmlspecialchars($selected_team['team_name']) ?>" required>
            <button type="submit" name="save_team_changes">Save Changes</button>
            <button type="submit" name="delete_team" onclick="return confirm('Are you sure you want to delete this team?');">Delete Team</button>
        </form>
    <?php endif; ?>
</div>
<!-- Create New User Section -->
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

<!-- Assign Draft Picks Section -->
<div class="form-container">
    <h3>Assign Draft Pick</h3>
    <form method="POST">
        <label for="from_team">From Team:</label>
        <select id="from_team" name="from_team" required onchange="updateDraftPicks()">
            <option value="">Select Team</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="to_team">To Team:</label>
        <select id="to_team" name="to_team" required>
            <option value="">Select Team</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="draft_pick">Draft Pick:</label>
        <select id="draft_pick" name="draft_pick" required>
            <option value="">Select Draft Pick</option>
        </select>

        <button type="submit" name="assign_draft_pick">Assign Draft Pick</button>
    </form>
</div>
    <!-- Backup Database Section --> 
    <div class="form-container">
        <h3>Backup Database</h3>
        <form method="GET">
            <input type="hidden" name="backup" value="true">
            <button type="submit">Backup Database</button>
        </form>
    </div>
    
    <!-- Initialize Database Section -->
    <div class="form-container">
        <h3>Initialize Database (you will be logged out)</h3>
        <form method="POST" action="admin.php">
            <label for="confirm_init">Type 'YES' to confirm initialization:</label>
            <input type="text" id="confirm_init" name="confirm_init" required>
            <button type="submit">Initialize Database</button>
        </form>
    </div>
<p class="center"><a href="dashboard.php">Back to Dashboard</a></p>

</body>
</html>

<script>
const teams = <?= json_encode($teams) ?>.reduce((acc, team) => {
    acc[team.id] = team;
    return acc;
}, {});

const draftPicks = <?= json_encode($draft_picks) ?>;

function updateDraftPicks() {
    const fromTeamSelect = document.getElementById('from_team');
    const draftPickSelect = document.getElementById('draft_pick');

    // Clear the current draft pick options
    draftPickSelect.innerHTML = '<option value="">Select Draft Pick</option>';

    // Get selected team ID
    const fromTeamId = fromTeamSelect.value;

    // Filter and add draft picks for the selected team
    const filteredDraftPicks = draftPicks.filter(pick => pick.team_id == fromTeamId);
    filteredDraftPicks.forEach(pick => {
        const option = document.createElement('option');
        const originalTeamName = teams[pick.team_id] ? teams[pick.team_id].team_name : 'Unknown';
        option.value = pick.id;
        option.textContent = `Round ${pick.round}, Year ${pick.year} (${originalTeamName})`;
        draftPickSelect.appendChild(option);
    });
}

// Initial call to populate the draft pick dropdown if a team is already selected
document.addEventListener('DOMContentLoaded', function() {
    updateDraftPicks();
});
</script>
