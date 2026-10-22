<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$type = mysql_real_escape_string($_GET[type]);
$player = mysql_real_escape_string($_GET[player]);
$map_key = mysql_real_escape_string($_GET[map_key]);
$map_condition = "";
if($map_key != null) {
	$map_condition = "AND uts_match.mapfile like '%$map_key%'";
}
$query = "select round(avg(uts_player.$type)) as stats from uts_player,uts_pinfo,uts_games,uts_match where uts_pinfo.id = uts_player.pid and uts_match.id = uts_player.matchid and uts_player.gid = uts_games.id and uts_games.name like '%Capture the Flag%' and uts_pinfo.name = '$player' $map_condition;";
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
