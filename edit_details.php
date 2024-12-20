<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Fetch the background color
$league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);
$background_color = $league['background_color'];

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user details
$user_stmt = $db->prepare('SELECT username, email FROM users WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Update user details
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?');
        $stmt->execute([$username, $email, $password_hash, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
    }

    $confirmation_message = "User details updated successfully.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User Details</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: 'Lato', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .edit-user-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type="submit"] {
            padding: 10px 20px;
            border: none;
            background-color: #333;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }
        p.center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="edit-user-container">
        <h2>Edit User Details</h2>

        <?php if (isset($confirmation_message)): ?>
            <p><?= $confirmation_message ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <label for="password">New Password (leave blank to keep current password):</label>
            <input type="password" id="password" name="password">

            <input type="submit" value="Update Details">
        </form>

        <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>

