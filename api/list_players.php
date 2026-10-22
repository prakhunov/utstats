<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$list_players = mysql_real_escape_string($_GET[list_players]);
$query = "select name from uts_pinfo;";
$result = mysql_query($query) or die (mysql_error());
header('Content-Type: application/json');
if(sizeof($result) == 0) {
	echo "player not found";
} else {
	$rows = array();
	while($r = mysql_fetch_assoc($result)) {
		$rows[] = $r['name'];
	}
	$output = json_encode($rows, JSON_PRETTY_PRINT);
	echo $output;
}
?>
