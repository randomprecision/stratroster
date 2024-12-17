<?php
$db = new PDO("sqlite:./stratroster.db");

// Fetch the list of teams
$teams_stmt = $db->query('SELECT id, team_name FROM teams ORDER BY team_name');
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Return the teams as JSON
header('Content-Type: application/json');
echo json_encode(['teams' => $teams]);
?>

