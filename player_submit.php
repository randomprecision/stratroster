<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $age = $_POST['age'];
    $current_year_team = $_POST['current_year_team'];
    $next_year_team = $_POST['next_year_team'];
    $is_pitcher = isset($_POST['is_pitcher']) ? 1 : 0;
    $is_catcher = isset($_POST['is_catcher']) ? 1 : 0;
    $is_infielder = isset($_POST['is_infielder']) ? 1 : 0;
    $is_outfielder = isset($_POST['is_outfielder']) ? 1 : 0;
    $no_card = isset($_POST['no_card']) ? 1 : 0;

    $stmt = $db->prepare('INSERT INTO players (first_name, last_name, age, current_year_team, next_year_team, fantasy_team_id, is_pitcher, is_catcher, is_infielder, is_outfielder, no_card) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$first_name, $last_name, $age, $current_year_team, $next_year_team, $_SESSION['user_id'], $is_pitcher, $is_catcher, $is_infielder, $is_outfielder, $no_card]);

    $success_message = "Player information submitted successfully.";
}

// Fetch current team roster, sorted by position and last name
$stmt = $db->prepare('SELECT * FROM players WHERE fantasy_team_id = ? ORDER BY is_catcher DESC, is_infielder DESC, is_outfielder DESC, is_pitcher DESC, last_name ASC');
$stmt->execute([$_SESSION['user_id']]);
$players = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Player Submission</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php if (isset($success_message)): ?>
            <p style="color: green;"><?= $success_message ?></p>
        <?php endif; ?>

        <h2>Current Team Roster</h2>
        <table border="1">
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Age</th>
                <th>CYT</th>
                <th>NYT</th>
                <th>POS</th>
                <th>No Card</th>
            </tr>
            <?php foreach ($players as $player): ?>
            <tr>
                <td><?= htmlspecialchars($player['first_name']) ?></td>
                <td><?= htmlspecialchars($player['last_name']) ?></td>
                <td><?= htmlspecialchars($player['age']) ?></td>
                <td><?= htmlspecialchars($player['current_year_team']) ?></td>
                <td><?= htmlspecialchars($player['next_year_team']) ?></td>
                <td>
                    <?= $player['is_catcher'] ? 'C' : '' ?>
                    <?= $player['is_infielder'] ? 'IF' : '' ?>
                    <?= $player['is_outfielder'] ? 'OF' : '' ?>
                    <?= $player['is_pitcher'] ? 'P' : '' ?>
                </td>
                <td><?= $player['no_card'] ? 'Yes' : 'No' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <p><a href="player_form.php">Add Another Player</a></p>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>

