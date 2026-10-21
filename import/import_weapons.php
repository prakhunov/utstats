<?php

// Read all available weapons, we'll need them later
if (!isset($weaponnames)) {
    $sql_weaponnames = "SELECT id, name FROM uts_weapons";
    $q_weaponnames = db_all($sql_weaponnames);
    foreach ($q_weaponnames as $r_weaponnames) {
        $weaponnames[$r_weaponnames['name']] = $r_weaponnames['id'];
    }
}


// Get all kills by weapon
$sql_weapons = "	SELECT 	col2 AS player,
									col3 AS weaponname, 
									COUNT(*) AS kills
						FROM 		uts_temp_$uid 
						WHERE		col1 = 'kill' 
							OR 	col1 = 'teamkill' 
						GROUP	BY	weaponname, player";

$q_weapons = db_all($sql_weapons);
$weapons = [];
foreach ($q_weapons as $r_weapons) {

    // Get the wepon's id or assign a new one
    if (empty($r_weapons['weaponname'])) {
        continue;
    }
    if (isset($weaponnames[$r_weapons['weaponname']])) {
        $weaponid = $weaponnames[$r_weapons['weaponname']];
    } else {
        db_exec("INSERT INTO uts_weapons SET name = ?", [$r_weapons['weaponname']]);
        $weaponid = db_insert_id();
        $weaponnames[$r_weapons['weaponname']] = $weaponid;
    }

    // Get the unique pid of this player
    if (!isset($playerid2pid[$r_weapons['player']])) {
        continue;
    } else {
        $pid = $playerid2pid[$r_weapons['player']];
    }

    $weapons[$pid][$weaponid] = [
                                                'weap_kills' 			=> $r_weapons['kills'],
                                                'weap_shotcount'		=> 0,
                                                'weap_hitcount'		=> 0,
                                                'weap_damagegiven'	=> 0,
                                                'weap_accuracy'		=> 0
                                                ];


}


// Get the weapon statistics
$sql_weapons = "	SELECT 	col1 AS type,
									col2 AS weaponname, 
									col3 AS player,
									col4 AS value
						FROM 		uts_temp_$uid 
						WHERE		col1 LIKE 'weap_%'";

$q_weapons = db_all($sql_weapons);
foreach ($q_weapons as $r_weapons) {
    // Get the wepon's id or assign a new one
    if (empty($r_weapons['weaponname'])) {
        continue;
    }
    if (isset($weaponnames[$r_weapons['weaponname']])) {
        $weaponid = $weaponnames[$r_weapons['weaponname']];
    } else {
        db_exec("INSERT INTO uts_weapons SET name = ?", [$r_weapons['weaponname']]);
        $weaponid = db_insert_id();
        $weaponnames[$r_weapons['weaponname']] = $weaponid;
    }

    // Get the unique pid of this player
    if (!isset($playerid2pid[$r_weapons['player']])) {
        //		Happens if we're ignoring bots or banned players
        continue;
    } else {
        $pid = $playerid2pid[$r_weapons['player']];
    }

    if (!isset($weapons[$pid][$weaponid]['weap_kills'])) {
        $weapons[$pid][$weaponid] = [
                                                'weap_kills' 			=> 0,
                                                'weap_shotcount'		=> 0,
                                                'weap_hitcount'		=> 0,
                                                'weap_damagegiven'	=> 0,
                                                'weap_accuracy'		=> 0
                                                ];
    }

    $weapons[$pid][$weaponid][$r_weapons['type']] = $r_weapons['value'];
}


