<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}

// Initialize the message variable
$message = "";

// Handle form submission for adding a new team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['team_name'])) {
    $team_name = $_POST['team_name'];

    // Check if the team name already exists
    $check_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE team_name = ?');
    $check_stmt->execute([$team_name]);
    $exists = $check_stmt->fetchColumn();

    if ($exists) {
        $message = "Team " . htmlspecialchars($team_name) . " already exists.";
    } else {
        // Insert the new team into the database
        $insert_stmt = $db->prepare('INSERT INTO teams (team_name) VALUES (?)');
        $insert_stmt->execute([$team_name]);
        $message = "Team " . htmlspecialchars($team_name) . " has been added.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Team</title>
</head>
<body>
    <h2>Add New Team</h2>
    <?php if ($message): ?>
        <p><?= $message ?></p>
    <?php endif; ?>
    <form method="POST">
        <label for="team_name">Team Name:</label>
        <input type="text" id="team_name" name="team_name" required>
        <button type="submit">Add Team</button>
    </form>
    <p><a href="admin.php">Back to Admin Page</a></p>
</body>
</html>

