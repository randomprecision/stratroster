<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit;
}

try {
    $db = new PDO("sqlite:./stratroster.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout = 10000'); // Set busy timeout to 10 seconds

    // Fetch current values for league properties
    $league_stmt = $db->query('SELECT background_color FROM league_properties LIMIT 1');
    $league = $league_stmt->fetch(PDO::FETCH_ASSOC);
    $background_color = isset($league['background_color']) ? $league['background_color'] : '#FFFFFF'; // Default to white if not set

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            $db->beginTransaction();
            $maint_mode = isset($_POST['maint_mode']) ? 1 : 0;
            $stmt = $db->prepare('UPDATE league_properties SET maint_mode = ?');
            $stmt->execute([$maint_mode]);
            $db->commit();
            $message = "Maintenance mode updated successfully!";
        } catch (PDOException $e) {
            $db->rollBack();
            $message = "Transaction error: " . htmlspecialchars($e->getMessage());
            error_log($e->getMessage());
        }
    }

    $league_stmt = $db->query('SELECT maint_mode FROM league_properties LIMIT 1');
    $maint_mode = $league_stmt->fetchColumn();
} catch (PDOException $e) {
    $message = "Database error: " . htmlspecialchars($e->getMessage());
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode</title>
    <style>
        body {
            background-color: <?= htmlspecialchars($background_color) ?>;
            font-family: 'Lato', sans-serif;
            padding: 20px;
        }
        .form-container {
            border: 1px solid #000; /* Black outline */
            padding: 20px;
            margin: auto;
            margin-top: 20px;
            width: 50%;
            border-radius: 5px;
            background-color: #f9f9f9;
            text-align: center; /* Center align the content */
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Maintenance Mode</h1>
        <?php if (isset($message)): ?>
            <p><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <form method="POST" action="maint_mode.php">
            <label for="maint_mode">Enable Maintenance Mode:</label>
            <input type="checkbox" id="maint_mode" name="maint_mode" <?= $maint_mode ? 'checked' : '' ?>>
            <button type="submit">Apply</button>
        </form>
    </div>
<p>
<center><a href="dashboard.php">Back to Dashboard</center>
</body>
</html>

