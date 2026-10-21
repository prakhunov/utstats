<?php

// Who did the objectives
$r_assobj = db_row("SELECT count(id) AS assobjcount FROM uts_temp_$uid WHERE col1 = 'assault_obj' AND col2 = ?", [$playerid]);
$assobjcount = $r_assobj['assobjcount'];
$upd_assobj = "UPDATE uts_player SET ass_obj = ? WHERE id = ?";
db_exec($upd_assobj, [$assobjcount, $playerecordid]);

// Get assault game code (unique code give for the 2 games played)

$r_asscode = db_row("SELECT col2 FROM uts_temp_$uid WHERE col1 = 'assault_gamecode' LIMIT 0,1");
$asscode = $r_asscode['col2'] ?? null;
$updateasscode = "UPDATE uts_match SET assaultid = ? WHERE id = ?";
db_exec($updateasscode, [(string) $asscode, $matchid]);

// Get Which Teams Attacking

$r_assteam = db_row("SELECT col2 FROM uts_temp_$uid WHERE col1 = 'assault_attacker' LIMIT 0,1");
$assteam = $r_assteam['col2'] ?? null;
$updateassteam = "UPDATE uts_match SET ass_att = ? WHERE id = ?;";
db_exec($updateassteam, [$assteam, $matchid]);

// Did they do it?

$sql_asswin = "SELECT col0 FROM uts_temp_$uid WHERE col1 = 'game_end' AND col2 = 'Assault succeeded!' LIMIT 0,1";
$q_asswin = db_all($sql_asswin);
$asswin = 0;

foreach ($q_asswin as $r_asswin) {
    if ($r_asswin['col0'] != null) {
        $asswin = 1;
    }
}

$updateasswin = "UPDATE uts_match SET ass_win = ? WHERE id = ?;";
db_exec($updateasswin, [$asswin, $matchid]);
