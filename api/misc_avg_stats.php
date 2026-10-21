<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$type = db_pick($_GET['type'] ?? '', array('id', 'matchid', 'insta', 'playerid', 'pid', 'team', 'isabot', 'ip', 'gid', 'gametime', 'gamescore', 'lowping', 'highping', 'avgping', 'frags', 'deaths', 'kills', 'suicides', 'teamkills', 'eff', 'accuracy', 'ttl', 'flag_taken', 'flag_dropped', 'flag_return', 'flag_capture', 'flag_cover', 'flag_seal', 'flag_assist', 'flag_kill', 'flag_pickedup', 'dom_cp', 'ass_obj', 'spree_double', 'spree_triple', 'spree_multi', 'spree_mega', 'spree_ultra', 'spree_monster', 'spree_kill', 'spree_rampage', 'spree_dom', 'spree_uns', 'spree_god', 'pu_pads', 'pu_armour', 'pu_keg', 'pu_invis', 'pu_belt', 'pu_amp', 'rank'), 'frags');
$player = $_GET['player'] ?? '';
$map_key = $_GET['map_key'] ?? '';
$map_condition = "";
$params = array('player' => $player);
if($map_key != null) {
	$map_condition = "AND uts_match.mapfile like :map_key";
$params['map_key'] = '%' . $map_key . '%';
}
$query = "select round(avg(uts_player.$type)) as stats from uts_player,uts_pinfo,uts_games,uts_match where uts_pinfo.id = uts_player.pid and uts_match.id = uts_player.matchid and uts_player.gid = uts_games.id and uts_games.name like '%Capture the Flag%' and uts_pinfo.name = :player $map_condition;";
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
