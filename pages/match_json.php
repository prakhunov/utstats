<?php

header('Content-Type: application/json');

$mid = preg_replace('/\D/', '', $_GET['mid'] ?? '');

if ($mid == "") {
 http_response_code(400);
 die('{"Succeeded":false,"Error":"No Match ID Provided"}');
}

$r_info = db_row("SELECT teamgame, t0, t1, t2, t3, t0score, t1score, t2score, t3score, mapfile FROM uts_match WHERE id = ?", [$mid]);

if (!$r_info) {
 http_response_code(404);
 die('{"Succeeded":false,"Error":"Match Not Found"}');
}

$sql_player_info = "SELECT p.playerid, pi.name, p.pid, p.team, p.gamescore, p.frags, p.suicides, p.teamkills, p.deaths, p.flag_taken, p.flag_pickedup, p.flag_dropped, p.flag_assist, p.flag_cover, p.flag_seal, p.flag_capture, p.flag_kill, p.flag_return, p.rank, p.accuracy
  FROM uts_player AS p, uts_pinfo AS pi WHERE p.pid = pi.id AND matchid = ? ORDER BY gamescore DESC";

$player_info_raw = db_query($sql_player_info, [$mid]);
$players = [];
while ($pi = zero_out($player_info_raw->fetch(PDO::FETCH_ASSOC))) {
 $players[] = $pi;
}

echo json_encode($players);

?>