// Finally write the weapon statistics for this match
$s_weapons = [];
foreach ($weapons as $playerid => $weapon) {
    foreach ($weapon as $weaponid => $infos) {
        if ($infos['weap_kills'] == 0 and $infos['weap_shotcount'] == 0) {
            continue;
        }
        db_exec("	INSERT	
							INTO		uts_weaponstats
							SET		matchid = ?,
										pid = ?,
										weapon = ?,
										kills = ?,
										shots = ?,
										hits= ?,
										damage = ?,
										acc = ?;", [$matchid, $playerid, $weaponid, $infos['weap_kills'], $infos['weap_shotcount'], $infos['weap_hitcount'], $infos['weap_damagegiven'], round((float) $infos['weap_accuracy'], 2)]);

        // Summarize totals for this match
        if (!isset($s_weapons[$weaponid]['weap_kills'])) {
            $s_weapons[$weaponid]['weap_kills'] = $infos['weap_kills'];
            $s_weapons[$weaponid]['weap_shotcount'] = $infos['weap_shotcount'];
            $s_weapons[$weaponid]['weap_hitcount'] = $infos['weap_hitcount'];
            $s_weapons[$weaponid]['weap_damagegiven'] = $infos['weap_damagegiven'];
            $s_weapons[$weaponid]['weap_accuracy'] = $infos['weap_accuracy'];
        } else {
            $s_weapons[$weaponid]['weap_kills'] += $infos['weap_kills'];
            $s_weapons[$weaponid]['weap_shotcount'] += $infos['weap_shotcount'];
            $s_weapons[$weaponid]['weap_hitcount'] += $infos['weap_hitcount'];
            $s_weapons[$weaponid]['weap_damagegiven'] += $infos['weap_damagegiven'];
            $s_weapons[$weaponid]['weap_accuracy'] = ($s_weapons[$weaponid]['weap_accuracy'] + $infos['weap_accuracy']) / 2;
        }
    }
}


// Update the player's weapon statistics (matchid 0);
foreach ($weapons as $playerid => $weapon) {
    foreach ($weapon as $weaponid => $infos) {
        if ($infos['weap_kills'] == 0 and $infos['weap_shotcount'] == 0) {
            continue;
        }
        // Check whether a record for this player and weapon already
        // exists
        $r_pstat = db_row("	SELECT	pid
											FROM		uts_weaponstats
											WHERE		matchid = '0'
												AND	pid = ?
												AND	weapon = ?", [$playerid, $weaponid]);
        // No -> create
        if (!$r_pstat) {
            db_exec("	INSERT	
								INTO		uts_weaponstats
								SET		matchid = '0',
											pid = ?,
											weapon = ?,
											kills = ?,
											shots = ?,
											hits= ?,
											damage = ?,
											acc = ?;", [$playerid, $weaponid, $infos['weap_kills'], $infos['weap_shotcount'], $infos['weap_hitcount'], $infos['weap_damagegiven'], round((float) $infos['weap_accuracy'], 2)]);
            // Yes -> update
        } else {
            db_exec("	UPDATE	uts_weaponstats
								SET		weapon = ?,
											kills = kills + ?,
											shots = shots + ?,
											hits = hits + ?,
											damage = damage + ?,
											acc = (acc + ?) / 2
								WHERE		matchid = '0'
									AND	pid = ?
									AND	weapon = ?;", [$weaponid, (string) $infos['weap_kills'], (string) $infos['weap_shotcount'], (string) $infos['weap_hitcount'], (string) $infos['weap_damagegiven'], (string) round((float) $infos['weap_accuracy'], 2), $playerid, $weaponid]);
        }
    }
}

// Update the global weapon statistics (matchid 0, playerid 0 );
foreach ($s_weapons as $weaponid => $infos) {
    if ($infos['weap_kills'] == 0 and $infos['weap_shotcount'] == 0) {
        continue;
    }
    // Check whether the global record for this weapon already exists
    $r_pstat = db_row("	SELECT	pid
										FROM		uts_weaponstats
										WHERE		matchid = '0'
											AND	pid = '0'
											AND	weapon = ?", [$weaponid]);
    // No -> create
    if (!$r_pstat) {
        db_exec("	INSERT	
							INTO		uts_weaponstats
							SET		matchid = '0',
										pid = '0',
										weapon = ?,
										kills = ?,
										shots = ?,
										hits= ?,
										damage = ?,
										acc = ?;", [$weaponid, $infos['weap_kills'], $infos['weap_shotcount'], $infos['weap_hitcount'], $infos['weap_damagegiven'], round((float) $infos['weap_accuracy'], 2)]);
        // Yes -> update
    } else {
        db_exec("	UPDATE	uts_weaponstats
							SET		weapon = ?,
										kills = kills + ?,
										shots = shots + ?,
										hits = hits + ?,
										damage = damage + ?,
										acc = (acc + ?) / 2
							WHERE		matchid = '0'
								AND	pid = '0'
								AND	weapon = ?;", [$weaponid, (string) $infos['weap_kills'], (string) $infos['weap_shotcount'], (string) $infos['weap_hitcount'], (string) $infos['weap_damagegiven'], (string) round((float) $infos['weap_accuracy'], 2), $weaponid]);
    }
}
