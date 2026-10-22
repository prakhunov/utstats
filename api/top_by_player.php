<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$order = mysql_real_escape_string($_GET[order]);
$order_by = mysql_real_escape_string($_GET[order_by]);
$limit = mysql_real_escape_string($_GET[limit]);
$type = mysql_real_escape_string($_GET[type]);
$player = mysql_real_escape_string($_GET[player]);
$insta = mysql_real_escape_string($_GET[insta]);
$map_key = mysql_real_escape_string($_GET[map_key]);
$map_condition = "";
if($map_key != null) {
        $map_condition = "AND uts_match.mapfile like '%$map_key%'";
}

if($limit == null) {
	$limit = 5;
}

$game = 'Capture the flag';
if($insta != null) {
	$game = 'Capture the flag (insta)';
}

// count >= 10 instead of 10 for 5v5 because sometimes player sub in the middle of game then stats will capture 11 players info instead of 10
$query = "select round(uts_player.$type) as stats, uts_player.matchid as matchid from uts_player,uts_pinfo,uts_games,uts_match where uts_player.pid = uts_pinfo.id and uts_pinfo.name = '$player' and uts_match.id = uts_player.matchid $map_condition and uts_player.gid = uts_games.id and uts_games.name like '%$game' and uts_player.matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count >= 10) order by uts_player.$order_by $order limit $limit;";

//echo "Query: $query";

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
