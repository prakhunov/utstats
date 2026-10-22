<?php
	include ("../includes/config.php");
	include ("../includes/functions.php");
	$defender_max_grab_count = 3;
	$player = mysql_real_escape_string($_GET[player]);
	$limit = mysql_real_escape_string($_GET[limit]);
	$stats_type = mysql_real_escape_string($_GET[stats_type]);
	$map_key = mysql_real_escape_string($_GET[map_key]);
        $map_condition = "";
	if($limit == null) {
		$limit = 10;
	}
        if($map_key != null) {
		$map_condition = "AND uts_match.mapfile like '%$map_key%'";
        }
	$player_count_condition = "";
	if($stats_type == '5v5') {
		// count >= 10 instead of 10 for 5v5 because sometimes player sub in the middle of game then stats will capture 11 players info instead of 10. This inner qiery can be optimized. This doesnt have any where clause to making query slow.
		$player_count_condition = "and matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count >= 10)";
	} else if($stats_type == '1v1') {
		$player_count_condition = "and matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count = 2)";
	}
	$query = "select case when uts_player.flag_taken > $defender_max_grab_count then 'Attack' else 'Defence' end as Role,uts_player.kills as Kills, uts_player.deaths as Deaths, round(eff,0) as Eff, round(accuracy,0) as Acc, case when uts_match.gamename like '%Capture the Flag%' then case when uts_match.insta = 1 then 'iCTF' else 'CTF' end when uts_match.gamename = 'Tournament Team Game' then 'TDM' when uts_match.gamename = 'Death match' then 'DM' else 'NA' end as 'Match', substring(replace(uts_match.mapfile, '.unr', ''), 1, 18) as Map, case when uts_match.gamename like '%Capture the Flag%' then uts_player.flag_capture else 'NA' end as Caps,case when uts_match.t0score = uts_match.t1score then 'Tie' when IF(uts_player.team = 0, IF((uts_match.t0score > uts_match.t1score AND uts_match.t0score > uts_match.t2score AND uts_match.t0score > uts_match.t3score), 1, 0), IF(uts_player.team = 1,IF((uts_match.t1score > uts_match.t0score AND uts_match.t1score > uts_match.t2score AND uts_match.t1score > uts_match.t3score), 1, 0),IF(uts_player.team = 2,IF((uts_match.t2score > uts_match.t0score AND uts_match.t2score > uts_match.t1score AND uts_match.t2score > uts_match.t3score), 1, 0),IF((uts_match.t3score > uts_match.t0score AND uts_match.t3score > uts_match.t1score AND uts_match.t3score > uts_match.t2score), 1, 0)))) = 0 then 'No' else 'Yes' end as Won from uts_player,uts_pinfo,uts_match,uts_games where uts_pinfo.id = uts_player.pid and uts_match.id = uts_player.matchid and uts_games.id = uts_player.gid and uts_pinfo.name = '$player' $map_condition $player_count_condition order by matchid desc limit $limit;";
	$result = mysql_query($query) or die (mysql_error());
	header('Content-Type: application/json');
	if(sizeof($result) == 0) {
		echo "player not found";
	} else {
		$rows = array();
		while($r = mysql_fetch_assoc($result)) {
			$rows[] = $r;
		}
		$output = json_encode($rows, JSON_PRETTY_PRINT);
		echo $output;
	}
?>
