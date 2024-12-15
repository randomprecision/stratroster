<?php
header('Content-Type: application/json');
$db = new PDO("sqlite:./stratroster.db");

$team_id = $_GET['team_id'];

// Fetch players
$players_stmt = $db->prepare('SELECT id, first_name, last_name, is_pitcher, throws, bats, no_card FROM players WHERE fantasy_team_id = ?');
$players_stmt->execute([$team_id]);
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch draft picks
$draft_picks_stmt = $db->prepare('SELECT id, round, year, original_team_id FROM draft_picks WHERE team_id = ?');
$draft_picks_stmt->execute([$team_id]);
$draft_picks = $draft_picks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Return data as JSON
echo json_encode(['players' => $players, 'draft_picks' => $draft_picks]);
?>
