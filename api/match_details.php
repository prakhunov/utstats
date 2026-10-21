<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$matchid = (int) ($_GET['matchid'] ?? 0);
$query = "select name as 'N', team as 'T', frags as 'F', deaths as 'D', flag_capture as 'Caps', flag_cover as 'Covs', flag_kill as 'FK', flag_taken as 'G', gamescore as 'S' from uts_player,uts_pinfo where uts_player.pid = uts_pinfo.id and matchid = ? order by team asc, gamescore desc;";
$params = array($matchid);
$result = db_query($query, $params);
	header('Content-Type: application/json');
	{
		$rows = array();
		while($r = $result->fetch(PDO::FETCH_ASSOC)) {
			$rows[] = $r;
		}
		$output = json_encode($rows, JSON_PRETTY_PRINT);
		echo $output;
	}
?>
