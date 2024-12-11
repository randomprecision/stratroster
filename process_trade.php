<?php
session_start();
$db = new PDO("sqlite:/var/www/stratroster/stratroster.db");

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if required POST data is available
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['team_id']) && isset($_POST['player_ids']) && isset($_POST['current_year_draft_pick_ids']) && isset($_POST['next_year_draft_pick_ids'])) {
    $team_id = $_POST['team_id'];
    $player_ids = $_POST['player_ids'];
    $current_year_draft_pick_ids = $_POST['current_year_draft_pick_ids'];
    $next_year_draft_pick_ids = $_POST['next_year_draft_pick_ids'];

    // Handle the trade logic here
    // 1. Transfer the players to the selected team
    foreach ($player_ids as $player_id) {
        $update_player_stmt = $db->prepare('UPDATE players SET team_id = ? WHERE id = ?');
        $update_player_stmt->execute([$team_id, $player_id]);
    }

    // 2. Transfer the current year draft picks to the selected team
    foreach ($current_year_draft_pick_ids as $draft_pick_id) {
        $update_draft_pick_stmt = $db->prepare('UPDATE draft_picks SET team_id = ? WHERE id = ?');
        $update_draft_pick_stmt->execute([$team_id, $draft_pick_id]);
    }

    // 3. Transfer the next year draft picks to the selected team
    foreach ($next_year_draft_pick_ids as $draft_pick_id) {
        $update_draft_pick_stmt = $db->prepare('UPDATE draft_picks SET team_id = ? WHERE id = ?');
        $update_draft_pick_stmt->execute([$team_id, $draft_pick_id]);
    }

    // Redirect back to trades page with a success message
    $_SESSION['message'] = "Trade processed successfully.";
    header('Location: trades.php');
    exit;
} else {
    // Redirect back to trades page with an error message
    $_SESSION['error'] = "Incomplete trade data.";
    header('Location: trades.php');
    exit;
}
?>

