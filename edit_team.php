<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the current user's team information
$team_stmt = $db->prepare('SELECT * FROM teams WHERE manager_user_id = ?');
$team_stmt->execute([$_SESSION['user_id']]);
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

$updated = false;

// Handle form submission to update ballpark details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ballpark_name = $_POST['ballpark_name'];
    $l_si = $_POST['l_si'];
    $r_si = $_POST['r_si'];
    $l_hr = $_POST['l_hr'];
    $r_hr = $_POST['r_hr'];

    // Validate input values
    if (filter_var($l_si, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 20]]) === false ||
        filter_var($r_si, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 20]]) === false ||
        filter_var($l_hr, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 20]]) === false ||
        filter_var($r_hr, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 20]]) === false) {
        echo "Error: All values must be integers between 1 and 20.";
    } else {
        $update_stmt = $db->prepare('UPDATE teams SET ballpark_name = ?, l_si = ?, r_si = ?, l_hr = ?, r_hr = ? WHERE id = ?');
        $update_stmt->execute([$ballpark_name, $l_si, $r_si, $l_hr, $r_hr, $team['id']]);
        $updated = true;
        // Fetch the updated team information
        $team_stmt->execute([$_SESSION['user_id']]);
        $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
        echo "Ballpark details updated successfully.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Your Ballpark</title>
</head>
<body>
    <h2>Edit Your Ballpark</h2>
    <form method="POST">
        <label for="ballpark_name">Ballpark Name:</label>
        <input type="text" id="ballpark_name" name="ballpark_name" value="<?= htmlspecialchars($team['ballpark_name']) ?>" required><br>

        <label for="l_si">LHB Singles (1-20):</label>
        <input type="number" id="l_si" name="l_si" value="<?= htmlspecialchars($team['l_si']) ?>" min="1" max="20" required><br>

        <label for="r_si">RHB Singles (1-20):</label>
        <input type="number" id="r_si" name="r_si" value="<?= htmlspecialchars($team['r_si']) ?>" min="1" max="20" required><br>

        <label for="l_hr">LHB Home Runs (1-20):</label>
        <input type="number" id="l_hr" name="l_hr" value="<?= htmlspecialchars($team['l_hr']) ?>" min="1" max="20" required><br>

        <label for="r_hr">RHB Home Runs (1-20):</label>
        <input type="number" id="r_hr" name="r_hr" value="<?= htmlspecialchars($team['r_hr']) ?>" min="1" max="20" required><br>

        <button type="submit">Update Ballpark</button>
    </form>

    <?php if ($updated): ?>
    <h3>Updated Ballpark Details</h3>
    <p><strong>Ballpark Name:</strong> <?= htmlspecialchars($team['ballpark_name']) ?></p>
    <p><strong>LHB Singles:</strong> <?= htmlspecialchars($team['l_si']) ?></p>
    <p><strong>RHB Singles:</strong> <?= htmlspecialchars($team['r_si']) ?></p>
    <p><strong>LHB Home Runs:</strong> <?= htmlspecialchars($team['l_hr']) ?></p>
    <p><strong>RHB Home Runs:</strong> <?= htmlspecialchars($team['r_hr']) ?></p>
    <?php endif; ?>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>

