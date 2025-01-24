<?php
header('Content-Type: application/json');
$db = new PDO("sqlite:./stratroster.db");

$team_id = $_GET['team_id'];

// Fetch team details, including `y1nc` and `y2nc`
$team_stmt = $db->prepare('SELECT y1nc, y2nc FROM teams WHERE id = ?');
$team_stmt->execute([$team_id]);
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch players
$players_stmt = $db->prepare('SELECT id, first_name, last_name, is_pitcher, throws, bats, no_card FROM players WHERE fantasy_team_id = ?');
$players_stmt->execute([$team_id]);
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch draft picks
$draft_picks_stmt = $db->prepare('SELECT id, round, year, original_team_id FROM draft_picks WHERE team_id = ?');
$draft_picks_stmt->execute([$team_id]);
$draft_picks = $draft_picks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Return data as JSON, including `y1nc` and `y2nc`
echo json_encode([
    'players' => $players,
    'draft_picks' => $draft_picks,
    'y1nc' => $team['y1nc'],
    'y2nc' => $team['y2nc'],
]);
?>

