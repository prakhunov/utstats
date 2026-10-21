<?php
include ("../includes/config.php");
include ("../includes/functions.php");

$player = $_GET['player'] ?? '';
$query = "select GROUP_CONCAT(DISTINCT name SEPARATOR ', ') as names from uts_pinfo where name like ? and length(name) >= 3 limit 5;";
$params = array('%' . $player . '%');
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
