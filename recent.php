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

// Handle clear trades action
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_trades'])) {
    $db->exec('DELETE FROM trade_log');
    $confirmation_message = "All trade history has been cleared.";
}

// Fetch recent trades
$trades_stmt = $db->query('SELECT * FROM trade_log ORDER BY trade_date DESC');
$trades = $trades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch team names
$teams_stmt = $db->query('SELECT id, team_name FROM teams');
$teams = $teams_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function fetch_trade_details($ids, $type) {
    global $db, $teams;
    if ($type == 'player') {
        $stmt = $db->prepare('SELECT first_name, last_name FROM players WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($item) {
            return $item['first_name'] . ' ' . $item['last_name'];
        }, $details);
    } elseif ($type == 'draft_pick') {
        $stmt = $db->prepare('SELECT round, original_team_id FROM draft_picks WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($item) use ($teams) {
            return $teams[$item['original_team_id']] . ' round ' . $item['round'];
        }, $details);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recent Trades</title>
    <style>
        body {
            font-family: monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .trades-container {
            text-align: center;
            padding: 20px;
            border: 2px solid black;
            border-radius: 10px;
            background-color: #f9f9f9;
            max-height: 90vh;
            overflow-y: auto;
        }
        h2 {
            color: #333;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin: 10px 0;
        }
        .admin-section {
            margin-top: 20px;
        }
        .center {
            text-align: center;
            margin-top: 20px;
        }
        hr {
            border: 0;
            border-top: 2px solid black;
            margin: 10px 0;
        }
    </style>
    <script>
        function confirmClear() {
            return confirm('Are you sure you want to clear the trade history? This action cannot be undone.');
        }
    </script>
</head>
<body>
    <div class="trades-container">
        <h2>Recent Trades</h2>

        <?php if (isset($confirmation_message)): ?>
            <p><?= $confirmation_message ?></p>
        <?php endif; ?>

        <ul>
            <?php foreach ($trades as $trade): ?>
                <li>
                    <strong><?= htmlspecialchars($trade['trade_date']) ?>:</strong>
                    <?php
                    $team_a_assets = array_filter([
                        implode(', ', fetch_trade_details(json_decode($trade['team_a_players'], true), 'player')),
                        implode(', ', fetch_trade_details(json_decode($trade['team_a_draft_picks'], true), 'draft_pick'))
                    ]);
                    $team_b_assets = array_filter([
                        implode(', ', fetch_trade_details(json_decode($trade['team_b_players'], true), 'player')),
                        implode(', ', fetch_trade_details(json_decode($trade['team_b_draft_picks'], true), 'draft_pick'))
                    ]);

                    echo htmlspecialchars($teams[$trade['team_a_id']]) . ' traded ' . implode(' and ', $team_a_assets) .
                         ' to ' . htmlspecialchars($teams[$trade['team_b_id']]) .
                         ' for ' . implode(' and ', $team_b_assets);
                    ?>
                </li>
                <hr>
            <?php endforeach; ?>
        </ul>

        <?php if ($is_admin): ?>
            <div class="admin-section">
                <form method="POST" onsubmit="return confirmClear();">
                    <button type="submit" name="clear_trades">Clear Trade History</button>
                </form>
            </div>
        <?php endif; ?>

        <p class="center"><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>

