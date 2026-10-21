<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$type = db_pick($_GET['type'] ?? '', array('id', 'matchid', 'insta', 'playerid', 'pid', 'team', 'isabot', 'ip', 'gid', 'gametime', 'gamescore', 'lowping', 'highping', 'avgping', 'frags', 'deaths', 'kills', 'suicides', 'teamkills', 'eff', 'accuracy', 'ttl', 'flag_taken', 'flag_dropped', 'flag_return', 'flag_capture', 'flag_cover', 'flag_seal', 'flag_assist', 'flag_kill', 'flag_pickedup', 'dom_cp', 'ass_obj', 'spree_double', 'spree_triple', 'spree_multi', 'spree_mega', 'spree_ultra', 'spree_monster', 'spree_kill', 'spree_rampage', 'spree_dom', 'spree_uns', 'spree_god', 'pu_pads', 'pu_armour', 'pu_keg', 'pu_invis', 'pu_belt', 'pu_amp', 'rank'), 'frags');				//stats type
$limit = $_GET['limit'] ?? '';			//max results
$threshold = $_GET['threshold'] ?? '';	//minimum game played
$insta = $_GET['insta'] ?? '';
$pugger = $_GET['pugger'] ?? '';
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

if($pugger != null) {
	$query = "select uts_pinfo.name as player, count(uts_player.pid) as stats from uts_player, uts_pinfo, uts_games where uts_player.pid = uts_pinfo.id and uts_player.gid = uts_games.id and uts_games.name like ? group by uts_player.pid order by stats desc limit ?;";
	$params = array('%' . $game, (int) $limit);
} else {
	$query = "select player, stats from (select uts_pinfo.name as player, count(*) as count, round(avg($type), 2) as stats from uts_player, uts_pinfo, uts_games where uts_games.id = uts_player.gid and uts_games.name like ? and uts_pinfo.id = uts_player.pid group by uts_player.pid order by stats desc) as t where count > ? and stats > '0E-5' limit ?;";
	$params = array('%' . $game, (int) $threshold, (int) $limit);
}

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
