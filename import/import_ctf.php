<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

// Get Player Flag Events Count
$sql_playerctf = "SELECT col1, COUNT(col1) AS flag_count FROM uts_temp_$uid WHERE (col1 LIKE 'flag_%' OR col1 = 'cover' OR col1 = 'seal') AND col2 = ? GROUP BY col1";
$q_playerctf = db_all($sql_playerctf, [$playerid]);

$flag_taken = 0;
$flag_dropped = 0;
$flag_return = 0;
$flag_capture = 0;
$flag_cover = 0;
$flag_seal = 0;
$flag_assist = 0;
$flag_kill = 0;
$flag_pickedup = 0;

foreach ($q_playerctf as $r_playerctf) {

    // Cycle through events and see what the player got

    if ($r_playerctf['col1'] == "flag_taken") {
        $flag_taken = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_dropped") {
        $flag_dropped = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_returned") {
        $flag_return = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_captured") {
        $flag_capture = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_cover" or $r_playerctf['col1'] == "Flag_cover" or $r_playerctf['col1'] == "cover") {
        $flag_cover = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_seal" or $r_playerctf['col1'] == "Flag_seal" or $r_playerctf['col1'] == "seal") {
        $flag_seal = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_assist" or $r_playerctf['col1'] == "Flag_assist") {
        $flag_assist = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_kill" or $r_playerctf['col1'] == "Flag_kill") {
        $flag_kill = $r_playerctf['flag_count'];
    }
    if ($r_playerctf['col1'] == "flag_pickedup" or $r_playerctf['col1'] == "flag_pickedup") {
        $flag_pickedup = $r_playerctf['flag_count'];
    }
}

$sql_playerflags = "	UPDATE 	uts_player
								SET 		flag_taken = ?,
											flag_dropped = ?,
											flag_return = ?,
											flag_capture = ?,
											flag_cover = ?,
											flag_seal = ?,
											flag_assist = ?,
											flag_kill = ?,
											flag_pickedup = ?
								WHERE 	id = ?";
db_exec($sql_playerflags, [$flag_taken, $flag_dropped, $flag_return, $flag_capture, $flag_cover, $flag_seal, $flag_assist, $flag_kill, $flag_pickedup, $playerecordid]);
