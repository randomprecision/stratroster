<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $team_id = $_POST['team_id'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Insert the new user
    $user_stmt = $db->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)');
    $user_stmt->execute([$username, $email, $password, $is_admin]);
    $user_id = $db->lastInsertId();

    // Update the team to assign the new manager
    $team_stmt = $db->prepare('UPDATE teams SET manager_name = ?, manager_email = ? WHERE id = ?');
    $team_stmt->execute([$username, $email, $team_id]);

    $success_message = "User and team added successfully.";
} else {
    $error_message = "Error adding user and team.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php if (isset($success_message)): ?>
            <p style="color: green;"><?= $success_message ?></p>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <p style="color: red;"><?= $error_message ?></p>
        <?php endif; ?>
        <p><a href="admin.php">Back to Admin Page</a></p>
    </div>
</body>
</html>

