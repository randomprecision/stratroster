<?php
session_start();
$db = new PDO("sqlite:./stratroster.db");

// Ensure the user is an admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (isset($_GET['trade_id'])) {
    $trade_id = $_GET['trade_id'];
    $stmt = $db->prepare('SELECT * FROM trade_log WHERE id = ?');
    $stmt->execute([$trade_id]);
    $trade = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($trade) {
        $team_a_id = $trade['team_a_id'];
        $team_b_id = $trade['team_b_id'];
        $team_a_players = json_decode($trade['team_a_players'], true);
        $team_b_players = json_decode($trade['team_b_players'], true);
        $team_a_draft_picks = json_decode($trade['team_a_draft_picks'], true);
        $team_b_draft_picks = json_decode($trade['team_b_draft_picks'], true);

        // Debugging: Output trade details
        error_log("Rolling back trade: $trade_id");
        error_log("Team A Players: " . print_r($team_a_players, true));
        error_log("Team B Players: " . print_r($team_b_players, true));
        error_log("Team A Draft Picks: " . print_r($team_a_draft_picks, true));
        error_log("Team B Draft Picks: " . print_r($team_b_draft_picks, true));

        // Move players and draft picks back to their original teams
        $move_players_stmt = $db->prepare('UPDATE players SET fantasy_team_id = ? WHERE id = ?');
        foreach ($team_a_players as $player_id) {
            $move_players_stmt->execute([$team_a_id, $player_id]);
        }
        foreach ($team_b_players as $player_id) {
            $move_players_stmt->execute([$team_b_id, $player_id]);
        }

        $move_draft_picks_stmt = $db->prepare('UPDATE draft_picks SET team_id = ? WHERE id = ?');
        foreach ($team_a_draft_picks as $pick_id) {
            $move_draft_picks_stmt->execute([$team_a_id, $pick_id]);
        }
        foreach ($team_b_draft_picks as $pick_id) {
            $move_draft_picks_stmt->execute([$team_b_id, $pick_id]);
        }

        // Remove the trade log entry
        $stmt = $db->prepare('DELETE FROM trade_log WHERE id = ?');
        $stmt->execute([$trade_id]);

        // Check if deletion was successful
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete trade log entry']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Trade not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>

