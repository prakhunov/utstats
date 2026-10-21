<?php

// Get team releases
$q_releases = db_row("SELECT COUNT(*) AS releases FROM uts_temp_$uid WHERE col1 = 'team_released' AND col3 = ?", [$playerid]);
$r_releases = $q_releases['releases'];

// Get the real suicide count
//	(Jail releases should not be counted as suicides)
$q_suicides = db_row("SELECT COUNT(*) AS suicides FROM uts_temp_$uid WHERE col1 = 'suicide' AND col2 = ? and col4 <> 'RedeemerDeath' and col4 <> 'JailRelease'", [$playerid]);
$r_suicides = $q_suicides['suicides'];

$r_frags = $r_kills - $r_suicides;
$r_events = $r_kills + $r_deaths + $r_suicides + $r_teamkills;
// Division by zero gave false (0) before PHP 8
$r_efficiency = get_dp(($r_events == 0 ? 0 : $r_kills / $r_events) * 100);


db_exec("	UPDATE 	uts_player 
						SET 		ass_obj = ?, 
									suicides = ?,
									frags = ?,
									eff = ?
						WHERE 	id = ?;", [$r_releases, $r_suicides, $r_frags, $r_efficiency, $playerecordid]);
