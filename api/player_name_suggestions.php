<?php
include ("../includes/config.php");
include ("../includes/functions.php");

$player = mysql_real_escape_string($_GET[player]);
$query = "select GROUP_CONCAT(DISTINCT name SEPARATOR ', ') as names from uts_pinfo where name like '%$player%' and length(name) >= 3 limit 5;";
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
