<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$player = mysql_real_escape_string($_GET[player]);
$map_key = mysql_real_escape_string($_GET[map_key]);
$map_condition = "";
if($map_key != null) {
        $map_condition = "AND uts_match.mapfile like '%$map_key%'";
}

$query = "select sum(flag_taken) as Grabs, sum(flag_capture) as Caps, sum(flag_cover) as Covs, sum(flag_assist) as Assists, sum(flag_kill) as FKills, sum(flag_return) as Returns, sum(flag_pickedup) as Pickups, sum(flag_dropped) as Drps, sum(flag_seal) as Seals from uts_player,uts_pinfo,uts_games,uts_match where uts_pinfo.id = uts_player.pid and uts_match.id = uts_player.matchid and uts_player.gid = uts_games.id and uts_games.name like '%Capture the Flag%' and uts_pinfo.name = '$player' $map_condition;";
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
