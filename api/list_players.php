<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$list_players = $_GET['list_players'] ?? '';
$params = array();
$query = "select name from uts_pinfo;";
$result = db_query($query, $params);
header('Content-Type: application/json');
{
	$rows = array();
	while($r = $result->fetch(PDO::FETCH_ASSOC)) {
		$rows[] = $r['name'];
	}
	$output = json_encode($rows, JSON_PRETTY_PRINT);
	echo $output;
}
?>
