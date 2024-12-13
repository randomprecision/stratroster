<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Fetch league properties
$league_stmt = $db->prepare('SELECT * FROM league_properties');
$league_stmt->execute();
$league = $league_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Authenticate user
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error_message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: monospace;
        }
        .container {
            width: 300px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .container h1 {
            font-size: 1.5em;
            margin-bottom: 20px;
        }
        .container label {
            display: block;
            margin-bottom: 5px;
        }
        .container input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .container button {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
        }
        .container p {
            color: red;
        }
        .container img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to <?= htmlspecialchars($league['name']) ?> Roster Manager</h1>
        <?php if (!empty($league['image'])): ?>
            <img src="data:image/jpeg;base64,<?= $league['image'] ?>" alt="League Image">
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <p><?= $error_message ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
