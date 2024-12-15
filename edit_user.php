<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Get the user ID to edit
$user_id = $_GET['id'];

// Fetch user details
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch all teams
$teams_stmt = $db->prepare('SELECT * FROM teams');
$teams_stmt->execute();
$teams = $teams_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $team_id = $_POST['team_id'];

    // Update the user
    $update_stmt = $db->prepare('UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?');
    $update_stmt->execute([$username, $email, $is_admin, $user_id]);

    // Update the team to assign the new manager
    $team_stmt = $db->prepare('UPDATE teams SET manager_name = ?, manager_email = ? WHERE id = ?');
    $team_stmt->execute([$username, $email, $team_id]);

    header('Location: admin.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function confirmTeamAssignment(teamName, userName) {
            return confirm(`This team is already assigned to ${userName} - do you wish to proceed?`);
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Edit User</h2>
        <form method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required><br>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><br>
            <label for="is_admin">Is Admin:</label>
            <input type="checkbox" id="is_admin" name="is_admin" <?= $user['is_admin'] ? 'checked' : '' ?>><br>
            <label for="team_id">Team:</label>
            <select id="team_id" name="team_id" required>
                <option value="" disabled selected>Select Team</option>
                <?php foreach ($teams as $team): ?>
                <?php
                $team_user_stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
                $team_user_stmt->execute([$team['manager_name']]);
                $team_user = $team_user_stmt->fetch();
                ?>
                <option value="<?= $team['id'] ?>" onclick="return <?= $team_user ? 'confirmTeamAssignment(\'' . htmlspecialchars($team['team_name']) . '\', \'' . htmlspecialchars($team_user['username']) . '\')' : 'true' ?>" <?= $team['id'] == $user['team_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($team['team_name']) ?>
                </option>
                <?php endforeach; ?>
            </select><br>
            <button type="submit">Update User</button>
        </form>
        <p><a href="admin.php">Back to Admin Page</a></p>
    </div>
</body>
</html>

