<?php

// admin page for doing admin tasks

session_start();
$db = new PDO("sqlite:./stratroster.db");

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}

// Fetch the background color from the league properties
$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = isset($league['background_color']) ? $league['background_color'] : '#FFFFFF'; // Default to white if not set

// Fetch the list of teams
$teams_stmt = $db->query('SELECT * FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the list of users
$users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: monospace;
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
    <?php if (isset($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="POST">
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
                        <select name="team_id">
                            <option value="none">None</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['id'] ?>" <?= $user['team_id'] == $team['id'] ? 'selected' : '' ?>><?= htmlspecialchars($team['team_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="update_user_team">Assign</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </form>
</div>
<!-- Add New Team Section -->
<div class="form-container">
    <h3>Add New Team</h3>
    <form method="POST">
        <label for="new_team_name">Team Name:</label>
        <input type="text" id="new_team_name" name="team_name" required>
        <button type="submit">Add Team</button>
    </form>
</div>

<!-- Edit League Properties Section -->
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

        <label for="background_color">Background Color:</label>
        <select id="background_color" name="background_color" required>
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
        </select>

        <button type="submit">Apply</button>
    </form>
</div>
<!-- Manage Users Section -->
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
updateDraftPicks();
</script>
<div class="form-container">
    <h3>Backup Database</h3>
    <form method="GET">
        <input type="hidden" name="backup" value="true">
        <button type="submit">Download Backup</button>
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

<p class="center"><a href="dashboard.php">Back to Dashboard</a></p>

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

// Fetch the list of users
$users_stmt = $db->query('SELECT u.id, u.username, u.team_id, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.username');
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Manage Users Section -->
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
<div class="form-container">
    <h3>Backup Database</h3>
    <form method="GET">
        <input type="hidden" name="backup" value="true">
        <button type="submit">Download Backup</button>
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

<p class="center"><a href="dashboard.php">Back to Dashboard</a></p>

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

</body>
</html>
