<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Check if a file was uploaded
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');

    // Skip the header row
    fgetcsv($csv_file);

    // Prepare the insert statement
    $stmt = $db->prepare('INSERT INTO players (first_name, last_name, age, current_year_team, next_year_team, fantasy_team_id, is_pitcher, is_catcher, is_infielder, is_outfielder, no_card) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    // Process each row of the CSV
    while (($row = fgetcsv($csv_file)) !== FALSE) {
        $stmt->execute([
            $row[0], // first_name
            $row[1], // last_name
            $row[2], // age
            $row[3], // current_year_team
            $row[4], // next_year_team
            $row[5], // fantasy_team_id
            $row[6], // is_pitcher
            $row[7], // is_catcher
            $row[8], // is_infielder
            $row[9], // is_outfielder
            $row[10] // no_card
        ]);
    }

    fclose($csv_file);
    $success_message = "Players uploaded successfully.";
} else {
    $error_message = "Error uploading file.";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Players</title>
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

