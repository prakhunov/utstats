<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$matchid = mysql_real_escape_string($_GET[matchid]);
$query = "select name as 'N', team as 'T', frags as 'F', deaths as 'D', flag_capture as 'Caps', flag_cover as 'Covs', flag_kill as 'FK', flag_taken as 'G', gamescore as 'S' from uts_player,uts_pinfo where uts_player.pid = uts_pinfo.id and matchid = $matchid order by team asc, gamescore desc;";
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
