<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['backup']) && $_GET['backup'] == 'true') {
    $dbFile = '/var/www/stratroster/stratroster.db'; // Path to your SQLite database file
    $backupFile = 'stratroster_backup_' . date('Y-m-d_H-i-s') . '.db'; // Backup file name with timestamp

    if (file_exists($dbFile)) {
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
    }
}

// Fetch the list of teams
$teams_stmt = $db->query('SELECT * FROM teams');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the list of users
$users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the league name from league_properties table
$league_stmt = $db->query('SELECT name FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission for updating league name
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['league_name'])) {
    $league_name = $_POST['league_name'];
    $update_league_stmt = $db->prepare('UPDATE league_properties SET name = ?');
    $update_league_stmt->execute([$league_name]);
    $message = "League name updated successfully.";
    $league['name'] = $league_name;
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

        // Insert draft picks for the next two years (10 rounds each year)
        $current_year = date('Y');
        for ($year = $current_year; $year <= $current_year + 1; $year++) {
            for ($round = 1; $round <= 10; $round++) {
                $insert_draft_pick_stmt = $db->prepare('INSERT INTO draft_picks (team_id, round, year) VALUES (?, ?, ?)');
                $insert_draft_pick_stmt->execute([$team_id, $round, $year]);
            }
        }

        $message = "Team " . htmlspecialchars($new_team_name) . " has been added with draft picks for the next two years.";

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
        <h3>Edit or Remove Team</h3>
        <form method="POST">
            <label for="edit_team_id">Select Team:</label>
            <select id="edit_team_id" name="edit_team_id" onchange="this.form.submit()">
                <option value="">Select a team</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if (isset($_POST['edit_team_id']) && $_POST['edit_team_id'] != ''): ?>
            <form method="POST">
                <input type="hidden" name="edit_team_id" value="<?= $_POST['edit_team_id'] ?>">
                <label for="edit_team_name">Edit Team Name:</label>
                <input type="text" id="edit_team_name" name="edit_team_name" value="<?= htmlspecialchars($teams[array_search($_POST['edit_team_id'], array_column($teams, 'id'))]['team_name']) ?>" required>
                <button type="submit">Save Changes</button>
            </form>
            <form method="POST">
                <input type="hidden" name="delete_team_id" value="<?= $_POST['edit_team_id'] ?>">
                <button type="submit" style="background-color: red; color: white;">Delete Team</button>
            </form>
        <?php endif; ?>
        <?php if (isset($message) && $message != ''): ?>
            <p class="success"><?= $message ?></p>
        <?php endif; ?>
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
        <?php if (isset($message) && $message != ''): ?>
            <p class="success"><?= $message ?></p>
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

    <!-- League Name Edit -->
    <div class="form-container">
        <h3>Edit League Name</h3>
        <form method="POST">
            <label for="league_name">League Name:</label>
            <input type="text" id="league_name" name="league_name" value="<?= htmlspecialchars($league['name']) ?>" required>
            <button type="submit">Update League Name</button>
        </form>
    </div>

    <!-- Backup Database Section -->
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


