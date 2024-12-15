<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the user's admin status
$user_stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

$is_admin = $user['is_admin'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        body {
            font-family: monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .dashboard-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Dashboard</h2>
        <ul>
            <li><a href="player_form.php">Add/Edit Players</a></li>
            <li><a href="view_team.php">View Your Team</a></li>
            <li><a href="full_rosters.php">View Full Rosters</a></li>
            <li><a href="recent.php">View Recent Trades</a></li>
            <?php if ($is_admin): ?>
                <li><a href="trades.php">Trade Players and Draft Picks</a></li>
                <li><a href="admin.php">Admin Panel</a></li>
            <?php endif; ?>
        </ul>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>

