<?php
include ("../includes/config.php");
include ("../includes/functions.php");

$last_index = mysql_real_escape_string($_GET[last_index]);
$matchid = mysql_real_escape_string($_GET[matchid]);
$query = "select uts_match.id as matchid, t0, t1, t2, t3, t0score, t1score, t2score, t3score, replace(mapfile, '.unr', '') as mapname, teamgame, gamename, insta, DATE_FORMAT(substring(time,1,8), '%Y-%m-%d') as date from uts_match where gamename like '%Capture the Flag%' and t2 = 0 and t3 = 0 order by id desc limit $last_index,1;";
if($matchid != null) {
	$query = "select uts_match.id as matchid, t0, t1, t2, t3, t0score, t1score, t2score, t3score, replace(mapfile, '.unr', '') as mapname, teamgame, gamename, insta, DATE_FORMAT(substring(time,1,8), '%Y-%m-%d') as date from uts_match where gamename like '%Capture the Flag%' and t2 = 0 and t3 = 0 and uts_match.id = $matchid;";
}
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
