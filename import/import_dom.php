<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

// Get dom player scores
$sql_domplayer = "SELECT col3 FROM uts_temp_$uid WHERE col1 = 'dom_playerscore_update' AND col2 = ? ORDER BY id DESC LIMIT 0,1";
$domplayer = db_value($sql_domplayer, [$playerid]);
if (empty($domplayer)) {
    $domplayer = 0;
}
$updatedomplayer = "UPDATE uts_player SET ass_obj = ? WHERE id = ?";
db_exec($updatedomplayer, [$domplayer, $playerecordid]);

// Who did the control points
$r_domcp = db_row("SELECT count(id) AS domcpcount FROM uts_temp_$uid WHERE col1 = 'controlpoint_capture' AND col3 = ?", [$playerid]);
$domcpcount = $r_domcp['domcpcount'];
$upd_domcp = "UPDATE uts_player SET dom_cp = ? WHERE id = ?";
db_exec($upd_domcp, [$domcpcount, $playerecordid]);
