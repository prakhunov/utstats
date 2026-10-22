<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$type = mysql_real_escape_string($_GET[type]);				//stats type
$limit = mysql_real_escape_string($_GET[limit]);			//max results
$threshold = mysql_real_escape_string($_GET[threshold]);	//minimum game played
$insta = mysql_real_escape_string($_GET[insta]);
if($limit == null) {
	$limit = 25;
}

if($threshold == null) {
	$threshold = 10;
}

$game = 'Capture the flag';
if($insta != null) {
	$game = 'Capture the flag (insta)';
}

$query = "select player, stats, count as matches from (select uts_pinfo.name as player, count(*) as count, sum($type) as stats from uts_player, uts_pinfo, uts_games where uts_games.id = uts_player.gid and uts_games.name like '%$game' and uts_pinfo.id = uts_player.pid group by uts_player.pid order by stats desc) as t where count > $threshold and stats > 0 limit $limit;";

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
