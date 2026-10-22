<?php
	include ("../includes/config.php");
	include ("../includes/functions.php");
	$player = mysql_real_escape_string($_GET[player]);
	$stats_type = mysql_real_escape_string($_GET[stats_type]);
	$map_key = mysql_real_escape_string($_GET[map_key]);
        $map_condition = "";
        if($map_key != null) {
                $map_condition = "AND m.mapfile like '%$map_key%'";
        }
        $player_count_condition = "";
        $ctf_condition = "AND m.gamename like '%Capture the Flag%'";
        if($stats_type == '5v5') {
                // count >= 10 instead of 10 for 5v5 because sometimes player sub in the middle of game then stats will capture 11 players info instead of 10. This inner qiery can be optimized. This doesnt have any where clause to making query slow.
                $player_count_condition = "and matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count >= 10)";
        } else if($stats_type == '1v1') {
		$ctf_condition = "";
                $player_count_condition = "and matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count = 2)";
        }
	$query = "SELECT SUM(IF(p.team = 0, IF((m.t0score > m.t1score AND m.t0score > m.t2score AND m.t0score > m.t3score), 1, 0), IF(p.team = 1, IF((m.t1score > m.t0score AND m.t1score > m.t2score AND m.t1score > m.t3score), 1, 0),IF(p.team = 2,IF((m.t2score > m.t0score AND m.t2score > m.t1score AND m.t2score > m.t3score), 1, 0),IF((m.t3score > m.t0score AND m.t3score > m.t1score AND m.t3score > m.t2score), 1, 0))))) as wins, COUNT(p.id) AS total FROM uts_player AS p, uts_games AS g, uts_match as m,uts_pinfo as i WHERE p.gid = g.id AND m.id = p.matchid AND p.pid = i.id AND i.name = '$player' $map_condition $ctf_condition $player_count_condition;";

	$result = mysql_query($query) or die (mysql_error());
	header('Content-Type: application/json');
	if(sizeof($result) == 0) {
		echo "player not found";
	} else {
		$rows = array();
		while($r = mysql_fetch_assoc($result)) {
			if($r['wins'] != null)
				$rows[] = $r;
		}
		$output = json_encode($rows, JSON_PRETTY_PRINT);
		echo $output;
	}
?>
