<?php

$sql_tgame = db_row("SELECT teamgame FROM uts_match WHERE id = ?", [$matchid]);
if ($sql_tgame == "True") {
    $rem_srecord = "DELETE FROM uts_player WHERE matchid = ? AND team > 3";
    db_exec($rem_srecord, [$matchid]);
}

$cleaned = false;
// Get list of players
$sql_pname = "SELECT pid, name FROM uts_player, uts_pinfo AS pi WHERE matchid = ? AND pid = pi.id";
$q_pname = db_all($sql_pname, [$matchid]);
foreach ($q_pname as $r_pname) {
    $playername = $r_pname['name'];
    $pid = $r_pname['pid'];


    // Check if player has more than 1 record
    $q_ids = db_all("SELECT playerid FROM uts_player WHERE pid = ? AND matchid = ?", [$pid, $matchid]);

    if (count($q_ids) > 1) {
        $numrecords = count($q_ids);
        echo (($html) ? htmlspecialchars($r_pname['name']) : $r_pname['name']) .' ';
        // get all the ids this player had
        $playerids	= [];
        foreach ($q_ids as $r_ids) {
            $playerids[] = $r_ids['playerid'];
        }
        $playerids_in = implode(",", array_fill(0, count($playerids), '?'));

        $r_newplayerid = db_row("SELECT (MAX(playerid) + 1) AS newplayerid FROM uts_player WHERE matchid = ?", [$matchid]);
        $newplayerid = $r_newplayerid['newplayerid'];

        // Fix the events table
        foreach ($playerids as $i => $oldplayerid) {
            db_exec("UPDATE uts_events SET playerid = ? WHERE playerid = ? AND matchid = ?", [$newplayerid, $oldplayerid, $matchid]);
        }

        // Fix matchcount in ranking table
        // db_exec("UPDATE uts_rank SET matches = matches - ? WHERE pid = ? AND gid = ?", [$numrecords - 1, $pid, $gid]);

        // ***********************
        // UPDATE THE KILLS MATRIX
        $sql_kmupdate = "	SELECT 	victim, 
											SUM(kills) AS kills 
								FROM 		uts_killsmatrix 
								WHERE 	matchid = ? 
									AND 	killer IN (". $playerids_in .") 
								GROUP BY	victim;";

        $q_kmupdate = db_all($sql_kmupdate, array_merge([$matchid], $playerids));
        foreach ($q_kmupdate as $r_kmupdate) {
            db_exec("	INSERT 	
								INTO 		uts_killsmatrix 
								SET 		matchid = ?, 
											killer = ?, 
											victim = ?,
											kills	= ?;", [$matchid, $newplayerid, $r_kmupdate['victim'], $r_kmupdate['kills']]);
        }

        $sql_kmupdate = "	SELECT 	killer, 
											SUM(kills) AS kills 
								FROM 		uts_killsmatrix 
								WHERE 	matchid = ? 
									AND 	victim IN (". $playerids_in .") 
								GROUP BY	killer;";

        $q_kmupdate = db_all($sql_kmupdate, array_merge([$matchid], $playerids));
        foreach ($q_kmupdate as $r_kmupdate) {
            db_exec("	INSERT 	
								INTO 		uts_killsmatrix 
								SET 		matchid = ?, 
											killer = ?, 
											victim = ?,
											kills	= ?;", [$matchid, $r_kmupdate['killer'], $newplayerid, $r_kmupdate['kills']]);
        }

        db_exec("	DELETE
							FROM		uts_killsmatrix
							WHERE 	matchid = ? 
								AND 	(killer IN (". $playerids_in .")
								OR		 victim IN (". $playerids_in ."));", array_merge([$matchid], $playerids, $playerids));


        // FINISHED UPDATING THE KILLS MATRiX
        // **********************************


        // Get non summed information

        $r_truepinfo1 = db_row("SELECT insta, pid, team, isabot, country, ip, gid FROM uts_player WHERE pid = ? AND matchid = ? LIMIT 0,1", [$pid, $matchid]);
        // fix rejoin as spec
        // sic: $r_treupinfo1 is a typo that was never set, so this block has never run (behavior kept)
        if (($r_treupinfo1['team'] ?? null) == 255) {
            $r_trueteam = db_row("SELECT team FROM uts_player WHERE pid = ? AND matchid = ? AND team != 255 LIMIT 0,1", [$pid, $matchid]);
            if ($r_trueteam['team'] < 255) {
                $r_truepinfo1['team'] = $r_trueteam['team'];
            }
        }

        // Group Player Stuff so we only have 1 player record per match
        if ($gamename == "Bunny Track") {
            // In bunnytrack the gamescore is 2000 - fastest time in seconds so use MAX gamescore rather then SUM
            $r_truepinfo2 = db_row("SELECT
			SUM(gametime) AS gametime,
			MAX(gamescore) AS gamescore,
			AVG(lowping) AS lowping,
			AVG(highping) AS highping,
			AVG(avgping) AS avgping,
			SUM(frags) AS frags,
			SUM(deaths) AS deaths,
			SUM(kills) AS kills,
			SUM(suicides) AS suicides,
			SUM(teamkills) AS teamkills,
			AVG(eff) AS eff,
			AVG(accuracy) AS accuracy,
			AVG(ttl) AS ttl,
			SUM(flag_taken) AS flag_taken,
			SUM(flag_pickedup) AS flag_pickedup,
			SUM(flag_dropped) AS flag_dropped,
			SUM(flag_return) AS flag_return,
			SUM(flag_capture) AS flag_capture,
			SUM(flag_cover) AS flag_cover,
			SUM(flag_seal) AS flag_seal,
			SUM(flag_assist) AS flag_assist,
			SUM(flag_kill) AS flag_kill,
			SUM(dom_cp) AS dom_cp,
			SUM(ass_obj) AS ass_obj,
			SUM(spree_double) AS spree_double,
			SUM(spree_triple) AS spree_triple,
			SUM(spree_multi) AS spree_multi,
			SUM(spree_mega) AS spree_mega,
			SUM(spree_ultra) AS spree_ultra,
			SUM(spree_monster) AS spree_monster,
			SUM(spree_kill) AS spree_kill,
			SUM(spree_rampage) AS spree_rampage,
			SUM(spree_dom) AS spree_dom,
			SUM(spree_uns) AS spree_uns,
			SUM(spree_god) AS spree_god,
			SUM(pu_pads) AS pu_pads,
			SUM(pu_armour) AS pu_armour,
			SUM(pu_keg) AS pu_keg,
			SUM(pu_invis) AS pu_invis,
			SUM(pu_belt) AS pu_belt,
			SUM(pu_amp) AS pu_amp,
			SUM(rank) AS rank
			FROM uts_player WHERE matchid = ? AND pid = ?", [$matchid, $pid]);
        } else {
            $r_truepinfo2 = db_row("SELECT
			SUM(gametime) AS gametime,
			SUM(gamescore) AS gamescore,
			AVG(lowping) AS lowping,
			AVG(highping) AS highping,
			AVG(avgping) AS avgping,
			SUM(frags) AS frags,
			SUM(deaths) AS deaths,
			SUM(kills) AS kills,
			SUM(suicides) AS suicides,
			SUM(teamkills) AS teamkills,
			AVG(eff) AS eff,
			AVG(accuracy) AS accuracy,
			AVG(ttl) AS ttl,
			SUM(flag_taken) AS flag_taken,
			SUM(flag_pickedup) AS flag_pickedup,
			SUM(flag_dropped) AS flag_dropped,
			SUM(flag_return) AS flag_return,
			SUM(flag_capture) AS flag_capture,
			SUM(flag_cover) AS flag_cover,
			SUM(flag_seal) AS flag_seal,
			SUM(flag_assist) AS flag_assist,
			SUM(flag_kill) AS flag_kill,
			SUM(dom_cp) AS dom_cp,
			SUM(ass_obj) AS ass_obj,
			SUM(spree_double) AS spree_double,
			SUM(spree_triple) AS spree_triple,
			SUM(spree_multi) AS spree_multi,
			SUM(spree_mega) AS spree_mega,
			SUM(spree_ultra) AS spree_ultra,
			SUM(spree_monster) AS spree_monster,
			SUM(spree_kill) AS spree_kill,
			SUM(spree_rampage) AS spree_rampage,
			SUM(spree_dom) AS spree_dom,
			SUM(spree_uns) AS spree_uns,
			SUM(spree_god) AS spree_god,
			SUM(pu_pads) AS pu_pads,
			SUM(pu_armour) AS pu_armour,
			SUM(pu_keg) AS pu_keg,
			SUM(pu_invis) AS pu_invis,
			SUM(pu_belt) AS pu_belt,
			SUM(pu_amp) AS pu_amp,
			SUM(rank) AS rank
			FROM uts_player WHERE matchid = ? AND pid = ?", [$matchid, $pid]);
        }

        // Remove all of this player's records
        $rem_precord = "DELETE FROM uts_player WHERE matchid = ? AND pid = ?";
        db_exec($rem_precord, [$matchid, $pid]);

        // Add this new record to match
        $upd_precord = "	INSERT 
								INTO 		uts_player 
								SET		matchid = :matchid,
											insta = :insta,
											playerid = :playerid,
											pid = :pid,
											team = :team,
											isabot = :isabot,
											country = :country,
											ip = :ip,
											gid = :gid,
											gametime = :gametime,
											gamescore = :gamescore,
											lowping = :lowping,
											highping = :highping,
											avgping = :avgping,  
											frags = :frags,
											deaths = :deaths,
											kills = :kills,
											suicides = :suicides,
											teamkills = :teamkills,
											eff = :eff,
											accuracy = :accuracy,
											ttl = :ttl,
											flag_taken = :flag_taken,
											flag_dropped = :flag_dropped,
											flag_return = :flag_return,
											flag_capture = :flag_capture,
											flag_cover = :flag_cover,
											flag_seal = :flag_seal,
											flag_assist = :flag_assist,
											flag_kill = :flag_kill,
											flag_pickedup = :flag_pickedup,
											dom_cp = :dom_cp,
											ass_obj = :ass_obj,
											spree_double = :spree_double,
											spree_triple = :spree_triple,
											spree_multi = :spree_multi,
											spree_mega = :spree_mega,
											spree_ultra = :spree_ultra,
											spree_monster = :spree_monster,
											spree_kill = :spree_kill,
											spree_rampage = :spree_rampage,
											spree_dom = :spree_dom,
											spree_uns = :spree_uns,
											spree_god = :spree_god,
											pu_pads = :pu_pads,
											pu_armour = :pu_armour,
											pu_keg = :pu_keg,
											pu_invis = :pu_invis,
											pu_belt = :pu_belt,
											pu_amp = :pu_amp,
											rank = :rank;";
        $precord = ['matchid' => $matchid, 'playerid' => $newplayerid, 'pid' => $pid];
        foreach (['insta', 'team', 'isabot', 'country', 'ip', 'gid'] as $field) {
            $precord[$field] = $r_truepinfo1[$field];
        }
        foreach (['gametime', 'gamescore', 'frags', 'deaths', 'kills', 'suicides', 'teamkills', 'eff', 'accuracy', 'ttl',
            'flag_taken', 'flag_dropped', 'flag_return', 'flag_capture', 'flag_cover', 'flag_seal', 'flag_assist', 'flag_kill', 'flag_pickedup',
            'dom_cp', 'ass_obj', 'spree_double', 'spree_triple', 'spree_multi', 'spree_mega', 'spree_ultra', 'spree_monster', 'spree_kill',
            'spree_rampage', 'spree_dom', 'spree_uns', 'spree_god', 'pu_pads', 'pu_armour', 'pu_keg', 'pu_invis', 'pu_belt', 'pu_amp', 'rank'] as $field) {
            $precord[$field] = $r_truepinfo2[$field];
        }
        foreach (['lowping', 'highping', 'avgping'] as $field) {
            $precord[$field] = (int)$r_truepinfo2[$field];
        }
        // Values were quoted strings before (NULL became ''), so bind them as strings
        db_exec($upd_precord, array_map('strval', $precord));
        $cleaned = true;
    }
}
if ($cleaned and $html) {
    echo "<br />";
}
