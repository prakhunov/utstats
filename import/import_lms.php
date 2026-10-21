<?php

// get the caps, captimes and store them in the events database
$ttl = 0;
if (($qc_deaths['col4'] ?? null) + ($q_suicides['col4'] ?? null) == $qm_gameinfofl) {
    // echo " get out time ";
    $outtime = db_row("SELECT col0, col1, col2 FROM uts_temp_$uid WHERE (col1 = 'kill' AND col4 = ?) or (col1 = 'suicide' AND col2 = ?) ORDER BY CONVERT(col0, UNSIGNED INTEGER) DESC LIMIT 0,1", [$playerid, $playerid]);
    $col0 = $outtime['col0'] ?? null;
    $col1 = 'out';
    $col2 = intval($col0 - $gamestart); //time
    $col3 = $outtime['col2'] ?? null; // killer
    $col4 = "";

    $sql_eventslms = "INSERT INTO uts_events 
			      (matchid, playerid, col0, col1, col2, col3, col4) VALUES
			      (?, ?, ?, ?, ?, ?, ?)";
    db_exec($sql_eventslms, [$matchid, $playerid, (string) $col0, $col1, $col2, (string) $col3, $col4]);

    // Fix ttl
    $ttl = ($col0 - $gamestart) / (($qc_deaths['col4'] ?? null) + ($q_suicides['col4'] ?? null));

    db_exec("UPDATE uts_player SET ttl = ? WHERE id = ?", [$ttl, $playerecordid]);
} else {
    $disconnect = db_row("SELECT col0 FROM uts_temp_$uid WHERE col1 = 'player' AND col2 = 'Disconnect' AND col3 = ?", [$playerid]);
    if ((!empty($disconnect['col0'])) and (intval($disconnect['col0']) < intval($gameend))) {
        // echo " get out time ";
        $col0 = $disconnect['col0'];
        $col1 = 'out';
        $col2 = intval($disconnect['col0'] - $gamestart); //time
        $col3 = "Disconnect";
        $col4 = "";

        $sql_eventslms = "INSERT INTO uts_events 
				      (matchid, playerid, col0, col1, col2, col3, col4) VALUES
				      (?, ?, ?, ?, ?, ?, ?)";
        db_exec($sql_eventslms, [$matchid, $playerid, $col0, $col1, $col2, $col3, $col4]);

        // Update ttl and set score to 0
        $ttl = ($disconnect['col0'] - $gamestart) / (($qc_deaths['col4'] ?? null) + ($q_suicides['col4'] ?? null));

        db_exec("UPDATE uts_player SET ttl = ?, gamescore = 0 WHERE id = ?", [$ttl, $playerecordid]);
    }
}
